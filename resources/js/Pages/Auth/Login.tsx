import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import type { SharedProps } from '@/types';
import { useUrl } from '@/lib/url';

export default function Login() {
    const url = useUrl();
    const { flash } = usePage<SharedProps>().props;
    const form = useForm({ username: '', password: '', remember: false });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(url('/login'), { onFinish: () => form.reset('password') });
    };

    return (
        <div className="flex min-h-screen items-center justify-center p-4">
            <Head title="ورود" />
            <form onSubmit={submit} className="card w-full max-w-sm space-y-4 p-6">
                <div>
                    <h1 className="text-xl font-bold text-brand">سامانه پورسانت فاران</h1>
                    <p className="mt-1 text-sm text-muted">برای ادامه وارد شوید.</p>
                </div>
                {flash.error && <div className="rounded-lg bg-bad/10 px-3 py-2 text-sm text-bad">{flash.error}</div>}
                <div>
                    <label className="label" htmlFor="username">نام کاربری</label>
                    <input
                        id="username"
                        className="input"
                        dir="ltr"
                        autoComplete="username"
                        autoFocus
                        value={form.data.username}
                        onChange={(e) => form.setData('username', e.target.value)}
                    />
                    {form.errors.username && <p className="mt-1 text-sm text-bad">{form.errors.username}</p>}
                </div>
                <div>
                    <label className="label" htmlFor="password">رمز عبور</label>
                    <input
                        id="password"
                        type="password"
                        className="input"
                        dir="ltr"
                        autoComplete="current-password"
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                    />
                    {form.errors.password && <p className="mt-1 text-sm text-bad">{form.errors.password}</p>}
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} />
                    مرا به خاطر بسپار
                </label>
                <button className="btn-primary w-full" disabled={form.processing}>
                    {form.processing ? 'در حال ورود…' : 'ورود'}
                </button>
            </form>
        </div>
    );
}
