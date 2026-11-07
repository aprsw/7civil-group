<?php

declare(strict_types=1);

namespace ArtaPharma\Core;

/**
 * Immutable configuration holder with dot-notation access.
 */
final class Config
{
    /** @param array<string, mixed> $items */
    public function __construct(private array $items)
    {
    }

    public static function fromFile(string $path): self
    {
        /** @var array<string, mixed> $items */
        $items = require $path;

        return new self($items);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
