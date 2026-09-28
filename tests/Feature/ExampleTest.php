<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_shopper_pages_are_public_but_catalog_management_requires_login(): void
    {
        foreach (['/', '/scan', '/saved', '/settings', '/login'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/admin/products')->assertRedirect('/login');
        foreach (['/customer', '/sales', '/register/state'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_demo_team_can_sign_in_and_sign_out(): void
    {
        $this->seed();
        $this->post('/login', ['email' => 'demo@allerscan.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'demo@allerscan.test', 'password' => config('demo.password')])->assertRedirect('/');
        $this->assertAuthenticated();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
