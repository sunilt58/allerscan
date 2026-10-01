<?php

namespace Tests\Feature;

use App\Models\Allergen;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * These tests assert English copy; the Japanese default is covered in LocalizationTest.
     */
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.locale' => 'en']);
        app()->setLocale('en');
    }

    /**
     * @return array<string, string>
     */
    private function registration(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Hana Sato', 'email' => 'hana@example.com',
            'password' => 'correct-horse', 'password_confirmation' => 'correct-horse', 'consent' => '1',
        ];
    }

    public function test_a_shopper_can_create_an_account_and_is_signed_in(): void
    {
        $this->post('/register', $this->registration(['role' => 'admin']))
            ->assertRedirect(route('settings'))->assertSessionHas('merge_preferences', true);
        $user = User::where('email', 'hana@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('shopper', $user->role);
        $this->get('/admin/products')->assertForbidden();
    }

    public function test_registration_requires_consent_a_confirmed_password_and_a_new_email(): void
    {
        $this->post('/register', $this->registration(['consent' => '']))->assertSessionHasErrors('consent');
        $this->post('/register', $this->registration(['password_confirmation' => 'different']))->assertSessionHasErrors('password');
        $this->post('/register', $this->registration(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->assertSame(0, User::count());
        User::factory()->create(['email' => 'hana@example.com']);
        $this->post('/register', $this->registration())->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_synced_preferences_replace_the_account_lists_and_drop_unknown_entries(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $product = Product::where('barcode', 'DEMO001')->firstOrFail();
        $this->actingAs($user)->putJson('/account/preferences', [
            'allergens' => ['milk', 'egg', 'not-an-allergen'], 'savedProducts' => [$product->id, 999999],
        ])->assertOk()->assertExactJson(['allergens' => ['egg', 'milk'], 'savedProducts' => [$product->id]]);
        $this->putJson('/account/preferences', ['allergens' => ['milk'], 'savedProducts' => []])
            ->assertOk()->assertExactJson(['allergens' => ['milk'], 'savedProducts' => []]);
        $this->assertSame(['milk'], $user->allergens()->pluck('code')->all());
    }

    public function test_preference_sync_requires_an_account_and_bounds_the_saved_list(): void
    {
        $this->putJson('/account/preferences', ['allergens' => [], 'savedProducts' => []])->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->putJson('/account/preferences', ['allergens' => [], 'savedProducts' => range(1, 51)])->assertJsonValidationErrors('savedProducts');
        $this->putJson('/account/preferences', ['allergens' => 'milk'])->assertJsonValidationErrors(['allergens', 'savedProducts']);
    }

    public function test_pages_give_the_browser_account_lists_only_while_signed_in(): void
    {
        $user = User::factory()->create();
        $user->allergens()->attach(Allergen::where('code', 'wheat')->firstOrFail());
        $this->get('/')->assertOk()->assertDontSee('id="account-state"', false);
        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('id="account-state"', false)->assertSee('"allergens":["wheat"]', false)->assertSee('"merge":false', false);
        $this->post('/logout')->assertRedirect('/login')->assertSessionHas('signed_out', true);
        $this->get('/login')->assertOk()->assertSee('id="account-signed-out"', false)->assertDontSee('id="account-state"', false);
    }

    public function test_signing_in_asks_the_browser_to_merge_its_lists_into_the_account(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/')->assertSessionHas('merge_preferences', true);
        $this->get('/')->assertSee('"merge":true', false);
        $this->get('/')->assertSee('"merge":false', false);
    }

    public function test_a_shopper_can_delete_their_account_with_their_password(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $user->allergens()->attach(Allergen::where('code', 'milk')->firstOrFail());
        $user->savedProducts()->attach(Product::firstOrFail());
        $this->actingAs($user)->delete('/account', ['password' => 'wrong'])->assertSessionHasErrorsIn('accountDeletion', 'password');
        $this->assertModelExists($user);
        $this->delete('/account', ['password' => 'password'])->assertRedirect('/')->assertSessionHas('signed_out', true);
        $this->assertGuest();
        $this->assertModelMissing($user);
        $this->assertDatabaseMissing('allergen_user', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('product_user', ['user_id' => $user->id]);
    }

    public function test_team_accounts_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->delete('/account', ['password' => 'password'])->assertForbidden();
        $this->assertModelExists($admin);
        $this->get('/settings')->assertOk()->assertDontSee('Delete my account');
    }

    public function test_the_team_command_grants_and_removes_catalog_access(): void
    {
        $user = User::factory()->create(['email' => 'teammate@example.com']);
        $this->artisan('allerscan:team', ['email' => 'nobody@example.com'])->assertFailed();
        $this->artisan('allerscan:team', ['email' => 'teammate@example.com'])->assertSuccessful();
        $this->assertTrue($user->fresh()->isAdmin());
        $this->actingAs($user->fresh())->get('/admin/products')->assertOk();
        $this->artisan('allerscan:team', ['email' => 'teammate@example.com', '--remove' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->isAdmin());
    }
}
