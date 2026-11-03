<?php

/*
|--------------------------------------------------------------------------
| Disziplinkataloge der Verbände
|--------------------------------------------------------------------------
|
| Schlüssel „Verband:SpO-Nummer“ => Bezeichnung nach der Sportordnung.
| Der Verein wählt seinen Katalog in den Einstellungen (Standard: VEREIN_DISZIPLINKATALOG).
| Ist kein Katalog gewählt, fragt die Kladde keine Disziplin ab.
|
| Die Nummern müssen exakt der gültigen Sportordnung des Verbands
| entsprechen (BSSB verlangt sie auf der Bedürfnisbescheinigung). Bitte
| den Katalog mit dem Verband abgleichen und per Pull Request ergänzen,
| statt Nummern zu raten.
|
| Schlüssel werden nie gelöscht oder umbenannt: Kladde-Einträge verweisen
| dauerhaft auf sie. Eine überholte Disziplin bleibt mit ihrem Namen stehen.
| Welcher Katalog gilt, wählt der Vorstand in den Einstellungen.
|
*/

return [

    // 'dsb' => [
    //     'DSB:1.10' => 'Luftgewehr',
    //     ...
    // ],

];
