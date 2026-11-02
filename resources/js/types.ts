export type AuthUser = {
    id: number;
    name: string;
    username: string;
    role: string;
    roleLabel: string;
    permissions: string[];
};

export type SharedProps = {
    appName: string;
    base: string;
    auth: { user: AuthUser | null };
    flash: { success?: string | null; error?: string | null };
    errors: Record<string, string>;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
