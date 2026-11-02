import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { jDateTime } from '@/lib/format';
import { useUrl } from '@/lib/url';

type Row = {
    id: number; username: string; name: string; role: string; team: string | null;
    marketerCode: string | null; isActive: boolean; lastLoginAt: string | null;
};

export default function UsersIndex({ users }: { users: Row[] }) {
    const url = useUrl();
    return (
        <AppLayout title="کاربران" actions={<Link href={url('/users/create')} className="btn-primary">کاربر جدید</Link>}>
            <div className="card overflow-x-auto p-0">
                <table className="table">
                    <thead>
                        <tr>
                            <th>نام</th><th>نام کاربری</th><th>نقش</th><th>تیم</th><th>کد بازاریاب</th><th>وضعیت</th><th>آخرین ورود</th><th />
                        </tr>
                    </thead>
                    <tbody>
                        {users.map((u) => (
                            <tr key={u.id}>
                                <td>{u.name}</td>
                                <td dir="ltr" className="text-right">{u.username}</td>
                                <td>{u.role}</td>
                                <td>{u.team ?? '—'}</td>
                                <td className="num">{u.marketerCode ?? '—'}</td>
                                <td>
                                    <span className={`badge ${u.isActive ? 'bg-ok/10 text-ok' : 'bg-bad/10 text-bad'}`}>{u.isActive ? 'فعال' : 'غیرفعال'}</span>
                                </td>
                                <td>{jDateTime(u.lastLoginAt)}</td>
                                <td><Link className="text-brand" href={url(`/users/${u.id}/edit`)}>ویرایش</Link></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <p className="text-xs text-muted">«کد بازاریاب» باید دقیقاً با کد فراپیام یکی باشد؛ فروش فقط با همین کد به افراد نسبت داده می‌شود.</p>
        </AppLayout>
    );
}
