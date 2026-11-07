<?php

declare(strict_types=1);

namespace ArtaPharma\Core;

/**
 * Hand-written PSR-4 autoloader.
 *
 * The project deliberately avoids Composer's generated autoloader ("خودت بساز").
 * This class implements the PSR-4 specification: a namespace prefix is mapped to
 * one or more base directories, and class names are resolved to files on disk.
 *
 * @see https://www.php-fig.org/psr/psr-4/
 */
final class Autoloader
{
    /** @var array<string, list<string>> prefix => base directories */
    private array $prefixes = [];

    /**
     * Register a PSR-4 namespace prefix and its base directory.
     */
    public function addNamespace(string $prefix, string $baseDir): self
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $this->prefixes[$prefix][] = $baseDir;

        return $this;
    }

    /**
     * Hook this autoloader onto the SPL autoload stack.
     */
    public function register(): self
    {
        spl_autoload_register([$this, 'loadClass']);

        return $this;
    }

    /**
     * Resolve a fully-qualified class name to a file and require it.
     */
    public function loadClass(string $class): bool
    {
        $prefix = $class;

        // Walk the namespace segments from the most specific to the least.
        while (($pos = strrpos($prefix, '\\')) !== false) {
            $prefix = substr($class, 0, $pos + 1);
            $relative = substr($class, $pos + 1);

            if ($this->loadMappedFile($prefix, $relative)) {
                return true;
            }

            $prefix = rtrim($prefix, '\\');
        }

        return false;
    }

    private function loadMappedFile(string $prefix, string $relativeClass): bool
    {
        if (!isset($this->prefixes[$prefix])) {
            return false;
        }

        foreach ($this->prefixes[$prefix] as $baseDir) {
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (is_readable($file)) {
                require $file;

                return true;
            }
        }

        return false;
    }
}
