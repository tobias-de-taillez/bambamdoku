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

export type RangeOption = {
    id: number;
    name: string;
    lanes?: number;
};

export type AdminShootingDay = {
    id: number;
    series: string | null;
    date: string;
    starts_at: string;
    ends_at: string;
    title: string | null;
    notes: string | null;
    restricted_to_groups: boolean;
    guests_allowed: boolean;
    cancelled: boolean;
    cancel_reason: string | null;
    ranges: RangeOption[];
    groups: GroupOption[];
};
