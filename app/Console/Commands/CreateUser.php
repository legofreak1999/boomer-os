<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-user')]
#[Description('Create a new user account')]
class CreateUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = text(
            label: 'Name',
            required: true,
        );

        $email = text(
            label: 'Email',
            required: true,
            validate: ['email' => 'required|email|unique:users,email'],
        );

        $password = password(
            label: 'Password',
            required: true,
        );

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("User '{$name}' created successfully.");

        return self::SUCCESS;
    }
}
