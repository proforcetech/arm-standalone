<?php

declare(strict_types=1);

namespace ARM\Auth;

final class JwtService
{
    private string $secret;
    private int $ttl;

    public function __construct(string $secret, int $ttl = 7200)
    {
        $this->secret = $secret;
        $this->ttl    = $ttl;
    }

    public function encode(array $claims): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now    = time();
        $claims['iat'] = $claims['iat'] ?? $now;
        $claims['exp'] = $claims['exp'] ?? $now + $this->ttl;

        $segments = [
            $this->base64Url(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64Url(json_encode($claims, JSON_UNESCAPED_SLASHES)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $this->secret, true);
        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header64, $payload64, $signature64] = $parts;
        $signature = $this->base64UrlDecode($signature64);
        $expected  = hash_hmac('sha256', $header64 . '.' . $payload64, $this->secret, true);

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payload64), true);
        if (!is_array($payload)) {
            return null;
        }

        if (($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return $payload;
    }

    private function base64Url(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $input): string
    {
        return base64_decode(strtr($input, '-_', '+/')) ?: '';
    }
}
