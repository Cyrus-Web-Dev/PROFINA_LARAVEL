<?php

namespace Profina\Sso;

/**
 * Token & tanda tangan PROFINA. HMAC-SHA256, tanpa ketergantungan luar.
 */
class Token
{
    public static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function unb64(string $text): string|false
    {
        return base64_decode(strtr($text, '-_', '+/'), true);
    }

    /** Kunci per-modul diturunkan dari kunci induk milik Portal. */
    public static function deriveSecret(string $master, string $module): string
    {
        return hash_hmac('sha256', 'profina-module:'.$module, $master);
    }

    public static function sign(array $claims, string $secret): string
    {
        $body = self::b64(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $body.'.'.self::b64(hash_hmac('sha256', $body, $secret, true));
    }

    /** @return array<string,mixed>|null null bila tanda tangan/format salah */
    public static function verify(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $secret === '') {
            return null;
        }
        [$body, $sig] = $parts;

        if (! hash_equals(self::b64(hash_hmac('sha256', $body, $secret, true)), $sig)) {
            return null;
        }

        $json = self::unb64($body);
        $claims = $json === false ? null : json_decode($json, true);

        return is_array($claims) ? $claims : null;
    }

    /** Tanda tangan untuk request server-ke-server (introspeksi & health check). */
    public static function requestSignature(string $secret, string $module, string $timestamp, string $payload = ''): string
    {
        return hash_hmac('sha256', $module.'|'.$timestamp.'|'.$payload, $secret);
    }
}
