import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Empty, Flags, Kpi, PeriodSelect, Progress, type Flag } from '@/Components/Ui';
import { jDateTime, num, pct, rateLabel, rial } from '@/lib/format';
import { useUrl } from '@/lib/url';

type Row = {
    id: number; userId: number; name: string; role: string; salesTotal: string; targetTotal: string; achievement: string;
    tierRate: string; final: string; payable: string; nextTier: { above: string; rate: string; sales_needed: string } | null;
    activityRate: string; flags: Flag[];
};

type Props = {
    scope: 'own' | 'team' | 'all' | 'none';
    periods: { id: number; label: string; status: string }[];
    periodId: number | null;
    totals: { documents: number; net: string; discount: string; credit: string } | null;
    byPerson: { user: string; documents: number; net: string }[];
    lastImportAt: string | null;
    calc: { runAt: string; mine: Row | null; team: Row[] } | null;
};

function MyCommission({ r, runAt }: { r: Row; runAt: string }) {
    const url = useUrl();
    const ach = Number(r.achievement);
    const activity = Number(r.activityRate);
    return (
        <section aria-labelledby="mine" className="space-y-3">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="mine" className="text-base font-bold">وضعیت پورسانت شما</h2>
                <span className="text-xs text-muted">محاسبه موقت · {jDateTime(runAt)}</span>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Kpi icon="target" label="تحقق تارگت" value={pct(r.achievement)} hint={`فروش ${rial(r.salesTotal)} از ${rial(r.targetTotal)} ریال`} tone={ach > 1 ? 'ok' : ach > 0.5 ? 'default' : 'bad'} />
                <Kpi icon="layers" label="Tier فعلی" value={Number(r.tierRate) ? rateLabel(r.tierRate) : 'زیر حد نصاب'} hint={r.role === 'sales_head' ? 'نرخ رئیس روی فروش تیم' : 'روی فروش اکشن‌پلن'} />
                <Kpi icon="wallet" label="پورسانت موقت (ریال)" value={rial(r.payable)} hint="پیش‌بینی؛ تا تسویه فصل قطعی نیست" tone="default" />
                <Kpi icon="activity" label="کسر فعالیت" value={pct(r.activityRate, 0)} tone={activity === 0 ? 'ok' : activity >= 0.4 ? 'bad' : 'warn'} hint={activity === 0 ? 'فعالیت کامل' : 'از پورسانت کسر می‌شود'} />
            </div>
            <div className="card space-y-3">
                <Progress value={ach} />
                {r.nextTier ? (
                    <p className="flex items-center gap-2 text-sm">
                        <span className="text-muted">تا پله بعد ({pct(r.nextTier.above, 0)}، نرخ {rateLabel(r.nextTier.rate)}):</span>
                        <strong className="num text-accent">{rial(r.nextTier.sales_needed)} ریال</strong>
                        <span className="text-muted">فروش دیگر</span>
                    </p>
                ) : (
                    <p className="text-sm text-ok">در بالاترین پله هستید.</p>
                )}
                <Flags flags={r.flags} />
                <Link className="text-sm text-brand" href={url(`/calculations/results/${r.id}`)}>مشاهده ریز محاسبه (فاکتوربه‌فاکتور)</Link>
            </div>
        </section>
    );
}

export default function Dashboard({ scope, periods, periodId, totals, byPerson, lastImportAt, calc }: Props) {
    const url = useUrl();

    return (
        <AppLayout title="داشبورد">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="text-sm text-muted">آخرین ورود داده فروش: {jDateTime(lastImportAt)}</div>
                <PeriodSelect periods={periods} value={periodId} href={url('/dashboard')} />
            </div>

            {calc?.mine ? (
                <MyCommission r={calc.mine} runAt={calc.runAt} />
            ) : (
                (scope === 'own' || scope === 'team') && <Empty>برای این دوره هنوز محاسبه‌ای انجام نشده است.</Empty>
            )}

            {calc && calc.team.length > 0 && (
                <section aria-labelledby="team" className="space-y-2">
                    <h2 id="team" className="text-base font-bold">{scope === 'all' ? 'عملکرد کارشناسان و رؤسا' : 'عملکرد تیم'}</h2>
                    <div className="card overflow-x-auto p-0">
                        <table className="table">
                            <thead>
                                <tr><th>نام</th><th>تحقق</th><th>Tier</th><th>تا پله بعد (ریال)</th><th>کسر فعالیت</th><th>پورسانت موقت (ریال)</th><th>هشدار</th></tr>
                            </thead>
                            <tbody>
                                {calc.team.map((r) => (
                                    <tr key={r.id}>
                                        <td><Link className="text-brand" href={url(`/calculations/results/${r.id}`)}>{r.name}</Link></td>
                                        <td className="num">{pct(r.achievement)}</td>
                                        <td className="num">{Number(r.tierRate) ? rateLabel(r.tierRate) : '—'}</td>
                                        <td className="num">{r.nextTier ? rial(r.nextTier.sales_needed) : '—'}</td>
                                        <td className="num">{pct(r.activityRate, 0)}</td>
                                        <td className="num font-medium">{rial(r.payable)}</td>
                                        <td>{r.flags.length ? <span className="badge bg-warn/10 text-warn">{num(r.flags.length)}</span> : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {totals && (
                <section aria-labelledby="sales" className="space-y-2">
                    <h2 id="sales" className="text-base font-bold">فروش ثبت‌شده دوره</h2>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Kpi icon="trend" label="فروش خالص (ریال)" value={rial(totals.net)} />
                        <Kpi icon="info" label="تخفیف (ریال)" value={rial(totals.discount)} />
                        <Kpi icon="wallet" label="غیرنقد / تعهدی (ریال)" value={rial(totals.credit)} />
                        <Kpi icon="layers" label="تعداد اسناد" value={num(totals.documents)} />
                    </div>
                    {byPerson.length > 0 && !calc?.team.length && (
                        <div className="card overflow-x-auto p-0">
                            <table className="table">
                                <thead><tr><th>کارشناس</th><th>تعداد اسناد</th><th>فروش خالص (ریال)</th></tr></thead>
                                <tbody>
                                    {byPerson.map((r, i) => (
                                        <tr key={i}><td>{r.user}</td><td className="num">{num(r.documents)}</td><td className="num">{rial(r.net)}</td></tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            )}
            {scope === 'none' && <Empty>برای نقش شما داده فروشی قابل نمایش نیست.</Empty>}
        </AppLayout>
    );
}
