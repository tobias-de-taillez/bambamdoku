export type RecordEntry = {
    id: number;
    date: string;
    weapon_class: string;
    weapon_label: string;
    weapon_owner: string | null;
    club_weapon: boolean;
    discipline: string | null;
    recorded_by: string;
    late: boolean;
    late_reason: string | null;
    active: boolean;
    cancel_reason: string | null;
};

export type RecordTotal = {
    class: string;
    label: string;
    days: number;
    own_days: number;
};
