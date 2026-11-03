import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Flags, Kpi, type Flag } from '@/Components/Ui';
import { CATEGORY_LABEL, jDateTime, pct, rateLabel, rial, rialDec } from '@/lib/format';
import { useUrl } from '@/lib/url';

type Component = { code: string; label: string; value: string; base?: string; rate?: string; formula?: string; rule?: string };
type Props = {
    result: {
        name: string; role: string; salesTotal: string; targetTotal: string; achievement: string; tierRate: string; payable: string;
        final: string; flags: Flag[]; components: Component[];
    };
    run: { id: number; status: string; period: string; periodId: number; rule: string; ruleStatus: string; createdAt: string };
    lines: { serial: string; date: string; customer: string; type: string; category: string; net: string; cash: string; credit: string; rate: string; commission: string }[];
};

const RATIO = new Set(['achievement', 'avg_discount', 'activity_rate', 'tier_rate']);
const TYPE: Record<string, string> = { sale: 'فروش', return: 'مرجوعی', non_operational: 'غیرعملیاتی' };

function display(c: Component): string {
    if (RATIO.has(c.code) || c.code.startsWith('activity_')) return c.code === 'tier_rate' ? rateLabel(c.value) : pct(c.value, 2);
    return rialDec(c.value);
}

export default function CalculationShow({ result, run, lines }: Props) {
    const url = useUrl();

    return (
        <AppLayout title={`ریز پورسانت ${result.name}`} actions={<Link href={url(`/calculations?period=${run.periodId}`)} className="btn-ghost">بازگشت</Link>}>
            <p className="text-sm text-muted">
                {run.period} · محاسبه {run.status === 'final' ? 'قطعی' : 'موقت'} #{run.id} · {jDateTime(run.createdAt)} · قوانین <span dir="ltr">{run.rule}</span>
                {run.ruleStatus === 'draft' && ' (پیش‌نویس)'}
            </p>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Kpi icon="trend" label={result.role === 'sales_head' ? 'فروش تیم (ریال)' : 'فروش (ریال)'} value={rial(result.salesTotal)} />
                <Kpi icon="target" label="تحقق" value={pct(result.achievement)} hint={`تارگت ${rial(result.targetTotal)}`} />
                <Kpi icon="layers" label="Tier" value={Number(result.tierRate) ? rateLabel(result.tierRate) : '—'} />
                <Kpi icon="wallet" label="قابل پرداخت (ریال)" value={rial(result.payable)} hint={`پیش از گرد کردن: ${rialDec(result.final)}`} />
            </div>
            <Flags flags={result.flags} />

            <section className="card overflow-x-auto p-0" aria-label="اجزای محاسبه">
                <table className="table">
                    <thead><tr><th>جزء</th><th>مقدار</th><th>نرخ</th><th>مبنا / فرمول</th><th>قانون</th></tr></thead>
                    <tbody>
                        {result.components.map((c) => (
                            <tr key={c.code} className={c.code === 'payable' ? 'font-bold' : ''}>
                                <td>{c.label}</td>
                                <td className="num">{display(c)}</td>
                                <td className="num">{c.rate ? rateLabel(c.rate) : ''}</td>
                                <td className="text-xs text-muted">{c.base ? `مبنا: ${rialDec(c.base)} ریال` : ''}{c.base && c.formula ? ' · ' : ''}{c.formula}</td>
                                <td dir="ltr" className="text-right text-xs text-muted">{c.rule}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            {lines.length > 0 && (
                <section className="space-y-2" aria-labelledby="lines">
                    <h2 id="lines" className="text-base font-bold">ریز فاکتورها ({lines.length})</h2>
                    <div className="card overflow-x-auto p-0">
                        <table className="table">
                            <thead><tr><th>فاکتور</th><th>تاریخ</th><th>مشتری</th><th>نوع</th><th>دسته</th><th>خالص</th><th>نقد</th><th>تعهدی</th><th>نرخ</th><th>پورسانت</th></tr></thead>
                            <tbody>
                                {lines.map((l, i) => (
                                    <tr key={i}>
                                        <td className="num">{l.serial}</td>
                                        <td className="num">{l.date}</td>
                                        <td className="num">{l.customer}</td>
                                        <td>{TYPE[l.type] ?? l.type}</td>
                                        <td>{CATEGORY_LABEL[l.category] ?? l.category}</td>
                                        <td className="num">{rial(l.net)}</td>
                                        <td className="num">{rial(l.cash)}</td>
                                        <td className="num">{rial(l.credit)}</td>
                                        <td className="num">{rateLabel(l.rate)}</td>
                                        <td className="num font-medium">{rialDec(l.commission)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="text-xs text-muted">پورسانت ردیف‌ها قبل از کسورات و مشوق‌هاست؛ کسورات در جدول اجزا آمده است.</p>
                </section>
            )}
        </AppLayout>
    );
}
