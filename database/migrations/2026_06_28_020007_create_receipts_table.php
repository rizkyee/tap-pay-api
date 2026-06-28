<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')->unique()
                ->constrained('transactions')->cascadeOnDelete();

            $table->string('receipt_number', 40)->unique();
            $table->string('pdf_path')->nullable();
            $table->string('image_path')->nullable();
            // Hash untuk verifikasi keaslian receipt.
            $table->string('receipt_hash', 64)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
