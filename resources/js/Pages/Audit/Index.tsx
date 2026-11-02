import { router } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import type { Paginated } from '@/types';
import { jDateTime, num } from '@/lib/format';
import { useUrl } from '@/lib/url';

type Event = {
    id: number; action: string; user: string | null; subject: string | null; before: unknown; after: unknown;
    reason: string | null; ip: string | null; createdAt: string; hash: string;
};

export default function AuditIndex({ events, action, verification }: {
    events: Paginated<Event>; action: string | null; verification: { ok: boolean; checked: number; broken_at: number | null } | null;
}) {
    const url = useUrl();
    const [filter, setFilter] = useState(action ?? '');
    const [open, setOpen] = useState<number | null>(null);

    return (
        <AppLayout
            title="تاریخچه حسابرسی"
            actions={<button className="btn-ghost" onClick={() => router.post(url('/audit/verify'))}>بررسی تمامیت زنجیره</button>}
        >
            {verification && (
                <div className={`rounded-lg px-4 py-2 text-sm ${verification.ok ? 'bg-ok/10 text-ok' : 'bg-bad/10 text-bad'}`}>
                    {verification.ok
                        ? `زنجیره سالم است — ${num(verification.checked)} رویداد بررسی شد.`
                        : `زنجیره از رویداد #${verification.broken_at} شکسته است؛ احتمال دست‌کاری مستقیم در پایگاه داده.`}
                </div>
            )}
            <form
                className="flex gap-2"
                onSubmit={(e) => { e.preventDefault(); router.get(url('/audit'), filter ? { action: filter } : {}); }}
            >
                <input className="input max-w-xs" dir="ltr" placeholder="مثلاً import یا user" value={filter} onChange={(e) => setFilter(e.target.value)} />
                <button className="btn-ghost">فیلتر</button>
            </form>
            <div className="card overflow-x-auto p-0">
                <table className="table">
                    <thead><tr><th>#</th><th>زمان</th><th>کاربر</th><th>عملیات</th><th>موضوع</th><th>IP</th><th>هش</th></tr></thead>
                    <tbody>
                        {events.data.map((e) => (
                            <Fragment key={e.id}>
                                <tr className="cursor-pointer hover:bg-surface" onClick={() => setOpen(open === e.id ? null : e.id)}>
                                    <td className="num">{e.id}</td>
                                    <td>{jDateTime(e.createdAt)}</td>
                                    <td>{e.user ?? 'سیستم'}</td>
                                    <td dir="ltr" className="text-right">{e.action}</td>
                                    <td dir="ltr" className="text-right">{e.subject ?? '—'}</td>
                                    <td dir="ltr" className="text-right">{e.ip ?? '—'}</td>
                                    <td dir="ltr" className="text-right font-mono text-xs">{e.hash}</td>
                                </tr>
                                {open === e.id && (
                                    <tr>
                                        <td colSpan={7} className="bg-surface">
                                            {e.reason && <div className="mb-2 text-sm">دلیل: {e.reason}</div>}
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                <pre dir="ltr" className="overflow-x-auto text-xs">قبل: {JSON.stringify(e.before, null, 2)}</pre>
                                                <pre dir="ltr" className="overflow-x-auto text-xs">بعد: {JSON.stringify(e.after, null, 2)}</pre>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </Fragment>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination page={events} />
        </AppLayout>
    );
}
