<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use SevenCivilGroup\Domain\Repositories\SettingsRepositoryInterface;
use SevenCivilGroup\Infrastructure\Database\Connection;
use PDO;

final class PdoSettingsRepository implements SettingsRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo()->prepare('SELECT svalue FROM settings WHERE skey = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        return $row ? (string) $row['svalue'] : $default;
    }

    /** @return array<string,string> */
    public function all(): array
    {
        $out = [];
        foreach ($this->pdo()->query('SELECT skey, svalue FROM settings')->fetchAll() as $row) {
            $out[(string) $row['skey']] = (string) $row['svalue'];
        }

        return $out;
    }

    public function set(string $key, string $value): void
    {
        $pdo = $this->pdo();
        $exists = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE skey = ?');
        $exists->execute([$key]);
        if ((int) $exists->fetchColumn() > 0) {
            $pdo->prepare('UPDATE settings SET svalue = ? WHERE skey = ?')->execute([$value, $key]);
        } else {
            $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)')->execute([$key, $value]);
        }
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
