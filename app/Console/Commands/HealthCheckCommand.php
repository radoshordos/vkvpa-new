<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Předspouštěcí kontrola produkční konfigurace.
 *
 * Ověří kritické předpoklady běhu (APP_KEY, debug, HTTPS/session, DB, fronta,
 * mail, symlink úložiště, oprávnění zapisovatelných adresářů, admin účet)
 * a vypíše přehlednou tabulku. Vrací
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

        $this->checkAppKey();
        $this->checkDebug($isProduction);
        $this->checkAppUrl($isProduction);
        $this->checkSession($isProduction);
        $this->checkDatabase();
        $this->checkQueue();
        $this->checkMail();
        $this->checkContact();
        $this->checkStorageLink();
        $this->checkWritablePaths();
        $this->checkAdminUser();
        $this->checkAdminer();

        $this->newLine();
        $this->table(['Kontrola', 'Stav', 'Detail'], $this->rows);

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
        if (is_link($link) || is_dir($link)) {
            $this->add('Storage link', self::OK, 'public/storage existuje');

            return;
        }
        $this->add('Storage link', self::WARN, 'public/storage chybí (php artisan storage:link) – nepůjdou fotky v diskusi');
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
            if (! is_writable($path)) {
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
                'nelze zapisovat: '.implode(', ', $blocking)
                    .' – nastav vlastníka na uživatele web serveru a práva (např. `chown -R '.$owner.':'.$owner.' storage bootstrap/cache && chmod -R 775 storage bootstrap/cache`)',
            );

            return;
        }

        if ($worldWritable !== []) {
            $this->add(
                'Oprávnění adresářů',
                self::WARN,
                'zapisovatelné, ale world-writable (riziko na sdíleném hostingu): '.implode(', ', $worldWritable)
                    .' – zúži na 775 (adresáře) / 664 (soubory), nikdy 777',
            );

            return;
        }

        $this->add('Oprávnění adresářů', self::OK, count($paths).' adresářů zapisovatelných procesem „'.$owner.'“');
    }

    /** Práva adresáře jako oktalový řetězec (poslední 4 číslice), např. „0775“. */
    private function pathMode(string $path): string
    {
        $perms = fileperms($path);

        return $perms === false ? '????' : substr(sprintf('%04o', $perms), -4);
    }

    /** Jméno uživatele, pod kterým běží PHP proces (kvůli návrhu `chown`). */
    private function processOwner(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());
            if (is_array($info) && isset($info['name']) && is_string($info['name']) && $info['name'] !== '') {
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
