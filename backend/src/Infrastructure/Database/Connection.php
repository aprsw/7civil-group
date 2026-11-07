<?php

declare(strict_types=1);

namespace ArtaPharma\Infrastructure\Database;

use ArtaPharma\Core\Config;
use PDO;
use RuntimeException;

/**
 * PDO connection factory (Factory pattern). Reads the configured driver and
 * builds a single, shared PDO instance. SQLite is the zero-config default;
 * MySQL is available by switching `database.driver`.
 */
final class Connection
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= $this->connect();
    }

    private function connect(): PDO
    {
        $driver = (string) $this->config->get('database.driver', 'sqlite');
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return match ($driver) {
            'sqlite' => $this->connectSqlite($options),
            'mysql' => $this->connectMysql($options),
            'sqlsrv', 'mssql' => $this->connectSqlsrv($options),
            default => throw new RuntimeException("درایور پایگاه‌داده پشتیبانی نمی‌شود: {$driver}"),
        };
    }

    /** @param array<int, mixed> $options */
    private function connectSqlite(array $options): PDO
    {
        $path = (string) $this->config->get('database.sqlite.path');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    /** @param array<int, mixed> $options */
    private function connectMysql(array $options): PDO
    {
        $c = (array) $this->config->get('database.mysql');
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $c['host'],
            $c['port'],
            $c['database'],
            $c['charset'],
        );

        return new PDO($dsn, (string) $c['username'], (string) $c['password'], $options);
    }

    /**
     * Microsoft SQL Server via the pdo_sqlsrv driver.
     *
     * @param array<int, mixed> $options
     */
    private function connectSqlsrv(array $options): PDO
    {
        $c = (array) $this->config->get('database.sqlsrv');

        // pdo_sqlsrv rejects PDO::ATTR_EMULATE_PREPARES, so build a clean set.
        $sqlsrvOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        // DSN: sqlsrv:Server=host,port;Database=name
        $server = (string) $c['host'];
        if (($c['port'] ?? '') !== '') {
            $server .= ',' . $c['port'];
        }
        $dsn = sprintf('sqlsrv:Server=%s;Database=%s', $server, $c['database']);

        // Local/self-signed instances need the certificate trusted; encryption
        // is negotiated by the driver. Both are opt-in via env.
        if (!empty($c['trust_cert'])) {
            $dsn .= ';TrustServerCertificate=1';
        }
        if (($c['encrypt'] ?? '') !== '') {
            $dsn .= ';Encrypt=' . $c['encrypt'];
        }

        return new PDO($dsn, (string) $c['username'], (string) $c['password'], $sqlsrvOptions);
    }
}
