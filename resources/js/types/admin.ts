import type { Role } from '@/types/auth';

export type AdminUser = {
    id: number;
    name: string;
    email: string | null;
    role: Role;
    active: boolean;
    member_number: string | null;
    supervisor_until: string | null;
    supervisor_scope: 'exempt' | 'licensed' | null;
    member_since: string | null;
    can_supervise: boolean;
    has_logged_in: boolean;
    adult_confirmed: boolean;
    applied_at: string | null;
};

export type RoleOption = {
    value: Role;
    label: string;
};

export type RangeOption = {
    id: number;
    name: string;
    lanes?: number;
};

export type AdminShootingDay = {
    id: number;
    date: string;
    starts_at: string;
    ends_at: string;
    title: string | null;
    notes: string | null;
    guests_allowed: boolean;
    cancelled: boolean;
    cancel_reason: string | null;
    ranges: RangeOption[];
};
