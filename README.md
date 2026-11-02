# bambamdoku

Open-Source-Software für Sportschützenvereine: Schießtage, Standbuchung mit Standaufsicht (§ 27 WaffG) und digitales Schießbuch. Für einen Verein, der sie selbst betreibt.

Stand: **M0** – Grundgerüst mit Login. Plan: [docs/PLAN.md](docs/PLAN.md), Produktkontext: [PRODUCT.md](PRODUCT.md).

## Stack

Laravel 13 (PHP ≥ 8.3), Inertia, Svelte 5, TypeScript, Tailwind 4. SQLite lokal, MariaDB oder SQLite im Betrieb.

## Lokal starten

```bash
composer setup          # installiert alles, legt .env und SQLite-DB an, baut das Frontend
```

In `.env` `ADMIN_EMAIL` und `ADMIN_PASSWORD` setzen, dann:

```bash
php artisan db:seed     # legt den ersten Admin an (Registrierung ist abgeschaltet)
composer dev            # Server + Vite unter http://localhost:8000
```

## Prüfen

```bash
composer ci:check       # Formatierung, svelte-check, Pint, PHPStan, Tests
```

## Deploy

Siehe [docs/DEPLOY.md](docs/DEPLOY.md).

## Lizenz

[Apache License 2.0](LICENSE)
