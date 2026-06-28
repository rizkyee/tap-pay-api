# Panduan Testing TapPay API dengan Postman

Dokumen ini menjelaskan cara menguji seluruh endpoint TapPay (selain Auth & Wallet yang sudah ada) memakai koleksi Postman `TapPay.postman_collection.json`.

## 1. Persiapan

### a. Jalankan migrasi & seeder

```bash
php artisan migrate
php artisan db:seed --class=UserSeeder
```

Seeder membuat akun demo berikut (password semua: `password`, PIN transaksi: `123456`):

| Role     | Email                     | Phone          | Saldo awal |
| -------- | ------------------------- | -------------- | ---------- |
| Customer | customer@tappay.test      | 082222222222   | 500.000    |
| Customer | customer.b@tappay.test    | 082333333333   | 100.000    |
| Merchant | merchant@tappay.test      | 083333333333   | 0          |
| Admin    | admin@tappay.test         | 081111111111   | 0          |

### b. Jalankan server

```bash
php artisan serve
```

Server default di `http://127.0.0.1:8000`.

### c. Import koleksi

1. Buka Postman → **Import** → pilih `postman/TapPay.postman_collection.json`.
2. Koleksi sudah punya **collection variables** (`base_url`, `token_a`, `token_b`, `token_merchant`, `nfc_token`, dll). Token login otomatis tersimpan lewat test script, jadi tidak perlu copy-paste token manual.
3. Jika port berbeda, ubah variable `base_url` (klik koleksi → tab **Variables**).

## 2. Konsep Penting

- **Authentication**: Bearer token (Laravel Sanctum). Header `Authorization: Bearer <token>`.
- **Header tambahan** (sesuai dokumentasi Open Payment):
  - `Idempotency-Key`: unik per transaksi, mencegah double transaction.
  - `X-Device-ID`: id device untuk audit & approval.
- **Format response** selalu konsisten:

```json
{ "success": true, "message": "...", "data": { ... } }
```

- **Error code** (section 13 dokumentasi) muncul di `errors.code`, contoh: `WALLET_INSUFFICIENT_BALANCE`, `NFC_TOKEN_EXPIRED`, `ROLE_NOT_ALLOWED`, `INVALID_PIN`, `DUPLICATE_REQUEST`.

## 3. Urutan Testing (Happy Path)

Jalankan request berikut **berurutan**. Variabel akan terisi otomatis.

### Langkah 1 — Login semua aktor (folder **Auth**)
1. `Login Customer A` → menyimpan `token_a` (+ `token`).
2. `Login Customer B` → menyimpan `token_b`.
3. `Login Merchant` → menyimpan `token_merchant`.

### Langkah 2 — Merchant Top Up ke Customer A (folder **Merchant**)
- `Top Up Customer` (amount 100.000). Respon `status: completed`, `new_balance` bertambah, dan `receipt_url` tersedia.
- Cek `Top Up History` & `Top Up Recent`.

> Coba kirim ulang request yang sama **dengan Idempotency-Key sama** untuk membuktikan transaksi tidak dobel (mengembalikan transaksi yang sama).

### Langkah 3 — Receiver buka NFC (folder **NFC**)
- Login dulu Customer B (Langkah 1). Jalankan `Create Receive Session` → menyimpan `nfc_token`.
- Token berlaku 5 menit dan **single-use**.

### Langkah 4 — Transfer C2C via Open Payment (folder **Open Payment**)
1. `1. Initiate (Customer A -> token B)` (amount 20.000). Status `pending`, menyimpan `transaction_id` & `transaction_code`.
2. (Opsional) cek `Transfers → Pending Incoming (B)`.
3. `2. Approve (Customer B + PIN)` dengan PIN `123456`. Status menjadi `completed` dan saldo berpindah secara atomic.

### Langkah 5 — Verifikasi hasil
- `Open Payment → Balance Inquiry` (pakai token A / B).
- `Wallet → Ledger` untuk melihat catatan debit/credit.
- `Transactions → Detail / Receipt / Verify`.
- `Statements → Generate` lalu `Detail` / `Download`.

## 4. Skenario Negatif yang Disarankan

| Skenario                              | Cara                                                                 | Hasil yang diharapkan                       |
| ------------------------------------- | ------------------------------------------------------------------- | ------------------------------------------- |
| Saldo tidak cukup                     | Initiate transfer > saldo pengirim                                  | `WALLET_INSUFFICIENT_BALANCE` (422)         |
| PIN salah                             | Approve dengan `pin` selain `123456`                               | `INVALID_PIN` (422)                         |
| Token NFC dipakai 2x                  | Initiate lagi dengan `nfc_token` yang sama setelah terpakai         | `NFC_TOKEN_EXPIRED` (422)                   |
| Transfer ke diri sendiri              | Customer A pakai token NFC milik Customer A                         | Validasi gagal (422)                        |
| Role tidak sesuai                     | Customer akses `GET /merchant/summary`                             | `ROLE_NOT_ALLOWED` (403)                    |
| Idempotency dobel                     | Top up / initiate dengan `Idempotency-Key` sama                    | Mengembalikan transaksi yang sama (no dobel)|
| Approval kedaluwarsa                  | Tunggu > 5 menit lalu approve                                       | `TRANSFER_EXPIRED` (422)                    |

## 5. Daftar Endpoint

```
AUTH        POST   /api/auth/register, /api/auth/login, /api/auth/logout, /api/auth/me
PROFILE     GET    /api/profile | PATCH /api/profile
            POST   /api/profile/change-password, /api/profile/set-pin, /api/profile/logout-all-devices
            GET    /api/profile/devices
WALLET      GET    /api/wallet, /api/wallet/balance, /api/wallet/history, /api/wallet/ledger
NFC         POST   /api/nfc/sessions, /api/nfc/sessions/{id}/refresh, /api/nfc/sessions/{id}/cancel
            GET    /api/nfc/sessions/{token}
TRANSFERS   POST   /api/transfers/nfc, /api/transfers/{id}/approve, /api/transfers/{id}/reject, /api/transfers/{id}/cancel
            GET    /api/transfers/pending-incoming, /api/transfers/{id}
MERCHANT    GET    /api/merchant/summary, /api/merchant/limits, /api/merchant/topups, /api/merchant/topups/recent
            POST   /api/merchant/topups
CUSTOMER    GET    /api/customers/search?phone=...
TRANSACTION GET    /api/transactions, /api/transactions/recent, /api/transactions/{id}
            GET    /api/transactions/{id}/receipt, /receipt-image, /print, /verify
STATEMENT   POST   /api/statements | GET /api/statements, /api/statements/{id}, /api/statements/{id}/download
OPEN PAY    POST   /api/open-payment/initiate, /approve, /reject, /statements
            GET    /api/open-payment/balance, /api/open-payment/status/{code}, /api/open-payment/receipt/{code}
```

## 6. Catatan

- **Receipt PDF/Image**: endpoint `/receipt` & `/receipt-image` mengembalikan data terstruktur + `receipt_hash`; `/print` mengembalikan halaman HTML siap cetak (browser → Print to PDF). Untuk PDF asli bisa ditambah paket `barryvdh/laravel-dompdf` lalu render view yang sama.
- **Statement opening balance** dihitung dari ledger. Saldo awal hasil seeder tidak masuk ledger, jadi `opening_balance` statement bisa 0 untuk periode pertama (semua mutasi tetap akurat).
- Semua perpindahan saldo bersifat **atomic** (`DB::transaction`) dengan pencatatan ledger debit/credit dan **audit log** otomatis.
