import { Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import type { Paginated } from '@/types';
import { jDateTime, num } from '@/lib/format';
import { useUrl } from '@/lib/url';
import { STATUS } from './Index';

type Issue = { id: number; row_number: number; severity: 'error' | 'warning'; code: string; message: string };
type Batch = {
    id: number; filename: string; status: string; total: number; valid: number; errors: number; duplicates: number;
    inserted: number; hash: string; meta: { ignored_columns?: string[]; line_level?: boolean; fatal?: string } | null;
    createdAt: string; committedAt: string | null; canCommit: boolean;
};

export default function ImportShow({ batch, issues, severity }: { batch: Batch; issues: Paginated<Issue>; severity: string | null }) {
    const url = useUrl();
    const commit = () => {
        if (confirm(`${batch.valid} ردیف معتبر ثبت شود؟ این کار در تاریخچه حسابرسی ثبت می‌شود.`)) {
            router.post(url(`/imports/${batch.id}/commit`));
        }
    };

    return (
        <AppLayout
            title={`Batch #${batch.id}`}
            actions={
                <>
                    <Link href={url('/imports')} className="btn-ghost">بازگشت</Link>
                    {batch.canCommit && <button className="btn-primary" onClick={commit}>ثبت {num(batch.valid)} ردیف</button>}
                </>
            }
        >
            <div className="card grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><div className="text-muted">فایل</div>{batch.filename}</div>
                <div><div className="text-muted">وضعیت</div><span className={`badge ${STATUS[batch.status]?.cls}`}>{STATUS[batch.status]?.label}</span></div>
                <div><div className="text-muted">بارگذاری</div>{jDateTime(batch.createdAt)}</div>
                <div><div className="text-muted">ثبت</div>{jDateTime(batch.committedAt)}</div>
                <div><div className="text-muted">کل ردیف‌ها</div><span className="num">{num(batch.total)}</span></div>
                <div><div className="text-muted">معتبر / ثبت‌شده</div><span className="num">{num(batch.valid)} / {num(batch.inserted)}</span></div>
                <div><div className="text-muted">خطا</div><span className={`num ${batch.errors ? 'text-bad' : ''}`}>{num(batch.errors)}</span></div>
                <div><div className="text-muted">تکراری (بدون اثر)</div><span className="num">{num(batch.duplicates)}</span></div>
                <div className="sm:col-span-2 lg:col-span-4"><div className="text-muted">هش فایل (SHA-256)</div><code className="break-all text-xs" dir="ltr">{batch.hash}</code></div>
                {batch.meta?.ignored_columns && batch.meta.ignored_columns.length > 0 && (
                    <div className="sm:col-span-2 lg:col-span-4">
                        <div className="text-muted">ستون‌های نادیده گرفته‌شده</div>
                        {batch.meta.ignored_columns.join('، ')}
                    </div>
                )}
                {batch.meta && batch.meta.line_level === false && (
                    <div className="rounded-lg bg-warn/10 px-3 py-2 text-warn sm:col-span-2 lg:col-span-4">
                        این فایل در سطح فاکتور است (کد کالا و مقدار ندارد)؛ تارگت تعدادی از آن قابل محاسبه نیست.
                    </div>
                )}
                {batch.errors > 0 && (
                    <div className="rounded-lg bg-bad/10 px-3 py-2 text-bad sm:col-span-2 lg:col-span-4">
                        فایل خطا دارد و قابل ثبت نیست. خطاها را در فایل اصلاح کنید و دوباره بارگذاری کنید.
                    </div>
                )}
            </div>

            <div className="flex gap-2 text-sm">
                {[null, 'error', 'warning'].map((s) => (
                    <Link key={String(s)} href={url(`/imports/${batch.id}`)} data={s ? { severity: s } : {}} className={`btn-ghost py-1 ${severity === s ? 'border-brand text-brand' : ''}`}>
                        {s === null ? 'همه' : s === 'error' ? 'خطاها' : 'هشدارها'}
                    </Link>
                ))}
            </div>

            <div className="card overflow-x-auto p-0">
                <table className="table">
                    <thead><tr><th>ردیف فایل</th><th>نوع</th><th>کد</th><th>شرح</th></tr></thead>
                    <tbody>
                        {issues.data.length === 0 && <tr><td colSpan={4} className="py-6 text-center text-muted">موردی نیست.</td></tr>}
                        {issues.data.map((i) => (
                            <tr key={i.id}>
                                <td className="num">{num(i.row_number)}</td>
                                <td><span className={`badge ${i.severity === 'error' ? 'bg-bad/10 text-bad' : 'bg-warn/10 text-warn'}`}>{i.severity === 'error' ? 'خطا' : 'هشدار'}</span></td>
                                <td dir="ltr" className="text-right text-xs">{i.code}</td>
                                <td>{i.message}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination page={issues} />
        </AppLayout>
    );
}
