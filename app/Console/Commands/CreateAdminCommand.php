<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * The supported way to create or reset the admin on a live server, so nobody
 * has to put a password in a seeder or a shell history line they forget about.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'shop:create-admin
                            {--email= : Admin email address}
                            {--name= : Display name}';

    protected $description = 'Create or reset the store admin account';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Admin email');
        $name = $this->option('name') ?: $this->ask('Display name', 'Store Admin');

        // secret() keeps it off the screen and out of shell history.
        $password = $this->secret('Password');
        $confirm = $this->secret('Confirm password');

        if ($password !== $confirm) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', Password::min(12)->letters()->numbers()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->exists();

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'is_active' => true,
                // Set by hand here, so no forced change is imposed.
                'must_change_password' => false,
            ]
        );

        $this->info($existing ? "Password reset for {$email}." : "Admin {$email} created.");

        return self::SUCCESS;
    }
}
