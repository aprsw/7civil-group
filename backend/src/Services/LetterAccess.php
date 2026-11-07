<?php

declare(strict_types=1);

namespace SevenCivilGroup\Services;

use SevenCivilGroup\Domain\Entities\Letter;
use SevenCivilGroup\Domain\Entities\User;
use SevenCivilGroup\Domain\Enums\Confidentiality;
use SevenCivilGroup\Domain\Enums\LetterScope;
use SevenCivilGroup\Domain\Enums\LetterStatus;
use SevenCivilGroup\Domain\Enums\Permission;
use SevenCivilGroup\Domain\Repositories\LetterRecipientRepositoryInterface;

/**
 * Decides who may read a letter (row-level visibility) — need-to-know.
 *
 * Rules — as an executive would set them:
 *   • Executives (DepartmentsView, i.e. مدیر عامل) have oversight of everything.
 *   • Drafts are private to their author.
 *   • Public internal broadcasts (بخشنامه/ابلاغ عمومی — no specific recipient)
 *     are visible to all staff.
 *   • Every other letter — targeted internal *and* external, confidential or not
 *     — is visible only to its parties (sender, named recipient, recipient unit).
 */
final class LetterAccess
{
    public function __construct(
        private readonly LetterRecipientRepositoryInterface $recipients,
        private readonly ?\SevenCivilGroup\Domain\Repositories\LetterApproverRepositoryInterface $approvers = null,
    ) {
    }

    public function canView(User $user, Letter $letter): bool
    {
        // The sender always sees their own letter.
        if ($letter->senderId === $user->id) {
            return true;
        }

        // Strictly-confidential (سری) letters are visible ONLY to the sender and
        // the explicit addressees — no executive oversight, no broadcast.
        $strict = $letter->confidentiality === Confidentiality::Secret;

        // Executive oversight — except for strictly-confidential letters.
        if (!$strict && ($user->role?->has(Permission::DepartmentsView) ?? false)) {
            return true;
        }

        // Drafts are visible only to their author.
        if ($letter->status === LetterStatus::Draft) {
            return false; // sender handled above
        }

        // A public internal broadcast: no specific recipient at all.
        $isBroadcast = $letter->scope === LetterScope::Internal
            && $letter->recipientId === null
            && $letter->recipientDepartmentId === null
            && $letter->externalRecipient === ''
            && $this->recipients->forLetter($letter->id) === [];
        if (!$strict && $isBroadcast && !$letter->confidentiality->isRestricted()) {
            return true;
        }

        // Otherwise: need-to-know — only the parties involved.
        return $this->isParty($user, $letter);
    }

    private function isParty(User $user, Letter $letter): bool
    {
        if ($letter->senderId === $user->id
            || $letter->recipientId === $user->id
            || ($letter->recipientDepartmentId !== null && $letter->recipientDepartmentId === $user->departmentId)) {
            return true;
        }

        // Multi-recipient + referral membership.
        foreach ($this->recipients->forLetter($letter->id) as $r) {
            if ($r->userId === $user->id) {
                return true;
            }
            if ($r->departmentId !== null && $r->departmentId === $user->departmentId) {
                return true;
            }
            if ($r->referredBy === $user->id) {
                return true; // the person who made the referral keeps visibility
            }
        }

        // Workflow approver-chain members may see the letter they must act on.
        foreach ($this->approvers?->forLetter($letter->id) ?? [] as $a) {
            if ((int) $a['user_id'] === $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Letter> $letters
     * @return list<Letter>
     */
    public function filter(User $user, array $letters): array
    {
        return array_values(array_filter($letters, fn (Letter $l) => $this->canView($user, $l)));
    }
}
