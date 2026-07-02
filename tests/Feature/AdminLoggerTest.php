<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\AdminLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class AdminLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_logger_includes_trace_and_admin_context(): void
    {
        $admin = $this->makeUser('Admin', isAdmin: true);
        $this->actingAs($admin);

        Context::flush();
        Context::add([
            'trace_id' => 'trace-admin-1',
            'route_name' => 'admin.dashboard',
            'request_path' => 'admin/statistiky',
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('admin.test', Mockery::on(fn (array $context): bool => $context === [
                'trace_id' => 'trace-admin-1',
                'route_name' => 'admin.dashboard',
                'request_path' => 'admin/statistiky',
                'admin_id' => $admin->id,
                'admin_name' => 'Admin',
                'target_id' => 123,
                'admin' => 'Admin',
            ]));

        AdminLogger::log('admin.test', ['target_id' => 123]);

        $this->assertSame($admin->id, Context::get('admin_id'));
        $this->assertSame('Admin', Context::get('admin_name'));
    }
}
