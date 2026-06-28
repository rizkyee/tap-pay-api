<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->string('phone', 20)->nullable()->unique();

            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();

            $table->string('password');
            $table->enum('role', [
                User::ROLE_ADMIN,
                User::ROLE_CUSTOMER,
                User::ROLE_MERCHANT,
            ])->default(User::ROLE_CUSTOMER)->index();

            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index(['role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
