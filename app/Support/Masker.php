<?php

namespace App\Support;

/**
 * Helper untuk masking data sensitif sebelum dikirim ke client
 * (lihat dokumentasi section 12 Data Security).
 */
class Masker
{
    public static function phone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $length = strlen($phone);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($phone, 0, 4) . str_repeat('*', max(0, $length - 8)) . substr($phone, -4);
    }

    public static function walletCode(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return substr($code, 0, 4) . str_repeat('*', max(0, strlen($code) - 8)) . substr($code, -4);
    }
}
