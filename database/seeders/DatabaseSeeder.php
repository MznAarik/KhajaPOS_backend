<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\User;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::updateOrCreate([
            'email' => 'admin@gmail.com',
        ], [
            'name' => 'admin',
            'password' => Hash::make('admin@gmail.com'),
            'role' => 'admin',
            'email_verified_at' => now(),
            'updated_at' => now(),

        ]);
        Business::updateOrCreate([
            'email' => 'admin@gmail.com',
        ], [
            'user_id' => $user->id,
            'name' => 'Khaja POS',
            'business_type' => 'Restaurant',
            'phone' => '9800000000',
            'address' => 'Kathmandu, Nepal',
            'created_by' => $user->id,
            'updated_at' => now(),
        ]);
    }
}
