import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Icon, { type IconName } from './Icon';

export type Flag = { code: string; message: string };

export function PeriodSelect({ periods, value, href }: { periods: { id: number; label: string; status: string }[]; value: number | null; href: string }) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <span className="text-muted">دوره</span>
            <select className="input w-auto" value={value ?? ''} onChange={(e) => router.get(href, { period: e.target.value }, { preserveState: true })}>
                {periods.map((p) => (
                    <option key={p.id} value={p.id}>
                        {p.label}
                        {p.status !== 'open' ? ' (قفل)' : ''}
                    </option>
                ))}
            </select>
        </label>
    );
}

const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

export function MonthSelect({ value, href }: { value: string; href: string }) {
    const [y, m] = value.split('-').map(Number);
    const go = (yy: number, mm: number) => router.get(href, { month: `${yy}-${String(mm).padStart(2, '0')}` }, { preserveState: true });
    return (
        <div className="flex items-center gap-2 text-sm">
            <span className="text-muted">ماه</span>
            <select className="input w-auto" value={m} onChange={(e) => go(y, Number(e.target.value))}>
                {MONTHS.map((n, i) => <option key={n} value={i + 1}>{n}</option>)}
            </select>
            <select className="input w-auto" value={y} onChange={(e) => go(Number(e.target.value), m)}>
                {[y - 1, y, y + 1].map((yy) => <option key={yy} value={yy}>{yy}</option>)}
            </select>
        </div>
    );
}

const STATUS: Record<string, { label: string; cls: string; icon: IconName }> = {
    approved: { label: 'تأییدشده', cls: 'bg-ok/10 text-ok', icon: 'check' },
    submitted: { label: 'منتظر تأیید', cls: 'bg-warn/10 text-warn', icon: 'info' },
    pending: { label: 'منتظر تأیید', cls: 'bg-warn/10 text-warn', icon: 'info' },
    rejected: { label: 'ردشده', cls: 'bg-bad/10 text-bad', icon: 'x' },
    new: { label: 'ثبت نشده', cls: 'bg-surface text-muted', icon: 'info' },
};

export function StatusBadge({ status }: { status: string }) {
    const s = STATUS[status] ?? { label: status, cls: 'bg-surface text-muted', icon: 'info' as IconName };
    return (
        <span className={`badge items-center gap-1 ${s.cls}`}>
            <Icon name={s.icon} className="size-3.5" />
            {s.label}
        </span>
    );
}

export function Flags({ flags }: { flags: Flag[] }) {
    if (!flags.length) return null;
    return (
        <ul className="space-y-1" aria-live="polite">
            {flags.map((f) => (
                <li key={f.code} className={`flex items-start gap-2 rounded-lg px-3 py-2 text-sm ${f.code === 'gate_failed' || f.code === 'negative_result' ? 'bg-bad/10 text-bad' : 'bg-warn/10 text-warn'}`}>
                    <Icon name="alert" className="mt-0.5 size-4 shrink-0" />
                    <span>{f.message}</span>
                </li>
            ))}
        </ul>
    );
}

export function Kpi({ icon, label, value, hint, tone = 'default' }: { icon: IconName; label: string; value: ReactNode; hint?: ReactNode; tone?: 'default' | 'ok' | 'warn' | 'bad' }) {
    const toneCls = { default: 'text-brand bg-brand/10', ok: 'text-ok bg-ok/10', warn: 'text-warn bg-warn/10', bad: 'text-bad bg-bad/10' }[tone];
    return (
        <div className="card flex gap-3">
            <div className={`flex size-10 shrink-0 items-center justify-center rounded-lg ${toneCls}`}>
                <Icon name={icon} />
            </div>
            <div className="min-w-0">
                <div className="text-sm text-muted">{label}</div>
                <div className="num mt-0.5 text-xl font-bold text-ink">{value}</div>
                {hint && <div className="mt-1 text-xs text-muted">{hint}</div>}
            </div>
        </div>
    );
}

export function Progress({ value, max = 1.2, marks = [0.5, 0.75, 0.85, 0.95, 1, 1.2] }: { value: number; max?: number; marks?: number[] }) {
    const pct = Math.min(100, (value / max) * 100);
    return (
        <div className="space-y-1">
            <div className="relative h-3 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuemin={0} aria-valuemax={max * 100} aria-valuenow={Math.round(value * 100)} aria-label="درصد تحقق تارگت">
                <div className="h-full rounded-full bg-brand transition-[width] duration-300" style={{ width: `${pct}%` }} />
                {marks.map((m) => (
                    <span key={m} className="absolute top-0 h-full w-px bg-card" style={{ right: `${(m / max) * 100}%` }} />
                ))}
            </div>
            {/* Physical `right` (the app is RTL-only): `.num` is LTR, so logical insets would flip the labels. */}
            <div className="relative h-4 text-[11px] text-muted" aria-hidden>
                {marks.map((m) => (
                    // On phones only the key marks are labelled, so labels never overlap.
                    <span key={m} className={`num absolute translate-x-1/2 ${[0.5, 1, 1.2].includes(m) ? '' : 'hidden sm:inline'}`} style={{ right: `${(m / max) * 100}%` }}>{Math.round(m * 100)}٪</span>
                ))}
            </div>
        </div>
    );
}

export function Empty({ children }: { children: ReactNode }) {
    return <div className="card py-8 text-center text-sm text-muted">{children}</div>;
}
