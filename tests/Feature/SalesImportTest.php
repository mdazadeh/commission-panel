<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\FiscalPeriod;
use App\Models\ImportBatch;
use App\Models\SalesDocument;
use App\Services\Imports\DuplicateImportException;
use App\Services\Imports\SalesImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Support\SalesWorkbook;
use Tests\TestCase;

class SalesImportTest extends TestCase
{
    use RefreshDatabase;

    private function importer(): SalesImporter
    {
        return app(SalesImporter::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function baseRows(): array
    {
        return [
            ['عملیاتی', '1405000068', '1405/01/24', '0401000002', '24', 112035715000, 0, 112035715000, 0, 112035714999.99998],
            ['عملیاتی', '1405000825', '1405/03/10', '0401000002', '24', 78000000000, 1000000, 77999000000, 77999000000, 0],
            ['مرجوعی', '1405000001', '1405/01/26', '0401000033', '24', -544000000, 0, -544000000, -544000000, 0],
            // Same return serial on another date (seen in the real export) must not collide.
            ['مرجوعی', '1405000001', '1405/01/30', '0401000034', '99', -930590000, 0, -930590000, -930590000, 0],
        ];
    }

    public function test_stages_and_commits_a_farapayam_export(): void
    {
        $finance = $this->makeUser(Role::Finance);
        $expert = $this->makeUser(Role::SalesExpert, ['marketer_code' => '24']);

        $batch = $this->importer()->stage(SalesWorkbook::make($this->baseRows()), 'riz.xlsx', $finance);

        $this->assertSame('validated', $batch->status);
        $this->assertSame([4, 4, 0], [$batch->total_rows, $batch->valid_rows, $batch->error_rows]);
        $this->assertSame(0, SalesDocument::withoutGlobalScopes()->count(), 'stage must not touch sales data');
        $this->assertTrue($batch->issues()->where('code', 'marketer_unknown')->exists(), 'marketer 99 has no user');
        $this->assertContains('محاسبه شده', $batch->meta['ignored_columns']);

        $batch = $this->importer()->commit($batch, $finance);
        $this->assertSame(4, $batch->inserted_rows);

        $doc = SalesDocument::withoutGlobalScopes()->where('serial', '1405000068')->first();
        $this->assertSame('112035715000', (string) $doc->credit_amount, 'float noise rounded to whole rial');
        $this->assertSame($expert->id, $doc->user_id, 'attributed by marketer code, not by name');
        $this->assertSame('2026-04-13', $doc->issued_on);
        $this->assertSame(1, FiscalPeriod::find($doc->fiscal_period_id)->quarter);
        $this->assertNull(SalesDocument::withoutGlobalScopes()->where('marketer_code', '99')->value('user_id'));
    }

    /** AT-01 */
    public function test_same_file_twice_is_rejected(): void
    {
        $finance = $this->makeUser(Role::Finance);
        $path = SalesWorkbook::make($this->baseRows());
        $this->importer()->stage($path, 'a.xlsx', $finance);

        $this->expectException(DuplicateImportException::class);
        $this->importer()->stage($path, 'a-copy.xlsx', $finance);
    }

    /** AT-01: overlapping rows from a different file create no double financial effect. */
    public function test_overlapping_rows_from_new_file_are_skipped(): void
    {
        $finance = $this->makeUser(Role::Finance);
        $first = $this->importer()->stage(SalesWorkbook::make($this->baseRows()), 'a.xlsx', $finance);
        $this->importer()->commit($first, $finance);

        $rows = $this->baseRows();
        $rows[] = ['عملیاتی', '1405000900', '1405/03/15', '0401000002', '24', 5000, 0, 5000, 5000, 0];
        $second = $this->importer()->stage(SalesWorkbook::make($rows), 'b.xlsx', $finance);

        $this->assertSame([5, 1, 4, 0], [$second->total_rows, $second->valid_rows, $second->duplicate_rows, $second->error_rows]);
        $this->importer()->commit($second, $finance);
        $this->assertSame(5, SalesDocument::withoutGlobalScopes()->count());
    }

    public function test_changed_row_for_existing_invoice_is_an_error_not_an_overwrite(): void
    {
        $finance = $this->makeUser(Role::Finance);
        $this->importer()->commit($this->importer()->stage(SalesWorkbook::make($this->baseRows()), 'a.xlsx', $finance), $finance);

        $rows = $this->baseRows();
        $rows[1][7] = 70000000000; // net changed
        $batch = $this->importer()->stage(SalesWorkbook::make($rows), 'b.xlsx', $finance);

        $this->assertSame(1, $batch->error_rows);
        $this->assertTrue($batch->issues()->where('code', 'conflict_existing')->exists());
        $this->expectException(\InvalidArgumentException::class);
        $this->importer()->commit($batch, $finance);
    }

    public function test_validation_errors(): void
    {
        $finance = $this->makeUser(Role::Finance);
        $rows = [
            ['مرجوعی', 'R1', '1405/02/01', 'C1', '24', 100, 0, 100, 100, 0],      // positive return
            ['عملیاتی', 'S1', '1405/13/01', 'C1', '24', 100, 0, 100, 100, 0],     // invalid date
            ['حواله', 'S2', '1405/02/01', 'C1', '24', 100, 0, 100, 100, 0],       // unknown type
            ['عملیاتی', 'S3', '1405/02/01', 'C1', '24', 100, 10, 50, 50, 0],      // net mismatch → warning only
            ['عملیاتی', 'S4', '1405/02/01', 'C1', '24', 100, 0, 100, 100, 0],
            ['عملیاتی', 'S4', '1405/02/01', 'C1', '24', 999, 0, 999, 999, 0],     // conflicting duplicate in file
        ];
        $batch = $this->importer()->stage(SalesWorkbook::make($rows), 'bad.xlsx', $finance);

        $codes = $batch->issues()->pluck('code')->all();
        foreach (['return_sign', 'parse', 'doc_type', 'net_mismatch', 'conflict_in_file'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertSame(4, $batch->error_rows);
    }

    /** AT-07: nothing enters a locked period. */
    public function test_rows_in_locked_period_are_rejected(): void
    {
        $finance = $this->makeUser(Role::Finance);
        FiscalPeriod::withoutGlobalScopes()->where(['jalali_year' => 1405, 'quarter' => 1])->update(['status' => 'locked']);

        $batch = $this->importer()->stage(SalesWorkbook::make($this->baseRows()), 'a.xlsx', $finance);

        $this->assertSame(4, $batch->error_rows);
        $this->assertTrue($batch->issues()->where('code', 'period_locked')->exists());
    }

    public function test_missing_required_columns_rejects_file(): void
    {
        $finance = $this->makeUser(Role::Finance);
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->addRow(Row::fromValues(['ستون اول', 'ستون دوم']));
        $w->addRow(Row::fromValues(['a', 'b']));
        $w->close();

        $batch = $this->importer()->stage($path, 'x.xlsx', $finance);
        $this->assertSame('rejected', $batch->status);
    }

    public function test_http_upload_flow(): void
    {
        $finance = $this->makeUser(Role::Finance);
        $file = new UploadedFile(SalesWorkbook::make($this->baseRows()), 'riz.xlsx', null, null, true);

        $this->actingAs($finance)->post('/imports', ['file' => $file])->assertRedirect();
        $batch = ImportBatch::first();
        $this->actingAs($finance)->get("/imports/{$batch->id}")->assertOk();
        $this->actingAs($finance)->post("/imports/{$batch->id}/commit")->assertRedirect("/imports/{$batch->id}");
        $this->assertSame(4, SalesDocument::withoutGlobalScopes()->count());

        // Marketing manager can view but not upload or commit.
        $mm = $this->makeUser(Role::MarketingManager);
        $this->actingAs($mm)->get('/imports')->assertOk();
        $this->actingAs($mm)->post("/imports/{$batch->id}/commit")->assertForbidden();
    }
}
