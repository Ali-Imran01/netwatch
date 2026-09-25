<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

#[Signature('netwatch:user {email} {name} {--role=admin : admin, engineer or viewer} {--password= : Avoid: ends up in shell history. Omit to be prompted.}')]
#[Description('Create a user (production has no default accounts)')]
class CreateUser extends Command
{
    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Password (min 12 characters)');

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'name' => $this->argument('name'), 'role' => $this->option('role'), 'password' => $password],
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
                'role' => ['required', Rule::enum(UserRole::class)],
                'password' => ['required', Password::min(12)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::create($validator->validated());
        $this->info("Created {$this->option('role')} {$this->argument('email')}.");

        return self::SUCCESS;
    }
}
