import { execSync } from 'node:child_process';
import { copyFileSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { basename, join } from 'node:path';
import type {
    FullResult,
    Reporter,
    TestCase,
    TestResult,
} from '@playwright/test/reporter';
import { NACHTPAUSE } from './lauf';
import { MATRIX, type Rolle, type Zeile } from './matrix';

/**
 * Beweisbericht des Rollen-Rundgangs: eine HTML-Seite mit allen Fotos je Geschichte und der
 * Matrix, welche Zeile durch welches Foto belegt ist. Bilder werden in den Berichtsordner
 * kopiert, damit der Ordner für sich allein lesbar ist (CI-Artefakt).
 *
 * Schranke: Bleibt eine Zeile der Matrix ohne Beleg aus einer bestandenen Geschichte (und ohne
 * Befund bei einem bekannten Fehler), ist der ganze Lauf rot.
 */

type Beweis = {
    person: string;
    rolle: Rolle;
    geraet: string;
    text: string;
    belegt: string[];
    befund: string[];
    bild: string;
};

type Geschichte = {
    titel: string;
    datei: string;
    zeile: number;
    status: TestResult['status'];
    erwartet: TestCase['expectedStatus'];
    versuch: number;
    dauer: number;
    beschreibung?: string;
    fehler?: string;
    fehlerbild?: string;
    nachtpause: boolean;
    beweise: Beweis[];
};

type Zustand = 'belegt' | 'offen' | 'rot' | 'fehlt';

const FARBEN: Record<Rolle, string> = {
    Gast: '#6b7280',
    Antragsteller: '#db2777',
    Mitglied: '#2563eb',
    Jugendlicher: '#0d9488',
    Aufsicht: '#d97706',
    Vorstand: '#7c3aed',
    Kiosk: '#16a34a',
    Gastschütze: '#78716c',
};

const ZUSTAND_TEXT: Record<Zustand, string> = {
    belegt: 'belegt',
    offen: 'bekannter Fehler',
    rot: 'fehlgeschlagen',
    fehlt: 'fehlt',
};

const ANSI = new RegExp(String.fromCharCode(27) + '\\[[0-9;]*m', 'g');

function e(text: string): string {
    return text
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function anker(g: Geschichte): string {
    return `g-${basename(g.datei, '.spec.ts')}-${g.zeile}`;
}

function bestanden(g: Geschichte): boolean {
    return g.status === g.erwartet && g.status === 'passed';
}

type Fundstelle = { g: Geschichte; i: number };

function fundstellen(
    geschichten: Geschichte[],
    art: 'belegt' | 'befund',
): Map<string, Fundstelle[]> {
    const karte = new Map<string, Fundstelle[]>();

    for (const g of geschichten) {
        g.beweise.forEach((b, i) => {
            for (const id of b[art]) {
                karte.set(id, [...(karte.get(id) ?? []), { g, i }]);
            }
        });
    }

    return karte;
}

function zustand(
    z: Zeile,
    belege: Map<string, Fundstelle[]>,
    befunde: Map<string, Fundstelle[]>,
): Zustand {
    const b = belege.get(z.id) ?? [];
    const f = befunde.get(z.id) ?? [];

    if (b.some(({ g }) => bestanden(g))) {
        return 'belegt';
    }

    if (z.offen && f.some(({ g }) => bestanden(g))) {
        return 'offen';
    }

    return b.length + f.length > 0 ? 'rot' : 'fehlt';
}

/** Wurde Playwright mit Dateien, Zeilen oder --grep aufgerufen? */
function teilLauf(): boolean {
    const args = process.argv.slice(process.argv.indexOf('test') + 1);

    for (let i = 0; i < args.length; i++) {
        const arg = args[i];

        if (
            [
                '-c',
                '--config',
                '--project',
                '--reporter',
                '-j',
                '--workers',
                '--retries',
                '--timeout',
                '--output',
            ].includes(arg)
        ) {
            i++;
        } else if (
            arg === '-g' ||
            arg.startsWith('--grep') ||
            arg === '--last-failed' ||
            arg === '--only-changed'
        ) {
            return true;
        } else if (!arg.startsWith('-')) {
            return true;
        }
    }

    return false;
}

export default class Beweisbericht implements Reporter {
    private readonly ordner: string;
    private readonly geschichten = new Map<string, Geschichte>();
    private readonly nummern = new Map<string, number>();
    private beginn = new Date();

    constructor(optionen: { ordner: string }) {
        this.ordner = optionen.ordner;
    }

    printsToStdio(): boolean {
        return false;
    }

    onBegin(): void {
        this.beginn = new Date();
        rmSync(this.ordner, { recursive: true, force: true });
        mkdirSync(join(this.ordner, 'bilder'), { recursive: true });
    }

    onTestEnd(test: TestCase, result: TestResult): void {
        if (!this.nummern.has(test.id)) {
            this.nummern.set(test.id, this.nummern.size + 1);
        }

        const nr = this.nummern.get(test.id)!;
        const meta = new Map<string, Omit<Beweis, 'bild'>>();

        for (const anhang of result.attachments) {
            const treffer = anhang.name.match(/^(beweis-\d+)\.json$/);

            if (treffer && anhang.body) {
                meta.set(treffer[1], JSON.parse(anhang.body.toString()));
            }
        }

        const beweise: Beweis[] = [];
        let fehlerbild: string | undefined;

        result.attachments.forEach((anhang, i) => {
            if (anhang.contentType !== 'image/png' || !anhang.path) {
                return;
            }

            const bild = `bilder/${nr}-${result.retry}-${String(i).padStart(3, '0')}.png`;

            try {
                copyFileSync(anhang.path, join(this.ordner, bild));
            } catch {
                return;
            }

            const daten = meta.get(anhang.name.replace(/\.png$/, ''));

            if (daten) {
                beweise.push({ ...daten, bild });
            } else {
                fehlerbild ??= bild;
            }
        });

        // Bei Wiederholungen zählt der letzte Versuch.
        this.geschichten.set(test.id, {
            titel: test.title,
            datei: basename(test.location.file),
            zeile: test.location.line,
            status: result.status,
            erwartet: test.expectedStatus,
            versuch: result.retry,
            dauer: result.duration,
            beschreibung: test.annotations.find((a) => a.type === 'geschichte')
                ?.description,
            fehler: result.error?.message?.replace(ANSI, ''),
            fehlerbild,
            nachtpause: result.annotations.some(
                (a) => a.type === 'skip' && a.description === NACHTPAUSE,
            ),
            beweise,
        });
    }

    onEnd(result: FullResult): { status?: FullResult['status'] } | void {
        const geschichten = [...this.geschichten.values()].sort(
            (a, b) => a.datei.localeCompare(b.datei) || a.zeile - b.zeile,
        );
        const belege = fundstellen(geschichten, 'belegt');
        const befunde = fundstellen(geschichten, 'befund');
        const zustaende = new Map(
            MATRIX.map((z) => [z.id, zustand(z, belege, befunde)]),
        );

        writeFileSync(
            join(this.ordner, 'beweise.json'),
            JSON.stringify(
                {
                    status: result.status,
                    matrix: MATRIX.map((z) => ({
                        ...z,
                        zustand: zustaende.get(z.id),
                    })),
                    geschichten,
                },
                null,
                2,
            ),
        );
        writeFileSync(
            join(this.ordner, 'index.html'),
            this.html(geschichten, result, belege, befunde, zustaende),
        );
        console.log(`\nBeweisbericht: ${join(this.ordner, 'index.html')}`);

        const offen = MATRIX.filter((z) => zustaende.get(z.id) === 'offen');
        const behoben = MATRIX.filter(
            (z) => z.offen && zustaende.get(z.id) === 'belegt',
        );
        const luecken = MATRIX.filter((z) =>
            ['rot', 'fehlt'].includes(zustaende.get(z.id)!),
        );

        if (offen.length > 0) {
            console.log(
                `Bekannte Fehler (offen): ${offen.map((z) => z.id).join(', ')}`,
            );
        }

        if (behoben.length > 0) {
            console.log(
                `Belegt, obwohl als offen markiert – „offen“ in matrix.ts entfernen: ${behoben.map((z) => z.id).join(', ')}`,
            );
        }

        if (luecken.length === 0) {
            return;
        }

        console.log(
            `Matrix-Zeilen ohne Beleg: ${luecken.map((z) => z.id).join(', ')}`,
        );

        if (geschichten.some((g) => g.nachtpause)) {
            console.log(NACHTPAUSE);

            return;
        }

        // Nur bei einem vollen Lauf: mit Datei- oder grep-Filter fehlen die übrigen Geschichten ohnehin.
        if (teilLauf()) {
            console.log('Teillauf (Filter), die Schranke greift nicht.');

            return;
        }

        if (result.status === 'passed') {
            return { status: 'failed' };
        }
    }

    private html(
        geschichten: Geschichte[],
        result: FullResult,
        belege: Map<string, Fundstelle[]>,
        befunde: Map<string, Fundstelle[]>,
        zustaende: Map<string, Zustand>,
    ): string {
        let commit = '';

        try {
            commit = execSync('git rev-parse --short HEAD', {
                encoding: 'utf8',
            }).trim();
        } catch {
            // ohne Git kein Commit im Kopf
        }

        const ok = geschichten.filter(bestanden).length;
        const belegt = MATRIX.filter(
            (z) => zustaende.get(z.id) === 'belegt',
        ).length;
        const offen = MATRIX.filter(
            (z) => zustaende.get(z.id) === 'offen',
        ).length;
        const fotos = geschichten.reduce((n, g) => n + g.beweise.length, 0);
        const zeit = this.beginn.toLocaleString('de-DE', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });

        const chip = (rolle: Rolle) =>
            `<span class="chip" style="--rolle:${FARBEN[rolle] ?? '#6b7280'}">${e(rolle)}</span>`;
        const mini = ({ g, i }: Fundstelle, art: string) =>
            `<a class="mini ${art}" href="#${anker(g)}-${i + 1}"><img src="${g.beweise[i].bild}" alt="" loading="lazy"></a>`;

        const matrixZeilen = MATRIX.map((z, i) => {
            const stand = zustaende.get(z.id)!;
            const bereich =
                i === 0 || MATRIX[i - 1].bereich !== z.bereich
                    ? `<tr class="bereich"><th colspan="4">${e(z.bereich)}</th></tr>`
                    : '';
            const links = [
                ...(belege.get(z.id) ?? []).map((f) => mini(f, 'beleg')),
                ...(befunde.get(z.id) ?? []).map((f) => mini(f, 'befund')),
            ].join('');

            return `${bereich}<tr id="m-${z.id}">
                <td class="id">${z.id}</td>
                <td>${e(z.ablauf)}<div class="rollen">${z.rollen.map(chip).join('')}</div>${z.offen ? `<div class="offen">Bekannter Fehler: ${e(z.offen)}</div>` : ''}${z.e2e ? `<div class="klein">auch in tests/e2e/${e(z.e2e)}</div>` : ''}</td>
                <td><span class="zustand ${stand}">${ZUSTAND_TEXT[stand]}</span></td>
                <td class="belege">${links}</td>
            </tr>`;
        }).join('');

        const abschnitte = geschichten
            .map((g) => {
                const schritte = g.beweise
                    .map(
                        (b, i) => `
                <li id="${anker(g)}-${i + 1}" class="schritt${b.befund.length ? ' mit-befund' : ''}">
                    <div class="meta">
                        <span class="nr">${i + 1}</span>
                        ${chip(b.rolle)}
                        <span class="person">${e(b.person)}</span>
                        <span class="klein">${e(b.geraet)}</span>
                        <p>${e(b.text)}</p>
                        ${b.belegt.length ? `<div class="ids">${b.belegt.map((id) => `<a href="#m-${id}">${id}</a>`).join('')}</div>` : ''}
                        ${b.befund.length ? `<div class="ids befund">Befund ${b.befund.map((id) => `<a href="#m-${id}">${id}</a>`).join('')}</div>` : ''}
                    </div>
                    <a class="foto ${e(b.geraet)}" href="${b.bild}" target="_blank"><img src="${b.bild}" alt="${e(`${b.person}: ${b.text}`)}" loading="lazy"></a>
                </li>`,
                    )
                    .join('');
                const zustandText = bestanden(g)
                    ? 'bestanden'
                    : g.nachtpause
                      ? 'Nachtpause'
                      : g.status;

                return `<section class="geschichte" id="${anker(g)}">
                <header>
                    <h2>${e(g.titel)}</h2>
                    <span class="zustand ${bestanden(g) ? 'belegt' : g.nachtpause ? 'fehlt' : 'rot'}">${e(zustandText)}</span>
                </header>
                <div class="klein">${e(g.datei)} · ${(g.dauer / 1000).toFixed(1)} s${g.versuch > 0 ? ` · Versuch ${g.versuch + 1}` : ''}</div>
                ${g.beschreibung ? `<p class="beschreibung">${e(g.beschreibung)}</p>` : ''}
                ${g.fehler ? `<pre class="fehler">${e(g.fehler)}</pre>` : ''}
                ${g.fehlerbild ? `<p class="klein">Bildschirm beim Fehler:</p><a class="foto" href="${g.fehlerbild}" target="_blank"><img src="${g.fehlerbild}" alt="Bildschirm beim Fehler" loading="lazy"></a>` : ''}
                <ol class="schritte">${schritte}</ol>
            </section>`;
            })
            .join('');

        const inhalt = geschichten
            .map(
                (g) =>
                    `<li><a href="#${anker(g)}">${e(g.titel)}</a> <span class="klein">${g.beweise.length} Fotos</span></li>`,
            )
            .join('');

        return `<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rollen-Rundgang</title>
<style>
:root { --bg:#fafaf9; --fg:#1c1917; --muted:#57534e; --line:#e7e5e4; --card:#fff; --ok:#15803d; --bad:#b91c1c; --warn:#a16207; --befund:#c2410c; }
@media (prefers-color-scheme: dark) { :root { --bg:#0c0a09; --fg:#f5f5f4; --muted:#a8a29e; --line:#292524; --card:#1c1917; --ok:#4ade80; --bad:#f87171; --warn:#facc15; --befund:#fb923c; } }
* { box-sizing:border-box; }
body { margin:0; background:var(--bg); color:var(--fg); font:15px/1.5 ui-sans-serif, system-ui, sans-serif; }
main { max-width:1100px; margin:0 auto; padding:32px 16px 64px; }
h1 { font-size:28px; margin:0 0 4px; letter-spacing:-0.01em; }
h2 { font-size:19px; margin:0; }
.klein { color:var(--muted); font-size:13px; }
.kopf { display:flex; flex-wrap:wrap; gap:8px 24px; margin:12px 0 24px; color:var(--muted); }
.kopf b { color:var(--fg); }
.inhalt { margin:0 0 40px; padding-left:20px; }
.chip { display:inline-block; padding:1px 8px; border-radius:999px; font-size:12px; font-weight:600; color:#fff; background:var(--rolle); white-space:nowrap; }
.rollen { display:flex; flex-wrap:wrap; gap:4px; margin-top:4px; }
.offen { color:var(--befund); font-size:13px; margin-top:4px; }
table { width:100%; border-collapse:collapse; margin:8px 0 48px; }
td, th { border-bottom:1px solid var(--line); padding:8px 6px; vertical-align:top; text-align:left; }
tr.bereich th { padding-top:20px; font-size:13px; color:var(--muted); }
td.id { font-variant-numeric:tabular-nums; font-weight:600; width:44px; }
.zustand { font-size:12px; font-weight:600; padding:1px 8px; border-radius:999px; border:1px solid currentColor; white-space:nowrap; }
.zustand.belegt { color:var(--ok); } .zustand.rot { color:var(--bad); } .zustand.fehlt { color:var(--warn); } .zustand.offen { color:var(--befund); }
.belege { display:flex; flex-wrap:wrap; gap:4px; width:36%; }
.mini img { height:44px; border:1px solid var(--line); border-radius:4px; display:block; }
.mini.befund img { border:2px solid var(--befund); }
.geschichte { border-top:1px solid var(--line); padding-top:28px; margin-top:28px; }
.geschichte header { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.beschreibung { max-width:70ch; }
.fehler { white-space:pre-wrap; background:var(--card); border:1px solid var(--bad); color:var(--bad); padding:12px; border-radius:8px; font-size:12px; overflow:auto; }
.schritte { list-style:none; padding:0; margin:16px 0 0; display:flex; flex-direction:column; gap:24px; }
.schritt { display:grid; grid-template-columns:minmax(0, 280px) minmax(0, 1fr); gap:20px; align-items:start; }
.meta { display:flex; flex-wrap:wrap; align-items:center; gap:6px; }
.meta p { flex-basis:100%; margin:4px 0 0; }
.nr { font-variant-numeric:tabular-nums; color:var(--muted); min-width:20px; }
.person { font-weight:600; }
.ids { display:flex; gap:6px; flex-basis:100%; font-size:12px; }
.ids.befund { color:var(--befund); font-weight:600; }
.mit-befund .foto img { border-color:var(--befund); }
.foto { display:block; justify-self:start; }
.foto img { display:block; max-width:100%; max-height:560px; border:1px solid var(--line); border-radius:8px; box-shadow:0 6px 18px -10px rgb(0 0 0 / 0.35); background:#fff; }
.foto.handy img { max-height:640px; }
a { color:inherit; }
@media (max-width: 720px) { .schritt { grid-template-columns:1fr; } .belege { width:auto; } }
</style>
</head>
<body>
<main>
<h1>Rollen-Rundgang</h1>
<p class="klein">Alle Rollen des Vereins und ihr Zusammenspiel, im Browser durchgespielt. Jedes Foto entsteht erst, nachdem die Prüfung davor bestanden ist; der rote Rahmen zeigt, was geprüft wurde. Orange markierte Fotos zeigen einen bekannten Fehler.</p>
<div class="kopf">
    <span>Lauf <b>${e(zeit)}</b></span>
    ${commit ? `<span>Commit <b>${e(commit)}</b></span>` : ''}
    <span>Geschichten <b>${ok} von ${geschichten.length} bestanden</b></span>
    <span>Matrix <b>${belegt} von ${MATRIX.length} belegt${offen ? `, ${offen} bekannte Fehler` : ''}</b></span>
    <span>Fotos <b>${fotos}</b></span>
    <span>Ergebnis <b>${result.status === 'passed' ? 'grün' : e(result.status)}</b></span>
</div>
<ol class="inhalt">${inhalt}</ol>
<h2>Matrix</h2>
<table>${matrixZeilen}</table>
<h2>Geschichten</h2>
${abschnitte}
</main>
</body>
</html>
`;
    }
}
