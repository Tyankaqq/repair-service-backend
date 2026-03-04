<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;                    // ← добавь эту строку
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'     => 'Диспетчер Иванов',
            'email'    => 'dispatcher@repair.local',
            'password' => Hash::make('password'),
            'role'     => 'dispatcher',
        ]);

        User::create([
            'name'     => 'Мастер Петров',
            'email'    => 'master1@repair.local',
            'password' => Hash::make('password'),
            'role'     => 'master',
        ]);

        User::create([
            'name'     => 'Мастер Сидоров',
            'email'    => 'master2@repair.local',
            'password' => Hash::make('password'),
            'role'     => 'master',
        ]);
    }
}
