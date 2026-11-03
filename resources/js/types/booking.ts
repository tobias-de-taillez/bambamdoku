export type SupervisionState = 'full' | 'partial' | 'none';

export type DaySummary = {
    id: number;
    date: string;
    starts_at: string;
    ends_at: string;
    title: string | null;
    notes?: string | null;
    cancelled: boolean;
    cancel_reason: string | null;
    guests_allowed: boolean;
    over: boolean;
    ranges: string[];
    capacity: number;
    booked: number;
    supervision: SupervisionState;
    supervisors: string[];
    my_booking_count: number;
    i_supervise: boolean;
};

export type GridSlot = {
    past: boolean;
    start: string;
    end: string;
    free: number;
    supervised: boolean;
};

export type GridRange = {
    id: number;
    name: string;
    lanes: number;
    weapon_types: string[];
    slots: GridSlot[];
};

export type SupervisionEntry = {
    id: number;
    name: string;
    range: string | null;
    starts_at: string;
    ends_at: string;
    mine: boolean;
    can_remove: boolean;
};

export type MyBooking = {
    id: number;
    shooting_day_id?: number;
    date?: string;
    range: string;
    starts_at: string;
    ends_at: string;
    weapon_type: string;
    club_weapon: boolean;
    guest_name: string | null;
    status: 'booked' | 'checked_in' | 'cancelled' | 'no_show';
    can_cancel: boolean;
};

export const statusLabels: Record<MyBooking['status'], string> = {
    booked: 'Gebucht',
    checked_in: 'Aktiviert',
    cancelled: 'Storniert',
    no_show: 'Nicht erschienen',
};
