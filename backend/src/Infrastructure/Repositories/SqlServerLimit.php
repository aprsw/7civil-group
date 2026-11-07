<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use PDO;

/**
 * SQL Server has no LIMIT clause. This trait rewrites a trailing literal
 * "LIMIT n" into "SELECT TOP n …" when the active driver is SQL Server, and
 * leaves the query untouched on SQLite/MySQL. Repositories using it must
 * expose a pdo(): PDO method.
 */
trait SqlServerLimit
{
    private function isSqlServerDriver(): bool
    {
        $driver = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

        return $driver === 'sqlsrv' || $driver === 'dblib' || $driver === 'mssql';
    }

    private function limitToTop(string $sql): string
    {
        if (!$this->isSqlServerDriver()) {
            return $sql;
        }

        return preg_replace_callback(
            '/^(\s*SELECT\s+)(.*?)(\s+LIMIT\s+(\d+)\s*)$/is',
            static fn (array $m): string => $m[1] . 'TOP ' . (int) $m[4] . ' ' . $m[2],
            $sql,
        ) ?? $sql;
    }
}
