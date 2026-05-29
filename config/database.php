<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            // Podpora obou konvencí: standardní Laravel i původní repozitář.
            'database' => env('DB_DATABASE', env('DB_NAME', 'digipa')),
            'username' => env('DB_USERNAME', env('DB_USER', 'digipa')),
            'password' => env('DB_PASSWORD', env('DB_PASS', '')),
            'unix_socket' => env('DB_SOCKET', ''),
            // Tabulky jsou mix utf8mb3/utf8mb4. utf8mb4 je doporučené;
            // při potížích s kolací (JOIN přes různé charset sloupce)
            // lze v .env nastavit DB_CHARSET=utf8.
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false, // zachování legacy chování (původně SESSION sql_mode = '')
            'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                \Pdo\Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
    ],

];
