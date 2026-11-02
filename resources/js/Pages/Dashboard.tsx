import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { jDateTime, num, rial } from '@/lib/format';
import { useUrl } from '@/lib/url';

type Props = {
    scope: 'own' | 'team' | 'all' | 'none';
    periods: { id: number; label: string; status: string }[];
    periodId: number | null;
    totals: { documents: number; net: string; discount: string; credit: string } | null;
    byPerson: { user: string; documents: number; net: string }[];
    lastImportAt: string | null;
};

const SCOPE_LABEL = { own: 'عملکرد شما', team: 'تیم شما', all: 'کل سازمان', none: '' };

function Stat({ label, value, hint }: { label: string; value: string; hint?: string }) {
    return (
        <div className="card">
            <div className="text-sm text-muted">{label}</div>
            <div className="num mt-1 text-xl font-bold">{value}</div>
            {hint && <div className="mt-1 text-xs text-muted">{hint}</div>}
        </div>
    );
}

export default function Dashboard({ scope, periods, periodId, totals, byPerson, lastImportAt }: Props) {
    const url = useUrl();

    return (
        <AppLayout title="داشبورد">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="text-sm text-muted">
                    {SCOPE_LABEL[scope]} · آخرین ورود داده: <span>{jDateTime(lastImportAt)}</span>
                </div>
                <select
                    className="input w-auto"
                    value={periodId ?? ''}
                    onChange={(e) => router.get(url('/dashboard'), { period: e.target.value }, { preserveState: true })}
                >
                    {periods.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.label}
                            {p.status !== 'open' ? ' (قفل)' : ''}
                        </option>
                    ))}
                </select>
            </div>

            <div className="rounded-lg border border-warn/30 bg-warn/10 px-4 py-2 text-sm text-warn">
                این داشبورد فعلاً فقط فروش ثبت‌شده را نشان می‌دهد. محاسبه پورسانت در فاز بعد اضافه می‌شود و هیچ عددی در این صفحه مبلغ قابل پرداخت نیست.
            </div>

            {totals ? (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Stat label="فروش خالص (ریال)" value={rial(totals.net)} />
                    <Stat label="تخفیف (ریال)" value={rial(totals.discount)} />
                    <Stat label="فروش غیرنقد / تعهدی (ریال)" value={rial(totals.credit)} />
                    <Stat label="تعداد اسناد" value={num(totals.documents)} />
                </div>
            ) : (
                <div className="card text-sm text-muted">برای نقش شما داده فروشی قابل نمایش نیست.</div>
            )}

            {byPerson.length > 0 && (
                <div className="card overflow-x-auto p-0">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>کارشناس</th>
                                <th>تعداد اسناد</th>
                                <th>فروش خالص (ریال)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {byPerson.map((r, i) => (
                                <tr key={i}>
                                    <td>{r.user}</td>
                                    <td className="num">{num(r.documents)}</td>
                                    <td className="num">{rial(r.net)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AppLayout>
    );
}
