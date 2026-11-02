import { Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { useUrl } from '@/lib/url';

type Option = { value: string; label: string };
type UserData = {
    id: number; username: string; name: string; email: string | null; role: string; team_id: number | null;
    marketer_code: string | null; sales_started_on: string | null; is_active: boolean;
};

function Field({ label, error, children, hint }: { label: string; error?: string; children: ReactNode; hint?: string }) {
    return (
        <div>
            <label className="label">{label}</label>
            {children}
            {hint && <p className="mt-1 text-xs text-muted">{hint}</p>}
            {error && <p className="mt-1 text-sm text-bad">{error}</p>}
        </div>
    );
}

export default function UserForm({ user, roles, teams }: { user: UserData | null; roles: Option[]; teams: { id: number; name: string }[] }) {
    const url = useUrl();
    const form = useForm({
        username: user?.username ?? '',
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? 'sales_expert',
        team_id: user?.team_id ? String(user.team_id) : '',
        marketer_code: user?.marketer_code ?? '',
        sales_started_on: user?.sales_started_on ?? '',
        is_active: user?.is_active ?? true,
        password: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, team_id: d.team_id || null, email: d.email || null, sales_started_on: d.sales_started_on || null }));
        if (user) form.put(url(`/users/${user.id}`));
        else form.post(url('/users'));
    };

    return (
        <AppLayout title={user ? `ویرایش ${user.name}` : 'کاربر جدید'}>
            <form onSubmit={submit} className="card grid gap-4 sm:grid-cols-2">
                <Field label="نام و نام خانوادگی" error={form.errors.name}>
                    <input className="input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </Field>
                <Field label="نام کاربری" error={form.errors.username} hint="حروف انگلیسی، عدد، نقطه، خط تیره">
                    <input className="input" dir="ltr" value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} />
                </Field>
                <Field label="نقش" error={form.errors.role}>
                    <select className="input" value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>
                        {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                    </select>
                </Field>
                <Field label="تیم" error={form.errors.team_id}>
                    <select className="input" value={form.data.team_id} onChange={(e) => form.setData('team_id', e.target.value)}>
                        <option value="">—</option>
                        {teams.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </select>
                </Field>
                <Field label="کد بازاریاب فراپیام" error={form.errors.marketer_code} hint="مثلاً 24 — مبنای انتساب فروش">
                    <input className="input" dir="ltr" value={form.data.marketer_code} onChange={(e) => form.setData('marketer_code', e.target.value)} />
                </Field>
                <Field label="تاریخ شروع فروش (میلادی)" error={form.errors.sales_started_on} hint="برای تعیین تارگت براساس سابقه">
                    <input type="date" className="input" value={form.data.sales_started_on} onChange={(e) => form.setData('sales_started_on', e.target.value)} />
                </Field>
                <Field label="ایمیل (اختیاری)" error={form.errors.email}>
                    <input className="input" dir="ltr" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                </Field>
                <Field label={user ? 'رمز عبور جدید (خالی = بدون تغییر)' : 'رمز عبور'} error={form.errors.password} hint="حداقل ۱۰ کاراکتر، شامل حرف و عدد">
                    <input type="password" className="input" dir="ltr" autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                </Field>
                <label className="flex items-center gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                    حساب فعال است
                </label>
                <div className="flex gap-2 sm:col-span-2">
                    <button className="btn-primary" disabled={form.processing}>ذخیره</button>
                    <Link href={url('/users')} className="btn-ghost">انصراف</Link>
                </div>
            </form>
        </AppLayout>
    );
}
