<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Slouží Swagger UI a OpenAPI specifikaci. */
final class ApiDocsController extends Controller
{
    public function index(): View
    {
        return view('api-docs');
    }

    public function spec(): Response
    {
        $path = public_path('api-docs/openapi.yaml');

        abort_unless(file_exists($path), 404, 'OpenAPI spec not found.');

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'application/yaml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
