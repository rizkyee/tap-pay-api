<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Kode transaksi human-readable, mis. TPP-20260627-000088 / TPU-...
            $table->string('transaction_code', 40)->nullable()->unique()->after('id');

            // Idempotency key untuk mencegah double transaction.
            $table->string('idempotency_key', 80)->nullable()->after('reference');

            // Wallet sumber & tujuan (untuk transfer & topup).
            $table->foreignId('sender_wallet_id')->nullable()->after('wallet_id')
                ->constrained('wallets')->nullOnDelete();
            $table->foreignId('receiver_wallet_id')->nullable()->after('sender_wallet_id')
                ->constrained('wallets')->nullOnDelete();

            // Merchant yang melakukan top up (jika tipe topup oleh merchant).
            $table->foreignId('merchant_id')->nullable()->after('receiver_wallet_id')
                ->constrained('users')->nullOnDelete();

            // Nominal tambahan.
            $table->decimal('fee', 15, 2)->default(0)->after('amount');
            $table->decimal('total_amount', 15, 2)->nullable()->after('fee');

            // Catatan transaksi & timestamp siklus hidup.
            $table->text('notes')->nullable()->after('description');
            $table->timestamp('expires_at')->nullable()->after('notes');
            $table->timestamp('approved_at')->nullable()->after('expires_at');
            $table->timestamp('completed_at')->nullable()->after('approved_at');

            $table->index(['type', 'status']);
            $table->index(['sender_wallet_id', 'receiver_wallet_id']);
            $table->index('merchant_id');
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sender_wallet_id');
            $table->dropConstrainedForeignId('receiver_wallet_id');
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropColumn([
                'transaction_code',
                'idempotency_key',
                'fee',
                'total_amount',
                'notes',
                'expires_at',
                'approved_at',
                'completed_at',
            ]);
        });
    }
};
