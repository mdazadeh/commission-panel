import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

/** Builds app URLs under the deployment sub-path (/commission). */
export function useUrl() {
    const { base } = usePage<SharedProps>().props;
    return (path: string) => `${base}${path.startsWith('/') ? path : `/${path}`}`;
}
