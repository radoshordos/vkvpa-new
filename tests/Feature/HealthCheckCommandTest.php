<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * Test předspouštěcí kontroly `app:health-check`.
 */
class HealthCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    /** Mimo produkci a s funkční DB nemá kontrola žádný blokující (FAIL) nález. */
    public function test_passes_outside_production(): void
    {
        $command = $this->artisan('app:health-check');
        $this->assertInstanceOf(PendingCommand::class, $command);

        $command
            ->expectsOutputToContain('APP_KEY')
            ->expectsOutputToContain('Databáze')
            ->assertExitCode(0);
    }

    /**
     * `session.secure` je defaultně null (env bez fallbacku) – kontrola to musí
     * snést a reportovat čistě, ne spadnout na Config::boolean (null není bool).
     */
    public function test_handles_null_session_secure(): void
    {
        config(['session.secure' => null, 'session.encrypt' => null]);

        $command = $this->artisan('app:health-check');
        $this->assertInstanceOf(PendingCommand::class, $command);

        $command
            ->expectsOutputToContain('Session')
            ->assertExitCode(0);
    }

    /** Kontrola oprávnění adresářů se objeví v přehledu a u zapisovatelného storage nehlásí blok. */
    public function test_reports_directory_permissions(): void
    {
        $command = $this->artisan('app:health-check');
        $this->assertInstanceOf(PendingCommand::class, $command);

        $command
            ->expectsOutputToContain('Oprávnění adresářů')
            ->assertExitCode(0);
    }

    /** PHP runtime a rozšíření se reportují a na podporované verzi/rozšířeních neblokují. */
    public function test_reports_php_runtime(): void
    {
        $command = $this->artisan('app:health-check');
        $this->assertInstanceOf(PendingCommand::class, $command);

        $command
            ->expectsOutputToContain('PHP')
            ->expectsOutputToContain('rozšíření')
            ->expectsOutputToContain('Node.js')
            ->assertExitCode(0);
    }

    /** Existující admin se v přehledu rozpozná. */
    public function test_reports_admin_account(): void
    {
        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'x',
            'is_admin' => true,
        ]);

        $command = $this->artisan('app:health-check');
        $this->assertInstanceOf(PendingCommand::class, $command);

        $command->assertExitCode(0);
    }
}
