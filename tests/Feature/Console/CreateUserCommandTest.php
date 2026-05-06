<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_user(): void
    {
        $this->artisan('app:create-user')
            ->expectsQuestion('Name', 'John Doe')
            ->expectsQuestion('Email', 'john@example.com')
            ->expectsQuestion('Password', 'password123')
            ->expectsOutput("User 'John Doe' created successfully.")
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_it_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $this->artisan('app:create-user')
            ->expectsQuestion('Name', 'Jane Doe')
            ->expectsQuestion('Email', 'john@example.com')
            ->assertFailed();
    }
}
