<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Controllers;

use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;
use ArtaPharma\Domain\Enums\Confidentiality;
use ArtaPharma\Domain\Enums\LetterPriority;
use ArtaPharma\Domain\Enums\LetterScope;
use ArtaPharma\Domain\Enums\ReferralType;
use ArtaPharma\Domain\Repositories\DepartmentRepositoryInterface;
use ArtaPharma\Domain\Repositories\UserRepositoryInterface;
use ArtaPharma\Domain\Repositories\WorkflowRepositoryInterface;
use ArtaPharma\Services\RecipientPolicy;

/**
 * Supplies the options the compose form needs: recipients (people + units),
 * selectable workflows, and the scope/confidentiality/priority enumerations.
 * Available to anyone authenticated (needed to address a letter).
 */
final class MetaController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly DepartmentRepositoryInterface $departments,
        private readonly WorkflowRepositoryInterface $workflows,
        private readonly RecipientPolicy $policy,
    ) {
    }

    /** GET /api/meta/compose */
    public function compose(Request $request): Response
    {
        $me = $this->user($request);

        // Recipients are filtered by the org-hierarchy messaging rules (item 12),
        // then ordered by family name (نام خانوادگی) for an easy-to-scan picker.
        $allowed = $this->policy->allowedRecipients($me, $this->users->all());
        $surname = static function (string $name): string {
            $parts = preg_split('/\s+/', trim($name)) ?: [];
            return $parts !== [] ? (string) end($parts) : $name;
        };
        usort($allowed, static fn ($a, $b) => $surname($a->name) <=> $surname($b->name));
        $recipients = array_values(array_map(static fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'role' => $u->role?->name,
            'tier' => $u->tier(),
            'department_id' => $u->departmentId,
            'department' => $u->department?->name,
            'photo_url' => $u->photoPath !== null && $u->photoPath !== '' ? '/uploads/photos/' . $u->photoPath : null,
        ], $allowed));

        // Every active user — used by the workflow approver-chain picker, which
        // is NOT limited by the messaging permissions.
        $allUsers = array_values(array_map(static fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'role' => $u->role?->name,
            'department' => $u->department?->name,
            'photo_url' => $u->photoPath !== null && $u->photoPath !== '' ? '/uploads/photos/' . $u->photoPath : null,
        ], array_filter($this->users->all(), static fn ($u) => $u->active && $u->id !== $me->id)));

        // Units the sender is allowed to address (honours the messaging matrix).
        $allowedDepts = $this->policy->allowedDepartments($me, $this->departments->all());
        $departments = array_map(static fn ($d) => ['id' => $d->id, 'name' => $d->name], $allowedDepts);
        $userName = function (int $uid): ?string {
            $u = $this->users->find($uid);
            return $u?->name;
        };
        $workflows = array_map(fn ($w) => [
            'id' => $w->id,
            'name' => $w->name,
            'steps' => count($w->steps),
            // Admin-defined fixed chain (empty ⇒ the sender defines the chain).
            'approver_ids' => $w->approverIds,
            'approver_names' => array_values(array_filter(array_map($userName, $w->approverIds))),
            'predefined' => $w->approverIds !== [],
        ], $this->workflows->all());

        $opts = static fn (array $cases) => array_map(static fn ($c) => ['value' => $c->value, 'label' => $c->label()], $cases);

        return Response::ok([
            'recipients' => $recipients,
            'all_users' => $allUsers,
            'departments' => $departments,
            'workflows' => $workflows,
            'scopes' => $opts(LetterScope::cases()),
            'confidentialities' => $opts(Confidentiality::cases()),
            'priorities' => $opts(LetterPriority::cases()),
            'referral_types' => array_map(
                static fn (ReferralType $t) => ['value' => $t->value, 'label' => $t->label(), 'tone' => $t->tone()],
                // «پاسخ» is issued via the reply flow, not the referral-type picker.
                array_values(array_filter(ReferralType::cases(), static fn (ReferralType $t) => $t !== ReferralType::Reply)),
            ),
        ]);
    }
}
