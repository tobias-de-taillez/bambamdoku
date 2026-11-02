import type { Role } from '@/types/auth';

export type GroupOption = {
    id: number;
    name: string;
};

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: Role;
    active: boolean;
    member_number: string | null;
    supervisor_until: string | null;
    can_supervise: boolean;
    groups: GroupOption[];
    has_logged_in: boolean;
};

export type RoleOption = {
    value: Role;
    label: string;
};
