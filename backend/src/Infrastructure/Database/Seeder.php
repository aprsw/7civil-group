<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Database;

use SevenCivilGroup\Domain\Enums\Permission;
use SevenCivilGroup\Services\OrgIdentity;
use PDO;

/**
 * Seeds realistic Persian civil-engineering/office data: departments, RBAC
 * roles with access levels, staff, cartable letters and a default workflow.
 */
final class Seeder
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function run(): void
    {
        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $this->clear($pdo);
            $this->seedDepartments($pdo);
            $this->seedRoles($pdo);
            $this->seedUsers($pdo);
            $this->seedLetters($pdo);
            $this->seedLetterActions($pdo);
            $this->seedWorkflows($pdo);
            $this->seedTemplates($pdo);
            $this->seedEvents($pdo);
            $this->seedLogos($pdo);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function isSqlSrv(PDO $pdo): bool
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return $driver === 'sqlsrv' || $driver === 'dblib' || $driver === 'mssql';
    }

    /**
     * Run $fn while explicit values may be written to an IDENTITY column.
     * On MySQL/SQLite this is a no-op; on SQL Server it toggles IDENTITY_INSERT.
     */
    private function withIdentityInsert(PDO $pdo, string $table, callable $fn): void
    {
        $tsql = $this->isSqlSrv($pdo);
        if ($tsql) {
            $pdo->exec("SET IDENTITY_INSERT {$table} ON");
        }
        try {
            $fn();
        } finally {
            if ($tsql) {
                $pdo->exec("SET IDENTITY_INSERT {$table} OFF");
            }
        }
    }

    private function clear(PDO $pdo): void
    {
        foreach (['org_logos', 'chat_messages', 'conversation_members', 'conversations', 'letter_folder_items', 'user_folders', 'letter_notes', 'letter_approvers', 'letter_reads', 'role_messaging_rules', 'messaging_rules', 'events', 'user_notes', 'secretariat_folders', 'user_permissions', 'office_files', 'letter_attachments', 'letter_recipients', 'letter_actions', 'templates', 'workflows', 'letters', 'users', 'roles', 'departments'] as $table) {
            $pdo->exec("DELETE FROM {$table}");
        }
    }

    private function seedDepartments(PDO $pdo): void
    {
        $rows = [
            [1, 'مدیریت عامل', 'EXEC', 'دفتر مدیریت عامل و هیئت مدیره'],
            [2, 'واحد مالی', 'FIN', 'امور مالی، حسابداری و خزانه‌داری'],
            [3, 'واحد فناوری اطلاعات', 'IT', 'زیرساخت، نرم‌افزار و پشتیبانی'],
            [4, 'امور قراردادها و پیمان', 'CONT', 'مناقصات، قراردادها و رسیدگی به صورت‌وضعیت'],
            [5, 'تدارکات و پشتیبانی', 'PROC', 'خرید مصالح، ماشین‌آلات و تأمین کارگاه'],
            [6, 'دفتر فنی و کنترل پروژه', 'ENG', 'طراحی، متره و برآورد، برنامه‌ریزی و کنترل پروژه'],
            [7, 'دبیرخانه', 'REG', 'ثبت و گردش نامه‌های وارده و صادره'],
        ];
        $stmt = $pdo->prepare('INSERT INTO departments (id, name, code, description) VALUES (?, ?, ?, ?)');
        $this->withIdentityInsert($pdo, 'departments', static function () use ($stmt, $rows): void {
            foreach ($rows as $r) {
                $stmt->execute($r);
            }
        });
    }

    private function seedRoles(PDO $pdo): void
    {
        $vals = static fn (array $perms) => json_encode(
            array_map(static fn (Permission $p) => $p->value, $perms),
            JSON_UNESCAPED_UNICODE,
        );

        // مدیر عامل (tier 1) — senior admin: everything.
        $ceo = $vals(Permission::cases());
        // مدیر (tier 2) — run letters + workflow/template builders, see staff.
        $manager = $vals([
            Permission::ViewDashboard,
            Permission::LettersView, Permission::LettersCreate, Permission::LettersApprove,
            Permission::LettersSign, Permission::LettersSend, Permission::LettersArchive,
            Permission::WorkflowView, Permission::WorkflowManage,
            Permission::TemplatesView, Permission::TemplatesManage,
            Permission::UsersView, Permission::ChatUse, Permission::SecretariatUse,
        ]);
        // سرپرست / رئیس (tier 3) — compose + approve their step.
        $supervisor = $vals([
            Permission::ViewDashboard, Permission::LettersView, Permission::LettersCreate,
            Permission::LettersApprove, Permission::WorkflowView, Permission::TemplatesView,
            Permission::ChatUse,
        ]);
        // مسئول (tier 4) / کارشناس · کاردان (tier 5) — compose & follow.
        $expert = $vals([
            Permission::ViewDashboard, Permission::LettersView, Permission::LettersCreate,
            Permission::WorkflowView, Permission::TemplatesView, Permission::ChatUse,
        ]);

        // level == hierarchy tier (1 highest). Positions are department-flavoured.
        $rows = [
            [1, 'مدیر عامل', 'ceo', 1, $ceo],
            [2, 'مدیر مالی', 'finance-manager', 2, $manager],
            [3, 'مدیر انفورماتیک', 'it-manager', 2, $manager],
            [4, 'مدیر تدارکات', 'proc-manager', 2, $manager],
            [5, 'رئیس حسابداری', 'accounting-head', 3, $supervisor],
            [6, 'سرپرست انفورماتیک', 'it-supervisor', 3, $supervisor],
            [7, 'مسئول خزانه', 'treasury-officer', 4, $expert],
            [8, 'کارشناس حسابداری', 'accountant', 5, $expert],
            [9, 'کارشناس انفورماتیک', 'it-expert', 5, $expert],
            [10, 'کارشناس قراردادها', 'contracts-expert', 5, $expert],
            [11, 'کاردان تدارکات', 'proc-technician', 5, $expert],
        ];
        $stmt = $pdo->prepare('INSERT INTO roles (id, name, slug, level, permissions) VALUES (?, ?, ?, ?, ?)');
        $this->withIdentityInsert($pdo, 'roles', static function () use ($stmt, $rows): void {
            foreach ($rows as $r) {
                $stmt->execute($r);
            }
        });
    }

    private function seedUsers(PDO $pdo): void
    {
        // All demo users share the password «password» for easy evaluation.
        $hash = password_hash('password', PASSWORD_BCRYPT);
        // [id, name, username, role_id (position/tier), department_id, email, avatar_color]
        $rows = [
            [1, 'دکتر رضا صادقی', 'reza.sadeghi', 1, 1, 'ceo@7civilgroup.example', 'brand'],      // مدیر عامل (t1)
            [2, 'امیر اسدی', 'amir.asadi', 2, 2, 'finance@7civilgroup.example', 'teal'],           // مدیر مالی (t2)
            [3, 'پارسا محمدی', 'parsa.mohammadi', 3, 3, 'it@7civilgroup.example', 'gold'],          // مدیر انفورماتیک (t2)
            [4, 'میلاد حیدری', 'milad.heydari', 8, 2, 'milad@7civilgroup.example', 'brand'],        // کارشناس حسابداری (t5)
            [5, 'سارا نجفی', 'sara.najafi', 10, 4, 'contracts@7civilgroup.example', 'teal'],        // کارشناس قراردادها (t5)
            [6, 'کاربر تست (مدیر عامل)', 'test', 1, 1, 'test@7civilgroup.example', 'gold'],         // مدیر عامل تست (t1)
            [7, 'نگار رحیمی', 'negar.rahimi', 5, 2, 'negar@7civilgroup.example', 'brand'],          // رئیس حسابداری (t3)
            [8, 'حسین کاظمی', 'hossein.kazemi', 6, 3, 'hossein@7civilgroup.example', 'teal'],       // سرپرست انفورماتیک (t3)
            [9, 'مریم صادقی', 'maryam.sadeghi', 9, 3, 'maryam@7civilgroup.example', 'gold'],        // کارشناس انفورماتیک (t5)
            [10, 'رضا تدارک', 'reza.tadarok', 4, 5, 'proc@7civilgroup.example', 'brand'],           // مدیر تدارکات (t2)
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO users (id, name, username, role_id, department_id, email, avatar_color, password_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $this->withIdentityInsert($pdo, 'users', static function () use ($stmt, $rows, $hash): void {
            foreach ($rows as $r) {
                $stmt->execute([...$r, $hash]);
            }
        });
    }

    private function seedLetters(PDO $pdo): void
    {
        // Rich, realistic rows exercising scope / confidentiality / workflow /
        // sender / recipient(person or department) / external recipient.
        $cols = ['number', 'subject', 'direction', 'scope', 'confidentiality', 'priority', 'status',
            'workflow_id', 'stage', 'from_label', 'to_label', 'sender_id', 'recipient_id',
            'recipient_department_id', 'external_recipient', 'attachment', 'body', 'body2',
            'sign_name', 'sign_role', 'department_id', 'created_at'];

        $rows = [
            ['۵/۱/۷۶۶۵۸', 'درخواست خرید میلگرد و سیمان پروژهٔ مسکونی فاز ۲', 'in', 'internal', 'normal', 'urgent', 'pending', 1, 1, 'تدارکات و پشتیبانی', 'جناب آقای اسدی — مدیر مالی', 4, 2, 2, '', 'پیش‌فاکتور-۸۸۲.pdf', 'احتراماً با توجه به برنامهٔ بتن‌ریزی سقف طبقهٔ سوم، دستور لازم جهت خرید میلگرد آجدار و سیمان تیپ ۲ مطابق فهرست پیوست صادر فرمایید.', 'لازم به توضیح است تأییدیهٔ دفتر فنی در خصوص متره و برآورد به پیوست ارسال گردیده است.', 'میلاد حیدری', 'مسئول حسابداری', 5, '2026-07-04 09:24:00'],
            ['۵/۱/۷۶۶۴۹', 'گزارش آزمایش مقاومت ۲۸ روزهٔ بتن سقف طبقهٔ سوم', 'in', 'internal', 'normal', 'normal', 'reviewing', null, 2, 'دفتر فنی و کنترل پروژه', 'مدیریت فنی و اجرایی', null, null, 6, '', 'Concrete-Test-882.pdf', 'نتایج آزمایش مقاومت فشاری نمونه‌های بتن سقف طبقهٔ سوم، بالاتر از مقاومت مشخصهٔ طرح اختلاط (C30) گزارش شده است.', 'خواهشمند است نسبت به صدور مجوز قالب‌برداری و ادامهٔ عملیات اقدام فرمایید.', 'مهندس کریمی', 'کارشناس دفتر فنی', 6, '2026-07-04 08:10:00'],
            ['۵/۱/۷۶۶۳۱', 'ابلاغ دستورالعمل ایمنی گودبرداری و پایدارسازی', 'in', 'internal', 'normal', 'normal', 'approved', null, 4, 'مدیریت عملیات', 'کلیه واحدها', 1, null, null, '', 'HSE-Excavation.pdf', 'کلیهٔ کارگاه‌ها موظف به اجرای نقشهٔ پایدارسازی جداره، نصب حصار حفاظتی و کنترل روزانهٔ نشست‌سنج‌ها هستند.', 'مسئولیت پایش روزانه بر عهدهٔ سرپرست کارگاه و افسر HSE پروژه می‌باشد.', 'دکتر رضا صادقی', 'مدیر عامل', 1, '2026-07-03 16:40:00'],
            ['۵/۱/۷۶۶۰۲', 'صورت‌جلسهٔ کمیتهٔ فنی پروژه (محرمانه)', 'in', 'internal', 'confidential', 'info', 'pending', null, 1, 'دبیرخانه', 'جناب آقای اسدی — مدیر مالی', 1, 2, null, '', 'Minutes-1405-02.pdf', 'مصوبات جلسه شامل بازنگری برنامهٔ زمان‌بندی، تعیین سقف تعدیل و تکلیف قرارداد پیمانکاران جزء است.', 'خواهشمند است نظرات تکمیلی حداکثر تا پایان هفته اعلام گردد.', 'خانم رحیمی', 'دبیر کمیته', 7, '2026-07-03 11:02:00'],
            ['۵/۱/۷۶۵۷۴', 'پیگیری تمدید گواهینامهٔ صلاحیت پیمانکاری', 'in', 'internal', 'normal', 'urgent', 'reviewing', null, 2, 'امور قراردادها و پیمان', 'مدیریت قراردادها', null, null, 4, '', 'Rank-Renewal-2025.pdf', 'مدارک رزومهٔ کاری، صورت‌های مالی حسابرسی‌شده و فهرست ماشین‌آلات جهت تمدید رتبهٔ ابنیه آماده و به پیوست ارسال شده است.', 'مهلت بارگذاری در سامانهٔ ساجات تا ۱۰ روز آینده است.', 'مهندس فلاح', 'کارشناس قراردادها', 4, '2026-07-02 10:00:00'],
            ['۵/۱/۷۶۷۰۱', 'پاسخ به استعلام کارفرما در خصوص صورت‌وضعیت شمارهٔ ۴', 'out', 'external', 'normal', 'normal', 'sent', null, 3, 'دکتر رضا صادقی — مدیر عامل', 'کارفرما — سازمان نوسازی شهری', 1, null, null, 'سازمان نوسازی شهری', 'Reply-Employer.pdf', 'ریز متره و مستندات صورت‌وضعیت شمارهٔ ۴ مطابق فهرست‌بهای ابنیه تنظیم و جهت رسیدگی تقدیم می‌گردد.', 'آمادگی کامل جهت بازدید کارشناسان آن سازمان از کارگاه اعلام می‌گردد.', 'دکتر رضا صادقی', 'مدیر عامل', 1, '2026-07-03 14:20:00'],
            ['۵/۱/۷۶۶۸۸', 'ابلاغ بخشنامهٔ مرخصی تابستان', 'out', 'internal', 'normal', 'info', 'sent', null, 4, 'خانم صابری — مدیر منابع انسانی', 'کلیه واحدها', 1, null, null, '', '—', 'مدیران واحدها موظف به تنظیم برنامهٔ مرخصی پرسنل دفتر مرکزی و کارگاه‌ها تا پایان خرداد هستند.', 'حداکثر مرخصی هم‌زمان هر واحد ۳۰ درصد نیرو می‌باشد.', 'خانم صابری', 'مدیر منابع انسانی', 1, '2026-07-01 09:00:00'],
            ['—', 'پیش‌نویس قرارداد تأمین و نصب اسکلت فلزی', 'draft', 'external', 'normal', 'normal', 'draft', null, 0, 'پیش‌نویس', 'شرکت سازهٔ فولاد پارس', 4, null, null, 'شرکت سازهٔ فولاد پارس', 'Draft-Steel.docx', 'مفاد اولیهٔ قرارداد شامل تناژ اسکلت، زمان‌بندی تحویل در کارگاه و شرایط پرداخت است.', 'بندهای مربوط به تضمین حسن انجام کار و جریمهٔ تأخیر در حال نهایی‌سازی است.', 'میلاد حیدری', 'کارشناس حسابداری', 2, '2026-07-04 07:30:00'],
        ];

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $stmt = $pdo->prepare(
            'INSERT INTO letters (' . implode(', ', $cols) . ', paper_size) VALUES (' . $placeholders . ", 'a4')"
        );
        foreach ($rows as $r) {
            $stmt->execute($r);
        }
        // Each seeded letter opens its own thread.
        $pdo->exec('UPDATE letters SET thread_id = id WHERE thread_id IS NULL');

        // A threaded reply to the purchase request (letter #1), so the tree is populated.
        $parentId = (int) $pdo->query("SELECT id FROM letters WHERE number = '۵/۱/۷۶۶۵۸'")->fetchColumn();
        $reply = ['۵/۱/۷۶۷۱۰', 'پاسخ: درخواست خرید میلگرد و سیمان پروژهٔ مسکونی فاز ۲', 'out', 'internal', 'normal', 'normal', 'sent', 1, 2, 'امیر اسدی — مدیر مالی', 'میلاد حیدری — کارشناس حسابداری', 2, 4, 2, '', '—', 'با سلام، درخواست خرید مصالح بررسی و تأیید شد. لطفاً نسبت به ثبت سفارش و هماهنگی حمل به کارگاه اقدام گردد.', 'اعتبار لازم از محل بودجهٔ جاری تأمین می‌گردد.', 'امیر اسدی', 'مدیر مالی', 2, '2026-07-04 12:05:00'];
        $stmt2 = $pdo->prepare(
            'INSERT INTO letters (' . implode(', ', $cols) . ', paper_size, reply_to_id, thread_id) VALUES (' . $placeholders . ", 'a4', ?, ?)"
        );
        $stmt2->execute([...$reply, $parentId, $parentId]);
    }

    private function seedLetterActions(PDO $pdo): void
    {
        // An opening "created" entry per letter so histories are populated.
        $letters = $pdo->query('SELECT id, sender_id, stage, created_at, reply_to_id FROM letters ORDER BY id ASC')->fetchAll();
        $stmt = $pdo->prepare(
            'INSERT INTO letter_actions (letter_id, user_id, action, from_stage, to_stage, note, created_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?)'
        );
        foreach ($letters as $l) {
            $action = $l['reply_to_id'] !== null ? 'replied' : 'created';
            $stmt->execute([$l['id'], $l['sender_id'], $action, $l['stage'], '', $l['created_at']]);
        }
    }

    private function seedWorkflows(PDO $pdo): void
    {
        $steps = [
            ['label' => 'ثبت پیش‌نویس', 'role_id' => 8],   // کارشناس حسابداری (t5)
            ['label' => 'تأیید رئیس', 'role_id' => 5],      // رئیس حسابداری (t3)
            ['label' => 'تأیید مدیر واحد', 'role_id' => 2], // مدیر مالی (t2)
            ['label' => 'امضا و تأیید نهایی', 'role_id' => 1], // مدیر عامل (t1)
            ['label' => 'بایگانی', 'role_id' => null],       // دبیرخانه / سیستم
        ];
        $stmt = $pdo->prepare('INSERT INTO workflows (name, department_id, steps, created_by) VALUES (?, ?, ?, ?)');
        $stmt->execute(['گردش کار تأیید صورت‌وضعیت و فاکتور خرید', 2, json_encode($steps, JSON_UNESCAPED_UNICODE), 2]);
    }

    private function seedTemplates(PDO $pdo): void
    {
        $header = '<p style="margin:16px 0 10px">با سلام و احترام؛</p>';
        $rows = [
            ['نامهٔ اداری استاندارد (A4)', 'a4',
                $header . '<p style="text-align:justify">احتراماً، به استحضار می‌رساند …</p><p style="text-align:justify">خواهشمند است دستور فرمایید نسبت به موضوع فوق اقدام لازم صورت پذیرد.</p>'],
            ['یادداشت داخلی (A5)', 'a5',
                $header . '<p style="text-align:justify">پیرو هماهنگی به‌عمل‌آمده، موارد زیر جهت اطلاع و اقدام ابلاغ می‌گردد:</p>'],
            ['درخواست خرید مصالح کارگاه', 'a4',
                $header . '<p style="text-align:justify">با توجه به برنامهٔ اجرایی کارگاه، خواهشمند است نسبت به تأمین اقلام مشروحهٔ ذیل اقدام فرمایید.</p>'],
            ['نامهٔ ارسال صورت‌وضعیت به کارفرما', 'a4',
                $header . '<p style="text-align:justify">بدین‌وسیله صورت‌وضعیت شمارهٔ … مربوط به دورهٔ … به همراه ریز متره و مستندات پیوست، جهت رسیدگی و پرداخت تقدیم می‌گردد.</p>'],
        ];
        $stmt = $pdo->prepare('INSERT INTO templates (title, body, paper_size, department_id, created_by) VALUES (?, ?, ?, ?, ?)');
        foreach ($rows as $r) {
            $stmt->execute([$r[0], $r[2], $r[1], 1, 1]);
        }
    }

    private function seedEvents(PDO $pdo): void
    {
        $now = date('Y-m-d H:i:s');
        $rows = [
            ['مجمع عمومی سالانهٔ سهامداران', 'ارائهٔ گزارش عملکرد و تصویب صورت‌های مالی سال گذشته.', '۱۴۰۵/۰۵/۱۵', 'سالن همایش مرکزی'],
            ['دورهٔ آموزشی ایمنی و بهداشت کار (HSE)', 'دورهٔ اجباری برای همهٔ کارکنان کارگاه‌ها و سرپرستان اجرایی.', '۱۴۰۵/۰۴/۲۸', 'واحد آموزش'],
            ['بازدید کارفرما از پیشرفت فیزیکی پروژه', 'بازدید دوره‌ای از کارگاه و بررسی انطباق اجرا با نقشه‌های کارگاهی.', '۱۴۰۵/۰۵/۰۳', 'پروژهٔ مسکونی — فاز ۲'],
        ];
        $stmt = $pdo->prepare('INSERT INTO events (title, description, event_date, location, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($rows as $r) {
            $stmt->execute([$r[0], $r[1], $r[2], $r[3], 1, $now]);
        }
    }

    /**
     * The logo library, pre-filled with the group's bundled marks (the files
     * shipped in public/uploads/logos). The group logo is assigned to every
     * slot; the admin can re-point any slot at a member company's logo from
     * «تنظیمات» without touching the code.
     */
    private function seedLogos(PDO $pdo): void
    {
        $now = date('Y-m-d H:i:s');
        $rows = [
            ['گروه متحد 7', 'group-motahed-7.jpg'],
            ['خاوران یول ایرانیان', 'khavaran-yol-iranian.jpg'],
            ['هفت پی مقاوم ایرانیان', 'haft-pey-moghavem-iranian.jpg'],
        ];
        $dir = __DIR__ . '/../../../public/uploads/logos';
        $stmt = $pdo->prepare('INSERT INTO org_logos (title, path, mime, size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $groupLogoId = null;
        foreach ($rows as [$title, $file]) {
            $full = $dir . '/' . $file;
            // Skip a mark whose file was removed from the deployment rather
            // than seeding a row that resolves to a broken image.
            if (!is_file($full)) {
                continue;
            }
            $stmt->execute([$title, $file, 'image/jpeg', (int) filesize($full), 1, $now]);
            $groupLogoId ??= (int) $pdo->lastInsertId();
        }

        if ($groupLogoId === null) {
            return;
        }
        $set = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
        // Letterhead and sidebar sit on white, so the colour mark fits there.
        // The login screen is dark navy: left unset, it falls back to the
        // bundled white version of the same mark, which reads properly.
        $set->execute([OrgIdentity::KEY_LOGO_LETTERHEAD, (string) $groupLogoId]);
        $set->execute([OrgIdentity::KEY_LOGO_SIDEBAR, (string) $groupLogoId]);
        $set->execute([OrgIdentity::KEY_LOGO_LOGIN, '']);
        $set->execute([OrgIdentity::KEY_NAME, OrgIdentity::DEFAULT_NAME]);
        $set->execute([OrgIdentity::KEY_NAME_LATIN, OrgIdentity::DEFAULT_NAME_LATIN]);
        $set->execute([OrgIdentity::KEY_TAGLINE, 'شرکت‌های عمرانی و صنعتی عضو گروه متحد ۷']);
    }
}
