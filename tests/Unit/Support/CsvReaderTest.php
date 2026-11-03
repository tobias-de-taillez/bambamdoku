<?php

namespace Tests\Unit\Support;

use App\Support\CsvReader;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class CsvReaderTest extends TestCase
{
    private function rejection(string $contents): string
    {
        try {
            CsvReader::records($contents);
        } catch (UnexpectedValueException $exception) {
            return $exception->getMessage();
        }

        $this->fail('Die Datei hätte abgewiesen werden müssen.');
    }

    public function test_unclosed_quote_rejects_the_file_with_the_line_where_the_record_starts()
    {
        $this->assertSame(
            'Zeile 2: Anführungszeichen nicht geschlossen – bitte die Datei prüfen. Ein einzelnes " bitte verdoppeln oder entfernen.',
            $this->rejection("Name;Bemerkung\r\nErika;\"Lehrgang 2019\r\nOtto;ok\r\nPia;ok\r\n"),
        );

        // Nach einem richtig geschlossenen mehrzeiligen Feld zählen die Zeilen wie in Excel weiter.
        $this->assertSame(
            'Zeile 5: Anführungszeichen nicht geschlossen – bitte die Datei prüfen. Ein einzelnes " bitte verdoppeln oder entfernen.',
            $this->rejection("Name;Bemerkung\nErika;\"zwei\nZeilen\"\nOtto;ok\nPia;\"offen\n"),
        );

        $this->assertSame(
            'Zeile 1: Anführungszeichen nicht geschlossen – bitte die Datei prüfen. Ein einzelnes " bitte verdoppeln oder entfernen.',
            $this->rejection("Name;\"Bemerkung\nErika;ok\n"),
        );
    }

    public function test_escaped_quotes_and_quoted_line_breaks_are_read()
    {
        $records = CsvReader::records("Name;Bemerkung\nErika;\"Lehrgang \"\"DSB\"\"\nzweite Zeile\"\nOtto;\"\"\"\"\n");

        $this->assertSame([
            ['line' => 1, 'values' => ['Name', 'Bemerkung']],
            ['line' => 2, 'values' => ['Erika', "Lehrgang \"DSB\"\nzweite Zeile"]],
            ['line' => 4, 'values' => ['Otto', '"']],
        ], $records);
    }

    public function test_a_single_quote_inside_a_field_also_rejects_the_file()
    {
        // fgetcsv läse „5" Kaliber“ zwar, die Zahl der Anführungszeichen ist aber ungerade:
        // lieber die Datei prüfen lassen als ein verschlucktes Mitglied riskieren.
        $this->assertSame(
            'Zeile 2: Anführungszeichen nicht geschlossen – bitte die Datei prüfen. Ein einzelnes " bitte verdoppeln oder entfernen.',
            $this->rejection("Name;Bemerkung\nErika;5\" Kaliber\nOtto;ok\n"),
        );
    }
}
