<?php

declare(strict_types=1);

namespace ArtaPharma\Infrastructure\Repositories;

use ArtaPharma\Domain\Repositories\ChatRepositoryInterface;
use ArtaPharma\Infrastructure\Database\Connection;
use PDO;

final class PdoChatRepository implements ChatRepositoryInterface
{
    use SqlServerLimit;

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM conversations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return list<int> */
    public function members(int $conversationId): array
    {
        $stmt = $this->pdo()->prepare('SELECT user_id FROM conversation_members WHERE conversation_id = ?');
        $stmt->execute([$conversationId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    }

    public function isMember(int $conversationId, int $userId): bool
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM conversation_members WHERE conversation_id = ? AND user_id = ?');
        $stmt->execute([$conversationId, $userId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findDirect(int $userA, int $userB): ?int
    {
        $sql = 'SELECT c.id FROM conversations c
                JOIN conversation_members m1 ON m1.conversation_id = c.id AND m1.user_id = ?
                JOIN conversation_members m2 ON m2.conversation_id = c.id AND m2.user_id = ?
                WHERE c.type = ? LIMIT 1';
        $stmt = $this->pdo()->prepare($this->limitToTop($sql));
        $stmt->execute([$userA, $userB, 'direct']);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function create(string $type, ?string $title, int $createdBy, array $memberIds): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO conversations (type, title, created_by, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$type, $title, $createdBy, date('Y-m-d H:i:s')]);
            $cid = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO conversation_members (conversation_id, user_id) VALUES (?, ?)');
            foreach (array_values(array_unique(array_map('intval', $memberIds))) as $uid) {
                $ins->execute([$cid, $uid]);
            }
            $pdo->commit();

            return $cid;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $conversationId): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM chat_messages WHERE conversation_id = ?')->execute([$conversationId]);
            $pdo->prepare('DELETE FROM conversation_members WHERE conversation_id = ?')->execute([$conversationId]);
            $pdo->prepare('DELETE FROM conversations WHERE id = ?')->execute([$conversationId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    public function forUser(int $userId): array
    {
        $sql = 'SELECT c.* FROM conversations c
                JOIN conversation_members m ON m.conversation_id = c.id
                WHERE m.user_id = ? ORDER BY c.id DESC';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function lastMessage(int $conversationId): ?array
    {
        $stmt = $this->pdo()->prepare($this->limitToTop('SELECT * FROM chat_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 1'));
        $stmt->execute([$conversationId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function messages(int $conversationId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM chat_messages WHERE conversation_id = ? ORDER BY id ASC');
        $stmt->execute([$conversationId]);

        return $stmt->fetchAll();
    }

    public function addMessage(int $conversationId, int $userId, array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO chat_messages (conversation_id, user_id, body, file_path, file_name, file_mime, file_size, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $conversationId, $userId,
            (string) ($data['body'] ?? ''),
            $data['file_path'] ?? null,
            $data['file_name'] ?? null,
            $data['file_mime'] ?? null,
            $data['file_size'] ?? null,
            date('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return list<string> */
    public function purgeOlderThan(int $days): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - $days * 86400);
        $pdo = $this->pdo();

        $sel = $pdo->prepare('SELECT file_path FROM chat_messages WHERE created_at < ? AND file_path IS NOT NULL');
        $sel->execute([$cutoff]);
        $files = array_values(array_filter(array_column($sel->fetchAll(), 'file_path')));

        $pdo->prepare('DELETE FROM chat_messages WHERE created_at < ?')->execute([$cutoff]);

        return array_map('strval', $files);
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
