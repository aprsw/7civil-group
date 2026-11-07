<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Repositories;

interface LetterNoteRepositoryInterface
{
    /**
     * @param list<array{
     *     title?:string, content?:string,
     *     file_name?:string, file_path?:string, file_mime?:string, file_size?:int
     * }> $notes
     */
    public function setForLetter(int $letterId, array $notes): void;

    /** @return list<array<string,mixed>> */
    public function forLetter(int $letterId): array;

    public function deleteForLetter(int $letterId): void;
}
