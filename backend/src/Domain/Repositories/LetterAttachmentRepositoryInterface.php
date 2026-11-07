<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Repositories;

use ArtaPharma\Domain\Entities\LetterAttachment;

interface LetterAttachmentRepositoryInterface
{
    /** @param array{name:string,path:string,mime?:?string,size?:?int} $data */
    public function add(int $letterId, array $data): LetterAttachment;

    /** @return list<LetterAttachment> */
    public function forLetter(int $letterId): array;

    public function find(int $id): ?LetterAttachment;

    public function deleteForLetter(int $letterId): void;
}
