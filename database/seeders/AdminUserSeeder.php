<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => config('admin.email')],
            [
                'name' => 'Admin',
                'password' => config('admin.password'),
            ],
        );

        $admin->roles()->syncWithoutDetaching(
            Role::query()->where('name', 'admin')->firstOrFail()
        );
    }
}
