<?php

namespace App\Services\Auth;

use App\Support\PlainText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleIdentityVerifier
{
    public function verify(string $token, string $nonce): array
    {
        abort_unless(filled(config('prd.google_client_id')), 503, 'Google login belum dikonfigurasi.');
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3 || strlen($token) > 8192) {
                throw new \RuntimeException;
            }
            $decode = function ($s) {
                $decoded = base64_decode(strtr($s, '-_', '+/'), true);
                if ($decoded === false) {
                    throw new \RuntimeException;
                }

                return $decoded;
            };
            $header = json_decode($decode($parts[0]), true, 16, JSON_THROW_ON_ERROR);
            $claims = json_decode($decode($parts[1]), true, 16, JSON_THROW_ON_ERROR);
            if (($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null)) {
                throw new \RuntimeException;
            }
            $certs = Cache::remember('google-id-certs-v1', 300, fn () => Http::connectTimeout(5)->timeout(10)->get('https://www.googleapis.com/oauth2/v1/certs')->throw()->json());
            $cert = $certs[$header['kid']] ?? null;
            if (! is_string($cert)) {
                Cache::forget('google-id-certs-v1');
                throw new \RuntimeException;
            }
            if (openssl_verify($parts[0].'.'.$parts[1], $decode($parts[2]), $cert, OPENSSL_ALGO_SHA256) !== 1) {
                throw new \RuntimeException;
            }
            $now = now()->timestamp;
            if (! in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
                || ($claims['aud'] ?? null) !== config('prd.google_client_id') || (isset($claims['azp']) && $claims['azp'] !== config('prd.google_client_id'))
                || ! is_numeric($claims['exp'] ?? null) || $claims['exp'] <= $now || ! is_numeric($claims['iat'] ?? null) || $claims['iat'] > $now + 60
                || (isset($claims['nbf']) && $claims['nbf'] > $now + 60) || ! hash_equals($nonce, (string) ($claims['nonce'] ?? ''))
                || ($claims['email_verified'] ?? false) !== true || ! filter_var($claims['email'] ?? '', FILTER_VALIDATE_EMAIL)
                || ! is_string($claims['sub'] ?? null) || strlen($claims['sub']) > 255 || $claims['sub'] === '') {
                throw new \RuntimeException;
            }

            $name = mb_substr(strip_tags((string) ($claims['name'] ?? '')), 0, 255);
            if (! PlainText::safe($name)) {
                $name = '';
            }

            return ['authoritative' => str_ends_with(mb_strtolower($claims['email']), '@gmail.com') || (is_string($claims['hd'] ?? null) && $claims['hd'] !== ''), 'sub' => $claims['sub'], 'email' => mb_strtolower($claims['email']), 'name' => $name];
        } catch (\Throwable) {
            abort(422, 'Identitas Google tidak valid atau belum dapat diverifikasi.');
        }
    }
}
