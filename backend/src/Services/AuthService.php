<?php

declare(strict_types=1);

namespace ArtaPharma\Services;

use ArtaPharma\Core\Exceptions\UnauthorizedException;
use ArtaPharma\Core\Exceptions\ValidationException;
use ArtaPharma\Domain\Entities\User;
use ArtaPharma\Domain\Repositories\UserRepositoryInterface;
use ArtaPharma\Security\Contracts\HasherInterface;
use ArtaPharma\Security\Contracts\TokenServiceInterface;

/**
 * Authentication use-cases: credential login (issues a JWT) and resolving the
 * current user from a bearer token.
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly HasherInterface $hasher,
        private readonly TokenServiceInterface $tokens,
    ) {
    }

    /**
     * @return array{token: string, user: User}
     */
    public function login(string $username, string $password): array
    {
        $errors = [];
        if (trim($username) === '') {
            $errors['username'] = 'نام کاربری الزامی است.';
        }
        if ($password === '') {
            $errors['password'] = 'رمز عبور الزامی است.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $user = $this->users->findByUsername($username);
        if ($user === null || !$this->hasher->verify($password, $user->passwordHash)) {
            throw new UnauthorizedException('نام کاربری یا رمز عبور نادرست است.');
        }
        if (!$user->active) {
            throw new UnauthorizedException('حساب کاربری شما غیرفعال است. با مدیر سیستم تماس بگیرید.');
        }

        $token = $this->tokens->issue([
            'sub' => $user->id,
            'username' => $user->username,
            'role' => $user->role?->slug,
        ]);

        return ['token' => $token, 'user' => $user];
    }

    public function userFromToken(string $token): User
    {
        $payload = $this->tokens->verify($token);
        $user = isset($payload['sub']) ? $this->users->find((int) $payload['sub']) : null;

        if ($user === null) {
            throw new UnauthorizedException('کاربر مرتبط با این توکن یافت نشد.');
        }

        return $user;
    }
}
