<?php

use App\Models\WalletLedgerEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_ledger_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('wallet_id')
                ->constrained('wallets')->cascadeOnDelete();

            $table->enum('direction', [
                WalletLedgerEntry::DIRECTION_DEBIT,
                WalletLedgerEntry::DIRECTION_CREDIT,
            ])->index();

            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);

            $table->string('description')->nullable();

            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger_entries');
    }
};
