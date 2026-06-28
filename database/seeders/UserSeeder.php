<?php

namespace Database\Seeders;

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
            ],
            [
                'name' => 'Demo Customer',
                'email' => 'customer@tappay.test',
                'phone' => '082222222222',
                'role' => User::ROLE_CUSTOMER,
                'wallet_prefix' => 'TC',
            ],
            [
                'name' => 'Demo Merchant',
                'email' => 'merchant@tappay.test',
                'phone' => '083333333333',
                'role' => User::ROLE_MERCHANT,
                'wallet_prefix' => 'TM',
            ],
        ];

        foreach ($users as $item) {
            $user = User::query()->updateOrCreate(
                ['email' => $item['email']],
                [
                    'name' => $item['name'],
                    'phone' => $item['phone'],
                    'password' => 'password',
                    'role' => $item['role'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'phone_verified_at' => now(),
                ]
            );

            Wallet::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'wallet_code' => Wallet::generateWalletCode($item['wallet_prefix']),
                    'balance' => 0,
                    'currency' => 'TPP',
                    'status' => Wallet::STATUS_ACTIVE,
                ]
            );
        }
    }
}
