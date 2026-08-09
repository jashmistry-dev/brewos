export interface User {
    id: number;
    name: string;
    email: string;
    is_super_admin: boolean;
}

export interface Cafe {
    id: number;
    name: string;
    slug: string;
    timezone: string;
    logo_url: string | null;
}

export interface AuthState {
    user: User | null;
    roles: string[];
    permissions: string[];
}

export interface TenantState {
    cafe: Cafe | null;
}

export interface FlashState {
    success: string | null;
    error: string | null;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: AuthState;
    tenant: TenantState;
    flash: FlashState;
};
