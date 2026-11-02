# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Delegiert, mit Vorgabe: moderner Web-Stack, der als Prototyp auf dem vorhandenen Hetzner-Webhosting (Apache, PHP, FTPS-Deploy über `scripts/deploy-hetzner.sh`) läuft. Gewählt: Laravel (PHP 8.3) + Inertia + Svelte 5 + Tailwind, Build lokal, Upload per FTPS. Begründung in `docs/PLAN.md`.

## Users

Ein einzelner Sportschützenverein, der die Software selbst betreibt. Rollen: Administrator (Vorstand/Schießwart), Standaufsicht nach § 27 WaffG, Mitglied (Sportschütze), Gastschütze. Benutzt wird es am Schießstand (Kiosk/Handy, oft unter Zeitdruck) und zu Hause (Planung, Nachweise).

## Product Purpose

Freie Open-Source-Alternative zu kommerzieller Schützenverein-Software (Referenz: rangetime.de): Schießtage planen, Bahnen buchen, Standaufsicht sicherstellen und den Bedürfnisnachweis über ein digitales Schießbuch führen. Erfolg: Der Verein kann einen Schießtag komplett ohne Papierliste abwickeln und Mitglieder erzeugen ihren Nachweis selbst als PDF.

## Positioning

Open Source, selbst gehostet, Daten bleiben beim Verein. Kein Mandanten-SaaS, keine Abo-Kosten.

## Operating Context

- Schießtag: Termine (auch wiederkehrend), mehrere Stände mit Bahnkapazität, Freigabe für alle oder bestimmte Gruppen/Gäste.
- Ohne eingeteilte Standaufsicht darf nicht geschossen werden (§ 27 WaffG, §§ 10, 11 AWaffV).
- Schießbuch-Einträge dienen dem Bedürfnisnachweis nach § 14 Abs. 5 WaffG und sind privat (nur das Mitglied sieht sie).

## Capabilities and Constraints

MVP: Standbuchung + Standaufsicht, Digitales Schießbuch (inkl. der dafür nötigen minimalen Benutzer-/Rollenverwaltung).
Später: Mitgliederverwaltung mit CSV-Import, Arbeitsdienste, Inventar/Tresor, Gebühren, Newsletter, Kleinanzeigen, Kiosk-Terminals.
Ausdrücklich nicht geplant: Mandantenfähigkeit, Online-Bezahlung (Stripe), kommerzielle Schießanlagen.
Rechtliche Anforderungen (Inhalt und Form des Schießbuch-Nachweises) sind mit dem Verein bzw. Verband zu prüfen, nicht erfunden.

## Brand Commitments

Projektname: bambamdoku. Keine Übernahme von Name, Logo, Texten, Screenshots oder Code von rangetime.de; nur der Funktionsumfang dient als Referenz.

## Evidence on Hand

Keine echten Vereinsdaten, Nutzerstimmen oder Screenshots. Funktionsreferenz: REA-Analyse von rangetime.de (öffentliche Seiten, 2026-11-02).

## Product Principles

1. Am Stand zählt Geschwindigkeit: Einbuchen in wenigen Taps, groß und robust bedienbar.
2. Rechtssicherheit vor Komfort: ohne Aufsicht keine Buchung, Nachweise sind vollständig und nachvollziehbar.
3. Privatsphäre: das Schießbuch gehört dem Mitglied.
4. Einfach selbst zu hosten: läuft auf günstigem Webhosting.
