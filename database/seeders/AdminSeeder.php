<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * REQ-009 — the single admin.
 *
 * In PRODUCTION this refuses to run without ADMIN_EMAIL and ADMIN_PASSWORD.
 * Seeding a known credential onto a live payment-handling store is the kind of
 * mistake that is only found by whoever finds it first.
 *
 * Locally it seeds a development account and flags it `must_change_password`,
 * so even that credential cannot survive contact with a real deployment.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (app()->isProduction() && (blank($email) || blank($password))) {
            throw new RuntimeException(
                'Refusing to seed a default admin in production. '.
                'Set ADMIN_EMAIL and ADMIN_PASSWORD, or run `php artisan shop:create-admin`.'
            );
        }

        User::updateOrCreate(
            ['email' => $email ?: 'admin@basic-ecom.test'],
            [
                'name' => env('ADMIN_NAME', 'Store Admin'),
                'password' => Hash::make($password ?: 'password'),
                'is_active' => true,
                // Set even when a real password was supplied: the person who
                // typed it into .env should not be the only one who knows it.
                'must_change_password' => true,
            ]
        );
    }
}
