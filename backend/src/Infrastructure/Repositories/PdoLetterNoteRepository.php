<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use SevenCivilGroup\Domain\Repositories\LetterNoteRepositoryInterface;
use SevenCivilGroup\Infrastructure\Database\Connection;
use PDO;

final class PdoLetterNoteRepository implements LetterNoteRepositoryInterface
{
    private const MAX = 10;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function setForLetter(int $letterId, array $notes): void
    {
        $pdo = $this->pdo();
        $pdo->prepare('DELETE FROM letter_notes WHERE letter_id = ?')->execute([$letterId]);
        $stmt = $pdo->prepare(
            'INSERT INTO letter_notes (letter_id, title, content, file_name, file_path, file_mime, file_size, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $now = date('Y-m-d H:i:s');
        foreach (array_slice(array_values($notes), 0, self::MAX) as $i => $n) {
            $title = trim((string) ($n['title'] ?? '')) ?: ('یادداشت ' . ($i + 1));
            $content = (string) ($n['content'] ?? '');
            $fileName = trim((string) ($n['file_name'] ?? '')) ?: null;
            $filePath = trim((string) ($n['file_path'] ?? '')) ?: null;
            $fileMime = trim((string) ($n['file_mime'] ?? '')) ?: null;
            $fileSize = isset($n['file_size']) && $n['file_size'] !== '' ? (int) $n['file_size'] : null;
            // Skip completely empty rows (no title, no body, no file).
            if ($content === '' && ($n['title'] ?? '') === '' && $filePath === null) {
                continue;
            }
            $stmt->execute([$letterId, $title, $content, $fileName, $filePath, $fileMime, $fileSize, $now]);
        }
    }

    /** @return list<array<string,mixed>> */
    public function forLetter(int $letterId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, title, content, file_name, file_path, file_mime, file_size
             FROM letter_notes WHERE letter_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$letterId]);

        return array_map(static function ($r) {
            $path = $r['file_path'] ?? null;

            return [
                'id' => (int) $r['id'],
                'title' => (string) $r['title'],
                'content' => (string) ($r['content'] ?? ''),
                'file_name' => $r['file_name'] !== null ? (string) $r['file_name'] : null,
                // Opaque storage token (private storage/uploads). The file is
                // streamed via GET /letters/{id}/notes/{noteId}/download, so a
                // resend can reattach it and recipients can download it.
                'file_path' => $path !== null ? (string) $path : null,
                'file_mime' => $r['file_mime'] !== null ? (string) $r['file_mime'] : null,
                'file_size' => $r['file_size'] !== null ? (int) $r['file_size'] : null,
            ];
        }, $stmt->fetchAll());
    }

    public function deleteForLetter(int $letterId): void
    {
        $this->pdo()->prepare('DELETE FROM letter_notes WHERE letter_id = ?')->execute([$letterId]);
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
