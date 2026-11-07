<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Database;

use PDO;

/**
 * Creates the schema. Portable across SQLite and MySQL by swapping only the
 * auto-increment primary-key clause; JSON is stored as TEXT on both.
 */
final class Migrator
{
    /**
     * Bump this whenever the schema (the CREATE statements below) changes. It is
     * used by ensureSchema() to auto-add any missing tables after a code update,
     * so a partial/older database heals itself on the next request.
     */
    public const SCHEMA_VERSION = '2026-11-07';

    /**
     * Every table this schema owns, children before parents so a drop never
     * trips over a dependency. Shared by fresh() and the seeder's clear().
     *
     * @var list<string>
     */
    public const TABLES = [
        'org_logos', 'chat_messages', 'conversation_members', 'conversations',
        'letter_folder_items', 'user_folders', 'letter_notes', 'letter_approvers',
        'letter_reads', 'role_messaging_rules', 'messaging_rules', 'events',
        'user_notes', 'secretariat_folders', 'user_permissions', 'office_files',
        'letter_attachments', 'settings', 'letter_recipients', 'letter_actions',
        'user_documents', 'templates', 'workflows', 'letters', 'users', 'roles',
        'departments',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Idempotently make sure every table exists — runs the non-destructive
     * migrate() (CREATE TABLE IF NOT EXISTS) at most once per schema version,
     * guarded by a marker file so normal requests do no database work.
     *
     * Safe to call on every boot: it never drops or alters data.
     */
    public function ensureSchema(string $markerDir): void
    {
        $marker = rtrim($markerDir, '/\\') . '/.schema_' . self::SCHEMA_VERSION;
        if (is_file($marker)) {
            return; // already ensured for this schema version
        }
        $this->migrate(); // CREATE TABLE IF NOT EXISTS — adds only what's missing
        if (!is_dir($markerDir)) {
            @mkdir($markerDir, 0775, true);
        }
        @file_put_contents($marker, date('c') . "\n");
    }

    public function migrate(): void
    {
        $pdo = $this->connection->pdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isMysql = $driver === 'mysql';
        $isSqlSrv = $driver === 'sqlsrv' || $driver === 'dblib' || $driver === 'mssql';

        if ($isMysql) {
            $pk = 'INT AUTO_INCREMENT PRIMARY KEY';
            $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        } elseif ($isSqlSrv) {
            $pk = 'INT IDENTITY(1,1) PRIMARY KEY';
            $engine = '';
        } else {
            $pk = 'INTEGER PRIMARY KEY AUTOINCREMENT';
            $engine = '';
        }

        foreach ($this->statements($pk, $engine) as $sql) {
            $pdo->exec($isSqlSrv ? $this->toTSql($sql) : $sql);
        }

        $this->createIndexes($pdo, $isSqlSrv);
    }

    /**
     * Secondary indexes for the columns every mailbox/cartable query filters
     * on. Without them SQL Server scans `letters` and `letter_recipients` for
     * each inbox render, which is the first thing to hurt in production.
     *
     * Each statement is guarded so re-running migrate() is a no-op, and a
     * single failure never aborts the rest of the schema.
     */
    private function createIndexes(PDO $pdo, bool $isSqlSrv): void
    {
        $indexes = [
            'ix_letters_direction' => 'letters (direction)',
            'ix_letters_sender' => 'letters (sender_id)',
            'ix_letters_thread' => 'letters (thread_id)',
            'ix_letters_number' => 'letters (number)',
            'ix_letters_secretariat' => 'letters (secretariat)',
            'ix_recipients_letter' => 'letter_recipients (letter_id)',
            'ix_recipients_user' => 'letter_recipients (user_id)',
            'ix_recipients_dept' => 'letter_recipients (department_id)',
            'ix_reads_letter_user' => 'letter_reads (letter_id, user_id)',
            'ix_actions_letter' => 'letter_actions (letter_id)',
            'ix_approvers_letter' => 'letter_approvers (letter_id, position)',
            'ix_attachments_letter' => 'letter_attachments (letter_id)',
            'ix_notes_letter' => 'letter_notes (letter_id)',
            'ix_folder_items_user' => 'letter_folder_items (user_id, letter_id)',
            'ix_user_permissions_user' => 'user_permissions (user_id)',
            'ix_chat_messages_conv' => 'chat_messages (conversation_id, id)',
            'ix_conv_members_user' => 'conversation_members (user_id)',
            'ix_user_notes_user' => 'user_notes (user_id)',
        ];

        foreach ($indexes as $name => $target) {
            [$table] = explode(' ', $target, 2);
            $sql = $isSqlSrv
                ? "IF OBJECT_ID(N'{$table}', N'U') IS NOT NULL AND NOT EXISTS "
                    . "(SELECT 1 FROM sys.indexes WHERE name = N'{$name}' AND object_id = OBJECT_ID(N'{$table}')) "
                    . "CREATE INDEX {$name} ON {$target}"
                : "CREATE INDEX IF NOT EXISTS {$name} ON {$target}";
            try {
                $pdo->exec($sql);
            } catch (\Throwable) {
                // An index is an optimisation, never a correctness requirement.
            }
        }
    }

    /**
     * Translate the portable CREATE TABLE text to T-SQL (SQL Server):
     *   • VARCHAR(n)          → NVARCHAR(n)      (full Unicode / Persian)
     *   • TEXT                → NVARCHAR(MAX)
     *   • CREATE TABLE IF NOT EXISTS x (…)  →  IF OBJECT_ID(N'x','U') IS NULL CREATE TABLE x (…)
     * (The primary-key token is already IDENTITY, and there is no ENGINE clause.)
     */
    private function toTSql(string $sql): string
    {
        $sql = preg_replace('/\bVARCHAR\s*\(/i', 'NVARCHAR(', $sql) ?? $sql;
        $sql = preg_replace('/\bTEXT\b/i', 'NVARCHAR(MAX)', $sql) ?? $sql;
        $sql = preg_replace_callback(
            '/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+([A-Za-z_][A-Za-z0-9_]*)/i',
            static fn (array $m): string => "IF OBJECT_ID(N'{$m[1]}', N'U') IS NULL CREATE TABLE {$m[1]}",
            $sql,
        ) ?? $sql;

        return $sql;
    }

    public function fresh(): void
    {
        $pdo = $this->connection->pdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        // "DROP TABLE IF EXISTS" only reached T-SQL in SQL Server 2016; on 2008
        // it is a syntax error, which used to make `console install` fail
        // outright. OBJECT_ID() is the portable spelling on every version.
        $isSqlSrv = in_array($driver, ['sqlsrv', 'dblib', 'mssql'], true);

        foreach (self::TABLES as $table) {
            $pdo->exec($isSqlSrv
                ? "IF OBJECT_ID(N'{$table}', N'U') IS NOT NULL DROP TABLE {$table}"
                : "DROP TABLE IF EXISTS {$table}");
        }
        $this->migrate();
    }

    /** @return list<string> */
    private function statements(string $pk, string $engine): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS departments (
                id {$pk},
                name VARCHAR(150) NOT NULL,
                code VARCHAR(50) NOT NULL,
                description TEXT NULL,
                manager_id INT NULL
            ){$engine}",

            "CREATE TABLE IF NOT EXISTS roles (
                id {$pk},
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(120) NOT NULL,
                level INT NOT NULL,
                permissions TEXT NOT NULL
            ){$engine}",

