<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Helper\Table;
use Throwable;

/**
 * Předspouštěcí kontrola produkční konfigurace.
 *
 * Ověří kritické předpoklady běhu (PHP verze + rozšíření, APP_KEY, práva .env,
 * debug, HTTPS/session, DB, fronta, mail, symlink úložiště, oprávnění
 * zapisovatelných adresářů, Node.js/npm pro build, admin účet) a vypíše
 * přehlednou tabulku. Vrací
 * nenulový exit kód, pokud narazí na blokující (FAIL) nález – vhodné zařadit
 * do nasazovacího pipeline.
 *
 * Ručně: `php artisan app:health-check`.
 */
final class HealthCheckCommand extends Command
{
    protected $signature = 'app:health-check';

    protected $description = 'Předspouštěcí kontrola produkční konfigurace (env, DB, fronta, mail, úložiště).';

    private const string OK = 'OK';

    private const string WARN = 'WARN';

    private const string FAIL = 'FAIL';

    /** @var list<array{0:string,1:string,2:string}> */
    private array $rows = [];

    public function handle(): int
    {
        $isProduction = $this->getLaravel()->environment('production');

        $this->checkPhpRuntime();
        $this->checkAppKey();
        $this->checkEnvFilePermissions();
        $this->checkDebug($isProduction);
        $this->checkAppUrl($isProduction);
        $this->checkSession($isProduction);
        $this->checkDatabase();
        $this->checkQueue();
        $this->checkMail();
        $this->checkContact();
        $this->checkStorageLink();
        $this->checkWritablePaths();
        $this->checkNode();
        $this->checkNpm();
        $this->checkAdminUser();
        $this->checkAdminer();

        $this->newLine();
        $this->renderTable();

        $this->newLine();
        $this->line('Pozn.: cron scheduler (<comment>schedule:run</comment>) a běžící <comment>queue:work</comment> nelze ověřit zevnitř – zkontroluj je na serveru zvlášť (viz README, sekce Nasazení do produkce).');

        $fails = array_filter($this->rows, static fn (array $r): bool => $r[1] === self::FAIL);

        if ($fails !== []) {
            $this->newLine();
            $fails
                |> count(...)
                |> (fn ($x) => sprintf('Nalezeno %d blokujících problémů – oprav je před spuštěním.', $x))
                |> $this->error(...);

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Žádné blokující problémy. Zkontroluj případné WARN nálezy.');

        return self::SUCCESS;
    }

    private function add(string $name, string $status, string $detail): void
    {
        $this->rows[] = [$name, $status, $detail];
    }

    /**
     * Vykreslí přehledovou tabulku: stav obarvený podle závažnosti a dlouhé
     * detaily zalomené uvnitř sloupce (rámeček se nerozpadne). Víceřádkové
     * detaily (oddělené `\n`) se vykreslí jako odrážky pod sebou.
     */
    private function renderTable(): void
    {
        $table = new Table($this->output);
        $table->setHeaders(['Kontrola', 'Stav', 'Detail']);
        $table->setColumnMaxWidth(2, 72);

        foreach ($this->rows as $row) {
            $table->addRow([$row[0], $this->colorStatus($row[1]), $row[2]]);
        }

        $table->render();
    }

    /** Stav obarvený dle závažnosti pro snadné vizuální skenování tabulky. */
    private function colorStatus(string $status): string
    {
        return match ($status) {
            self::OK => '<fg=green;options=bold>OK</>',
            self::WARN => '<fg=yellow;options=bold>WARN</>',
            self::FAIL => '<fg=red;options=bold>FAIL</>',
            default => $status,
        };
    }

    /**
     * PHP runtime – verze a kritická rozšíření. Na hostingu se často liší CLI a
     * web (FPM) verze, nebo chybí `gd` (generování obrázků: mailová adresa,
     * OG náhled, zpracování fotek v diskusi). Bez `gd`/`pdo` aplikace nepoběží.
     */
    private function checkPhpRuntime(): void
    {
        $required = '8.5.0';
        if (version_compare(PHP_VERSION, $required, '<')) {
            $this->add('PHP', self::FAIL, sprintf('běží %s, aplikace vyžaduje >= %s (ověř, že CLI i web/FPM používají stejnou verzi)', PHP_VERSION, $required));
        } else {
            $this->add('PHP', self::OK, 'verze '.PHP_VERSION);
        }

        // Tvrdě vyžadovaná (composer.json: ext-gd, ext-pdo) vs. doporučená.
        $hard = ['gd', 'pdo'];
        $recommended = ['mbstring', 'intl', 'zip', 'fileinfo'];

        $missingHard = array_values(array_filter($hard, static fn (string $e): bool => ! extension_loaded($e)));
        $missingSoft = array_values(array_filter($recommended, static fn (string $e): bool => ! extension_loaded($e)));

        if ($missingHard !== []) {
            $this->add('PHP rozšíření', self::FAIL, 'chybí povinná: '.implode(', ', $missingHard).' – aplikace bez nich nepoběží (doinstaluj balíčky php-'.implode(', php-', $missingHard).')');

            return;
        }
        if ($missingSoft !== []) {
            $this->add('PHP rozšíření', self::WARN, 'chybí doporučená: '.implode(', ', $missingSoft).' – některé funkce (mime detekce uploadu, archivy) mohou selhat');

            return;
        }
        $this->add('PHP rozšíření', self::OK, 'gd, pdo, mbstring, intl, zip, fileinfo načtena');
    }

    /**
     * Práva souboru `.env` – obsahuje DB heslo, APP_KEY i SMTP přihlášení.
     * Na sdíleném hostingu je čtení pro skupinu/ostatní únik citlivých údajů.
     */
    private function checkEnvFilePermissions(): void
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            $this->add('.env práva', self::OK, '.env neexistuje – konfigurace z proměnných prostředí serveru');

            return;
        }

