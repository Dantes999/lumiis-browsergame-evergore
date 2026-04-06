<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Small operational helper for promoting an existing account to admin.
 */
class GrantAdminCommand extends Command
{
    protected $signature = 'game:grant-admin {email : Email address of the user to promote}';

    protected $description = 'Grant admin access to an existing account';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // This command intentionally promotes by email because login is email-based as well.
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user found for that email address.');

            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->info($user->email.' already has admin access.');

            return self::SUCCESS;
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->info('Admin access granted to '.$user->email.'.');

        return self::SUCCESS;
    }
}
