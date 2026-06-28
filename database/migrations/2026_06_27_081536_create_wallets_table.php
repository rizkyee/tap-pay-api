<?php

use App\Models\Wallet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('wallet_code', 32)->unique();

            // Saldo ini adalah saldo virtual/internal aplikasi, bukan uang asli.
            $table->decimal('balance', 15, 2)->unsigned();
            // TPP = TapPay Point. Bukan kode mata uang resmi.
            $table->string('currency', 10)->default('TPP');

            $table->enum('status', [
                Wallet::STATUS_ACTIVE,
                Wallet::STATUS_FROZEN,
                Wallet::STATUS_CLOSED,
            ])->default(Wallet::STATUS_ACTIVE)->index();

            $table->timestamps();

            $table->index(['currency', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
