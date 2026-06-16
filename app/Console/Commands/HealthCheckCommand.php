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
 * mail, symlink úložiště, admin účet) a vypíše přehlednou tabulku. Vrací
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
                |> (fn($x) => sprintf('Nalezeno %d blokujících problémů – oprav je před spuštěním.', $x))
                |> $this(...);

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
        $secure = Config::boolean('session.secure', false);
        $encrypt = Config::boolean('session.encrypt', false);
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
