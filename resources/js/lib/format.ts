const dateFormatter = new Intl.DateTimeFormat('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

/** Formatiert ein ISO-Datum (YYYY-MM-DD) als TT.MM.JJJJ. */
export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const [year, month, day] = value.slice(0, 10).split('-').map(Number);

    return dateFormatter.format(new Date(year, month - 1, day));
}

export const roleLabels: Record<string, string> = {
    admin: 'Administrator',
    member: 'Mitglied',
    guest: 'Gast',
    applicant: 'Antrag',
};

const weekdayFormatter = new Intl.DateTimeFormat('de-DE', { weekday: 'short' });
const longDateFormatter = new Intl.DateTimeFormat('de-DE', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});
const monthFormatter = new Intl.DateTimeFormat('de-DE', {
    month: 'long',
    year: 'numeric',
});

function parseDate(value: string): Date {
    const [year, month, day] = value.slice(0, 10).split('-').map(Number);

    return new Date(year, month - 1, day);
}

/** "Mo" für ein ISO-Datum. */
export function formatWeekday(value: string): string {
    return weekdayFormatter.format(parseDate(value)).replace('.', '');
}

/** "Montag, 3. November" */
export function formatLongDate(value: string): string {
    return longDateFormatter.format(parseDate(value));
}

/** "November 2026" */
export function formatMonth(value: string): string {
    return monthFormatter.format(parseDate(value));
}

export function isToday(value: string): boolean {
    const today = new Date();

    return parseDate(value).toDateString() === today.toDateString();
}

export const weaponTypeLabels: Record<string, string> = {
    kurzwaffe: 'Kurzwaffe',
    langwaffe: 'Langwaffe',
    luftdruck: 'Luftdruck',
    bogen: 'Bogen',
};
