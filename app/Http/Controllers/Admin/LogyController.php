<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Rap2hpoutre\LaravelLogViewer\LaravelLogViewer;

/**
 * Administrace – prohlížeč logů aplikace.
 *
 * Logy parsuje balík rap2hpoutre/laravel-log-viewer, ale vykreslují se ve
 * vlastní šabloně (layouts.app) – původní balíkový view tahá Bootstrap z CDN
 * a používá inline skripty, což přísná CSP webu (bez 'unsafe-inline') blokuje.
 */
class LogyController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = new LaravelLogViewer;

        /** @var list<string> $files (jen názvy souborů z storage/logs) */
        $files = $viewer->getFiles(true);

        // Vybraný soubor jen z whitelistu skutečných logů – chrání před path traversal.
        $current = $request->string('soubor')->value();
        if ($current === '' || ! in_array($current, $files, true)) {
            $current = $files[0] ?? null;
        }

        if ($current !== null) {
            $viewer->setFile($current);
        }

        return view('pages.admin.logy', [
            'active' => 'logy.index',
            'files' => $files,
            'current' => $current,
            'logs' => $current !== null ? $viewer->all() : [],
        ]);
    }
}
