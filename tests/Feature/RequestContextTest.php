<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class RequestContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_context_uses_incoming_trace_id_and_authenticated_admin(): void
    {
        $this->registerContextProbeRoute();
        $admin = $this->makeUser('Admin', isAdmin: true);

        $this->actingAs($admin)
            ->withHeader('X-Request-Id', 'trace-abc-123')
            ->get('/_tests/request-context')
            ->assertOk()
            ->assertHeader('X-Request-Id', 'trace-abc-123')
            ->assertJson([
                'trace_id' => 'trace-abc-123',
                'request_method' => 'GET',
                'request_path' => '_tests/request-context',
                'route_name' => 'tests.request-context',
                'user_id' => $admin->id,
                'admin_id' => $admin->id,
                'admin_name' => 'Admin',
            ]);
    }

    public function test_invalid_incoming_trace_id_is_replaced(): void
    {
        $this->registerContextProbeRoute();

        $response = $this->withHeader('X-Request-Id', '<script>')
            ->get('/_tests/request-context')
            ->assertOk();

        $traceId = (string) $response->headers->get('X-Request-Id');

        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/',
            $traceId,
        );
        $this->assertSame($traceId, $response->json('trace_id'));
    }

    private function registerContextProbeRoute(): void
    {
        Route::get('/_tests/request-context', static fn () => response()->json(
            Context::only([
                'trace_id',
                'request_method',
                'request_path',
                'route_name',
                'user_id',
                'admin_id',
                'admin_name',
            ]),
        ))->middleware('web')->name('tests.request-context');
    }
}
