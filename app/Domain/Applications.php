<?php

namespace App\Domain;

use App\Enums\Role;
use App\Models\AuditEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mitgliedsanträge (E7): Ein Konto mit role=applicant und active=false ist der Antrag.
 * Der Vorstand nimmt auf oder lehnt ab; alte Anträge löscht der Löschlauf.
 */
class Applications
{
    /**
     * Nach so vielen Tagen löscht der Löschlauf einen nicht bearbeiteten Antrag.
     */
    public const RETENTION_DAYS = 30;

    /**
     * Aufnehmen: Identität und Volljährigkeit hat der Vorstand geprüft.
     */
    public function admit(User $admin, User $applicant, ?string $memberNumber, ?string $memberSince): User
    {
        $this->ensureApplicant($applicant);

        $applicant->forceFill([
            'role' => Role::Member,
            'active' => true,
            'member_number' => $memberNumber,
            'member_since' => $memberSince ?? CarbonImmutable::today()->toDateString(),
            'email_verified_at' => now(),
            'adult_confirmed_at' => now(),
        ])->save();

        AuditEntry::record($admin, 'application.admitted', $applicant, sprintf(
            'Antrag aufgenommen: %s (Nr. %s, Mitglied seit %s), Identität und Volljährigkeit geprüft',
            $applicant->name,
            $applicant->member_number ?? '–',
            $applicant->member_since?->toDateString() ?? '–',
        ));

        return $applicant;
    }

    /**
     * Ablehnen: Der Antrag wird gelöscht; das Protokoll nennt keine Personendaten.
     */
    public function reject(User $admin, User $applicant): void
    {
        $this->ensureApplicant($applicant);

        DB::transaction(function () use ($admin, $applicant) {
            $id = $applicant->id;
            $this->delete($applicant);
            AuditEntry::record($admin, 'application.rejected', null, sprintf('Mitgliedsantrag #%d abgelehnt und gelöscht', $id));
        });
    }

    /**
     * Löschlauf: nur Anträge, die älter als die Frist sind. Nie Mitglieder, auch keine deaktivierten.
     */
    public function purge(): int
    {
        $count = 0;

        User::query()
            ->where('role', Role::Applicant->value)
            ->where('active', false)
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->each(function (User $applicant) use (&$count) {
                $this->delete($applicant);
                $count++;
            });

        if ($count > 0) {
            AuditEntry::record(null, 'application.purged', null, sprintf('%d Mitgliedsantrag/-anträge nach %d Tagen gelöscht', $count, self::RETENTION_DAYS));
        }

        return $count;
    }

    private function delete(User $applicant): void
    {
        if ($applicant->email !== null) {
            DB::table('password_reset_tokens')->where('email', $applicant->email)->delete();
        }

        DB::table('sessions')->where('user_id', $applicant->id)->delete();
        $applicant->delete();
    }

    private function ensureApplicant(User $user): void
    {
        if (! $user->isApplicant() || $user->active) {
            throw ValidationException::withMessages(['user' => __('Das ist kein offener Mitgliedsantrag.')]);
        }
    }
}
