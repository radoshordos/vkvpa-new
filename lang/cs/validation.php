<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validační hlášky (čeština)
    |--------------------------------------------------------------------------
    |
    | Laravel bez tohoto souboru spadne na vestavěné anglické hlášky.
    | :attribute se nahradí názvem pole z pole `attributes` níže (jinak
    | samotným názvem pole). Pravidla aplikace doplňují vlastní `attributes`
    | přímo ve validaci (např. „kontakt / e-mail“).
    |
    */

    'accepted' => 'Pole :attribute musí být přijato.',
    'accepted_if' => 'Pole :attribute musí být přijato, když má :other hodnotu :value.',
    'active_url' => 'Pole :attribute musí být platná URL adresa.',
    'after' => 'Pole :attribute musí obsahovat datum po :date.',
    'after_or_equal' => 'Pole :attribute musí obsahovat datum po nebo shodné s :date.',
    'alpha' => 'Pole :attribute smí obsahovat pouze písmena.',
    'alpha_dash' => 'Pole :attribute smí obsahovat pouze písmena, číslice, pomlčky a podtržítka.',
    'alpha_num' => 'Pole :attribute smí obsahovat pouze písmena a číslice.',
    'array' => 'Pole :attribute musí být pole.',
    'ascii' => 'Pole :attribute smí obsahovat pouze jednobytové alfanumerické znaky a symboly.',
    'before' => 'Pole :attribute musí obsahovat datum před :date.',
    'before_or_equal' => 'Pole :attribute musí obsahovat datum před nebo shodné s :date.',
    'between' => [
        'array' => 'Pole :attribute musí mít položek mezi :min a :max.',
        'file' => 'Soubor v poli :attribute musí mít velikost mezi :min a :max kilobajty.',
        'numeric' => 'Pole :attribute musí mít hodnotu mezi :min a :max.',
        'string' => 'Pole :attribute musí mít délku mezi :min a :max znaky.',
    ],
    'boolean' => 'Pole :attribute musí mít hodnotu true nebo false.',
    'confirmed' => 'Pole :attribute se neshoduje s potvrzením.',
    'current_password' => 'Heslo není správné.',
    'date' => 'Pole :attribute musí obsahovat platné datum.',
    'date_equals' => 'Pole :attribute musí obsahovat datum shodné s :date.',
    'date_format' => 'Pole :attribute musí mít formát :format.',
    'decimal' => 'Pole :attribute musí mít :decimal desetinných míst.',
    'declined' => 'Pole :attribute musí být odmítnuto.',
    'declined_if' => 'Pole :attribute musí být odmítnuto, když má :other hodnotu :value.',
    'different' => 'Pole :attribute a :other se musí lišit.',
    'digits' => 'Pole :attribute musí mít :digits číslic.',
    'digits_between' => 'Pole :attribute musí mít mezi :min a :max číslicemi.',
    'dimensions' => 'Pole :attribute má neplatné rozměry obrázku.',
    'distinct' => 'Pole :attribute má duplicitní hodnotu.',
    'doesnt_end_with' => 'Pole :attribute nesmí končit na: :values.',
    'doesnt_start_with' => 'Pole :attribute nesmí začínat na: :values.',
    'email' => 'Pole :attribute musí být platná e-mailová adresa.',
    'ends_with' => 'Pole :attribute musí končit na: :values.',
    'enum' => 'Zvolená hodnota pro :attribute je neplatná.',
    'exists' => 'Zvolená hodnota pro :attribute je neplatná.',
    'file' => 'Pole :attribute musí být soubor.',
    'filled' => 'Pole :attribute musí být vyplněno.',
    'gt' => [
        'array' => 'Pole :attribute musí mít více než :value položek.',
        'file' => 'Soubor v poli :attribute musí být větší než :value kilobajtů.',
        'numeric' => 'Pole :attribute musí mít hodnotu vyšší než :value.',
        'string' => 'Pole :attribute musí být delší než :value znaků.',
    ],
    'gte' => [
        'array' => 'Pole :attribute musí mít :value nebo více položek.',
        'file' => 'Soubor v poli :attribute musí být alespoň :value kilobajtů.',
        'numeric' => 'Pole :attribute musí mít hodnotu :value nebo vyšší.',
        'string' => 'Pole :attribute musí být dlouhé alespoň :value znaků.',
    ],
    'image' => 'Pole :attribute musí být obrázek.',
    'in' => 'Zvolená hodnota pro :attribute je neplatná.',
    'in_array' => 'Pole :attribute musí existovat v :other.',
    'integer' => 'Pole :attribute musí být celé číslo.',
    'ip' => 'Pole :attribute musí být platná IP adresa.',
    'ipv4' => 'Pole :attribute musí být platná IPv4 adresa.',
    'ipv6' => 'Pole :attribute musí být platná IPv6 adresa.',
    'json' => 'Pole :attribute musí být platný JSON řetězec.',
    'lowercase' => 'Pole :attribute musí být malými písmeny.',
    'lt' => [
        'array' => 'Pole :attribute musí mít méně než :value položek.',
        'file' => 'Soubor v poli :attribute musí být menší než :value kilobajtů.',
        'numeric' => 'Pole :attribute musí mít hodnotu nižší než :value.',
        'string' => 'Pole :attribute musí být kratší než :value znaků.',
    ],
    'lte' => [
        'array' => 'Pole :attribute nesmí mít více než :value položek.',
        'file' => 'Soubor v poli :attribute musí být nejvýše :value kilobajtů.',
        'numeric' => 'Pole :attribute musí mít hodnotu :value nebo nižší.',
        'string' => 'Pole :attribute musí být dlouhé nejvýše :value znaků.',
    ],
    'mac_address' => 'Pole :attribute musí být platná MAC adresa.',
    'max' => [
        'array' => 'Pole :attribute nesmí mít více než :max položek.',
        'file' => 'Soubor v poli :attribute nesmí být větší než :max kilobajtů.',
        'numeric' => 'Pole :attribute nesmí být vyšší než :max.',
        'string' => 'Pole :attribute nesmí být delší než :max znaků.',
    ],
    'max_digits' => 'Pole :attribute nesmí mít více než :max číslic.',
    'mimes' => 'Pole :attribute musí být soubor typu: :values.',
    'mimetypes' => 'Pole :attribute musí být soubor typu: :values.',
    'min' => [
        'array' => 'Pole :attribute musí mít alespoň :min položek.',
        'file' => 'Soubor v poli :attribute musí být alespoň :min kilobajtů.',
        'numeric' => 'Pole :attribute musí mít hodnotu alespoň :min.',
        'string' => 'Pole :attribute musí být dlouhé alespoň :min znaků.',
    ],
    'min_digits' => 'Pole :attribute musí mít alespoň :min číslic.',
    'missing' => 'Pole :attribute musí chybět.',
    'missing_if' => 'Pole :attribute musí chybět, když má :other hodnotu :value.',
    'missing_unless' => 'Pole :attribute musí chybět, pokud :other nemá hodnotu :value.',
    'missing_with' => 'Pole :attribute musí chybět, je-li přítomno :values.',
    'missing_with_all' => 'Pole :attribute musí chybět, jsou-li přítomna :values.',
    'multiple_of' => 'Pole :attribute musí být násobkem :value.',
    'not_in' => 'Zvolená hodnota pro :attribute je neplatná.',
    'not_regex' => 'Formát pole :attribute je neplatný.',
    'numeric' => 'Pole :attribute musí být číslo.',
    'password' => [
        'letters' => 'Pole :attribute musí obsahovat alespoň jedno písmeno.',
        'mixed' => 'Pole :attribute musí obsahovat alespoň jedno velké a jedno malé písmeno.',
        'numbers' => 'Pole :attribute musí obsahovat alespoň jednu číslici.',
        'symbols' => 'Pole :attribute musí obsahovat alespoň jeden symbol.',
        'uncompromised' => 'Zadaná hodnota :attribute se objevila v úniku dat. Zvolte prosím jinou.',
    ],
    'present' => 'Pole :attribute musí být přítomno.',
    'prohibited' => 'Pole :attribute je zakázáno.',
    'prohibited_if' => 'Pole :attribute je zakázáno, když má :other hodnotu :value.',
    'prohibited_unless' => 'Pole :attribute je zakázáno, pokud :other nemá hodnotu :values.',
    'prohibits' => 'Pole :attribute zakazuje, aby bylo :other přítomno.',
    'regex' => 'Formát pole :attribute je neplatný.',
    'required' => 'Pole :attribute je povinné.',
    'required_array_keys' => 'Pole :attribute musí obsahovat položky pro: :values.',
    'required_if' => 'Pole :attribute je povinné, když má :other hodnotu :value.',
    'required_if_accepted' => 'Pole :attribute je povinné, když je :other přijato.',
    'required_unless' => 'Pole :attribute je povinné, pokud :other nemá hodnotu :values.',
    'required_with' => 'Pole :attribute je povinné, je-li přítomno :values.',
    'required_with_all' => 'Pole :attribute je povinné, jsou-li přítomna :values.',
    'required_without' => 'Pole :attribute je povinné, není-li přítomno :values.',
    'required_without_all' => 'Pole :attribute je povinné, není-li přítomno žádné z :values.',
    'same' => 'Pole :attribute se musí shodovat s :other.',
    'size' => [
        'array' => 'Pole :attribute musí obsahovat :size položek.',
        'file' => 'Soubor v poli :attribute musí mít velikost :size kilobajtů.',
        'numeric' => 'Pole :attribute musí mít hodnotu :size.',
        'string' => 'Pole :attribute musí mít délku :size znaků.',
    ],
    'starts_with' => 'Pole :attribute musí začínat na: :values.',
    'string' => 'Pole :attribute musí být řetězec.',
    'timezone' => 'Pole :attribute musí být platné časové pásmo.',
    'unique' => 'Hodnota pole :attribute již byla použita.',
    'uploaded' => 'Nahrání souboru v poli :attribute se nezdařilo.',
    'uppercase' => 'Pole :attribute musí být velkými písmeny.',
    'url' => 'Pole :attribute musí být platná URL adresa.',
    'ulid' => 'Pole :attribute musí být platné ULID.',
    'uuid' => 'Pole :attribute musí být platné UUID.',

    /*
    |--------------------------------------------------------------------------
    | Vlastní validační hlášky
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vlastní názvy polí (:attribute)
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'email' => 'e-mail',
        'mail' => 'e-mail',
        'jmeno' => 'jméno',
        'telefon' => 'telefon',
        'znacka' => 'volací znak',
        'locator' => 'lokátor',
        'kolo' => 'kolo',
        'kategorie' => 'kategorie',
        'pocet' => 'počet QSO',
        'qso_points' => 'body za QSO',
        'multiplier' => 'násobiče',
        'body' => 'body',
        'poznamka' => 'poznámka',
        'soapbox' => 'poznámka',
        'upload' => 'soubor',
        'password' => 'heslo',
        'name' => 'jméno',
    ],

];
