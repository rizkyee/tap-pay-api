<?php

use App\Models\NfcSession;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfc_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();

            $table->string('token', 80)->unique();
            $table->string('mode', 30)->default(NfcSession::MODE_RECEIVE);

            $table->enum('status', [
                NfcSession::STATUS_ACTIVE,
                NfcSession::STATUS_USED,
                NfcSession::STATUS_EXPIRED,
                NfcSession::STATUS_CANCELLED,
            ])->default(NfcSession::STATUS_ACTIVE)->index();

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfc_sessions');
    }
};
