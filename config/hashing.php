<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Výchozí algoritmus pro hashování hesel uživatelů aplikace. Použit
    | argon2id (vítěz Password Hashing Competition) – odolnější vůči
    | GPU/ASIC útokům než bcrypt. Lze přepsat přes HASH_DRIVER.
    |
    | Pozn.: existující bcrypt hashe zůstávají platné – Hash::check rozpozná
    | algoritmus z prefixu ($2y$ vs $argon2id$). Díky rehash_on_login se při
    | příštím úspěšném přihlášení heslo automaticky přehashuje na argon2id.
    |
    | (Toto se NETÝKÁ Apache .htpasswd pro Adminer – tam musí zůstat bcrypt,
    | protože mod_auth_basic argon2 neumí ověřit.)
    |
    | Podporováno: "bcrypt", "argon", "argon2id"
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
        'limit' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | memory v KiB, time = počet iterací, threads = paralelismus. Hodnoty
    | odpovídají doporučením OWASP (min. 19 MiB) s rezervou.
    |
    */

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash On Login
    |--------------------------------------------------------------------------
    |
    | Při úspěšném přihlášení se heslo přehashuje, pokud jeho hash neodpovídá
    | aktuálnímu driveru/parametrům – migruje staré bcrypt hashe na argon2id.
    |
    */

    'rehash_on_login' => true,

];
