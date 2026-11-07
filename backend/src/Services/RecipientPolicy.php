<?php

declare(strict_types=1);

namespace ArtaPharma\Services;

use ArtaPharma\Domain\Entities\User;
use ArtaPharma\Domain\Repositories\MessagingRuleRepositoryInterface;

/**
 * Messaging permissions. By default the org "pyramid" (item 12):
 *
 *  • مدیر عامل (tier 1) و مدیر (tier 2): می‌توانند به همه نامه بزنند.
 *  • هر کسی می‌تواند به زیردستان (tier بزرگ‌تر) در هر واحدی نامه بزند.
 *  • هر کسی می‌تواند به هم‌ردیفان (tier برابر) در هر واحدی نامه بزند.
 *  • به بالادست فقط درونِ واحد خود و تا یک سطحِ محدود.
 *
 * When the senior admin has configured explicit per-unit rules (item 2), the
 * allow-list for the sender's unit REPLACES the hierarchy for that unit — the
 * sender may only address units in its allow-list (plus its own unit). The CEO
 * (tier 1) is never restricted, so the org can't lock itself out.
 */
final class RecipientPolicy
{
    /** @var array<int,list<int>>|null cached matrix for the request */
    private ?array $rules = null;

    public function __construct(
        private readonly ?MessagingRuleRepositoryInterface $messagingRules = null,
    ) {
    }

    public function canMessage(User $sender, User $target): bool
    {
        if ($sender->id === $target->id || !$target->active) {
            return false;
        }

        $s = $sender->tier();
        $t = $target->tier();

        if ($s <= 1) {
            return true; // مدیرعامل → همیشه همه (هرگز محدود نمی‌شود)
        }

        // «مجوزهای مکاتبه» (سمت به سمت‌ها) تنها منبعِ کنترل است:
        //  • اگر برای سمتِ فرستنده فهرست تعریف شده باشد → فقط به همان سمت‌ها
        //    (به‌علاوهٔ سمتِ خودش).
        //  • اگر برای سمتِ فرستنده چیزی تعریف نشده باشد → محدودیتی نیست (به همه).
        // بدین ترتیب فهرست گیرندگان دقیقاً بازتابِ تنظیماتِ ادمین است.
        $roleMatrix = $this->roleMatrix();
        if ($sender->roleId !== null && isset($roleMatrix[$sender->roleId])) {
            $allowedRoles = array_merge([$sender->roleId], $roleMatrix[$sender->roleId]);

            return $target->roleId !== null && in_array($target->roleId, $allowedRoles, true);
        }

        return true;
    }

    /**
     * May the sender address a whole unit? When the sender's unit has an
     * explicit allow-list it is honoured (own unit always allowed); otherwise
     * managers (tier ≤ 2) may address any unit and everyone may address their
     * own unit.
     */
    public function canMessageDepartment(User $sender, int $departmentId): bool
    {
        if ($sender->tier() <= 1) {
            return true; // مدیرعامل
        }
        $matrix = $this->matrix();
        if (isset($matrix[$sender->departmentId])) {
            return in_array($departmentId, array_merge([$sender->departmentId], $matrix[$sender->departmentId]), true);
        }

        return $sender->tier() <= 2 || $departmentId === $sender->departmentId;
    }

    /**
     * @param list<\ArtaPharma\Domain\Entities\Department> $departments
     * @return list<\ArtaPharma\Domain\Entities\Department>
     */
    public function allowedDepartments(User $sender, array $departments): array
    {
        return array_values(array_filter($departments, fn ($d) => $this->canMessageDepartment($sender, $d->id)));
    }

    /** @return array<int,list<int>> */
    private function matrix(): array
    {
        if ($this->rules === null) {
            $this->rules = $this->messagingRules?->matrix() ?? [];
        }

        return $this->rules;
    }

    /** @var array<int,list<int>>|null */
    private ?array $roleRules = null;

    /** @return array<int,list<int>> */
    private function roleMatrix(): array
    {
        if ($this->roleRules === null) {
            $this->roleRules = $this->messagingRules?->roleMatrix() ?? [];
        }

        return $this->roleRules;
    }

    /**
     * @param list<User> $all
     * @return list<User> the people $sender is allowed to address
     */
    public function allowedRecipients(User $sender, array $all): array
    {
        return array_values(array_filter($all, fn (User $u) => $this->canMessage($sender, $u)));
    }
}
