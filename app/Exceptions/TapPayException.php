<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception domain TapPay yang membawa error code spesifik sesuai dokumentasi
 * (section 13 Error Handling), sehingga frontend bisa menampilkan pesan tepat.
 */
class TapPayException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode = 422,
    ) {
        parent::__construct($message);
    }

    public static function authInvalid(string $message = 'Email, phone, atau password tidak sesuai.'): self
    {
        return new self('AUTH_INVALID', $message, 401);
    }

    public static function roleNotAllowed(string $message = 'Akun Anda tidak memiliki akses ke fitur ini.'): self
    {
        return new self('ROLE_NOT_ALLOWED', $message, 403);
    }

    public static function insufficientBalance(string $message = 'Saldo Anda tidak cukup untuk melakukan transaksi ini.'): self
    {
        return new self('WALLET_INSUFFICIENT_BALANCE', $message, 422);
    }

    public static function nfcTokenExpired(string $message = 'Sesi NFC sudah berakhir. Silakan scan ulang.'): self
    {
        return new self('NFC_TOKEN_EXPIRED', $message, 422);
    }

    public static function transferRejected(string $message = 'Penerima menolak transaksi.'): self
    {
        return new self('TRANSFER_REJECTED', $message, 422);
    }

    public static function transferExpired(string $message = 'Transaksi berakhir karena tidak disetujui tepat waktu.'): self
    {
        return new self('TRANSFER_EXPIRED', $message, 422);
    }

    public static function duplicateRequest(string $message = 'Transaksi sedang diproses. Jangan tekan tombol berulang.'): self
    {
        return new self('DUPLICATE_REQUEST', $message, 409);
    }

    public static function notFound(string $message = 'Data tidak ditemukan.'): self
    {
        return new self('NOT_FOUND', $message, 404);
    }

    public static function invalidPin(string $message = 'PIN transaksi tidak valid.'): self
    {
        return new self('INVALID_PIN', $message, 422);
    }

    public static function validation(string $message): self
    {
        return new self('VALIDATION_ERROR', $message, 422);
    }
}
