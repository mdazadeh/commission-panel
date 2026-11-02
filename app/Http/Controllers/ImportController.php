<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Services\Imports\DuplicateImportException;
use App\Services\Imports\SalesImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Imports/Index', [
            'batches' => ImportBatch::with('user:id,name')->latest()->limit(100)->get()->map(fn (ImportBatch $b) => [
                'id' => $b->id,
                'type' => $b->type,
                'filename' => $b->original_filename,
                'status' => $b->status,
                'total' => $b->total_rows,
                'valid' => $b->valid_rows,
                'errors' => $b->error_rows,
                'duplicates' => $b->duplicate_rows,
                'inserted' => $b->inserted_rows,
                'user' => $b->user?->name,
                'createdAt' => $b->created_at->toIso8601String(),
                'committedAt' => $b->committed_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request, SalesImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:xlsx,csv,txt'],
        ], ['file.mimes' => 'فقط فایل xlsx یا csv مجاز است.']);

        $file = $request->file('file');
        try {
            $batch = $importer->stage($file->getRealPath(), $file->getClientOriginalName(), $request->user());
        } catch (DuplicateImportException $e) {
            return redirect()->route('imports.show', $e->existing)->with('error', $e->getMessage());
        }

        return redirect()->route('imports.show', $batch)->with(
            $batch->error_rows > 0 || $batch->status === 'rejected' ? 'error' : 'success',
            $batch->status === 'rejected' ? 'فایل رد شد.' : 'فایل بررسی شد. نتیجه را مرور و سپس ثبت کنید.'
        );
    }

    public function show(Request $request, ImportBatch $import): Response
    {
        $severity = in_array($request->query('severity'), ['error', 'warning'], true) ? $request->query('severity') : null;
        $issues = $import->issues()->when($severity, fn ($q) => $q->where('severity', $severity))
            ->orderBy('row_number')->paginate(50)->withQueryString();

        return Inertia::render('Imports/Show', [
            'batch' => [
                'id' => $import->id,
                'filename' => $import->original_filename,
                'status' => $import->status,
                'total' => $import->total_rows,
                'valid' => $import->valid_rows,
                'errors' => $import->error_rows,
                'duplicates' => $import->duplicate_rows,
                'inserted' => $import->inserted_rows,
                'hash' => $import->file_hash,
                'meta' => $import->meta,
                'createdAt' => $import->created_at->toIso8601String(),
                'committedAt' => $import->committed_at?->toIso8601String(),
                'canCommit' => $import->status === 'validated' && $import->error_rows === 0 && $import->valid_rows > 0
                    && $request->user()->hasPermission('imports.manage'),
            ],
            'issues' => $issues,
            'severity' => $severity,
        ]);
    }

    public function commit(Request $request, ImportBatch $import, SalesImporter $importer): RedirectResponse
    {
        try {
            $batch = $importer->commit($import, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('imports.show', $batch)->with('success', "{$batch->inserted_rows} ردیف ثبت شد.");
    }

    public function template()
    {
        $headers = ['نوع فروش', 'شماره سریال فاکتور فروش', 'ردیف', 'تاریخ فاکتور', 'کد خریدار', 'نام خریدار', 'کد بازاریاب', 'بازاریاب', 'دپارتمان', 'کد کالا', 'مقدار', 'مبلغ کل', 'تخفیف ردیفی', 'مبلغ کل پس از تخفیف', 'نقدی', 'تعهدی'];
        $sample = ['عملیاتی', '1405000001', '1', '1405/01/15', '0401000002', 'نمونه مشتری', '24', 'نام کارشناس', 'دیجیتال', 'UPS-10KS', '2', '1000000000', '50000000', '950000000', '950000000', '0'];
        $csv = "\u{FEFF}".implode(',', $headers)."\n".implode(',', $sample)."\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sales-import-template.csv"',
        ]);
    }
}
