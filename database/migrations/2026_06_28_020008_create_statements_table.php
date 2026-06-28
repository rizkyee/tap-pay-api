<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('statement_number', 40)->unique();
            $table->string('type', 30)->default('all');

            $table->date('start_date');
            $table->date('end_date');

            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->default(0);
            $table->unsignedInteger('total_transactions')->default(0);

            $table->string('pdf_path')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statements');
    }
};
