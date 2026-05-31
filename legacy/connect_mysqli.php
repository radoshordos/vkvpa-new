<?php

declare(strict_types=1);

/**
 * connect_mysqli.php — sjednocené připojení k DB pro legacy běh.
 *
 * Změny oproti původní verzi (Fáze 2 migrace):
 *  - ŽÁDNÁ hesla v kódu – vše se čte z .env (viz .env.example).
 *  - Sjednoceno s connect.php (oba legacy include cíle míří sem).
 *  - Janitor přepsán na prepared statements (žádná interpolace).
 *  - Kompatibilní helpery mq()/mfa()/mnr() zachovány (guarded).
 *
 * Soubor zůstává jako most pro postupnou migraci; po dokončení Fáze 6–7
 * (přechod na Eloquent/controllery) bude odstraněn.
 */

// --- Minimální, bezzávislostní načtení .env (pro běh mimo Laravel) ---
if (!function_exists('vkvpa_env')) {
    function vkvpa_env(string $key, ?string $default = null): ?string
    {
        static $loaded = false;
        if (!$loaded) {
            $loaded = true;
            $candidates = [__DIR__ . '/.env', dirname(__DIR__) . '/.env'];
            foreach ($candidates as $path) {
                if (!is_readable($path)) {
                    continue;
                }
                foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                        continue;
                    }
                    [$k, $v] = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v);
                    if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[strlen($v) - 1] === $v[0]) {
                        $v = substr($v, 1, -1);
                    }
                    if (getenv($k) === false) {
                        putenv("$k=$v");
                        $_ENV[$k] = $v;
                    }
                }
                break;
            }
        }
        $val = getenv($key);
        return $val === false ? $default : $val;
    }
}

// --- Připojení k DB (přihlašovací údaje výhradně z .env) ---
$dbHost    = vkvpa_env('DB_HOST', '127.0.0.1');
$dbName    = (string) vkvpa_env('DB_NAME', vkvpa_env('DB_DATABASE', 'digipa'));
$dbUser    = (string) vkvpa_env('DB_USER', vkvpa_env('DB_USERNAME', 'digipa'));
$dbPass    = (string) vkvpa_env('DB_PASS', vkvpa_env('DB_PASSWORD', ''));
$dbPort    = (int) vkvpa_env('DB_PORT', '3306');
$dbCharset = (string) vkvpa_env('DB_CHARSET', 'utf8mb4');

// Zachování legacy chování: chyby řešíme ručně (ne výjimkami).
mysqli_report(MYSQLI_REPORT_OFF);
$dbi = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($dbi->connect_error) {
    http_response_code(500);
    die('DB connection error');
}
$dbi->set_charset($dbCharset);
$dbi->query("SET SESSION sql_mode = ''");
$link = $dbi;

// --- Kompatibilní helpery (mfa = fetch_array kvůli shodě s legacy head.php) ---
if (!function_exists('mq')) {
    function mq(string $sql): mysqli_result|bool
    {
        global $dbi;
        return $dbi->query($sql);
    }
}
if (!function_exists('mfa')) {
    function mfa(mysqli_result|false|null $result): array|null
    {
        return $result ? $result->fetch_array() : null;
    }
}
if (!function_exists('mnr')) {
    function mnr(mysqli_result|false|null $result): int
    {
        return $result ? (int) $result->num_rows : 0;
    }
}

// --- Konfigurace z DB (vkvpa_config) ---
$CONFIG = [];
if ($res = $dbi->query('SELECT cfg_key, cfg_value FROM vkvpa_config')) {
    while ($row = $res->fetch_assoc()) {
        $CONFIG[$row['cfg_key']] = $row['cfg_value'];
    }
}
$CONFIG['V_ADMIN_MAIL'] ??= vkvpa_env('CONTACT_MAIL', 'admin@vkvpa.cz');

$debug = filter_var(vkvpa_env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
$debug_EDI = false;

// --- Janitor: úklid opuštěných nedokončených hlášení (> 60 min) ---
$janitor = $dbi->prepare(
    'SELECT id, EDI_ID FROM vkvpa_data
     WHERE odeslano = 0 AND `timestamp` < DATE_SUB(NOW(), INTERVAL 60 MINUTE)'
);
if ($janitor) {
    $janitor->execute();
    $trashRes  = $janitor->get_result();
    $delLines  = $dbi->prepare('DELETE FROM edilines WHERE IDS = ?');
    $delHead   = $dbi->prepare('DELETE FROM edihead WHERE ID = ? LIMIT 1');
    $delData   = $dbi->prepare('DELETE FROM vkvpa_data WHERE id = ? LIMIT 1');

    while ($trash = $trashRes->fetch_assoc()) {
        $tId  = (int) $trash['id'];
        $tEdi = (int) $trash['EDI_ID'];
        if ($tEdi > 0) {
            $delLines->bind_param('i', $tEdi);
            $delLines->execute();
            $delHead->bind_param('i', $tEdi);
            $delHead->execute();
        }
        $delData->bind_param('i', $tId);
        $delData->execute();
    }

    $janitor->close();
    $delLines->close();
    $delHead->close();
    $delData->close();
}
