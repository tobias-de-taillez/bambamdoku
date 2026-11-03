# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Delegiert, mit Vorgabe: moderner Web-Stack, der als Prototyp auf dem vorhandenen Hetzner-Webhosting (Apache, PHP, FTPS-Deploy über `scripts/deploy-hetzner.sh`) läuft. Gewählt: Laravel (PHP 8.3) + Inertia + Svelte 5 + Tailwind, Build lokal, Upload per FTPS. Begründung in `docs/PLAN.md`.

## Users

Ein einzelner Sportschützenverein, der die Software selbst betreibt. Rollen: Administrator (Vorstand/Schießwart), Standaufsicht nach § 27 WaffG, Mitglied (Sportschütze), Gastschütze. Benutzt wird es am Schießstand (Kiosk/Handy, oft unter Zeitdruck) und zu Hause (Planung, Nachweise).

## Product Purpose

Freie Open-Source-Alternative zu kommerzieller Schützenverein-Software (Referenz: rangetime.de): Schießtage planen, Bahnen buchen, Standaufsicht sicherstellen und am Stand eine digitale Schießkladde führen, aus der Verein und Mitglied den Nachweis nach § 14 WaffG ziehen. Erfolg: Der Verein erfasst die Schießbesuche eines Schießtags ohne Papierliste, und Mitglieder laden ihren Auszug selbst als PDF. Drucken lässt sich trotzdem alles, was der Nachweis braucht. Minderjährige samt Einwilligungen, Vorkommnisse und der Aushang der Aufsicht bleiben vorerst auf Papier.

## Positioning

Open Source, selbst gehostet, Daten bleiben beim Verein. Kein Mandanten-SaaS, keine Abo-Kosten.

## Operating Context

- Schießtag: Termine (auch wiederkehrend), mehrere Stände mit Bahnkapazität, Freigabe für alle oder bestimmte Gruppen/Gäste.
- Ohne eingeteilte Standaufsicht darf nicht geschossen werden (§ 27 WaffG, §§ 10, 11 AWaffV).
- Die Schießkladde gehört dem Verein. Jeder Schießbesuch wird am Stand erfasst (wer, wann, Kurz- oder Langwaffe, eigene oder Vereinswaffe) und von der Standaufsicht bestätigt.
- Nachweiskette nach § 14 Abs. 3 und 4 WaffG: Der Schütze macht das Bedürfnis glaubhaft, der Verband bescheinigt, der Verein prüft vorher gegen seine Kladde. Ab 10 Jahren nach der ersten WBK-Eintragung genügt eine Mitgliedschaftsbescheinigung des Vereins. Quelle: https://www.gesetze-im-internet.de/waffg_2002/__14.html
- Mitglieder sehen nur ihre eigenen Einträge und exportieren daraus ihren Auszug; Vorstand und Schießwart sehen alle, weil der Verein gegen die Kladde bescheinigt.

## Capabilities and Constraints

MVP: Standbuchung + Standaufsicht, Schieß-Session mit Selbsteintrag der Mitglieder (eigenes Handy oder Kiosk-Tablet mit PIN) und Abzeichnen durch die Aufsicht, digitale Schießkladde mit Nachweis-Auszug und Drucken, Selbstregistrierung mit Aufnahme durch den Verein (inkl. der dafür nötigen minimalen Benutzer-/Rollenverwaltung). Bauplan: `docs/UMBAU-SESSION.md`.
Später: Mitgliederverwaltung mit CSV-Import, Arbeitsdienste, Inventar/Tresor, Gebühren, Newsletter, Kleinanzeigen, Minderjährige digital.
Ausdrücklich nicht geplant: Mandantenfähigkeit, Online-Bezahlung (Stripe), kommerzielle Schießanlagen.
Rechtliche Anforderungen werden mit Primärquellen (Gesetzestext) belegt, nicht erfunden. Was das Gesetz offenlässt (Format der Bescheinigung, Muster des Verbands), ist mit Verein bzw. Verband zu klären.

## Brand Commitments

Projektname: bambamdoku. Keine Übernahme von Name, Logo, Texten, Screenshots oder Code von rangetime.de; nur der Funktionsumfang dient als Referenz.

## Evidence on Hand

Keine echten Vereinsdaten, Nutzerstimmen oder Screenshots. Funktionsreferenz: REA-Analyse von rangetime.de (öffentliche Seiten, 2026-11-02).

## Product Principles

1. Am Stand zählt Geschwindigkeit: Einbuchen in wenigen Taps, groß und robust bedienbar.
2. Rechtssicherheit vor Komfort: ohne Aufsicht keine Buchung, Nachweise sind vollständig und nachvollziehbar.
3. Datensparsam: Die Kladde gehört dem Verein, Mitglieder sehen nur ihre eigenen Einträge, erfasst wird nur, was § 14 WaffG und der Standbetrieb brauchen.
4. Einfach selbst zu hosten: läuft auf günstigem Webhosting.
