<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Promote an existing user to streamer.
 *
 * Streamer users are highlighted in the frontend (e.g., purple nickname + Twitch icon).
 */
class UserMakeStreamer extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'user:make-streamer {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promote a user to streamer (UI highlight).';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        $user->is_streamer = true;
        $user->save();

        $this->info("User promoted to streamer: {$user->email}");

        return self::SUCCESS;
    }
}
