export type RecordEntry = {
    id: number;
    date: string;
    weapon_class: string;
    weapon_label: string;
    weapon_owner: string | null;
    club_weapon: boolean;
    discipline: string | null;
    recorded_by: string;
    confirmed_by: string;
    via: string;
    shots: number | null;
    late: boolean;
    correction: boolean;
    corrects_id: number | null;
    late_reason: string | null;
    active: boolean;
    cancel_reason: string | null;
};

export type RecordTotal = {
    class: string;
    label: string;
    days: number;
    own_days: number;
    shots: number;
    shots_missing: number;
};

export type DisciplinePrint = {
    key: string;
    label: string;
    days: number;
};

/** Eigener Eintrag der Vorstufe, der nicht in die Kladde gekommen ist */
export type NotRecordedEntry = {
    id: number;
    date: string;
    time: string;
    weapon_label: string;
    weapon_owner: string | null;
    discipline: string | null;
    shots: number | null;
    via: string;
    supervisor: string;
    status: 'pending' | 'withdrawn' | 'rejected' | 'signed';
    status_label: string;
    reject_reason: string | null;
    rejected_by: string | null;
};
