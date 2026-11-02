export type RecordEntry = {
    id: number;
    date: string;
    starts_at: string;
    ends_at: string;
    range: string;
    weapon_type: string;
    weapon_label: string;
    club_weapon: boolean;
    confirmed_by: string | null;
    confirmed_at: string | null;
};

export type AcquisitionCheck = {
    from: string;
    to: string;
    total: number;
    months: { month: string; count: number }[];
    months_covered: number;
    every_month: boolean;
    eighteen: boolean;
    fulfilled: boolean;
};

export type PossessionCheck = {
    weapon_type: string;
    label: string;
    total: number;
    quarters: { from: string; to: string; count: number }[];
    quarters_covered: number;
    every_quarter: boolean;
    best_twelve_months: number;
    six_in_twelve: boolean;
    fulfilled: boolean;
};
