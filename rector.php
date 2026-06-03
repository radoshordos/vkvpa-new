<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelLevelSetList;

/**
 * Konfigurace Rectoru (automatizovaný refaktoring/upgrade).
 *
 * Spuštění:
 *   vendor/bin/rector process --dry-run   # jen ukáže změny (nic nezapíše)
 *   vendor/bin/rector process             # provede změny
 *   docker compose exec web vendor/bin/rector process --dry-run
 */

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/routes',
        __DIR__.'/database/migrations',
        __DIR__.'/database/seeders',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/database/source_sql',
        __DIR__.'/bootstrap/cache',
    ])
    ->withSets([LaravelLevelSetList::UP_TO_LARAVEL_130])
    ->withPhpSets(php85: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true
    )
    ->withImportNames(
        removeUnusedImports: true,
    );
