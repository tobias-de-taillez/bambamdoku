<?php

namespace App\Domain;

use App\Enums\SessionEntryStatus;
use App\Models\LogEntry;
use App\Models\SessionEntry;
use App\Models\SessionParticipant;
use App\Models\ShootingSession;
use App\Models\Supervision;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Session-Protokoll (c) für den Vorstand (E14): Kopf, Teilnehmer, Einträge der Vorstufe, Abzeichnen
 * und spätere Stornos oder Korrekturen der zugehörigen Kladde-Zeilen. Bundesrechtlich keine Pflicht.
 */
final class SessionProtocol
{
    /**
     * Daten der Vorlage pdf/session, auch für Inhaltstests als HTML.
     *
     * @return array<string, mixed>
     */
    public static function data(ShootingSession $session, User $creator): array
    {
        $session->load([
            'supervisor:id,name',
            'participants' => fn ($q) => $q->orderBy('id'),
            'participants.user:id,name,member_number',
            'participants.entries' => fn ($q) => $q->orderBy('id'),
            'participants.entries.enteredBy:id,name',
            'participants.entries.rejectedBy:id,name',
            'participants.entries.logEntry.cancelledBy:id,name',
        ]);

        // Spätere Änderungen: Storno und Korrekturen der Kladde-Zeilen dieser Session (auch Ketten)
        $rows = $session->participants->flatMap(fn (SessionParticipant $p) => $p->entries)
            ->map(fn (SessionEntry $e) => $e->logEntry)
            ->filter();
        $changes = [];
        $queue = $rows->values()->all();

        while ($queue !== []) {
            /** @var LogEntry $row */
            $row = array_shift($queue);

            $corrections = LogEntry::query()->where('corrects_id', $row->id)->with(['recordedBy:id,name', 'cancelledBy:id,name', 'user:id,name'])->get();

            // Ein Storno ohne Korrektur; bei einer Korrektur steht die Änderung in deren Zeile.
            if (! $row->isActive() && $corrections->isEmpty()) {
                $changes[] = [
                    'at' => $row->cancelled_at,
                    'text' => sprintf('%s, %s (%s): storniert von %s: %s', $row->shooterName(), $row->weapon_class->label(), Disciplines::label($row->discipline) ?? 'ohne Disziplin', $row->cancelledBy->name ?? '–', (string) $row->cancel_reason),
                ];
            }

            foreach ($corrections as $correction) {
                $changes[] = [
                    'at' => $correction->created_at,
                    'text' => sprintf('%s, %s (%s): korrigiert von %s: %s; neu: %s, %s, %s, Schüsse %s', $row->shooterName(), $row->weapon_class->label(), Disciplines::label($row->discipline) ?? 'ohne Disziplin', $correction->recordedBy->name, (string) $correction->late_reason, $correction->weapon_class->label(), $correction->weapon_owner?->label() ?? 'Gast', Disciplines::label($correction->discipline) ?? 'ohne Disziplin', $correction->shots ?? '–'),
                ];
                $queue[] = $correction;
            }
        }

        usort($changes, fn (array $a, array $b) => $a['at'] <=> $b['at']);

        $supervisions = Supervision::query()
            ->where('user_id', $session->supervisor_id)
            ->whereHas('shootingDay', fn ($q) => $q->whereDate('date', $session->shot_on))
            ->with(['range:id,name', 'shootingDay:id,date,title'])
            ->orderBy('starts_at')
            ->get();

        return [
            'session' => $session,
            'state' => $session->isExpired() ? 'expired' : ($session->isOpen() ? 'open' : 'signed'),
            'supervisions' => $supervisions->map(fn (Supervision $s) => [
                'range' => $s->range->name ?? 'ganze Anlage',
                'window' => substr($s->starts_at, 0, 5).'–'.substr($s->ends_at, 0, 5),
            ])->all(),
            'participants' => $session->participants->map(fn (SessionParticipant $p) => [
                'name' => $p->name(),
                'guest' => $p->isGuest(),
                'member_number' => $p->user?->member_number,
                'activated_at' => $p->created_at->format('H:i'),
                'booking' => $p->booking_id !== null,
                'entries' => $p->entries->map(fn (SessionEntry $e) => [
                    'at' => $e->created_at->format('H:i'),
                    'via' => $e->via->label(),
                    'discipline' => Disciplines::label($e->discipline),
                    'weapon_label' => $e->weapon_class->label(),
                    'weapon_owner' => $e->weapon_owner?->label(),
                    'shots' => $e->shots,
                    'status' => $e->status->label(),
                    'rejected' => $e->status === SessionEntryStatus::Rejected,
                    'withdrawn' => $e->status === SessionEntryStatus::Withdrawn,
                    'reason' => $e->reject_reason,
                    'rejected_by' => $e->rejectedBy?->name,
                    'entered_by' => $e->enteredBy?->name,
                    // abgezeichnet, aber in eine schon vorhandene gleiche Kladde-Zeile zusammengeführt (K5)
                    'merged' => $e->status === SessionEntryStatus::Signed && $e->logEntry === null,
                ])->all(),
            ])->all(),
            'changes' => $changes,
            'createdAt' => CarbonImmutable::now(),
            'createdBy' => $creator->name,
            'club' => config('verein.name'),
            'clubAddress' => config('verein.address'),
        ];
    }
}
