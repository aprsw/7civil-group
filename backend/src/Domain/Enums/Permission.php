<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Enums;

/**
 * Fine-grained permissions granted to roles (RBAC). Kept as an enum so the
 * whole permission surface is discoverable and type-safe.
 */
enum Permission: string
{
    case ViewDashboard = 'dashboard.view';

    case LettersView = 'letters.view';
    case LettersCreate = 'letters.create';
    case LettersApprove = 'letters.approve';
    case LettersSign = 'letters.sign';
    case LettersSend = 'letters.send';
    case LettersArchive = 'letters.archive';

    case WorkflowView = 'workflow.view';
    case WorkflowManage = 'workflow.manage';

    case TemplatesView = 'templates.view';
    case TemplatesManage = 'templates.manage';

    case UsersView = 'users.view';
    case UsersManage = 'users.manage'; // create / edit / delete staff

    case DepartmentsView = 'departments.view'; // executive-only, read-only
    case DepartmentsManage = 'departments.manage'; // create / edit / delete units (senior admin)
    case DocumentsView = 'documents.view'; // view staff personal documents (e.g. HR)
    case SettingsManage = 'settings.manage'; // org-wide settings (numbering, …)
    case EventsManage = 'events.manage'; // create/edit org-wide events
    case RolesManage = 'roles.manage'; // define/edit/delete roles & their permissions
    case MessagingManage = 'messaging.manage'; // configure per-unit messaging permissions
    case ChatUse = 'chat.use'; // access the staff chat (گفتگو)
    case SecretariatUse = 'secretariat.use'; // register/route official letters (دبیرخانه)
    case SecretariatManage = 'secretariat.manage'; // define the secretariat's folders/sections (admin)

    public function label(): string
    {
        return match ($this) {
            self::ViewDashboard => 'مشاهده داشبورد',
            self::LettersView => 'مشاهده نامه‌ها',
            self::LettersCreate => 'ایجاد نامه',
            self::LettersApprove => 'تأیید نامه',
            self::LettersSign => 'امضای نامه',
            self::LettersSend => 'ارسال نامه',
            self::LettersArchive => 'بایگانی نامه',
            self::WorkflowView => 'مشاهده گردش کار',
            self::WorkflowManage => 'مدیریت گردش کار',
            self::TemplatesView => 'مشاهده قالب‌ها',
            self::TemplatesManage => 'مدیریت قالب‌ها',
            self::UsersView => 'مشاهده کارکنان',
            self::UsersManage => 'مدیریت کارکنان',
            self::DepartmentsView => 'مشاهده واحدها',
            self::DepartmentsManage => 'مدیریت واحدها',
            self::DocumentsView => 'مشاهده مدارک کارکنان',
            self::SettingsManage => 'مدیریت تنظیمات سامانه',
            self::EventsManage => 'مدیریت رویدادها',
            self::RolesManage => 'مدیریت نقش‌ها و دسترسی‌ها',
            self::MessagingManage => 'مدیریت مجوزهای مکاتبه',
            self::ChatUse => 'استفاده از گفتگو',
            self::SecretariatUse => 'دبیرخانه (ثبت و ارجاع نامه)',
            self::SecretariatManage => 'مدیریت بخش‌بندی دبیرخانه (پوشه‌ها)',
        };
    }
}
