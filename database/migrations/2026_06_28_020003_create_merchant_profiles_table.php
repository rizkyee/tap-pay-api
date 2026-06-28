<?php

use App\Models\MerchantProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique()
                ->constrained('users')->cascadeOnDelete();

            $table->string('merchant_code', 32)->unique();
            $table->string('business_name', 150);
            $table->string('business_address')->nullable();
            $table->string('business_id_number', 60)->nullable();

            $table->enum('merchant_status', [
                MerchantProfile::STATUS_ACTIVE,
                MerchantProfile::STATUS_SUSPENDED,
                MerchantProfile::STATUS_CLOSED,
            ])->default(MerchantProfile::STATUS_ACTIVE)->index();

            // Limit operasional merchant (saldo virtual TPP).
            $table->decimal('daily_topup_limit', 15, 2)->default(50000000);
            $table->decimal('single_topup_limit', 15, 2)->default(10000000);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_profiles');
    }
};
