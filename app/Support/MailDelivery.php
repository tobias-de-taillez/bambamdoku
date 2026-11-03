<?php

namespace App\Support;

/**
 * Ob Mails wirklich verschickt werden. Mit `log` oder `array` landen sie nur im Log
 * bzw. im Speicher; dann darf die Oberfläche nicht „benachrichtigt“ behaupten.
 */
class MailDelivery
{
    public static function mailer(): string
    {
        return (string) config('mail.default');
    }

    public static function active(): bool
    {
        return ! in_array(self::mailer(), ['log', 'array'], true);
    }
}
