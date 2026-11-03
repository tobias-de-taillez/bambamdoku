export type Role = 'admin' | 'member' | 'guest';

export type User = {
    id: number;
    name: string;
    email: string;
    role: Role;
    active: boolean;
    member_number: string | null;
    supervisor_until: string | null;
    avatar?: string;
    two_factor_enabled?: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    isAdmin: boolean;
    supervisesToday: boolean;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
