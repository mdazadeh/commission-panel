<?php

namespace App\Services\Imports;

use App\Enums\Department;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\ImportBatch;
use App\Models\ImportIssue;
use App\Models\SalesDocument;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FiscalCalendar;
use App\Support\Persian;
use Brick\Math\BigInteger;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Morilog\Jalali\CalendarUtils;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Imports Farapayam «ریز فروش» exports (invoice- or line-level) in two steps:
 *   stage()  → parse + validate into a staged JSON file, nothing touches sales data;
 *   commit() → insert valid rows in one transaction (only when the batch has zero errors).
 *
 * Guarantees (brief AT-01 / AT-11 / AT-07):
 *   - identical file twice → rejected by file hash;
 *   - identical row from another file → skipped as duplicate (natural key + row hash);
 *   - same natural key with different content → error, never silently overwritten;
 *   - rows in a locked fiscal period → error.
 */
class SalesImporter
{
    public const TYPE = 'sales';

    /** canonical field => accepted header keys (after Persian::key normalisation) */
    private const HEADERS = [
        'doc_type' => ['نوعفروش', 'نوعسند', 'نوعفاکتور'],
        'serial' => ['شمارهسریالفاکتورفروش', 'شمارهسریالفاکتور', 'شمارهفاکتور', 'سریالفاکتور'],
        'date' => ['تاریخفاکتور', 'تاریخ'],
        'customer_code' => ['کدخریدار', 'کدمشتری'],
        'customer_name' => ['نامخریدار', 'ناممشتری'],
        'marketer_code' => ['کدبازاریاب'],
        'marketer_name' => ['بازاریاب', 'نامبازاریاب'],
        'department' => ['دپارتمان', 'واحد'],
        'line_no' => ['ردیف', 'ردیففاکتور', 'شمارهردیف'],
        'product_code' => ['کدکالا'],
        'quantity' => ['مقدار', 'تعداد'],
        'gross' => ['مبلغکل'],
        'discount' => ['تخفیفردیفی', 'تخفیف'],
        'net' => ['مبلغکلپسازتخفیف', 'مبلغخالص'],
        'cash' => ['نقدی'],
        'credit' => ['تعهدی', 'اعتباری'],
    ];

    private const REQUIRED = ['doc_type', 'serial', 'date', 'customer_code', 'marketer_code', 'gross', 'net'];

    private const DOC_TYPES = [
        'عملیاتی' => 'sale',
        'فروش' => 'sale',
        'مرجوعی' => 'return',
        'برگشتازفروش' => 'return',
        'غیرعملیاتی' => 'non_operational',
    ];

    public function __construct(private AuditLogger $audit, private FiscalCalendar $calendar) {}

    public function stage(string $path, string $originalName, User $user): ImportBatch
    {
        $hash = hash_file('sha256', $path);

        $existing = ImportBatch::withoutGlobalScopes()
            ->where(['tenant_id' => $user->tenant_id, 'type' => self::TYPE, 'file_hash' => $hash])
            ->first();
        if ($existing) {
            throw new DuplicateImportException($existing);
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) === 'csv' ? 'csv' : 'xlsx';
        $stored = "imports/{$user->tenant_id}/{$hash}.{$ext}";
        Storage::disk('local')->put($stored, file_get_contents($path));

        $batch = ImportBatch::create([
            'tenant_id' => $user->tenant_id,
            'type' => self::TYPE,
            'original_filename' => mb_substr($originalName, 0, 255),
            'stored_path' => $stored,
            'file_hash' => $hash,
            'status' => 'validated',
            'user_id' => $user->id,
        ]);

        try {
            $this->validate($batch, Storage::disk('local')->path($stored), $ext, $user);
        } catch (Throwable $e) {
            $batch->update(['status' => 'rejected', 'meta' => ['fatal' => $e->getMessage()]]);
            ImportIssue::create(['import_batch_id' => $batch->id, 'row_number' => 0, 'severity' => 'error', 'code' => 'file_unreadable', 'message' => 'فایل قابل خواندن نیست: '.$e->getMessage()]);
        }

        $this->audit->log('import.staged', $batch, null, $batch->only(['original_filename', 'file_hash', 'total_rows', 'valid_rows', 'error_rows', 'duplicate_rows']));

        return $batch->fresh();
    }

