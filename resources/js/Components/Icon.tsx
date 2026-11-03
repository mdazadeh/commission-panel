// Inline SVG icons (Lucide-style, 1.75 stroke) — bundled, no icon font / CDN, no emoji.
const PATHS = {
    menu: 'M4 6h16M4 12h16M4 18h16',
    check: 'M20 6 9 17l-5-5',
    x: 'M18 6 6 18M6 6l12 12',
    alert: 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
    info: 'M12 16v-4m0-4h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0z',
    trend: 'm3 17 6-6 4 4 8-8M14 7h7v7',
    target: 'M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0zM18 12a6 6 0 1 1-12 0 6 6 0 0 1 12 0zM14 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0z',
    wallet: 'M19 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h14a2 2 0 0 1 2 2v4h-4a2 2 0 0 0 0 4h4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5',
    layers: 'm12 2 10 5-10 5L2 7l10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
    activity: 'M22 12h-4l-3 9L9 3l-3 9H2',
    trash: 'M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14',
} as const;

export type IconName = keyof typeof PATHS;

export default function Icon({ name, className = 'size-5', label }: { name: IconName; className?: string; label?: string }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            className={className}
            role={label ? 'img' : undefined}
            aria-label={label}
            aria-hidden={label ? undefined : true}
        >
            <path d={PATHS[name]} />
        </svg>
    );
}
