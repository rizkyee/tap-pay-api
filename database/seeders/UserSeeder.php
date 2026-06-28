<?php

namespace Database\Seeders;

use App\Models\MerchantProfile;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'TapPay Admin',
                'email' => 'admin@tappay.test',
                'phone' => '081111111111',
                'role' => User::ROLE_ADMIN,
                'wallet_prefix' => 'TA',
                'balance' => 0,
            ],
            [
                'name' => 'Customer A',
                'email' => 'customer@tappay.test',
                'phone' => '082222222222',
                'role' => User::ROLE_CUSTOMER,
                'wallet_prefix' => 'TC',
                'balance' => 500000,
            ],
            [
                'name' => 'Customer B',
                'email' => 'customer.b@tappay.test',
                'phone' => '082333333333',
                'role' => User::ROLE_CUSTOMER,
                'wallet_prefix' => 'TC',
                'balance' => 100000,
            ],
            [
                'name' => 'Demo Merchant',
                'email' => 'merchant@tappay.test',
                'phone' => '083333333333',
                'role' => User::ROLE_MERCHANT,
                'wallet_prefix' => 'TM',
                'balance' => 0,
            ],
        ];

        foreach ($users as $item) {
            $user = User::query()->updateOrCreate(
                ['email' => $item['email']],
                [
                    'name' => $item['name'],
                    'phone' => $item['phone'],
                    'password' => 'password',
                    // PIN transaksi default untuk testing approval transfer.
                    'transaction_pin' => '123456',
                    'role' => $item['role'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'phone_verified_at' => now(),
                ]
            );

            Wallet::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'wallet_code' => Wallet::generateWalletCode($item['wallet_prefix']),
                    'balance' => $item['balance'],
                    'currency' => 'TPP',
                    'status' => Wallet::STATUS_ACTIVE,
                ]
            );

            if ($user->role === User::ROLE_MERCHANT) {
                MerchantProfile::query()->firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'merchant_code' => MerchantProfile::generateMerchantCode(),
                        'business_name' => 'Demo Merchant Store',
                        'business_address' => 'Jl. Contoh No. 1, Jakarta',
                        'merchant_status' => MerchantProfile::STATUS_ACTIVE,
                    ]
                );
            }
        }
    }
}
