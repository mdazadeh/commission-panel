import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import type { SharedProps } from '@/types';
import { can } from '@/lib/format';
import Icon from '@/Components/Icon';
import { useUrl } from '@/lib/url';

type Item = { label: string; href?: string; permission?: string; soon?: boolean };
type Group = { title: string; items: Item[] };

// Menu architecture from brief §10. «soon» items are later phases and are shown disabled.
const MENU: Group[] = [
    {
        title: 'داشبوردها',
        items: [
            { label: 'داشبورد', href: '/dashboard' },
            { label: 'محاسبه و ریز پورسانت', href: '/calculations', permission: 'calc.view' },
        ],
    },
    {
        title: 'مدیریت داده',
        items: [
            { label: 'ورود اطلاعات فروش', href: '/imports', permission: 'imports.view' },
            { label: 'اکشن‌پلن', href: '/action-plans', permission: 'action_plan.submit' },
            { label: 'اکشن‌پلن', href: '/action-plans', permission: 'action_plan.review' },
            { label: 'فعالیت ماهانه', href: '/activity', permission: 'activity.submit' },
            { label: 'فعالیت ماهانه', href: '/activity', permission: 'activity.review' },
            { label: 'مشتریان', href: '/customers', permission: 'customers.manage' },
        ],
    },
    { title: 'موتور پورسانت', items: [{ label: 'تارگت‌ها', href: '/targets', permission: 'targets.manage' }] },
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

    // Filter by permission and de-duplicate items reachable through more than one permission.
    const groups = MENU.map((g) => ({
        ...g,
        items: g.items.filter((i, idx, all) => (!i.permission || can(perms, i.permission)) && all.findIndex((o) => o.href === i.href && (!o.permission || can(perms, o.permission))) === idx),
    })).filter(
        (g) => g.items.some((i) => !i.soon),
    );

    return (
        <>
            <Head title={title} />
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded focus:bg-card focus:p-2">پرش به محتوا</a>
            <div className="min-h-dvh lg:flex">
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
                            <button className="btn-ghost px-2 py-1 lg:hidden" onClick={() => setOpen(true)} aria-label="باز کردن منو">
                                <Icon name="menu" />
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
                    <main id="main" className="mx-auto max-w-7xl space-y-4 p-4">
                        {flash.success && <div role="status" className="flex items-center gap-2 rounded-lg border border-ok/30 bg-ok/10 px-4 py-2 text-sm text-ok"><Icon name="check" className="size-4" />{flash.success}</div>}
                        {flash.error && <div role="alert" className="flex items-center gap-2 rounded-lg border border-bad/30 bg-bad/10 px-4 py-2 text-sm text-bad"><Icon name="alert" className="size-4" />{flash.error}</div>}
                        {actions && <div className="flex flex-wrap justify-end gap-2">{actions}</div>}
                        {children}
                    </main>
                </div>
            </div>
        </>
    );
}
