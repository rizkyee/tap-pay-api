<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletHistoryDirectionTest extends TestCase
{
    use RefreshDatabase;

    private function makeWalletUser(float $balance = 100000): User
    {
        $user = User::factory()->create();

        Wallet::create([
            'user_id' => $user->id,
            'wallet_code' => Wallet::generateWalletCode(),
            'balance' => $balance,
            'currency' => 'TPP',
            'status' => Wallet::STATUS_ACTIVE,
        ]);

        return $user->refresh();
    }

    /**
     * Ambil direction per type dari payload history.
     */
    private function directionsByType(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['type']] = $row['direction'];
        }

        return $map;
    }

    public function test_history_returns_correct_direction_for_sender(): void
    {
        $sender = $this->makeWalletUser();
        $receiver = $this->makeWalletUser();

        Transaction::create([
            'user_id' => $sender->id,
            'wallet_id' => $sender->wallet->id,
            'type' => Transaction::TYPE_TOPUP,
            'amount' => 50000,
            'balance_before' => 100000,
            'balance_after' => 150000,
            'reference' => 'ref-topup-1',
            'status' => Transaction::STATUS_COMPLETED,
        ]);

        Transaction::create([
            'user_id' => $sender->id,
            'wallet_id' => $sender->wallet->id,
            'type' => Transaction::TYPE_WITHDRAW,
            'amount' => 20000,
            'balance_before' => 150000,
            'balance_after' => 130000,
            'reference' => 'ref-withdraw-1',
            'status' => Transaction::STATUS_COMPLETED,
        ]);

        Transaction::create([
            'user_id' => $sender->id,
            'wallet_id' => $sender->wallet->id,
            'sender_wallet_id' => $sender->wallet->id,
            'receiver_wallet_id' => $receiver->wallet->id,
            'type' => Transaction::TYPE_TRANSFER,
            'amount' => 30000,
            'balance_before' => 130000,
            'balance_after' => 100000,
            'reference' => 'ref-transfer-1',
            'status' => Transaction::STATUS_COMPLETED,
        ]);

        Sanctum::actingAs($sender);

        $response = $this->getJson('/api/wallet/history');

        $response->assertOk();
        $rows = $response->json('data.data');
        $this->assertCount(3, $rows);

        $directions = $this->directionsByType($rows);
        $this->assertSame('in', $directions[Transaction::TYPE_TOPUP]);
        $this->assertSame('out', $directions[Transaction::TYPE_WITHDRAW]);
        $this->assertSame('out', $directions[Transaction::TYPE_TRANSFER]);
    }

    public function test_history_shows_incoming_transfer_with_in_direction_for_receiver(): void
    {
        $sender = $this->makeWalletUser();
        $receiver = $this->makeWalletUser();

        Transaction::create([
            'user_id' => $sender->id,
            'wallet_id' => $sender->wallet->id,
            'sender_wallet_id' => $sender->wallet->id,
            'receiver_wallet_id' => $receiver->wallet->id,
            'type' => Transaction::TYPE_TRANSFER,
            'amount' => 30000,
            'balance_before' => 100000,
            'balance_after' => 70000,
            'reference' => 'ref-transfer-2',
            'status' => Transaction::STATUS_COMPLETED,
            'approved_at' => now(),
        ]);

        Sanctum::actingAs($receiver);

        $response = $this->getJson('/api/wallet/history');

        $response->assertOk();
        $rows = $response->json('data.data');

        $this->assertCount(1, $rows);
        $this->assertSame(Transaction::TYPE_TRANSFER, $rows[0]['type']);
        $this->assertSame('in', $rows[0]['direction']);
    }
}
