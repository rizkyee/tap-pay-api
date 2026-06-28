<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // PIN transaksi (di-hash) untuk approval transfer NFC.
            $table->string('transaction_pin')->nullable()->after('password');
            // Toggle biometric approval di sisi device.
            $table->boolean('biometric_enabled')->default(false)->after('transaction_pin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['transaction_pin', 'biometric_enabled']);
        });
    }
};
