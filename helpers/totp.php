<?php
// ============================================================
// NexusGear - TOTP (RFC 6238) — sin dependencias externas
// Funciona con Google Authenticator, Authy, Microsoft Authenticator
// ============================================================

class TOTP {

    // Generar un secreto Base32 aleatorio de 16 caracteres
    public static function generateSecret(): string {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    // Decodificar Base32 → bytes
    public static function base32Decode(string $secret): string {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret));
        $bits   = '';
        foreach (str_split($secret) as $c) {
            $idx = strpos($chars, $c);
            if ($idx !== false) {
                $bits .= str_pad(decbin($idx), 5, '0', STR_PAD_LEFT);
            }
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }
        return $bytes;
    }

    // Calcular código TOTP para un timestamp dado
    public static function getCode(string $secret, int $timestamp = 0, int $period = 30): string {
        if ($timestamp === 0) $timestamp = time();
        $counter = (int)floor($timestamp / $period);

        $key  = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $time, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $code   = (
            ((ord($hash[$offset])   & 0x7F) << 24) |
            ((ord($hash[$offset+1]) & 0xFF) << 16) |
            ((ord($hash[$offset+2]) & 0xFF) << 8)  |
             (ord($hash[$offset+3]) & 0xFF)
        ) % 1000000;

        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    // Verificar código (acepta ±1 ventana = 90 segundos de tolerancia)
    public static function verify(string $secret, string $inputCode, int $window = 1): bool {
        $inputCode = preg_replace('/\D/', '', $inputCode);
        $inputCode = str_pad($inputCode, 6, '0', STR_PAD_LEFT);
        $now       = time();

        for ($i = -$window; $i <= $window; $i++) {
            $expected = self::getCode($secret, $now + ($i * 30));
            if (hash_equals($expected, $inputCode)) {
                return true;
            }
        }
        return false;
    }

    // Generar URL para el código QR (formato otpauth)
    public static function getOtpauthUrl(string $secret, string $email, string $issuer = 'NexusGear'): string {
        $label = rawurlencode("{$issuer}:{$email}");
        return "otpauth://totp/{$label}?"
             . http_build_query([
                 'secret'    => $secret,
                 'issuer'    => $issuer,
                 'algorithm' => 'SHA1',
                 'digits'    => 6,
                 'period'    => 30,
               ]);
    }

    // URL del QR (usa qrserver.com — servicio gratuito, sin API key)
    public static function getQRImageUrl(string $secret, string $email, string $issuer = 'NexusGear'): string {
        $otpauth = self::getOtpauthUrl($secret, $email, $issuer);
        return 'https://api.qrserver.com/v1/create-qr-code/'
             . '?data=' . rawurlencode($otpauth)
             . '&size=220x220'
             . '&color=00-d8-f0'
             . '&bgcolor=11-11-1e'
             . '&margin=10'
             . '&format=svg';
    }
}
