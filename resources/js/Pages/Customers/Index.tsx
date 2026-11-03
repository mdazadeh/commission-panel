import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import { Empty } from '@/Components/Ui';
import type { Paginated } from '@/types';
import { useUrl } from '@/lib/url';

type Customer = { id: number; code: string; name: string; category: string | null };
type Props = { customers: Paginated<Customer>; q: string; category: string | null; categories: { value: string; label: string }[] };

export default function CustomersIndex({ customers, q, category, categories }: Props) {
    const url = useUrl();
    const [search, setSearch] = useState(q);
    const setCategory = (c: Customer, value: string) =>
        router.put(url(`/customers/${c.id}`), { category: value || null }, { preserveScroll: true, preserveState: true });

    return (
        <AppLayout title="مشتریان">
            <p className="text-sm text-muted">مشتریان از فاکتورهای فراپیام ساخته می‌شوند. «نماینده با قرارداد» پورسانت ثابت ۱٪ خالص و بدون کسورات دارد (D-10).</p>
            <form className="flex flex-wrap gap-2" onSubmit={(e) => { e.preventDefault(); router.get(url('/customers'), { q: search, category: category ?? undefined }); }}>
                <input className="input max-w-xs" placeholder="جست‌وجوی کد یا نام" aria-label="جست‌وجو" value={search} onChange={(e) => setSearch(e.target.value)} />
                <select className="input w-auto" aria-label="فیلتر نوع" value={category ?? ''} onChange={(e) => router.get(url('/customers'), { q: search, category: e.target.value || undefined })}>
                    <option value="">همه انواع</option>
                    {categories.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
                <button className="btn-ghost">جست‌وجو</button>
            </form>
            {customers.data.length === 0 ? (
                <Empty>مشتری‌ای پیدا نشد.</Empty>
            ) : (
                <div className="card overflow-x-auto p-0">
                    <table className="table">
                        <thead><tr><th>کد</th><th>نام</th><th>نوع</th></tr></thead>
                        <tbody>
                            {customers.data.map((c) => (
                                <tr key={c.id}>
                                    <td className="num">{c.code}</td>
                                    <td>{c.name}</td>
                                    <td>
                                        <select className="input w-auto" aria-label={`نوع ${c.name}`} value={c.category ?? ''} onChange={(e) => setCategory(c, e.target.value)}>
                                            <option value="">تعیین نشده (غیر قراردادی)</option>
                                            {categories.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                                        </select>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <Pagination page={customers} />
        </AppLayout>
    );
}
