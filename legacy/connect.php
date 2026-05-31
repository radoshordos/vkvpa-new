<?php

declare(strict_types=1);

/**
 * connect.php — sjednoceno do connect_mysqli.php (Fáze 2).
 *
 * Původní soubor obsahoval hardcoded heslo k DB. Nahrazeno: připojení nyní
 * řeší výhradně connect_mysqli.php z hodnot v .env. Tento soubor je ponechán
 * jen kvůli kompatibilitě legacy include("connect.php").
 */

require_once __DIR__ . '/connect_mysqli.php';
