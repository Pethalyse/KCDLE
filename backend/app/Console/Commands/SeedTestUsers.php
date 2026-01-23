<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Seed deterministic test users with verified emails.
 *
 * Creates users with:
 * - name: test{n}
 * - email: test@test.test{n}
 * - password: test
 *
 * Emails are marked as verified for immediate login.
 */
class SeedTestUsers extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'kcdle:seed-test-users {count=10 : Number of users to create} {--start=1 : Starting index for test names (test{n})}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create deterministic verified test users (test1, test2, ...) with password "test".';

    /**
     * Execute the console command.
     *
     * @return int
     * @throws Throwable
     */
    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $start = (int) $this->option('start');

        if ($count <= 0) {
            $this->error('count must be a positive integer.');
            return self::FAILURE;
        }

        if ($start <= 0) {
            $this->error('start must be a positive integer.');
            return self::FAILURE;
        }

        $created = 0;
        $updatedVerified = 0;

        DB::transaction(function () use ($count, $start, &$created, &$updatedVerified): void {
            for ($i = 0; $i < $count; $i++) {
                $n = $start + $i;

                $name = "test{$n}";
                $email = "test@test.test{$n}";

                $user = User::query()
                    ->where('email', $email)
                    ->orWhere('name', $name)
                    ->first();

                $wasExisting = (bool) $user;

                if (!$user) {
                    $user = new User();
                }

                $user->fill([
                    'name' => $name,
                    'email' => $email,
                    'password' => 'test',
                ]);

                if (!$user->getAttribute('email_verified_at')) {
                    $user->setAttribute('email_verified_at', now());
                    if ($wasExisting) {
                        $updatedVerified++;
                    }
                }

                $user->save();

                if (!$wasExisting) {
                    $created++;
                }
            }
        });

        $this->info("Users created: {$created}");
        $this->info("Existing users marked as verified: {$updatedVerified}");
        $this->info("Done (range: test{$start}..test" . ($start + $count - 1) . ")");

        return self::SUCCESS;
    }
}
