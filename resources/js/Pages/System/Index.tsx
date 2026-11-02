import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { jDateTime, num } from '@/lib/format';
import { useUrl } from '@/lib/url';

type Status = {
    appVersion: string; php: string; database: string; pendingMigrations: string[]; schedulerHeartbeat: string | null;
    queuePending: number; queueFailed: number; cronCommand: string;
};

export default function SystemIndex({ status }: { status: Status }) {
    const url = useUrl();
    const cronOk = status.schedulerHeartbeat && Date.now() - new Date(status.schedulerHeartbeat).getTime() < 5 * 60 * 1000;

    return (
        <AppLayout title="وضعیت سیستم">
            <div className="card grid gap-3 text-sm sm:grid-cols-3">
                <div><div className="text-muted">نسخه سامانه</div><span dir="ltr">{status.appVersion}</span></div>
                <div><div className="text-muted">PHP</div><span dir="ltr">{status.php}</span></div>
                <div><div className="text-muted">پایگاه داده</div><span dir="ltr">{status.database}</span></div>
                <div><div className="text-muted">صف در انتظار / خطا</div><span className="num">{num(status.queuePending)} / {num(status.queueFailed)}</span></div>
                <div className="sm:col-span-2">
                    <div className="text-muted">آخرین اجرای Cron</div>
                    <span className={cronOk ? 'text-ok' : 'text-bad'}>{status.schedulerHeartbeat ? jDateTime(status.schedulerHeartbeat) : 'هنوز اجرا نشده'}</span>
                </div>
            </div>

            <div className="card space-y-3 text-sm">
                <div className="font-medium">به‌روزرسانی پایگاه داده</div>
                {status.pendingMigrations.length === 0 ? (
                    <div className="text-ok">پایگاه داده به‌روز است.</div>
                ) : (
                    <>
                        <ul dir="ltr" className="list-inside list-disc text-xs">{status.pendingMigrations.map((m) => <li key={m}>{m}</li>)}</ul>
                        <button className="btn-primary" onClick={() => confirm('قبل از ادامه از پایگاه داده پشتیبان گرفته‌اید؟') && router.post(url('/system/migrate'))}>
                            اجرای {num(status.pendingMigrations.length)} به‌روزرسانی
                        </button>
                    </>
                )}
            </div>

            <div className="card space-y-2 text-sm">
                <div className="font-medium">دستور Cron (DirectAdmin ← Cron Jobs، هر دقیقه)</div>
                <code dir="ltr" className="block overflow-x-auto rounded bg-surface p-2 text-xs">{status.cronCommand}</code>
                <p className="text-muted">اگر مسیر PHP روی هاست متفاوت است، آن را مطابق خروجی تست Cron اصلاح کنید.</p>
            </div>
        </AppLayout>
    );
}