            "CREATE TABLE IF NOT EXISTS users (
                id {$pk},
                name VARCHAR(150) NOT NULL,
                username VARCHAR(120) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(150) NULL,
                role_id INT NOT NULL,
                department_id INT NOT NULL,
                avatar_color VARCHAR(30) NOT NULL DEFAULT 'brand',
                birth_date VARCHAR(20) NULL,
                address VARCHAR(255) NULL,
                mobile VARCHAR(30) NULL,
                phone VARCHAR(30) NULL,
                description TEXT NULL,
                photo_path VARCHAR(255) NULL,
                signature_path VARCHAR(255) NULL,
                active INT NOT NULL DEFAULT 1,
                last_seen_at VARCHAR(40) NULL
            ){$engine}",

            "CREATE TABLE IF NOT EXISTS user_documents (
                id {$pk},
                user_id INT NOT NULL,
                title VARCHAR(150) NOT NULL,
                path VARCHAR(255) NOT NULL,
                mime VARCHAR(120) NULL,
                size INT NULL,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",

            "CREATE TABLE IF NOT EXISTS letters (
                id {$pk},
                number VARCHAR(60) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                body TEXT NULL,
                body2 TEXT NULL,
                direction VARCHAR(20) NOT NULL,
                scope VARCHAR(20) NOT NULL DEFAULT 'internal',
                confidentiality VARCHAR(20) NOT NULL DEFAULT 'normal',
                priority VARCHAR(20) NOT NULL,
                kind VARCHAR(20) NOT NULL DEFAULT 'action',
                secretariat VARCHAR(10) NULL,
                secretariat_folder_id INT NULL,
                status VARCHAR(20) NOT NULL,
                paper_size VARCHAR(5) NOT NULL DEFAULT 'a4',
                workflow_id INT NULL,
                stage INT NOT NULL DEFAULT 0,
                from_label VARCHAR(200) NULL,
                to_label VARCHAR(200) NULL,
                sender_id INT NULL,
                recipient_id INT NULL,
                recipient_department_id INT NULL,
                external_recipient VARCHAR(200) NULL,
                reply_to_id INT NULL,
                thread_id INT NULL,
                department_id INT NOT NULL DEFAULT 0,
                attachment VARCHAR(200) NULL,
                attachment_path VARCHAR(255) NULL,
                attachment_mime VARCHAR(120) NULL,
                attachment_size INT NULL,
                sign_name VARCHAR(150) NULL,
                sign_role VARCHAR(150) NULL,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",

            "CREATE TABLE IF NOT EXISTS workflows (
                id {$pk},
                name VARCHAR(200) NOT NULL,
                description TEXT NULL,
                department_id INT NOT NULL,
                steps TEXT NOT NULL,
                approver_ids TEXT NULL,
                created_by INT NULL
            ){$engine}",

            // Per-letter approver chain (workflow letters). The sender picks an
            // ordered list of approvers; each approves/rejects in turn.
            "CREATE TABLE IF NOT EXISTS letter_approvers (
                id {$pk},
                letter_id INT NOT NULL,
                position INT NOT NULL,
                user_id INT NOT NULL,
                status VARCHAR(12) NOT NULL DEFAULT 'pending',
                note TEXT NULL,
                acted_at VARCHAR(40) NULL
            ){$engine}",

            "CREATE TABLE IF NOT EXISTS templates (
                id {$pk},
                title VARCHAR(200) NOT NULL,
                body TEXT NOT NULL,
                category VARCHAR(80) NULL,
                paper_size VARCHAR(5) NOT NULL DEFAULT 'a4',
                department_id INT NULL,
                created_by INT NULL
            ){$engine}",

            "CREATE TABLE IF NOT EXISTS letter_actions (
                id {$pk},
                letter_id INT NOT NULL,
                user_id INT NULL,
                action VARCHAR(20) NOT NULL,
                from_stage INT NULL,
                to_stage INT NULL,
                note TEXT NULL,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",

            // Organisation-wide settings (key/value) — e.g. the letter
            // numbering pattern, editable by the CEO.
            "CREATE TABLE IF NOT EXISTS settings (
                skey VARCHAR(80) NOT NULL,
                svalue TEXT NULL,
                PRIMARY KEY (skey)
            ){$engine}",

            // Editable notes/documents delivered with a letter (up to 10). The
            // sender writes/edits them in-app (rich text + tables) and they are
            // saved and delivered to the recipients.
            "CREATE TABLE IF NOT EXISTS letter_notes (
                id {$pk},
                letter_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                content TEXT NULL,
                file_name VARCHAR(255) NULL,
                file_path VARCHAR(255) NULL,
                file_mime VARCHAR(120) NULL,
                file_size INT NULL,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",

            // Shared دبیرخانه folders — any secretariat user can create/rename/
            // delete them; each registered letter may be filed under one.
            "CREATE TABLE IF NOT EXISTS secretariat_folders (
                id {$pk},
                name VARCHAR(150) NOT NULL,
                created_by INT NULL,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",

            // Per-user permission grants — the admin may grant an individual
            // capability (e.g. secretariat.use) to any user, on top of their role.
            "CREATE TABLE IF NOT EXISTS user_permissions (
                id {$pk},
                user_id INT NOT NULL,
                permission VARCHAR(60) NOT NULL
            ){$engine}",

            // Admin-managed Word/Excel library. Users pick from these when
            // composing a letter's «فایل‌های پیوستی» (no arbitrary drive files).
            "CREATE TABLE IF NOT EXISTS office_files (
                id {$pk},
                name VARCHAR(255) NOT NULL,
                path VARCHAR(255) NOT NULL,
                mime VARCHAR(150) NULL,
                size INT NULL,
                uploaded_by INT NULL,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",

            // Multiple attachments per letter (up to 10, many file types).
            "CREATE TABLE IF NOT EXISTS letter_attachments (
                id {$pk},
                letter_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                path VARCHAR(255) NOT NULL,
                mime VARCHAR(150) NULL,
                size INT NULL,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",

            // Per-user read state — a letter leaves «کارتابل من» once opened and
            // appears in «وارده»; also powers read-receipts and recall guards.
            "CREATE TABLE IF NOT EXISTS letter_reads (
                id {$pk},
                letter_id INT NOT NULL,
                user_id INT NOT NULL,
                read_at VARCHAR(40) NOT NULL
            ){$engine}",

            // Per-user folders (بایگانی + custom). Each user organises their own
            // mailbox; a letter is filed via letter_folder_items.
            "CREATE TABLE IF NOT EXISTS user_folders (
                id {$pk},
                user_id INT NOT NULL,
                name VARCHAR(120) NOT NULL,
                is_system INT NOT NULL DEFAULT 0,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS letter_folder_items (
                id {$pk},
                folder_id INT NOT NULL,
                letter_id INT NOT NULL,
                user_id INT NOT NULL
            ){$engine}",

            // ── Staff chat (گفتگو) ───────────────────────────────────────────
            "CREATE TABLE IF NOT EXISTS conversations (
                id {$pk},
                type VARCHAR(20) NOT NULL DEFAULT 'direct',
                title VARCHAR(200) NULL,
                created_by INT NULL,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS conversation_members (
                id {$pk},
                conversation_id INT NOT NULL,
                user_id INT NOT NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_messages (
                id {$pk},
                conversation_id INT NOT NULL,
                user_id INT NOT NULL,
                body TEXT NULL,
                file_path VARCHAR(255) NULL,
                file_name VARCHAR(255) NULL,
                file_mime VARCHAR(150) NULL,
                file_size INT NULL,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",

            // Per-unit messaging permissions (item 2). Each row allows members
            // of from_department_id to address to_department_id. When a source
            // unit has any rows, the allow-list replaces the default hierarchy
            // for that unit.
            "CREATE TABLE IF NOT EXISTS messaging_rules (
                id {$pk},
                from_department_id INT NOT NULL,
                to_department_id INT NOT NULL
            ){$engine}",

            // Role-to-role messaging permissions (سمت به سمت‌ها) — the admin
            // decides, per سمت, which سمت‌ها a normal letter may be addressed to.
            "CREATE TABLE IF NOT EXISTS role_messaging_rules (
                id {$pk},
                from_role_id INT NOT NULL,
                to_role_id INT NOT NULL
            ){$engine}",

            // Organisation-wide events module (رویدادها) — managed by users
            // holding events.manage.
            "CREATE TABLE IF NOT EXISTS events (
                id {$pk},
                title VARCHAR(200) NOT NULL,
                description TEXT NULL,
                event_date VARCHAR(20) NOT NULL,
                location VARCHAR(200) NULL,
                created_by INT NULL,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",

            // Per-user private notes/reminders (یادداشت کاربر). Each note is
            // visible only to its owner; note_gdate drives the reminder math.
            "CREATE TABLE IF NOT EXISTS user_notes (
                id {$pk},
                user_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                description TEXT NULL,
                note_date VARCHAR(20) NOT NULL,
                note_gdate VARCHAR(20) NOT NULL,
                remind_days_before INT NOT NULL DEFAULT 0,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",

            // The organisation's logo library. The group has several member
            // companies; `settings` holds, per slot (letterhead/login/sidebar),
            // the id of the logo chosen here.
            "CREATE TABLE IF NOT EXISTS org_logos (
                id {$pk},
                title VARCHAR(150) NOT NULL,
                path VARCHAR(255) NOT NULL,
                mime VARCHAR(120) NULL,
                size INT NULL,
                uploaded_by INT NULL,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",

            // Multi-recipient + referrals (ارجاع). One row per person/unit a
            // letter is addressed or referred to. 'kind' distinguishes the
            // original recipients from later referrals; 'referral_type' marks
            // the routing intent (اقدام/اطلاع/استحضار). 'read_at' powers read
            // receipts (Phase 4).
            "CREATE TABLE IF NOT EXISTS letter_recipients (
                id {$pk},
                letter_id INT NOT NULL,
                user_id INT NULL,
                department_id INT NULL,
                kind VARCHAR(20) NOT NULL DEFAULT 'recipient',
                referral_type VARCHAR(20) NULL,
                referred_by INT NULL,
                note TEXT NULL,
                read_at VARCHAR(40) NULL,
                created_at VARCHAR(40) NOT NULL
            ){$engine}",
        ];
    }
}
