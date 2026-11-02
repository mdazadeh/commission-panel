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
