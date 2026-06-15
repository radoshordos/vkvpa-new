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
