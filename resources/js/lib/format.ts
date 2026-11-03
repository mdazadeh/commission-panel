const faNumber = new Intl.NumberFormat('fa-IR');
const faDate = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { year: 'numeric', month: '2-digit', day: '2-digit' });
const faDateTime = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
});

/** Amounts arrive as integer strings (rial) to avoid float precision loss; BigInt formats them exactly. */
export function rial(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    try {
        return faNumber.format(BigInt(String(value).split('.')[0]));
    } catch {
        return String(value);
    }
}

export function num(value: number | null | undefined): string {
    return value === null || value === undefined ? '—' : faNumber.format(value);
}

export function jDate(iso: string | null | undefined): string {
    return iso ? faDate.format(new Date(iso)) : '—';
}

export function jDateTime(iso: string | null | undefined): string {
    return iso ? faDateTime.format(new Date(iso)) : '—';
}

export function can(permissions: string[] | undefined, permission: string): boolean {
    return !!permissions?.includes(permission);
}

export function pct(ratio: string | number | null | undefined, digits = 1): string {
    if (ratio === null || ratio === undefined || ratio === '') return '—';
    return `${new Intl.NumberFormat('fa-IR', { maximumFractionDigits: digits }).format(Number(ratio) * 100)}٪`;
}

export function rateLabel(rate: string | number): string {
    return `${new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 3 }).format(Number(rate) * 100)}٪`;
}

/** Integer rial formatting for 4-decimal engine values (truncates the fraction for display). */
export function rialDec(value: string | null | undefined): string {
    if (!value) return '—';
    return rial(value.split('.')[0]);
}

export const CATEGORY_LABEL: Record<string, string> = {
    action_plan: 'اکشن‌پلن لیبل‌دار',
    rep_contract: 'نماینده با قرارداد',
    rep_no_contract: 'نماینده بدون قرارداد / غیر لیبل',
};
