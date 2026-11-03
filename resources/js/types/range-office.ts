export type Option = { value: string; label: string };

export type EntryMember = {
    id: number;
    name: string;
    member_number: string | null;
    adult_confirmed: boolean;
    last: {
        weapon_class: string;
        weapon_owner: string | null;
        discipline: string | null;
        shots: number | null;
    } | null;
};

export type SessionEntryRow = {
    id: number;
    time: string;
    via: string;
    entered_by: string | null;
    weapon_class: string;
    weapon_owner: string | null;
    discipline: string | null;
    shots: number | null;
    status: 'pending' | 'withdrawn' | 'rejected' | 'signed';
    status_label: string;
    reject_reason: string | null;
    can_withdraw: boolean;
};

export type Participant = {
    id: number;
    user_id: number | null;
    name: string;
    member_number: string | null;
    guest: boolean;
    from_booking: boolean;
    activated_at: string;
    removable: boolean;
    entries: SessionEntryRow[];
};

export type OpenSession = {
    id: number;
    started_at: string;
    participants: Participant[];
};

export type SignedSession = {
    id: number;
    started_at: string;
    signed_at: string | null;
    entries: number;
};

export type LastEntry = {
    weapon_class: string;
    weapon_owner: string | null;
    discipline: string | null;
    shots: number | null;
};

/** eigener Eintrag unter „Heute schießen“ */
export type OwnEntryRow = Omit<SessionEntryRow, 'entered_by'>;
