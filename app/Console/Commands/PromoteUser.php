<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('allerscan:team {email : Email address of a registered account} {--remove : Return the account to a shopper account}')]
#[Description('Give a registered account access to catalog management, or remove it')]
class PromoteUser extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No account uses that email address. Ask them to create an account first.');

            return self::FAILURE;
        }
        $user->forceFill(['role' => $this->option('remove') ? 'shopper' : 'admin'])->save();
        $this->info($this->option('remove')
            ? "{$user->email} is now a shopper account."
            : "{$user->email} can now manage the catalog.");

        return self::SUCCESS;
    }
}
