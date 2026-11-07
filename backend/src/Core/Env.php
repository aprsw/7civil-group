<?php

declare(strict_types=1);

namespace ArtaPharma\Core;

/**
 * Minimal .env loader (no external dependency). Parses simple KEY=VALUE lines
 * from a dotenv file into $_ENV / $_SERVER / putenv so config.php can read them.
 *
 * Real environment variables already set by the web server always win — the
 * file only fills in what is missing, so a hosting panel's SetEnv is respected.
 */
final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip surrounding quotes, if any.
            if (strlen($value) >= 2
                && ($value[0] === '"' || $value[0] === "'")
                && $value[strlen($value) - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            }

            if ($key === '') {
                continue;
            }
            // Do not override a variable already provided by the environment.
            if (array_key_exists($key, $_ENV) || getenv($key) !== false) {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}
