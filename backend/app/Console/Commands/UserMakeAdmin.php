<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserMakeAdmin extends Command
{
    protected $signature = 'user:make-admin {email}';
    protected $description = 'Promote a user to admin (Filament access).';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            $this->error("User not found for email: {$email}");
            return self::FAILURE;
        }

        $user->is_admin = true;
        $user->save();

        $this->info("User {$email} is now admin.");
        return self::SUCCESS;
    }
}
