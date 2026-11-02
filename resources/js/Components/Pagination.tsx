import { Link } from '@inertiajs/react';
import type { Paginated } from '@/types';

function label(raw: string): string {
    if (raw.includes('Previous') || raw.includes('pagination.previous')) return 'قبلی';
    if (raw.includes('Next') || raw.includes('pagination.next')) return 'بعدی';
    return raw;
}

export default function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.last_page <= 1) return null;
    return (
        <nav className="mt-3 flex flex-wrap gap-1">
            {page.links.map((l, i) =>
                l.url ? (
                    <Link
                        key={i}
                        href={l.url}
                        preserveScroll
                        className={`rounded-md border px-3 py-1 text-sm ${l.active ? 'border-brand bg-brand text-white' : 'border-line bg-card'}`}
                    >
                        {label(l.label)}
                    </Link>
                ) : null,
            )}
        </nav>
    );
}
