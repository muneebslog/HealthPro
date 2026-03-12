<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        User::firstOrCreate(
            ['email' => 'admin@hp.com'],
            array_merge(User::factory()->definition(), [
                'name' => 'Admin',
                'email' => 'admin@hp.com',
                'role' => 'admin',
                'password' => Hash::make('admin123'),
            ])
        );

        Service::firstOrCreate(
            ['name' => 'consultation'],
            ['is_standalone' => false]
        );

        Doctor::firstOrCreate(
            ['name' => 'MO'],
            [
                'specialization' => 'GP',
                'is_on_payroll' => true,
                'status' => 'active',
            ]
        );
    }
}
