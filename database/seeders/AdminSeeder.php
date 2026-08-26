<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * REQ-009 — the single admin.
 *
 * The password here is a KNOWN placeholder. Deployment forces a change on first
 * login (Planning §17.4); never ship this credential to production as-is.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@basic-ecom.test'],
            [
                'name' => 'Store Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
    }
}
