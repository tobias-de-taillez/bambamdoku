<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Die folgenden Texte enthalten die Standard-Fehlermeldungen der
    | Validierung. Einige Regeln haben mehrere Varianten, z. B. die
    | Größenregeln.
    |
    */

    'accepted' => ':Attribute muss akzeptiert werden.',
    'accepted_if' => ':Attribute muss akzeptiert werden, wenn :other :value ist.',
    'active_url' => ':Attribute muss eine gültige URL sein.',
    'after' => ':Attribute muss ein Datum nach dem :date sein.',
    'after_or_equal' => ':Attribute muss ein Datum am oder nach dem :date sein.',
    'alpha' => ':Attribute darf nur Buchstaben enthalten.',
    'alpha_dash' => ':Attribute darf nur Buchstaben, Ziffern, Binde- und Unterstriche enthalten.',
    'alpha_num' => ':Attribute darf nur Buchstaben und Ziffern enthalten.',
    'any_of' => ':Attribute ist ungültig.',
    'array' => ':Attribute muss eine Liste sein.',
    'array_keys' => ':Attribute darf nur die folgenden Schlüssel enthalten: :values.',
    'ascii' => ':Attribute darf nur alphanumerische Einzelbyte-Zeichen und Symbole enthalten.',
    'base64' => ':Attribute muss eine gültige Base64-Zeichenkette sein.',
    'before' => ':Attribute muss ein Datum vor dem :date sein.',
    'before_or_equal' => ':Attribute muss ein Datum am oder vor dem :date sein.',
    'between' => [
        'array' => ':Attribute muss zwischen :min und :max Einträge haben.',
        'file' => ':Attribute muss zwischen :min und :max Kilobyte groß sein.',
        'numeric' => ':Attribute muss zwischen :min und :max liegen.',
        'string' => ':Attribute muss zwischen :min und :max Zeichen lang sein.',
    ],
    'boolean' => ':Attribute muss wahr oder falsch sein.',
    'can' => ':Attribute enthält einen nicht erlaubten Wert.',
    'confirmed' => ':Attribute stimmt nicht mit der Bestätigung überein.',
    'contains' => ':Attribute fehlt ein erforderlicher Wert.',
    'current_password' => 'Das Passwort ist falsch.',
    'date' => ':Attribute muss ein gültiges Datum sein.',
    'date_equals' => ':Attribute muss ein Datum gleich dem :date sein.',
    'date_format' => ':Attribute muss dem Format :format entsprechen.',
    'decimal' => ':Attribute muss :decimal Nachkommastellen haben.',
    'declined' => ':Attribute muss abgelehnt werden.',
    'declined_if' => ':Attribute muss abgelehnt werden, wenn :other :value ist.',
    'different' => ':Attribute und :other müssen sich unterscheiden.',
    'digits' => ':Attribute muss :digits Ziffern lang sein.',
    'digits_between' => ':Attribute muss zwischen :min und :max Ziffern lang sein.',
    'dimensions' => ':Attribute hat ungültige Bildabmessungen.',
    'distinct' => ':Attribute enthält einen doppelten Wert.',
    'doesnt_contain' => ':Attribute darf keinen der folgenden Werte enthalten: :values.',
    'doesnt_end_with' => ':Attribute darf nicht mit einem der folgenden Werte enden: :values.',
    'doesnt_start_with' => ':Attribute darf nicht mit einem der folgenden Werte beginnen: :values.',
    'email' => ':Attribute muss eine gültige E-Mail-Adresse sein.',
    'encoding' => ':Attribute muss in :encoding kodiert sein.',
    'ends_with' => ':Attribute muss mit einem der folgenden Werte enden: :values.',
    'enum' => 'Der gewählte Wert für :attribute ist ungültig.',
    'exists' => 'Der gewählte Wert für :attribute ist ungültig.',
    'extensions' => ':Attribute muss eine der folgenden Dateiendungen haben: :values.',
    'file' => ':Attribute muss eine Datei sein.',
    'filled' => ':Attribute muss einen Wert haben.',
    'gt' => [
        'array' => ':Attribute muss mehr als :value Einträge haben.',
        'file' => ':Attribute muss größer als :value Kilobyte sein.',
        'numeric' => ':Attribute muss größer als :value sein.',
        'string' => ':Attribute muss länger als :value Zeichen sein.',
    ],
    'gte' => [
        'array' => ':Attribute muss mindestens :value Einträge haben.',
        'file' => ':Attribute muss größer oder gleich :value Kilobyte sein.',
        'numeric' => ':Attribute muss größer oder gleich :value sein.',
        'string' => ':Attribute muss mindestens :value Zeichen lang sein.',
    ],
    'hex_color' => ':Attribute muss eine gültige hexadezimale Farbe sein.',
    'image' => ':Attribute muss ein Bild sein.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'in_array' => ':Attribute muss in :other enthalten sein.',
    'in_array_keys' => ':Attribute muss mindestens einen der folgenden Schlüssel enthalten: :values.',
    'integer' => ':Attribute muss eine ganze Zahl sein.',
    'ip' => ':Attribute muss eine gültige IP-Adresse sein.',
    'ipv4' => ':Attribute muss eine gültige IPv4-Adresse sein.',
    'ipv6' => ':Attribute muss eine gültige IPv6-Adresse sein.',
    'json' => ':Attribute muss eine gültige JSON-Zeichenkette sein.',
    'list' => ':Attribute muss eine Liste sein.',
    'lowercase' => ':Attribute darf nur Kleinbuchstaben enthalten.',
    'lt' => [
        'array' => ':Attribute muss weniger als :value Einträge haben.',
        'file' => ':Attribute muss kleiner als :value Kilobyte sein.',
        'numeric' => ':Attribute muss kleiner als :value sein.',
        'string' => ':Attribute muss kürzer als :value Zeichen sein.',
    ],
    'lte' => [
        'array' => ':Attribute darf nicht mehr als :value Einträge haben.',
        'file' => ':Attribute muss kleiner oder gleich :value Kilobyte sein.',
        'numeric' => ':Attribute muss kleiner oder gleich :value sein.',
        'string' => ':Attribute darf höchstens :value Zeichen lang sein.',
    ],
    'mac_address' => ':Attribute muss eine gültige MAC-Adresse sein.',
    'max' => [
        'array' => ':Attribute darf nicht mehr als :max Einträge haben.',
        'file' => ':Attribute darf nicht größer als :max Kilobyte sein.',
        'numeric' => ':Attribute darf nicht größer als :max sein.',
        'string' => ':Attribute darf nicht länger als :max Zeichen sein.',
    ],
    'max_digits' => ':Attribute darf nicht mehr als :max Ziffern haben.',
    'mimes' => ':Attribute muss eine Datei vom Typ :values sein.',
    'mimetypes' => ':Attribute muss eine Datei vom Typ :values sein.',
    'min' => [
        'array' => ':Attribute muss mindestens :min Einträge haben.',
        'file' => ':Attribute muss mindestens :min Kilobyte groß sein.',
        'numeric' => ':Attribute muss mindestens :min sein.',
        'string' => ':Attribute muss mindestens :min Zeichen lang sein.',
    ],
    'min_digits' => ':Attribute muss mindestens :min Ziffern haben.',
    'missing' => ':Attribute darf nicht vorhanden sein.',
    'missing_if' => ':Attribute darf nicht vorhanden sein, wenn :other :value ist.',
    'missing_unless' => ':Attribute darf nicht vorhanden sein, außer :other ist :value.',
    'missing_with' => ':Attribute darf nicht vorhanden sein, wenn :values angegeben ist.',
    'missing_with_all' => ':Attribute darf nicht vorhanden sein, wenn :values angegeben sind.',
    'multiple_of' => ':Attribute muss ein Vielfaches von :value sein.',
    'not_in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'not_regex' => 'Das Format von :attribute ist ungültig.',
    'numeric' => ':Attribute muss eine Zahl sein.',
    'password' => [
        'letters' => ':Attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':Attribute muss mindestens einen Groß- und einen Kleinbuchstaben enthalten.',
        'numbers' => ':Attribute muss mindestens eine Ziffer enthalten.',
        'symbols' => ':Attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => ':Attribute ist in einem Datenleck aufgetaucht. Bitte wählen Sie ein anderes Passwort.',
    ],
    'present' => ':Attribute muss vorhanden sein.',
    'present_if' => ':Attribute muss vorhanden sein, wenn :other :value ist.',
    'present_unless' => ':Attribute muss vorhanden sein, außer :other ist :value.',
    'present_with' => ':Attribute muss vorhanden sein, wenn :values angegeben ist.',
    'present_with_all' => ':Attribute muss vorhanden sein, wenn :values angegeben sind.',
    'prohibited' => ':Attribute ist nicht erlaubt.',
    'prohibited_if' => ':Attribute ist nicht erlaubt, wenn :other :value ist.',
    'prohibited_if_accepted' => ':Attribute ist nicht erlaubt, wenn :other akzeptiert ist.',
    'prohibited_if_declined' => ':Attribute ist nicht erlaubt, wenn :other abgelehnt ist.',
    'prohibited_unless' => ':Attribute ist nicht erlaubt, außer :other ist in :values enthalten.',
    'prohibits' => ':Attribute verbietet die Angabe von :other.',
    'regex' => 'Das Format von :attribute ist ungültig.',
    'required' => ':Attribute muss ausgefüllt werden.',
    'required_array_keys' => ':Attribute muss Einträge für :values enthalten.',
    'required_if' => ':Attribute muss ausgefüllt werden, wenn :other :value ist.',
    'required_if_accepted' => ':Attribute muss ausgefüllt werden, wenn :other akzeptiert ist.',
    'required_if_declined' => ':Attribute muss ausgefüllt werden, wenn :other abgelehnt ist.',
    'required_unless' => ':Attribute muss ausgefüllt werden, außer :other ist in :values enthalten.',
    'required_with' => ':Attribute muss ausgefüllt werden, wenn :values angegeben ist.',
    'required_with_all' => ':Attribute muss ausgefüllt werden, wenn :values angegeben sind.',
    'required_without' => ':Attribute muss ausgefüllt werden, wenn :values nicht angegeben ist.',
    'required_without_all' => ':Attribute muss ausgefüllt werden, wenn keines der Felder :values angegeben ist.',
    'same' => ':Attribute und :other müssen übereinstimmen.',
    'size' => [
        'array' => ':Attribute muss genau :size Einträge enthalten.',
        'file' => ':Attribute muss :size Kilobyte groß sein.',
        'numeric' => ':Attribute muss gleich :size sein.',
        'string' => ':Attribute muss genau :size Zeichen lang sein.',
    ],
    'starts_with' => ':Attribute muss mit einem der folgenden Werte beginnen: :values.',
    'string' => ':Attribute muss eine Zeichenkette sein.',
    'timezone' => ':Attribute muss eine gültige Zeitzone sein.',
    'unique' => ':Attribute ist bereits vergeben.',
    'uploaded' => ':Attribute konnte nicht hochgeladen werden.',
    'uppercase' => ':Attribute darf nur Großbuchstaben enthalten.',
    'url' => ':Attribute muss eine gültige URL sein.',
    'ulid' => ':Attribute muss eine gültige ULID sein.',
    'uuid' => ':Attribute muss eine gültige UUID sein.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Eigene Meldungen für einzelne Attribute nach dem Schema
    | "attribut.regel".
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Lesbare Bezeichnungen für die Platzhalter :attribute bzw. :Attribute.
    |
    */

    'attributes' => [
        'active' => 'aktiv',
        'can_supervise' => 'Aufsichtsberechtigung',
        'code' => 'Code',
        'credential' => 'Passkey',
        'current_password' => 'aktuelles Passwort',
        'description' => 'Beschreibung',
        'email' => 'E-Mail-Adresse',
        'groups' => 'Gruppen',
        'groups.*' => 'Gruppe',
        'member_number' => 'Mitgliedsnummer',
        'name' => 'Name',
        'password' => 'Passwort',
        'password_confirmation' => 'Passwortbestätigung',
        'recovery_code' => 'Wiederherstellungscode',
        'remember' => 'Angemeldet bleiben',
        'role' => 'Rolle',
        'search' => 'Suche',
        'send_invitation' => 'Einladung senden',
        'supervisor_until' => 'Aufsicht gültig bis',
        'token' => 'Token',
    ],

];
