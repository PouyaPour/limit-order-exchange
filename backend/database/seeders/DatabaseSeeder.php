<?php

namespace Database\Seeders;

use App\Enums\SymbolEnum;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $demoUsers = [
            [
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'password' => 'password',
                'balance' => '10000.00000000',
                'assets' => [
                    ['symbol' => SymbolEnum::BTC, 'amount' => '1.00000000'],
                    ['symbol' => SymbolEnum::ETH, 'amount' => '10.00000000'],
                ],
            ],
            [
                'name' => 'Bob',
                'email' => 'bob@example.com',
                'password' => 'password',
                'balance' => '10000.00000000',
                'assets' => [
                    ['symbol' => SymbolEnum::BTC, 'amount' => '1.00000000'],
                    ['symbol' => SymbolEnum::ETH, 'amount' => '10.00000000'],
                ],
            ],
        ];

        foreach ($demoUsers as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'balance' => $userData['balance'],
            ]);

            foreach ($userData['assets'] as $asset) {
                $user->assets()->create([
                    'symbol' => $asset['symbol'],
                    'amount' => $asset['amount'],
                    'locked_amount' => '0.00000000',
                ]);
            }
        }
    }
}
