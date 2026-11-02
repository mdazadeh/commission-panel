import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import type { SharedProps } from '@/types';
import { can } from '@/lib/format';
import { useUrl } from '@/lib/url';

type Item = { label: string; href?: string; permission?: string; soon?: boolean };
type Group = { title: string; items: Item[] };

// Menu architecture from brief §10. «soon» items are later phases and are shown disabled.
const MENU: Group[] = [
    { title: 'داشبوردها', items: [{ label: 'داشبورد', href: '/dashboard' }] },
    {
        title: 'مدیریت داده',
        items: [
            { label: 'ورود اطلاعات فروش', href: '/imports', permission: 'imports.view' },
            { label: 'اکشن‌پلن', soon: true },
            { label: 'فعالیت ماهانه', soon: true },
        ],
    },
    { title: 'موتور پورسانت', items: [{ label: 'تارگت و قوانین', soon: true }, { label: 'محاسبه و Trace', soon: true }] },
    { title: 'تسویه مالی', items: [{ label: 'تسویه فصلی', soon: true }] },
    { title: 'گزارش و تحلیل', items: [{ label: 'گزارش‌ها', soon: true }] },
    {
        title: 'مدیریت سامانه',
        items: [
            { label: 'کاربران', href: '/users', permission: 'users.manage' },
            { label: 'تاریخچه حسابرسی', href: '/audit', permission: 'audit.view' },
            { label: 'وضعیت سیستم', href: '/system', permission: 'system.manage' },
        ],
    },
];

export default function AppLayout({ title, children, actions }: { title: string; children: ReactNode; actions?: ReactNode }) {
    const { auth, flash } = usePage<SharedProps>().props;
    const url = useUrl();
    const [open, setOpen] = useState(false);
    const current = typeof window !== 'undefined' ? window.location.pathname : '';
    const perms = auth.user?.permissions ?? [];

    const groups = MENU.map((g) => ({ ...g, items: g.items.filter((i) => !i.permission || can(perms, i.permission)) })).filter(
        (g) => g.items.some((i) => !i.soon),
    );

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen lg:flex">
                <aside
                    className={`${open ? 'block' : 'hidden'} fixed inset-y-0 right-0 z-30 w-64 overflow-y-auto border-l border-line bg-card p-4 lg:static lg:block`}
                >
                    <div className="mb-6 text-base font-bold text-brand">سامانه پورسانت فاران</div>
                    <nav className="space-y-5">
                        {groups.map((g) => (
                            <div key={g.title}>
                                <div className="mb-1 text-xs font-medium text-muted">{g.title}</div>
                                <ul className="space-y-0.5">
                                    {g.items.map((i) =>
                                        i.soon || !i.href ? (
                                            <li key={i.label} className="flex items-center justify-between rounded-lg px-3 py-1.5 text-sm text-muted/70">
                                                {i.label}
                                                <span className="badge bg-surface text-muted">به‌زودی</span>
                                            </li>
                                        ) : (
                                            <li key={i.label}>
                                                <Link
                                                    href={url(i.href)}
                                                    className={`block rounded-lg px-3 py-1.5 text-sm ${
                                                        current === url(i.href) || current.startsWith(`${url(i.href)}/`)
                                                            ? 'bg-brand/10 font-medium text-brand'
                                                            : 'text-ink hover:bg-surface'
                                                    }`}
                                                >
                                                    {i.label}
                                                </Link>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        ))}
                    </nav>
                </aside>
                {open && <div className="fixed inset-0 z-20 bg-black/30 lg:hidden" onClick={() => setOpen(false)} />}

                <div className="min-w-0 flex-1">
                    <header className="flex items-center justify-between gap-3 border-b border-line bg-card px-4 py-3">
                        <div className="flex items-center gap-3">
                            <button className="btn-ghost px-2 py-1 lg:hidden" onClick={() => setOpen(true)} aria-label="منو">
                                ☰
                            </button>
                            <h1 className="text-lg font-bold">{title}</h1>
                        </div>
                        <div className="flex items-center gap-3 text-sm">
                            <span className="hidden text-muted sm:inline">
                                {auth.user?.name} · {auth.user?.roleLabel}
                            </span>
                            <button className="btn-ghost py-1" onClick={() => router.post(url('/logout'))}>
                                خروج
                            </button>
                        </div>
                    </header>
                    <main className="mx-auto max-w-6xl space-y-4 p-4">
                        {flash.success && <div className="rounded-lg border border-ok/30 bg-ok/10 px-4 py-2 text-sm text-ok">{flash.success}</div>}
                        {flash.error && <div className="rounded-lg border border-bad/30 bg-bad/10 px-4 py-2 text-sm text-bad">{flash.error}</div>}
                        {actions && <div className="flex flex-wrap justify-end gap-2">{actions}</div>}
                        {children}
                    </main>
                </div>
            </div>
        </>
    );
}
