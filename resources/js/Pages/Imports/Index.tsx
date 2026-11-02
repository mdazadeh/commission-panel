import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import type { SharedProps } from '@/types';
import { can, jDateTime, num } from '@/lib/format';
import { useUrl } from '@/lib/url';

type Batch = {
    id: number; type: string; filename: string; status: string; total: number; valid: number; errors: number;
    duplicates: number; inserted: number; user: string | null; createdAt: string; committedAt: string | null;
};

export const STATUS: Record<string, { label: string; cls: string }> = {
    validated: { label: 'بررسی‌شده، منتظر ثبت', cls: 'bg-warn/10 text-warn' },
    committed: { label: 'ثبت شد', cls: 'bg-ok/10 text-ok' },
    rejected: { label: 'رد شد', cls: 'bg-bad/10 text-bad' },
};

export default function ImportsIndex({ batches }: { batches: Batch[] }) {
    const url = useUrl();
    const { auth } = usePage<SharedProps>().props;
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(url('/imports'), { forceFormData: true });
    };

    return (
        <AppLayout title="ورود اطلاعات فروش">
            {can(auth.user?.permissions, 'imports.manage') && (
                <form onSubmit={submit} className="card space-y-3">
                    <div className="text-sm">
                        فایل «ریز فروش» خروجی فراپیام (xlsx یا csv). ابتدا فایل بررسی می‌شود و تا وقتی «ثبت» نزنید، هیچ داده‌ای وارد سامانه نمی‌شود.
                        بارگذاری دوباره همان فایل یا ردیف‌های تکراری اثر مالی مضاعف ندارد.
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <input type="file" accept=".xlsx,.csv" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} />
                        <button className="btn-primary" disabled={!form.data.file || form.processing}>
                            {form.processing ? 'در حال بررسی…' : 'بارگذاری و بررسی'}
                        </button>
                        <a className="text-sm text-brand" href={url('/imports/template/sales')}>دانلود قالب</a>
                    </div>
                    {form.errors.file && <p className="text-sm text-bad">{form.errors.file}</p>}
                </form>
            )}

            <div className="card overflow-x-auto p-0">
                <table className="table">
                    <thead>
                        <tr><th>#</th><th>فایل</th><th>وضعیت</th><th>کل</th><th>معتبر</th><th>خطا</th><th>تکراری</th><th>ثبت‌شده</th><th>کاربر</th><th>زمان</th></tr>
                    </thead>
                    <tbody>
                        {batches.length === 0 && (
                            <tr><td colSpan={10} className="py-6 text-center text-muted">هنوز فایلی بارگذاری نشده است.</td></tr>
                        )}
                        {batches.map((b) => (
                            <tr key={b.id}>
                                <td className="num">{b.id}</td>
                                <td><Link className="text-brand" href={url(`/imports/${b.id}`)}>{b.filename}</Link></td>
                                <td><span className={`badge ${STATUS[b.status]?.cls}`}>{STATUS[b.status]?.label ?? b.status}</span></td>
                                <td className="num">{num(b.total)}</td>
                                <td className="num">{num(b.valid)}</td>
                                <td className={`num ${b.errors ? 'text-bad' : ''}`}>{num(b.errors)}</td>
                                <td className="num">{num(b.duplicates)}</td>
                                <td className="num">{num(b.inserted)}</td>
                                <td>{b.user ?? '—'}</td>
                                <td>{jDateTime(b.createdAt)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
