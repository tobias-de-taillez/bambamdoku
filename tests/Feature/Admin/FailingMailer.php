<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Ein echter Mailer (nicht log oder array), der beim Senden scheitert,
 * etwa weil der SMTP-Server nicht erreichbar ist.
 */
trait FailingMailer
{
    /**
     * @param  string|null  $only  nur an Adressen scheitern, die diesen Teil enthalten; sonst an allen
     */
    protected function mailFails(?string $only = null): void
    {
        Mail::extend('kaputt', fn () => new class($only) extends AbstractTransport
        {
            public function __construct(private ?string $only)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                foreach ($message->getEnvelope()->getRecipients() as $recipient) {
                    if ($this->only === null || str_contains($recipient->getAddress(), $this->only)) {
                        throw new TransportException('Verbindung zum Mailserver abgelehnt.');
                    }
                }
            }

            public function __toString(): string
            {
                return 'kaputt://';
            }
        });

        config(['mail.mailers.kaputt' => ['transport' => 'kaputt'], 'mail.default' => 'kaputt']);
    }
}
