<?php

namespace Tests\Feature;

use App\Models\NfcSession;
use App\Models\Transaction;
use App\Models\TransactionApproval;
use App\Models\User;
use App\Models\Wallet;
use App\Services\TransactionService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regresi: pada sebagian konfigurasi PDO/MySQL (mis. shared hosting dengan
 * emulate prepares), kolom foreign key dikembalikan sebagai string. Perbandingan
 * ketat (=== / !==) lalu gagal sehingga:
 *  - penerima ditolak saat approve ("Anda bukan penerima transaksi ini"),
 *  - detail transaksi kosong padahal riwayat tetap muncul.
 *
 * Test ini memastikan kode tahan terhadap tipe string tersebut.
 */
class TransferTypeSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function makeWalletUser(float $balance = 100000, ?string $pin = null): User
    {
        $user = User::factory()->create($pin ? ['transaction_pin' => $pin] : []);

        Wallet::create([
            'user_id' => $user->id,
            'wallet_code' => Wallet::generateWalletCode(),
            'balance' => $balance,
            'currency' => 'TPP',
            'status' => Wallet::STATUS_ACTIVE,
        ]);

        return $user->refresh();
    }

    public function test_foreign_keys_are_cast_to_integer(): void
    {
        $trx = new Transaction();
        $trx->setRawAttributes([
            'wallet_id' => '2',
            'sender_wallet_id' => '2',
            'receiver_wallet_id' => '3',
            'merchant_id' => '4',
            'user_id' => '2',
        ], true);

        $this->assertSame(2, $trx->wallet_id);
        $this->assertSame(2, $trx->sender_wallet_id);
        $this->assertSame(3, $trx->receiver_wallet_id);
        $this->assertSame(4, $trx->merchant_id);
        $this->assertSame(2, $trx->user_id);
    }

    public function test_receiver_can_approve_even_when_fk_returned_as_string(): void
    {
        $sender = $this->makeWalletUser(100000);
        $receiver = $this->makeWalletUser(0, '1234');

        $session = NfcSession::create([
            'user_id' => $receiver->id,
            'wallet_id' => $receiver->wallet->id,
            'token' => NfcSession::generateToken(),
            'mode' => NfcSession::MODE_RECEIVE,
            'status' => NfcSession::STATUS_ACTIVE,
            'expires_at' => now()->addMinutes(5),
        ]);

        /** @var TransferService $transfers */
        $transfers = app(TransferService::class);

        $trx = $transfers->initiate($sender, $session->token, 25000, null, null, 'dev');

        // Simulasikan driver yang mengembalikan FK sebagai string.
        $stringified = Transaction::find($trx->id);
        $raw = $stringified->getAttributes();
        $raw['receiver_wallet_id'] = (string) $raw['receiver_wallet_id'];
        $raw['sender_wallet_id'] = (string) $raw['sender_wallet_id'];
        $stringified->setRawAttributes($raw, true);

        // Walau di-set string, cast model harus menormalkan ke int lagi.
        $this->assertIsInt($stringified->receiver_wallet_id);

        $completed = $transfers->approve($receiver, $trx->id, '1234', 'dev');

        $this->assertSame(Transaction::STATUS_COMPLETED, $completed->status);
        $this->assertSame(25000.0, (float) $receiver->wallet->fresh()->balance);
    }

    public function test_sender_can_open_transaction_detail(): void
    {
        $sender = $this->makeWalletUser(100000);
        $receiver = $this->makeWalletUser(0);

        $trx = Transaction::create([
            'transaction_code' => Transaction::generateCode(Transaction::TYPE_TRANSFER),
            'user_id' => $sender->id,
            'wallet_id' => $sender->wallet->id,
            'sender_wallet_id' => $sender->wallet->id,
            'receiver_wallet_id' => $receiver->wallet->id,
            'type' => Transaction::TYPE_TRANSFER,
            'amount' => 30000,
            'balance_before' => 100000,
            'balance_after' => 70000,
            'reference' => 'ref-detail-1',
            'status' => Transaction::STATUS_PENDING,
        ]);

        TransactionApproval::create([
            'transaction_id' => $trx->id,
            'receiver_user_id' => $receiver->id,
            'status' => TransactionApproval::STATUS_PENDING,
        ]);

        Sanctum::actingAs($sender);

        $response = $this->getJson("/api/transactions/{$trx->id}");

        $response->assertOk();
        $response->assertJsonPath('data.transaction.id', $trx->id);
    }
}
