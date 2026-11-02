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
};
