<?php

use App\Models\TransactionApproval;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('receiver_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->enum('status', [
                TransactionApproval::STATUS_PENDING,
                TransactionApproval::STATUS_APPROVED,
                TransactionApproval::STATUS_REJECTED,
                TransactionApproval::STATUS_EXPIRED,
            ])->default(TransactionApproval::STATUS_PENDING)->index();

            $table->string('reason')->nullable();
            $table->string('device_id')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_approvals');
    }
};