        $perms = fileperms($path);
        if ($perms === false) {
            $this->add('.env práva', self::WARN, 'práva .env nelze zjistit');

            return;
        }

        // 0o044 = čtení pro skupinu nebo ostatní.
        if (($perms & 0o044) !== 0) {
            $this->add('.env práva', self::WARN, sprintf('.env je čitelný i pro skupinu/ostatní (%s) – obsahuje hesla; nastav `chmod 640` (či 600)', $this->modeFromPerms($perms)));

            return;
        }
        $this->add('.env práva', self::OK, '.env čte jen vlastník ('.$this->modeFromPerms($perms).')');
    }

    private function checkAppKey(): void
    {
        $key = Config::string('app.key', '');
        $this->add('APP_KEY', $key === '' ? self::FAIL : self::OK, $key === '' ? 'není nastaven (php artisan key:generate)' : 'nastaven');
    }

    private function checkDebug(bool $isProduction): void
    {
        $debug = Config::boolean('app.debug', false);
        if ($isProduction && $debug) {
            $this->add('APP_DEBUG', self::FAIL, 'v produkci musí být false (únik citlivých informací)');

            return;
        }
        $this->add('APP_DEBUG', self::OK, $debug ? 'true (mimo produkci)' : 'false');
    }

    private function checkAppUrl(bool $isProduction): void
    {
        $url = Config::string('app.url', '');
        $https = str_starts_with($url, 'https://');
        if ($isProduction && ! $https) {
            $this->add('APP_URL', self::WARN, 'v produkci by mělo být https:// – '.($url ?: 'prázdné'));

            return;
        }
        $this->add('APP_URL', self::OK, $url ?: '(prázdné)');
    }

    private function checkSession(bool $isProduction): void
    {
        // session.secure defaults to null (env('SESSION_SECURE_COOKIE') with no
        // fallback), so read it null-safely – Config::boolean() throws on null.
        $secure = Config::get('session.secure') === true;
        $encrypt = Config::get('session.encrypt') === true;
        if ($isProduction && (! $secure || ! $encrypt)) {
            $this->add('Session', self::FAIL, 'v produkci je vyžadováno SESSION_SECURE_COOKIE=true a SESSION_ENCRYPT=true (a HTTPS)');

            return;
        }
        $this->add('Session', self::OK, sprintf('secure=%s, encrypt=%s', $secure ? 'true' : 'false', $encrypt ? 'true' : 'false'));
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->add('Databáze', self::OK, 'připojení funguje ('.DB::connection()->getName().')');
        } catch (Throwable $e) {
            $this->add('Databáze', self::FAIL, 'připojení selhalo: '.$e->getMessage());
        }
    }

    private function checkQueue(): void
    {
        $conn = Config::string('queue.default', 'sync');
        if ($conn === 'sync') {
            $this->add('Fronta', self::WARN, 'QUEUE_CONNECTION=sync – e-maily se odešlou synchronně (doporučeno database + worker)');

            return;
        }

        if ($conn === 'database') {
            try {
                DB::table('jobs')->exists();
                $this->add('Fronta', self::OK, 'database – tabulka jobs existuje (nezapomeň na běžící queue:work)');
            } catch (Throwable) {
                $this->add('Fronta', self::FAIL, 'database, ale tabulka jobs chybí (php artisan migrate)');
            }

            return;
        }

        $this->add('Fronta', self::OK, $conn.' (nezapomeň na běžícího workera)');
    }

    private function checkMail(): void
    {
        $mailer = Config::string('mail.default', '');
        $needsHost = in_array($mailer, ['smtp', 'ses', 'postmark', 'resend', 'mailgun'], true);
        $host = Config::string('mail.mailers.smtp.host', '');
        if ($needsHost && $mailer === 'smtp' && $host === '') {
            $this->add('Mail', self::WARN, 'MAIL_MAILER=smtp, ale MAIL_HOST je prázdný – potvrzovací e-maily se neodešlou');

            return;
        }
        $this->add('Mail', self::OK, 'mailer='.($mailer ?: '(prázdné)').($host !== '' ? ', host='.$host : ''));
    }

    private function checkContact(): void
    {
        $mail = Config::string('vkvpa.contact_mail', '');
        $this->add('Kontaktní e-mail', $mail === '' ? self::WARN : self::OK, $mail === '' ? 'CONTACT_MAIL není nastaven – nepřijde notifikace vyhodnocovateli' : $mail);
    }

    private function checkStorageLink(): void
    {
        $link = public_path('storage');

        if (is_link($link)) {
            // Symlink existuje – ověř, že cíl je dostupný (rozbitý odkaz = nefunkční).
            $dest = readlink($link);
            if (! is_dir($link)) {
                $this->add('Storage link', self::WARN, 'public/storage je symlink na neexistující cíl ('.($dest !== false ? $dest : '?').') – spusť znovu `php artisan storage:link`');

                return;
            }
            $this->add('Storage link', self::OK, 'symlink → '.($dest !== false ? $dest : storage_path('app/public')));

            return;
        }

        if (is_dir($link)) {
            // Obyčejný adresář místo symlinku: typicky FTP/sdílený hosting bez
            // podpory symlinků zkopíroval cíl. Nově nahrané soubory se pak
            // neprojeví, protože míří jinam než `storage/app/public`.
            $this->add('Storage link', self::WARN, 'public/storage je obyčejný adresář, ne symlink – na hostingu bez symlinků se nově nahrané soubory neprojeví; vytvoř symlink (`php artisan storage:link`) nebo nastav alias ve web serveru');

            return;
        }

        $this->add('Storage link', self::WARN, 'public/storage chybí (`php artisan storage:link`) – nepůjdou veřejné soubory v úložišti');
    }

    /**
     * Oprávnění adresářů – po nahrání na Linux hosting bývá nejčastější příčina
     * 500 / „failed to open stream: Permission denied“. Laravel (a tahle aplikace)
     * potřebuje zapisovat do storage a bootstrap/cache uživatelem web serveru
     * (PHP-FPM / Apache). FTP nahraje soubory pod jiným vlastníkem, takže je
     * nutné po nahrání srovnat vlastníka/skupinu a práva.
     */
    private function checkWritablePaths(): void
    {
        /** @var array<string,string> $paths */
        $paths = [
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/logs' => storage_path('logs'),
            'storage/app/private' => storage_path('app/private'),
            'storage/app/public' => storage_path('app/public'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $blocking = [];
        $worldWritable = [];

        foreach ($paths as $label => $path) {
            if (! is_dir($path)) {
                $blocking[] = $label.' (chybí)';

                continue;
            }
            // Reálný zápis je spolehlivější než is_writable() – odhalí read-only
            // mount, plný disk, ACL i open_basedir, kde is_writable() vrací true.
            if (! $this->canWriteInto($path)) {
                $blocking[] = $label.' ('.$this->pathMode($path).', nezapisovatelný)';

                continue;
            }
            // 0o002 = zápis pro „ostatní“; na sdíleném hostingu nebezpečné.
            $perms = fileperms($path);
            if ($perms !== false && ($perms & 0o002) !== 0) {
                $worldWritable[] = $label.' ('.$this->pathMode($path).')';
            }
        }

        $owner = $this->processOwner();

        if ($blocking !== []) {
            $this->add(
                'Oprávnění adresářů',
                self::FAIL,
                'nelze zapisovat:'."\n".$this->bullets($blocking)."\n"
                    .'→ nastav vlastníka na uživatele web serveru a práva (např.'."\n"
                    .'  `chown -R '.$owner.':'.$owner.' storage bootstrap/cache`'."\n"
                    .'  `chmod -R 775 storage bootstrap/cache`)',
            );

            return;
        }

        if ($worldWritable !== []) {
            $this->add(
                'Oprávnění adresářů',
                self::WARN,
                'zapisovatelné, ale world-writable (riziko na sdíleném hostingu):'."\n"
                    .$this->bullets($worldWritable)."\n"
                    .'→ zúži na 775 (adresáře) / 664 (soubory), nikdy 777',
            );

            return;
        }

        $this->add('Oprávnění adresářů', self::OK, count($paths).' adresářů zapisovatelných procesem „'.$owner.'“');
    }

    /**
     * Node.js – `composer deploy` staví frontend přímo na serveru (`npm ci` +
     * `npm run build`, vite), což vyžaduje Node >= 22. Za běhu aplikace Node
     * nutný není, proto je nález jen WARN (neblokuje), ale upozorní na chybějící
     * nebo zastaralou verzi, na které by build selhal.
     */
    private function checkNode(): void
    {
        $required = 22;

        try {
            $result = Process::run('node --version');
        } catch (Throwable $e) {
            $this->add('Node.js', self::WARN, 'nelze spustit node: '.$e->getMessage().' – potřeba pro build frontendu při `composer deploy`');

            return;
        }

        if (! $result->successful()) {
            $this->add('Node.js', self::WARN, 'node nenalezen v PATH – potřeba (>= '.$required.') pro build frontendu při `composer deploy` (`npm run build`); za běhu aplikace nutný není');

            return;
        }

        $raw = trim($result->output());
        $major = (int) ltrim(explode('.', $raw)[0], 'vV');

        if ($major < $required) {
            $this->add('Node.js', self::WARN, sprintf('verze %s < %d – `npm run build` (vite) může selhat; aktualizuj Node na >= %d', $raw, $required, $required));

            return;
        }

        $this->add('Node.js', self::OK, 'verze '.$raw);
    }

    /**
     * npm – `composer deploy` instaluje balíčky přes `npm ci` z commitnutého
     * `package-lock.json` (lockfileVersion 3), což vyžaduje npm >= 7; Node 22
     * standardně dodává npm 10. Stejně jako Node je nutný jen pro build, proto WARN.
     */
    private function checkNpm(): void
    {
        $required = 9;

        try {
            $result = Process::run('npm --version');
        } catch (Throwable $e) {
            $this->add('npm', self::WARN, 'nelze spustit npm: '.$e->getMessage().' – potřeba pro `npm ci` při `composer deploy`');

            return;
        }

        if (! $result->successful()) {
            $this->add('npm', self::WARN, 'npm nenalezen v PATH – potřeba (>= '.$required.') pro `npm ci` při `composer deploy`; za běhu aplikace nutný není');

            return;
        }

        $raw = trim($result->output());
        $major = (int) ltrim(explode('.', $raw)[0], 'vV');

        if ($major < $required) {
            $this->add('npm', self::WARN, sprintf('verze %s < %d – `npm ci` z lockfileVersion 3 vyžaduje novější npm; aktualizuj na >= %d', $raw, $required, $required));

            return;
        }

        $this->add('npm', self::OK, 'verze '.$raw);
    }

    /**
     * Skutečně zapíše a smaže sondu – spolehlivější než is_writable() (to umí
     * lhát u ACL, read-only mountu, plného disku nebo open_basedir).
     */
    private function canWriteInto(string $dir): bool
    {
        $probe = $dir.DIRECTORY_SEPARATOR.'.health-check-'.bin2hex(random_bytes(6));
        $written = @file_put_contents($probe, '') !== false;
        if ($written) {
            @unlink($probe);
        }

        return $written;
    }

    /**
     * Seznam položek jako odrážky pod sebou (jedna na řádek) pro víceřádkovou
     * buňku tabulky – čitelnější než dlouhý výčet oddělený čárkami.
     *
     * @param  list<string>  $items
     */
    private function bullets(array $items): string
    {
        return implode("\n", array_map(static fn (string $i): string => '  • '.$i, $items));
    }

    /** Práva adresáře jako oktalový řetězec (poslední 4 číslice), např. „0775“. */
    private function pathMode(string $path): string
    {
        return $this->modeFromPerms(fileperms($path));
    }

    /** Oktalový řetězec práv (poslední 4 číslice) z hodnoty fileperms(). */
    private function modeFromPerms(int|false $perms): string
    {
        return $perms === false ? '????' : substr(sprintf('%04o', $perms), -4);
    }

    /** Jméno uživatele, pod kterým běží PHP proces (kvůli návrhu `chown`). */
    private function processOwner(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());
            if (is_array($info) && $info['name'] !== '') {
                return $info['name'];
            }
        }

        $current = get_current_user();

        return $current !== '' ? $current : 'www-data';
    }

    private function checkAdminUser(): void
    {
        try {
            $count = User::query()->where('is_admin', true)->count();
            $this->add('Admin účet', $count > 0 ? self::OK : self::WARN, $count > 0 ? $count.' administrátorů' : 'žádný admin (spusť AdminUserSeeder)');
        } catch (Throwable $e) {
            $this->add('Admin účet', self::WARN, 'nelze ověřit: '.$e->getMessage());
        }
    }

    private function checkAdminer(): void
    {
        if (! is_dir(public_path('adminer'))) {
            $this->add('Adminer', self::OK, 'není ve webrootu');

            return;
        }
        $user = Config::string('vkvpa.adminer_auth_user', '');
        $pass = Config::string('vkvpa.adminer_auth_password', '');
        if ($user === '' || $pass === '') {
            $this->add('Adminer', self::WARN, 'je ve webrootu, ale ADMINER_AUTH_USER/PASSWORD nejsou nastaveny (fail-closed = nedostupný)');

            return;
        }
        $this->add('Adminer', self::OK, 'chráněn Basic auth (uživatel '.$user.')');
    }
}
