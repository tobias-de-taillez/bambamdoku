export type Option = { value: string; label: string };

export type EntryMember = {
    id: number;
    name: string;
    member_number: string | null;
    last: {
        weapon_class: string;
        weapon_owner: string | null;
        discipline: string | null;
    } | null;
};

export type TodayEntry = {
    id: number;
    name: string;
    member_number: string | null;
    guest: boolean;
    weapon_class: string;
    weapon_owner: string | null;
    discipline: string | null;
    recorded_by: string;
    time: string;
    active: boolean;
    cancel_reason: string | null;
    can_cancel: boolean;
};
