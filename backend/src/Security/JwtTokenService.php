<?php

declare(strict_types=1);

namespace SevenCivilGroup\Security;

use SevenCivilGroup\Core\Exceptions\UnauthorizedException;
use SevenCivilGroup\Security\Contracts\TokenServiceInterface;

/**
 * Self-contained JWT (HS256) implementation — no firebase/php-jwt dependency.
 * Signs with HMAC-SHA256 and validates signature + `exp`/`nbf` claims.
 */
final class JwtTokenService implements TokenServiceInterface
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttl,
        private readonly string $issuer,
    ) {
    }

    /** @param array<string, mixed> $claims */
    public function issue(array $claims): string
    {
        $now = time();
        $payload = array_merge($claims, [
            'iss' => $this->issuer,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->ttl,
        ]);

        $header = $this->base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body = $this->base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature = $this->sign("{$header}.{$body}");

        return "{$header}.{$body}.{$signature}";
    }

    /** @return array<string, mixed> */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new UnauthorizedException('توکن نامعتبر است.');
        }
        [$header, $body, $signature] = $parts;

        $expected = $this->sign("{$header}.{$body}");
        if (!hash_equals($expected, $signature)) {
            throw new UnauthorizedException('امضای توکن معتبر نیست.');
        }

        $payload = json_decode($this->base64UrlDecode($body), true);
        if (!is_array($payload)) {
            throw new UnauthorizedException('محتوای توکن نامعتبر است.');
        }

        $now = time();
        if (isset($payload['nbf']) && $now < (int) $payload['nbf']) {
            throw new UnauthorizedException('توکن هنوز فعال نشده است.');
        }
        if (isset($payload['exp']) && $now >= (int) $payload['exp']) {
            throw new UnauthorizedException('نشست شما منقضی شده است. دوباره وارد شوید.');
        }

        return $payload;
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
