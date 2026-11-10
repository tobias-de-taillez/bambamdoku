<?php

namespace App\Enums;

/**
 * Was aus der Einladungsmail geworden ist. Die Oberfläche sagt „per E-Mail verschickt“
 * nur bei Sent; sonst bietet sie den Link zum Weitergeben an.
 */
enum InvitationDelivery: string
{
    /** An einen echten Mailer übergeben, ohne Fehler. */
    case Sent = 'sent';

    /** Mailversand nicht eingerichtet (MAIL_MAILER=log oder array), nichts ging hinaus. */
    case NotConfigured = 'not_configured';

    /** Der Versand ist mit einem Fehler abgebrochen, z. B. SMTP nicht erreichbar. */
    case Failed = 'failed';

    /** Der Vorstand wollte keine Mail, nur den Link. */
    case NotRequested = 'not_requested';
}
