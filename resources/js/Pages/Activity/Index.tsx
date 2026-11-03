import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import ReviewActions from '@/Components/ReviewActions';
import { Empty, MonthSelect, StatusBadge } from '@/Components/Ui';
import { num, pct } from '@/lib/format';
import { useUrl } from '@/lib/url';

type Entry = {
    id: number | null; label: string; user?: string | null; indicator: string; target: number; actual: number | null;
    evidence: string | null; status: string; reviewNote: string | null; reviewer: string | null;
};
type Props = { month: string; monthLabel: string; own: Entry[]; review: Entry[]; canSubmit: boolean; canReview: boolean };

function band(actual: number | null, target: number): { text: string; cls: string } {
    if (actual === null) return { text: '—', cls: 'text-muted' };
    const r = target > 0 ? actual / target : 1;
    if (r >= 1) return { text: 'کامل', cls: 'text-ok' };
    if (r >= 0.7) return { text: 'کسر ۱۰٪', cls: 'text-warn' };
    return { text: 'کسر ۲۰٪', cls: 'text-bad' };
}

function OwnRow({ e, month }: { e: Entry; month: string }) {
    const url = useUrl();
    const form = useForm({ month, indicator: e.indicator, target: String(e.target), actual: e.actual === null ? '' : String(e.actual), evidence: e.evidence ?? '' });
    const locked = e.status === 'approved';
    const b = band(form.data.actual === '' ? null : Number(form.data.actual), Number(form.data.target));

    return (
        <tr>
            <td className="font-medium">{e.label}</td>
            <td><input className="input num w-24" inputMode="numeric" aria-label={`هدف ${e.label}`} value={form.data.target} disabled={locked} onChange={(x) => form.setData('target', x.target.value.replace(/\D/g, ''))} /></td>
            <td><input className="input num w-24" inputMode="numeric" aria-label={`انجام‌شده ${e.label}`} value={form.data.actual} disabled={locked} onChange={(x) => form.setData('actual', x.target.value.replace(/\D/g, ''))} /></td>
            <td className={`text-sm ${b.cls}`}>{b.text}</td>
            <td><input className="input min-w-48" aria-label={`شواهد ${e.label}`} placeholder="صورتجلسه، نام شرکت‌ها، …" value={form.data.evidence} disabled={locked} onChange={(x) => form.setData('evidence', x.target.value)} /></td>
            <td><StatusBadge status={e.status} />{e.reviewNote && <div className="mt-1 text-xs text-muted">{e.reviewNote}</div>}</td>
            <td>
                {!locked && (
                    <button className="btn-primary px-3 py-1" disabled={form.processing || form.data.actual === ''} onClick={() => form.post(url('/activity'), { preserveScroll: true })}>
                        {e.id ? 'ثبت دوباره' : 'ثبت'}
                    </button>
                )}
                {Object.values(form.errors)[0] && <p className="mt-1 text-xs text-bad" role="alert">{Object.values(form.errors)[0]}</p>}
            </td>
        </tr>
    );
}

export default function ActivityIndex({ month, monthLabel, own, review, canSubmit, canReview }: Props) {
    const url = useUrl();
    return (
        <AppLayout title={`فعالیت ماهانه — ${monthLabel}`}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <MonthSelect value={month} href={url('/activity')} />
                <p className="text-sm text-muted">زیر ۷۰٪ هدف: ۲۰٪ کسر (۱۰٪ اگر تارگت فروش ماه محقق شده) · ۷۰ تا ۱۰۰٪: ۱۰٪ · بدترین شاخص ملاک است (D-02).</p>
            </div>

            {canSubmit && (
                <section className="space-y-2" aria-labelledby="own">
                    <h2 id="own" className="text-base font-bold">فعالیت من</h2>
                    <div className="card overflow-x-auto p-0">
                        <table className="table">
                            <thead><tr><th>شاخص</th><th>هدف</th><th>انجام‌شده</th><th>اثر</th><th>شواهد</th><th>وضعیت</th><th /></tr></thead>
                            <tbody>{own.map((e) => <OwnRow key={`${month}-${e.indicator}`} e={e} month={month} />)}</tbody>
                        </table>
                    </div>
                    <p className="text-xs text-muted">فعالیت تا تأیید رئیس در محاسبه اثری ندارد؛ هر ویرایش دوباره به صف تأیید می‌رود.</p>
                </section>
            )}

            {canReview && (
                <section className="space-y-2" aria-labelledby="review">
                    <h2 id="review" className="text-base font-bold">تأیید فعالیت تیم</h2>
                    {review.length === 0 ? <Empty>موردی برای این ماه ثبت نشده است.</Empty> : (
                        <div className="card overflow-x-auto p-0">
                            <table className="table">
                                <thead><tr><th>کارشناس</th><th>شاخص</th><th>انجام / هدف</th><th>تحقق</th><th>شواهد</th><th>وضعیت</th><th>اقدام</th></tr></thead>
                                <tbody>
                                    {review.map((e) => (
                                        <tr key={e.id}>
                                            <td>{e.user}</td>
                                            <td>{e.label}</td>
                                            <td className="num">{num(e.actual)} / {num(e.target)}</td>
                                            <td className="num">{e.target ? pct((e.actual ?? 0) / e.target, 0) : '—'}</td>
                                            <td className="max-w-64 text-xs">{e.evidence}</td>
                                            <td><StatusBadge status={e.status} /></td>
                                            <td>{e.status === 'pending' ? <ReviewActions action={url(`/activity/${e.id}/review`)} /> : <span className="text-xs text-muted">{e.reviewer}</span>}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            )}
        </AppLayout>
    );
}
