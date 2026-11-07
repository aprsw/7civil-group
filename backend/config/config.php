<?php

declare(strict_types=1);

/**
 * Application configuration. Values may be overridden via environment variables
 * so the same code runs against SQLite (zero-config default) or MySQL.
 */
return [
    'app' => [
        'name' => 'سامانه اتوماسیون اداری گروه متحد 7',
        // Production-safe default: no stack traces leak. Set APP_DEBUG=true locally.
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
        // Comma-separated list of allowed CORS origins ('*' allows any).
        'cors_origins' => $_ENV['CORS_ORIGINS'] ?? getenv('CORS_ORIGINS') ?: '*',
    ],

    'auth' => [
        // HMAC secret for the self-implemented JWT. Uses JWT_SECRET when set;
        // otherwise a cryptographically-random secret is generated once and
        // persisted to storage/app_secret.key (never a shared hardcoded value,
        // so tokens can't be forged against a known default).
        'jwt_secret' => (static function (): string {
            $env = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');
            if (is_string($env) && $env !== '') {
                return $env;
            }
            $keyFile = __DIR__ . '/../storage/app_secret.key';
            if (is_file($keyFile) && ($k = trim((string) file_get_contents($keyFile))) !== '') {
                return $k;
            }
            $secret = bin2hex(random_bytes(32));
            if (!is_dir(dirname($keyFile))) {
                @mkdir(dirname($keyFile), 0775, true);
            }
            @file_put_contents($keyFile, $secret);

            return $secret;
        })(),
        'jwt_ttl' => 60 * 60 * 8, // 8 hours
        'issuer' => '7civil-group-automation',
    ],

    'database' => [
        // 'sqlite' (default, zero-config), 'mysql', or 'sqlsrv' (SQL Server).
        'driver' => $_ENV['DB_DRIVER'] ?? getenv('DB_DRIVER') ?: 'sqlite',
        'sqlite' => [
            'path' => __DIR__ . '/../storage/database.sqlite',
        ],
        'mysql' => [
            'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306',
            'database' => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'civil7_automation',
            'username' => $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root',
            'password' => $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '',
            'charset' => 'utf8mb4',
        ],
        // Microsoft SQL Server 2008+ (needs the pdo_sqlsrv extension). Reuses the
        // same DB_* variables as MySQL; the default port is 1433. The default
        // login mode is Windows Authentication — see 'windows_auth' below.
        // TrustServerCertificate
        // defaults on for local/self-signed instances — set DB_TRUST_CERT=0 to require
        // a valid certificate, and DB_ENCRYPT to force/disable encryption.
        'sqlsrv' => [
            'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '1433',
            'database' => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'civil7_automation',
            'username' => $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '',
            'password' => $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '',
            // Windows Authentication (Integrated Security): the connection is
            // made as the process identity — the IIS application pool account
            // for web requests, the signed-in user for `php bin/console`. That
            // account needs rights on the database. Leaving DB_USER empty
            // selects it automatically; DB_WINDOWS_AUTH forces it either way.
            'windows_auth' => (static function (): bool {
                $flag = $_ENV['DB_WINDOWS_AUTH'] ?? getenv('DB_WINDOWS_AUTH');
                if (is_string($flag) && $flag !== '') {
                    return filter_var($flag, FILTER_VALIDATE_BOOL);
                }

                return (string) ($_ENV['DB_USER'] ?? getenv('DB_USER') ?: '') === '';
            })(),
            'trust_cert' => filter_var($_ENV['DB_TRUST_CERT'] ?? getenv('DB_TRUST_CERT') ?: 'true', FILTER_VALIDATE_BOOL),
            'encrypt' => $_ENV['DB_ENCRYPT'] ?? getenv('DB_ENCRYPT') ?: '',
        ],
    ],
];
