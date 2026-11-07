<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use SevenCivilGroup\Domain\Entities\Letter;
use SevenCivilGroup\Domain\Enums\LetterDirection;
use SevenCivilGroup\Domain\Repositories\LetterRepositoryInterface;
use SevenCivilGroup\Infrastructure\Database\Connection;
use SevenCivilGroup\Services\NumberingService;
use PDO;

final class PdoLetterRepository implements LetterRepositoryInterface
{
    use SqlServerLimit;

    public function __construct(
        private readonly Connection $connection,
        private readonly NumberingService $numbering,
    ) {
    }

    /** @return list<Letter> */
    public function all(): array
    {
        $rows = $this->pdo()->query('SELECT * FROM letters ORDER BY id DESC')->fetchAll();

        return array_map(Letter::fromRow(...), $rows);
    }

    /** @return list<Letter> */
    public function byDirection(LetterDirection $direction): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM letters WHERE direction = ? ORDER BY id DESC');
        $stmt->execute([$direction->value]);

        return array_map(Letter::fromRow(...), $stmt->fetchAll());
    }

    public function find(int $id): ?Letter
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM letters WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Letter::fromRow($row) : null;
    }

    /** @return list<Letter> */
    public function recent(int $limit): array
    {
        // SQL Server uses "SELECT TOP (?) …" with the count bound first; other
        // drivers keep the portable "… LIMIT ?" with the count bound last.
        if ($this->isSqlServerDriver()) {
            $stmt = $this->pdo()->prepare('SELECT TOP (?) * FROM letters WHERE direction = ? ORDER BY id DESC');
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(2, LetterDirection::Incoming->value);
        } else {
            $stmt = $this->pdo()->prepare('SELECT * FROM letters WHERE direction = ? ORDER BY id DESC LIMIT ?');
            $stmt->bindValue(1, LetterDirection::Incoming->value);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        }
        $stmt->execute();

        return array_map(Letter::fromRow(...), $stmt->fetchAll());
    }

    /** @return list<Letter> */
    public function archived(): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT * FROM letters WHERE status IN ('approved', 'sent', 'archived') ORDER BY id DESC"
        );
        $stmt->execute();

        return array_map(Letter::fromRow(...), $stmt->fetchAll());
    }

    public function create(Letter $letter): Letter
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $id = $this->insert($letter);

            // Derive the official registration number from the atomic row id (an
            // empty number means "auto") using the admin-configured pattern —
            // no COUNT()-based race, always unique.
            if ($letter->number === '' || $letter->number === 'AUTO') {
                $num = $this->numbering->format($id, $letter->departmentId);
                $pdo->prepare('UPDATE letters SET number = ? WHERE id = ?')->execute([$num, $id]);
            }

            // A letter that opens a new conversation is its own thread root.
            if ($letter->threadId === null) {
                $pdo->prepare('UPDATE letters SET thread_id = ? WHERE id = ?')->execute([$id, $id]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $this->find($id) ?? $letter;
    }

    private function insert(Letter $letter): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO letters
             (number, subject, body, body2, direction, scope, confidentiality, priority, kind, secretariat, status,
              paper_size, workflow_id, stage, from_label, to_label, sender_id, recipient_id,
              recipient_department_id, external_recipient, reply_to_id, thread_id, department_id,
              attachment, attachment_path, attachment_mime, attachment_size,
              sign_name, sign_role, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $letter->number, $letter->subject, $letter->body, $letter->body2,
            $letter->direction->value, $letter->scope->value, $letter->confidentiality->value,
            $letter->priority->value, $letter->kind->value, $letter->secretariat, $letter->status->value, $letter->paperSize->value,
            $letter->workflowId, $letter->stage, $letter->fromLabel, $letter->toLabel,
            $letter->senderId, $letter->recipientId, $letter->recipientDepartmentId,
            $letter->externalRecipient, $letter->replyToId, $letter->threadId,
            $letter->departmentId, $letter->attachment, $letter->attachmentPath,
            $letter->attachmentMime, $letter->attachmentSize,
            $letter->signName, $letter->signRole, $letter->createdAt,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return list<Letter> */
    public function thread(int $threadId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM letters WHERE thread_id = ? ORDER BY id ASC');
        $stmt->execute([$threadId]);

        return array_map(Letter::fromRow(...), $stmt->fetchAll());
    }

    public function updateStatus(int $id, string $status, int $stage): ?Letter
    {
        $stmt = $this->pdo()->prepare('UPDATE letters SET status = ?, stage = ? WHERE id = ?');
        $stmt->execute([$status, $stage, $id]);

        return $this->find($id);
    }

    /** @param array<string,mixed> $fields */
    public function update(int $id, array $fields): ?Letter
    {
        $allowed = [
            'subject', 'body', 'body2', 'direction', 'scope', 'confidentiality', 'priority', 'kind',
            'status', 'paper_size', 'workflow_id', 'stage', 'to_label', 'recipient_id',
            'recipient_department_id', 'external_recipient', 'attachment', 'attachment_path',
            'attachment_mime', 'attachment_size', 'secretariat_folder_id',
        ];
        $sets = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $sets[] = "{$k} = ?";
                $vals[] = $v;
            }
        }
        if ($sets === []) {
            return $this->find($id);
        }
        $vals[] = $id;
        $stmt = $this->pdo()->prepare('UPDATE letters SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($vals);

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM letter_recipients WHERE letter_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM letter_attachments WHERE letter_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM letter_reads WHERE letter_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM letter_approvers WHERE letter_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM letter_notes WHERE letter_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM letter_actions WHERE letter_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM letters WHERE id = ?')->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function countByDirection(LetterDirection $direction): int
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM letters WHERE direction = ?');
        $stmt->execute([$direction->value]);

        return (int) $stmt->fetchColumn();
    }

    /** @param list<string> $numbers */
    public function numberExists(array $numbers): bool
    {
        $numbers = array_values(array_unique(array_filter(array_map('strval', $numbers), static fn ($n) => $n !== '')));
        if ($numbers === []) {
            return false;
        }
        $marks = implode(', ', array_fill(0, count($numbers), '?'));
        $stmt = $this->pdo()->prepare("SELECT COUNT(*) FROM letters WHERE number IN ({$marks})");
        $stmt->execute($numbers);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function lastSecretariatNumber(?string $box = null): ?string
    {
        $sql = 'SELECT number FROM letters WHERE secretariat IS NOT NULL';
        $params = [];
        if ($box !== null) {
            $sql .= ' AND secretariat = ?';
            $params[] = $box;
        }
        $sql .= ' ORDER BY id DESC';
        if ($this->isSqlServerDriver()) {
            $sql = preg_replace('/^SELECT /', 'SELECT TOP (1) ', $sql);
        } else {
            $sql .= ' LIMIT 1';
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $number = $stmt->fetchColumn();

        return $number === false || $number === null || $number === '' ? null : (string) $number;
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
