import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import ReviewActions from '@/Components/ReviewActions';
import Icon from '@/Components/Icon';
import { Empty, MonthSelect, StatusBadge } from '@/Components/Ui';
import { rial } from '@/lib/format';
import { useUrl } from '@/lib/url';

type Item = {
    id: number; user: string | null; customerCode: string; customerName: string; leadStatus: string; segment: string | null;
    productForecast: string | null; amountForecast: string | null; notes: string | null; status: string; reviewNote: string | null; reviewer: string | null;
};
type Props = {
    month: string; monthLabel: string; own: Item[]; review: Item[]; canSubmit: boolean; canReview: boolean;
    leadStatuses: { value: string; label: string }[];
};

export default function ActionPlansIndex({ month, monthLabel, own, review, canSubmit, canReview, leadStatuses }: Props) {
    const url = useUrl();
    const lead = Object.fromEntries(leadStatuses.map((l) => [l.value, l.label]));
    const form = useForm({ month, customer_code: '', customer_name: '', lead_status: 'cold', segment: '', product_forecast: '', amount_forecast: '', notes: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, month }));
        form.post(url('/action-plans'), { preserveScroll: true, onSuccess: () => form.reset('customer_code', 'customer_name', 'segment', 'product_forecast', 'amount_forecast', 'notes') });
    };

    return (
        <AppLayout title={`اکشن‌پلن — ${monthLabel}`}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <MonthSelect value={month} href={url('/action-plans')} />
                <p className="text-sm text-muted">فقط فروش به مشتریان <strong>تأییدشده</strong> اکشن‌پلن همان ماه با نرخ Tier حساب می‌شود (D-05).</p>
            </div>

            {canSubmit && (
                <section className="space-y-3" aria-labelledby="own">
                    <h2 id="own" className="text-base font-bold">اکشن‌پلن من</h2>
                    <form onSubmit={submit} className="card grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label className="label" htmlFor="ap-code">کد مشتری فراپیام *</label>
                            <input id="ap-code" className="input num" dir="ltr" value={form.data.customer_code} onChange={(e) => form.setData('customer_code', e.target.value)} />
                            {form.errors.customer_code && <p className="mt-1 text-xs text-bad" role="alert">{form.errors.customer_code}</p>}
                        </div>
                        <div>
                            <label className="label" htmlFor="ap-name">نام مشتری / پروژه</label>
                            <input id="ap-name" className="input" value={form.data.customer_name} onChange={(e) => form.setData('customer_name', e.target.value)} />
                            <p className="mt-1 text-xs text-muted">برای مجموعه بزرگ، نام پروژه را هم بنویسید.</p>
                            {form.errors.customer_name && <p className="mt-1 text-xs text-bad" role="alert">{form.errors.customer_name}</p>}
                        </div>
                        <div>
                            <label className="label" htmlFor="ap-lead">وضعیت لید *</label>
                            <select id="ap-lead" className="input" value={form.data.lead_status} onChange={(e) => form.setData('lead_status', e.target.value)}>
                                {leadStatuses.map((l) => <option key={l.value} value={l.value}>{l.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="label" htmlFor="ap-amount">پیش‌بینی مبلغ (ریال)</label>
                            <input id="ap-amount" className="input num" dir="ltr" inputMode="numeric" value={form.data.amount_forecast} onChange={(e) => form.setData('amount_forecast', e.target.value.replace(/\D/g, ''))} />
                            {form.errors.amount_forecast && <p className="mt-1 text-xs text-bad" role="alert">{form.errors.amount_forecast}</p>}
                        </div>
                        <div>
                            <label className="label" htmlFor="ap-seg">سگمنت</label>
                            <input id="ap-seg" className="input" value={form.data.segment} onChange={(e) => form.setData('segment', e.target.value)} />
                        </div>
                        <div>
                            <label className="label" htmlFor="ap-prod">پیش‌بینی محصول</label>
                            <input id="ap-prod" className="input" value={form.data.product_forecast} onChange={(e) => form.setData('product_forecast', e.target.value)} />
                        </div>
                        <div className="sm:col-span-2">
                            <label className="label" htmlFor="ap-notes">توضیحات</label>
                            <input id="ap-notes" className="input" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                        </div>
                        <div className="sm:col-span-2 lg:col-span-4">
                            <button className="btn-primary" disabled={form.processing || !form.data.customer_code}>{form.processing ? 'در حال ثبت…' : 'افزودن به اکشن‌پلن'}</button>
                        </div>
                    </form>

                    {own.length === 0 ? <Empty>برای این ماه هنوز مشتری‌ای ثبت نکرده‌اید.</Empty> : (
                        <div className="card overflow-x-auto p-0">
                            <table className="table">
                                <thead><tr><th>کد</th><th>مشتری</th><th>لید</th><th>پیش‌بینی (ریال)</th><th>وضعیت</th><th>نظر بازبین</th><th /></tr></thead>
                                <tbody>
                                    {own.map((i) => (
                                        <tr key={i.id}>
                                            <td className="num">{i.customerCode}</td>
                                            <td>{i.customerName}<div className="text-xs text-muted">{[i.segment, i.productForecast].filter(Boolean).join(' · ')}</div></td>
                                            <td>{lead[i.leadStatus]}</td>
                                            <td className="num">{rial(i.amountForecast)}</td>
                                            <td><StatusBadge status={i.status} /></td>
                                            <td className="text-xs">{i.reviewNote ?? ''}{i.reviewer ? <div className="text-muted">{i.reviewer}</div> : null}</td>
                                            <td>
                                                {i.status !== 'approved' && (
                                                    <button className="btn-ghost px-2 py-1 text-bad" aria-label="حذف"
                                                        onClick={() => confirm('این ردیف حذف شود؟') && router.delete(url(`/action-plans/${i.id}`), { preserveScroll: true })}>
                                                        <Icon name="trash" className="size-4" />
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            )}

            {canReview && (
                <section className="space-y-2" aria-labelledby="review">
                    <h2 id="review" className="text-base font-bold">بازبینی اکشن‌پلن تیم</h2>
                    {review.length === 0 ? <Empty>موردی برای بازبینی در این ماه نیست.</Empty> : (
                        <div className="card overflow-x-auto p-0">
                            <table className="table">
                                <thead><tr><th>کارشناس</th><th>کد</th><th>مشتری</th><th>لید</th><th>پیش‌بینی (ریال)</th><th>وضعیت</th><th>اقدام</th></tr></thead>
                                <tbody>
                                    {review.map((i) => (
                                        <tr key={i.id}>
                                            <td>{i.user}</td>
                                            <td className="num">{i.customerCode}</td>
                                            <td>{i.customerName}{i.notes && <div className="text-xs text-muted">{i.notes}</div>}</td>
                                            <td>{lead[i.leadStatus]}</td>
                                            <td className="num">{rial(i.amountForecast)}</td>
                                            <td><StatusBadge status={i.status} />{i.reviewNote && <div className="mt-1 text-xs text-muted">{i.reviewNote}</div>}</td>
                                            <td>{i.status === 'submitted' ? <ReviewActions action={url(`/action-plans/${i.id}/review`)} /> : <span className="text-xs text-muted">{i.reviewer}</span>}</td>
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
