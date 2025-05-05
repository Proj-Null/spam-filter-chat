<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //seeding users
        User::create([
            'name' => 'Ujwal Parajuli',
            'email' => 'Ujwal@ujwalp.com.np',
            'password' => Hash::make('password')
        ]);
        User::create([
            'name' => 'Test User 1',
            'email' => 'Test1@ujwalp.com.np',
            'password' => Hash::make('password')
        ]);
        User::create([
            'name' => 'Test User 2',
            'email' => 'Test2@ujwalp.com.np',
            'password' => Hash::make('password')
        ]);
        User::create([
            'name' => 'Test User 3',
            'email' => 'Test3@ujwalp.com.np',
            'password' => Hash::make('password')
        ]);
        User::create([
            'name' => 'Test User 4',
            'email' => 'Test4@ujwalp.com.np',
            'password' => Hash::make('password')
        ]);
        User::create([
            'name' => 'Test User 5',
            'email' => 'Test5@ujwalp.com.np',
            'password' => Hash::make('password')
        ]);
    }
}
