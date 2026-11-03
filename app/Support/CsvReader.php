<?php

namespace App\Support;

use UnexpectedValueException;

/**
 * Liest CSV-Dateien aus Excel, Tabellenkalkulation oder Vereinsverwaltung: UTF-8 (mit oder ohne
 * BOM) oder Windows-1252, Trennzeichen Semikolon oder Komma (was in der ersten Zeile öfter vorkommt).
 */
final class CsvReader
{
    /**
     * Datensätze mit der Zeilennummer, in der sie beginnen (Kopfzeile = Zeile 1, wie Excel zählt).
     * fgetcsv liest auch Felder mit Zeilenumbruch in Anführungszeichen richtig. Leere Zeilen und
     * Zeilen nur aus Trennzeichen (Excel hängt gern „;;;;“ an) fallen weg.
     *
     * Ein nicht geschlossenes Anführungszeichen schluckt alle folgenden Zeilen in ein Feld; die
     * Mitglieder darin fehlten still und landeten als Text z. B. in der Bemerkung. Darum gilt
     * die ganze Datei als fehlerhaft, sobald ein Datensatz eine ungerade Zahl von
     * Anführungszeichen hat (ein verdoppeltes „""“ im Feld ändert daran nichts).
     *
     * @return list<array{line: int, values: list<string>}>
     *
     * @throws UnexpectedValueException mit der Meldung für die Vorschau
     */
    public static function records(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        $contents = str_replace(["\r\n", "\r"], "\n", trim($contents));
        $firstLine = strtok($contents, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return [];
        }

        fwrite($stream, $contents);
        rewind($stream);

        $records = [];
        $line = 1;
        $position = 0;

        while (($values = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $next = (int) ftell($stream);
            $start = $line;
            $length = max(0, min($next, strlen($contents)) - $position);

            if (substr_count($contents, '"', $position, $length) % 2 === 1) {
                fclose($stream);

                throw new UnexpectedValueException(sprintf('Zeile %d: Anführungszeichen nicht geschlossen – bitte die Datei prüfen. Ein einzelnes " bitte verdoppeln oder entfernen.', $start));
            }

            $line += substr_count($contents, "\n", $position, $length);
            $position = $next;

            $values = array_map(fn ($value) => (string) $value, $values);

            if (implode('', array_map('trim', $values)) === '') {
                continue;
            }

            $records[] = ['line' => $start, 'values' => $values];
        }

        fclose($stream);

        return $records;
    }
}