    public function commit(ImportBatch $batch, User $user): ImportBatch
    {
        if ($batch->status !== 'validated') {
            throw new InvalidArgumentException('این Batch قابل ثبت نیست.');
        }
        if ($batch->error_rows > 0) {
            throw new InvalidArgumentException('فایل دارای خطاست؛ ابتدا خطاها را در فایل اصلاح و دوباره بارگذاری کنید.');
        }

        $rows = json_decode(Storage::disk('local')->get($this->stagePath($batch)), true, 512, JSON_THROW_ON_ERROR);

        return DB::transaction(function () use ($batch, $rows) {
            $inserted = 0;
            $skipped = 0;
            foreach ($rows as $r) {
                // Re-check at commit time: another batch may have inserted the same row meanwhile.
                $exists = SalesDocument::withoutGlobalScopes()->where([
                    'tenant_id' => $batch->tenant_id, 'doc_type' => $r['doc_type'], 'serial' => $r['serial'],
                    'issued_on' => $r['issued_on'], 'line_no' => $r['line_no'],
                ])->first();
                if ($exists) {
                    if ($exists->row_hash !== $r['row_hash']) {
                        throw new InvalidArgumentException("ردیف {$r['row_number']}: مغایرت با رکورد ثبت‌شده قبلی (فاکتور {$r['serial']}).");
                    }
                    $skipped++;

                    continue;
                }

                $period = FiscalPeriod::withoutGlobalScopes()->find($r['fiscal_period_id']);
                if ($period?->isLocked()) {
                    throw new InvalidArgumentException("ردیف {$r['row_number']}: دوره {$period->label()} قفل شده است.");
                }

                $customer = Customer::withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $batch->tenant_id, 'code' => $r['customer_code']],
                    ['name' => $r['customer_name'] ?: $r['customer_code']],
                );

                unset($r['row_number'], $r['customer_name']);
                SalesDocument::create($r + [
                    'tenant_id' => $batch->tenant_id,
                    'import_batch_id' => $batch->id,
                    'customer_id' => $customer->id,
                ]);
                $inserted++;
            }

            $batch->update([
                'status' => 'committed',
                'inserted_rows' => $inserted,
                'duplicate_rows' => $batch->duplicate_rows + $skipped,
                'committed_at' => Carbon::now(),
            ]);
            $this->audit->log('import.committed', $batch, null, ['inserted' => $inserted, 'skipped_duplicates' => $skipped], null, $batch->tenant_id);

            return $batch->fresh();
        });
    }

    public function stagePath(ImportBatch $batch): string
    {
        return "imports/{$batch->tenant_id}/{$batch->file_hash}.staged.json";
    }

    private function validate(ImportBatch $batch, string $path, string $ext, User $user): void
    {
        $tmp = storage_path('framework/cache');
        $reader = $ext === 'csv'
            ? new CsvReader
            // temp folder inside the app keeps OpenSpout within open_basedir on shared hosting
            : new XlsxReader(new XlsxOptions(SHOULD_PRESERVE_EMPTY_ROWS: true, tempFolder: $tmp));
        $reader->open($path);

        $marketers = User::query()->where('tenant_id', $user->tenant_id)->whereNotNull('marketer_code')
            ->pluck('id', 'marketer_code')->all();

        $map = null;
        $ignored = [];
        $staged = [];
        $seen = [];
        $issues = [];
        $stats = ['total' => 0, 'valid' => 0, 'errors' => 0, 'duplicates' => 0];
        $periodsCache = [];

        // Prefer a sheet called «ریز فروش», otherwise the first sheet.
        $targetSheet = $ext === 'xlsx' ? $this->salesSheetIndex($path) : 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getIndex() !== $targetSheet) {
                continue;
            }

            $rowNumber = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $rowNumber++;
                $cells = $row->toArray();
                if ($this->isBlank($cells)) {
                    continue;
                }
                if ($map === null) {
                    [$map, $ignored] = $this->mapHeaders($cells);
                    $missing = array_diff(self::REQUIRED, array_keys($map));
                    if ($missing) {
                        throw new InvalidArgumentException('ستون‌های الزامی پیدا نشد: '.implode('، ', $missing));
                    }

                    continue;
                }

                $stats['total']++;
                $get = fn (string $f) => isset($map[$f]) ? ($cells[$map[$f]] ?? null) : null;
                $rowIssues = [];
                $raw = array_combine(array_keys($map), array_map(fn ($i) => $this->scalar($cells[$i] ?? null), array_values($map)));

                try {
                    $docTypeKey = Persian::key($get('doc_type'));
                    $docType = self::DOC_TYPES[$docTypeKey] ?? null;
                    if (! $docType) {
                        $rowIssues[] = ['error', 'doc_type', 'نوع سند نامعتبر: '.$get('doc_type')];
                    }

                    $serial = Persian::normalize($get('serial'));
                    if ($serial === '') {
                        $rowIssues[] = ['error', 'serial', 'شماره فاکتور خالی است.'];
                    }

                    [$date, $jalali] = $this->parseDate($get('date'));

                    $customerCode = Persian::normalize($get('customer_code'));
                    if ($customerCode === '') {
                        $rowIssues[] = ['error', 'customer_code', 'کد خریدار خالی است.'];
                    }

                    $marketerCode = Persian::normalize($get('marketer_code'));
                    $userId = $marketers[$marketerCode] ?? null;
                    if ($marketerCode === '') {
                        $rowIssues[] = ['error', 'marketer_code', 'کد بازاریاب خالی است.'];
                    } elseif (! $userId) {
                        $rowIssues[] = ['warning', 'marketer_unknown', "کد بازاریاب {$marketerCode} به هیچ کاربری متصل نیست؛ ردیف بدون انتساب ثبت می‌شود."];
                    }

                    $gross = Persian::toRial($get('gross'));
                    $discount = Persian::toRial($get('discount'));
                    $net = Persian::toRial($get('net'));
                    $cash = Persian::toRial($get('cash'));
                    $credit = Persian::toRial($get('credit'));

                    if (BigInteger::of($gross)->minus($discount)->compareTo($net) !== 0) {
                        $rowIssues[] = ['warning', 'net_mismatch', 'مبلغ کل منهای تخفیف با مبلغ خالص برابر نیست.'];
                    }
                    if (isset($map['cash'], $map['credit']) && BigInteger::of($cash)->plus($credit)->compareTo($net) !== 0) {
                        $rowIssues[] = ['warning', 'cash_credit_mismatch', 'جمع نقدی و تعهدی با مبلغ خالص برابر نیست.'];
                    }
                    if ($docType === 'return' && BigInteger::of($net)->isPositive()) {
                        $rowIssues[] = ['error', 'return_sign', 'مبلغ مرجوعی باید منفی باشد.'];
                    }
                    if ($docType === 'sale' && BigInteger::of($net)->isNegative()) {
                        $rowIssues[] = ['error', 'sale_sign', 'مبلغ فروش منفی است.'];
                    }

                    $lineNo = isset($map['line_no']) ? max(1, (int) Persian::normalize($get('line_no'))) : 1;

                    if (! isset($periodsCache[$jalali[0]])) {
                        $this->calendar->ensureYear($user->tenant_id, $jalali[0]);
                        $periodsCache[$jalali[0]] = true;
                    }
                    $period = $this->calendar->periodFor($user->tenant_id, $date);
                    if ($period?->isLocked()) {
                        $rowIssues[] = ['error', 'period_locked', "دوره {$period->label()} قفل شده و ورود داده جدید مجاز نیست."];
                    }

                    $record = [
                        'doc_type' => $docType,
                        'serial' => $serial,
                        'line_no' => $lineNo,
                        'issued_on' => $date->toDateString(),
                        'issued_on_jalali' => Persian::formatJalali(...$jalali),
                        'fiscal_period_id' => $period?->id,
                        'customer_code' => $customerCode,
                        'customer_name' => Persian::normalize($get('customer_name')),
                        'marketer_code' => $marketerCode,
                        'user_id' => $userId,
                        'department' => Department::fromPersian(Persian::normalize($get('department')))?->value,
                        'product_code' => isset($map['product_code']) ? (Persian::normalize($get('product_code')) ?: null) : null,
                        'quantity' => isset($map['quantity']) && $get('quantity') !== null ? (string) Persian::normalize($get('quantity')) : null,
                        'gross_amount' => $gross,
                        'discount_amount' => $discount,
                        'net_amount' => $net,
                        'cash_amount' => $cash,
                        'credit_amount' => $credit,
                    ];
                    $record['row_hash'] = hash('sha256', json_encode($record, JSON_UNESCAPED_UNICODE));
                    $record['row_number'] = $rowNumber;
                } catch (InvalidArgumentException $e) {
                    $rowIssues[] = ['error', 'parse', $e->getMessage()];
                    $record = null;
                }

                $hasError = (bool) array_filter($rowIssues, fn ($i) => $i[0] === 'error');

                if (! $hasError && $record) {
                    $key = implode('|', [$record['doc_type'], $record['serial'], $record['issued_on'], $record['line_no']]);
                    if (isset($seen[$key])) {
                        if ($seen[$key] === $record['row_hash']) {
                            $rowIssues[] = ['warning', 'duplicate_in_file', 'ردیف تکراری در همین فایل؛ نادیده گرفته می‌شود.'];
                            $stats['duplicates']++;
                            $record = null;
                        } else {
                            $rowIssues[] = ['error', 'conflict_in_file', "فاکتور {$record['serial']} با همین تاریخ دو بار با مقادیر متفاوت آمده است."];
                            $hasError = true;
                        }
                    } else {
                        $seen[$key] = $record['row_hash'];
                        $existing = SalesDocument::withoutGlobalScopes()->where([
                            'tenant_id' => $user->tenant_id, 'doc_type' => $record['doc_type'], 'serial' => $record['serial'],
                            'issued_on' => $record['issued_on'], 'line_no' => $record['line_no'],
                        ])->value('row_hash');
                        if ($existing === $record['row_hash']) {
                            $rowIssues[] = ['warning', 'already_imported', 'این ردیف قبلاً ثبت شده است؛ اثر مالی مضاعف ایجاد نمی‌شود.'];
                            $stats['duplicates']++;
                            $record = null;
                        } elseif ($existing !== null) {
                            $rowIssues[] = ['error', 'conflict_existing', "فاکتور {$record['serial']} قبلاً با مقادیر متفاوت ثبت شده است؛ اصلاح باید با سند تعدیل انجام شود."];
                            $hasError = true;
                        }
                    }
                }

                foreach ($rowIssues as [$severity, $code, $message]) {
                    $issues[] = ['import_batch_id' => $batch->id, 'row_number' => $rowNumber, 'severity' => $severity, 'code' => $code, 'message' => mb_substr($message, 0, 255), 'raw' => json_encode($raw, JSON_UNESCAPED_UNICODE)];
                }

                if ($hasError) {
                    $stats['errors']++;
                } elseif ($record) {
                    $stats['valid']++;
                    $staged[] = $record;
                }
            }
            break;
        }
        $reader->close();

        if ($map === null) {
            throw new InvalidArgumentException('فایل خالی است یا ردیف عنوان ندارد.');
        }

        foreach (array_chunk($issues, 500) as $chunk) {
            ImportIssue::insert($chunk);
        }
        Storage::disk('local')->put($this->stagePath($batch), json_encode($staged, JSON_UNESCAPED_UNICODE));

        $batch->update([
            'total_rows' => $stats['total'],
            'valid_rows' => $stats['valid'],
            'error_rows' => $stats['errors'],
            'duplicate_rows' => $stats['duplicates'],
            'meta' => ['ignored_columns' => $ignored, 'line_level' => isset($map['product_code'])],
        ]);
    }

    /** @return array{0:array<string,int>,1:list<string>} */
    private function mapHeaders(array $cells): array
    {
        $lookup = [];
        foreach (self::HEADERS as $field => $aliases) {
            foreach ($aliases as $alias) {
                $lookup[$alias] = $field;
            }
        }
        $map = [];
        $ignored = [];
        foreach ($cells as $i => $cell) {
            $label = Persian::normalize($this->scalar($cell));
            if ($label === '') {
                continue;
            }
            $field = $lookup[Persian::key($label)] ?? null;
            if ($field && ! isset($map[$field])) {
                $map[$field] = $i;
            } else {
                $ignored[] = $label;
            }
        }

        return [$map, $ignored];
    }

    /** @return array{0:CarbonImmutable,1:array{0:int,1:int,2:int}} */
    private function parseDate(mixed $value): array
    {
        if ($value instanceof DateTimeInterface) {
            [$jy, $jm, $jd] = CalendarUtils::toJalali((int) $value->format('Y'), (int) $value->format('n'), (int) $value->format('j'));
            $value = Persian::formatJalali($jy, $jm, $jd);
        }
        $date = Persian::jalaliToDate($value);
        $jalali = array_map('intval', explode('/', Persian::normalize(str_replace(['-', '.'], '/', (string) $value))));

        return [$date, [$jalali[0], $jalali[1], $jalali[2]]];
    }

    private function salesSheetIndex(string $path): int
    {
        $r = new XlsxReader;
        $r->open($path);
        $index = 0;
        foreach ($r->getSheetIterator() as $sheet) {
            if (Persian::key($sheet->getName()) === 'ریزفروش') {
                $index = $sheet->getIndex();
                break;
            }
        }
        $r->close();

        return $index;
    }

    private function isBlank(array $cells): bool
    {
        foreach ($cells as $c) {
            if ($c !== null && trim((string) $this->scalar($c)) !== '') {
                return false;
            }
        }

        return true;
    }

    private function scalar(mixed $v): string|int|float|null
    {
        return match (true) {
            $v instanceof DateTimeInterface => $v->format('Y-m-d'),
            is_bool($v) => $v ? '1' : '0',
            is_scalar($v) || $v === null => $v,
            default => (string) json_encode($v),
        };
    }
}
