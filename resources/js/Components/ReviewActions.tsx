import { router } from '@inertiajs/react';
import { useState } from 'react';
import Icon from './Icon';

/** Approve / reject with a mandatory reason for rejection (error shown next to the field). */
export default function ReviewActions({ action }: { action: string }) {
    const [note, setNote] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const send = (decision: 'approved' | 'rejected') => {
        if (decision === 'rejected' && !note.trim()) {
            setError('برای رد کردن، دلیل را بنویسید.');
            return;
        }
        setError(null);
        router.post(action, { decision, note: note || null }, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: (e) => setError(Object.values(e)[0] ?? 'خطا'),
        });
    };

    return (
        <div className="flex min-w-56 flex-col gap-1">
            <div className="flex gap-1">
                <input className="input py-1 text-xs" placeholder="توضیح / دلیل رد" aria-label="توضیح بازبینی" value={note} onChange={(e) => setNote(e.target.value)} />
                <button className="btn-primary px-2 py-1" disabled={busy} onClick={() => send('approved')} aria-label="تأیید">
                    <Icon name="check" className="size-4" />
                </button>
                <button className="btn-danger px-2 py-1" disabled={busy} onClick={() => send('rejected')} aria-label="رد">
                    <Icon name="x" className="size-4" />
                </button>
            </div>
            {error && <p className="text-xs text-bad" role="alert">{error}</p>}
        </div>
    );
}
