import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Empty, PeriodSelect, type Flag } from '@/Components/Ui';
import Icon from '@/Components/Icon';
import { jDateTime, num, pct, rateLabel, rial } from '@/lib/format';
import { useUrl } from '@/lib/url';

type Row = {
    id: number; name: string; role: string; salesTotal: string; targetTotal: string; achievement: string; tierRate: string;
    payable: string; activityRate: string; flags: Flag[];
};
type Props = {
    periods: { id: number; label: string; status: string }[];
    periodId: number | null;
    run: { id: number; status: string; createdAt: string; creator: string | null; rule: string; ruleStatus: string; summary: { people: number; payable_total: string; flags: number } | null } | null;
    results: Row[];
    canRun: boolean;
};

export default function CalculationsIndex({ periods, periodId, run, results, canRun }: Props) {
    const url = useUrl();
    const [busy, setBusy] = useState(false);
    const runNow = () => {
        if (!confirm('محاسبه موقت این دوره با داده‌های فعلی انجام شود؟ نتیجه قبلی در تاریخچه می‌ماند.')) return;
        router.post(url('/calculations'), { period: periodId }, { onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    };

    return (
        <AppLayout
            title="محاسبه و ریز پورسانت"
            actions={canRun ? <button className="btn-primary" onClick={runNow} disabled={busy}>{busy ? 'در حال محاسبه…' : 'اجرای محاسبه موقت'}</button> : undefined}
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <PeriodSelect periods={periods} value={periodId} href={url('/calculations')} />
                {run && (
                    <div className="text-sm text-muted">
                        آخرین اجرا: {jDateTime(run.createdAt)} · {run.creator ?? '—'} · قوانین <span dir="ltr">{run.rule}</span>
                    </div>
                )}
            </div>

            {run?.ruleStatus === 'draft' && (
                <div className="flex items-start gap-2 rounded-lg border border-warn/30 bg-warn/10 px-4 py-2 text-sm text-warn" role="note">
                    <Icon name="alert" className="mt-0.5 size-4 shrink-0" />
                    قوانین این محاسبه هنوز به تصویب مالی نرسیده (پیش‌نویس). همه مبالغ موقت‌اند و مبنای پرداخت نیستند.
                </div>
            )}

            {run?.summary && (
                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="card"><div className="text-sm text-muted">تعداد افراد</div><div className="num text-xl font-bold">{num(run.summary.people)}</div></div>
                    <div className="card"><div className="text-sm text-muted">جمع پورسانت موقت (ریال)</div><div className="num text-xl font-bold">{rial(run.summary.payable_total)}</div></div>
                    <div className="card"><div className="text-sm text-muted">هشدارها</div><div className="num text-xl font-bold">{num(run.summary.flags)}</div></div>
                </div>
            )}

            {!run ? (
                <Empty>برای این دوره هنوز محاسبه‌ای انجام نشده است.</Empty>
            ) : (
                <div className="card overflow-x-auto p-0">
                    <table className="table">
                        <thead>
                            <tr><th>نام</th><th>نقش</th><th>فروش (ریال)</th><th>تارگت (ریال)</th><th>تحقق</th><th>Tier</th><th>کسر فعالیت</th><th>پورسانت موقت (ریال)</th><th>هشدار</th></tr>
                        </thead>
                        <tbody>
                            {results.map((r) => (
                                <tr key={r.id}>
                                    <td><Link className="text-brand" href={url(`/calculations/results/${r.id}`)}>{r.name}</Link></td>
                                    <td>{r.role === 'sales_head' ? 'رئیس' : 'کارشناس'}</td>
                                    <td className="num">{rial(r.salesTotal)}</td>
                                    <td className="num">{rial(r.targetTotal)}</td>
                                    <td className="num">{pct(r.achievement)}</td>
                                    <td className="num">{Number(r.tierRate) ? rateLabel(r.tierRate) : '—'}</td>
                                    <td className="num">{pct(r.activityRate, 0)}</td>
                                    <td className="num font-medium">{rial(r.payable)}</td>
                                    <td>{r.flags.length ? <span className="badge bg-warn/10 text-warn" title={r.flags.map((f) => f.message).join('\n')}>{num(r.flags.length)}</span> : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AppLayout>
    );
}
