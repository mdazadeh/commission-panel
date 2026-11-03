import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Empty, PeriodSelect } from '@/Components/Ui';
import { pct, rial } from '@/lib/format';
import { useUrl } from '@/lib/url';

type Row = {
    userId: number; name: string; role: string; roleLabel: string; team: string | null;
    annual_target: string; quarter_target: string; carried_over_override: string; carried_over_auto: string;
    lost_sales: string; prior_receivables: string; notes: string;
};
type Props = { periods: { id: number; label: string; status: string }[]; periodId: number | null; quarterShare: string | null; locked: boolean; rows: Row[] };

const digits = (v: string) => v.replace(/[^\d۰-۹]/g, '').replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)));

function MoneyInput({ value, onChange, label, disabled }: { value: string; onChange: (v: string) => void; label: string; disabled?: boolean }) {
    return (
        <input className="input num min-w-36 text-left" dir="ltr" inputMode="numeric" aria-label={label} value={value} disabled={disabled}
            onChange={(e) => onChange(digits(e.target.value))} />
    );
}

function TargetRow({ row, periodId, share, locked }: { row: Row; periodId: number; share: string | null; locked: boolean }) {
    const url = useUrl();
    const form = useForm({
        annual_target: row.annual_target === '0' ? '' : row.annual_target,
        quarter_target: row.quarter_target === '0' ? '' : row.quarter_target,
        carried_over_override: row.carried_over_override,
        lost_sales: row.lost_sales === '0' ? '' : row.lost_sales,
        prior_receivables: row.prior_receivables === '0' ? '' : row.prior_receivables,
        notes: row.notes,
    });
    const fillQuarter = () => {
        if (!share || !form.data.annual_target) return;
        const q = (BigInt(form.data.annual_target) * BigInt(Math.round(Number(share) * 10000))) / 10000n;
        form.setData('quarter_target', q.toString());
    };
    const err = Object.values(form.errors)[0];

    return (
        <tr>
            <td>
                <div className="font-medium">{row.name}</div>
                <div className="text-xs text-muted">{row.roleLabel}{row.team ? ` · ${row.team}` : ''}</div>
            </td>
            <td><MoneyInput label="تارگت سالانه" value={form.data.annual_target} onChange={(v) => form.setData('annual_target', v)} disabled={locked} /></td>
            <td>
                <div className="flex items-center gap-1">
                    <MoneyInput label="تارگت فصل" value={form.data.quarter_target} onChange={(v) => form.setData('quarter_target', v)} disabled={locked} />
                    {share && !locked && <button type="button" className="btn-ghost px-2 py-1 text-xs" onClick={fillQuarter} title={`تارگت سالانه × ${pct(share, 0)}`}>× {pct(share, 0)}</button>}
                </div>
            </td>
            <td>
                <MoneyInput label="تارگت معوق (دستی)" value={form.data.carried_over_override} onChange={(v) => form.setData('carried_over_override', v)} disabled={locked} />
                <div className="mt-1 text-xs text-muted">خودکار: <span className="num">{rial(row.carried_over_auto)}</span></div>
            </td>
            <td><MoneyInput label={row.role === 'sales_head' ? 'فروش از دست رفته تیم' : 'فروش از دست رفته'} value={form.data.lost_sales} onChange={(v) => form.setData('lost_sales', v)} disabled={locked} /></td>
            <td><MoneyInput label={row.role === 'sales_head' ? 'مطالبات معوق تیم' : 'مطالبات معوق'} value={form.data.prior_receivables} onChange={(v) => form.setData('prior_receivables', v)} disabled={locked} /></td>
            <td>
                <button className="btn-primary px-3 py-1" disabled={locked || form.processing || !form.isDirty}
                    onClick={() => form.put(url(`/targets/${periodId}/${row.userId}`), { preserveScroll: true })}>
                    {form.processing ? '…' : 'ذخیره'}
                </button>
                {err && <p className="mt-1 max-w-40 text-xs text-bad" role="alert">{err}</p>}
            </td>
        </tr>
    );
}

export default function TargetsIndex({ periods, periodId, quarterShare, locked, rows }: Props) {
    const url = useUrl();
    return (
        <AppLayout title="تارگت‌ها و ورودی‌های فصل">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <PeriodSelect periods={periods} value={periodId} href={url('/targets')} />
                <p className="text-sm text-muted">همه مبالغ به <strong>ریال</strong>، عدد صحیح. برای رئیس، «از دست رفته» و «مطالبات» جمع تیم است.</p>
            </div>
            {locked && <div className="rounded-lg bg-warn/10 px-4 py-2 text-sm text-warn">این دوره قفل است.</div>}
            {rows.length === 0 || !periodId ? (
                <Empty>کارشناس یا رئیس فروش فعالی تعریف نشده است.</Empty>
            ) : (
                <div className="card overflow-x-auto p-0">
                    <table className="table">
                        <thead>
                            <tr><th>نام</th><th>تارگت سالانه</th><th>تارگت فصل</th><th>تارگت معوق</th><th>فروش از دست رفته</th><th>مطالبات معوق</th><th /></tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => <TargetRow key={`${periodId}-${r.userId}`} row={r} periodId={periodId} share={quarterShare} locked={locked} />)}
                        </tbody>
                    </table>
                </div>
            )}
            <p className="text-xs text-muted">«تارگت معوق» اگر خالی باشد از نتیجه محاسبه فصل قبل همان سال به‌دست می‌آید (تارگت − فروش − از دست رفته). مقدار دستی جایگزین آن می‌شود.</p>
        </AppLayout>
    );
}
