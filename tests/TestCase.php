<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create an authenticated user and return the token
     */
    protected function authenticateAs(string $role = 'customer'): array
    {
        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ];
    }

    /**
     * Create a user with a specific role
     */
    protected function createUserWithRole(string $role = 'customer'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /**
     * Create an admin user
     */
    protected function createAdmin(): User
    {
        return $this->createUserWithRole('admin');
    }

    /**
     * Create an organizer user
     */
    protected function createOrganizer(): User
    {
        return $this->createUserWithRole('organizer');
    }

    /**
     * Create a customer user
     */
    protected function createCustomer(): User
    {
        return $this->createUserWithRole('customer');
    }

    /**
     * Assert that the response has a successful API structure
     */
    protected function assertSuccessResponse($response): void
    {
        $response->assertJsonStructure([
            'success',
            'message',
            'data',
        ])->assertJson([
            'success' => true,
        ]);
    }

    /**
     * Assert that the response has a failed API structure
     */
    protected function assertFailureResponse($response): void
    {
        $response->assertJsonStructure([
            'success',
            'message',
        ])->assertJson([
            'success' => false,
        ]);
    }
}
