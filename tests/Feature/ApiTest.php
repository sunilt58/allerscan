<?php

namespace Tests\Feature;

use App\Models\Allergen;
use App\Models\Product;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Accept-Language', 'en');
    }

    public function test_the_app_can_register_sign_in_and_sign_out_with_tokens(): void
    {
        $registration = $this->postJson('/api/v1/register', [
            'name' => 'Kain', 'email' => 'kain@example.com', 'password' => 'correct-horse',
            'password_confirmation' => 'correct-horse', 'consent' => true, 'device_name' => 'Pixel 9', 'role' => 'admin',
        ])->assertCreated()->assertJsonPath('user.email', 'kain@example.com')->assertJsonPath('user.allergens', []);
        $this->assertNotEmpty($registration->json('token'));
        $this->assertSame('shopper', User::where('email', 'kain@example.com')->value('role'));

        $this->postJson('/api/v1/login', ['email' => 'kain@example.com', 'password' => 'wrong', 'device_name' => 'iPhone'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', 'Incorrect email or password.');
        $token = $this->postJson('/api/v1/login', ['email' => 'kain@example.com', 'password' => 'correct-horse', 'device_name' => 'iPhone'])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.name', 'Kain');
        $this->withToken($token)->postJson('/api/v1/logout')->assertNoContent();
        $this->assertSame(1, User::firstWhere('email', 'kain@example.com')->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_registration_needs_consent_and_a_device_name(): void
    {
        $this->postJson('/api/v1/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'correct-horse', 'password_confirmation' => 'correct-horse'])
            ->assertJsonValidationErrors(['consent', 'device_name']);
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'correct-horse', 'password_confirmation' => 'correct-horse', 'consent' => '1', 'device_name' => 'web'])
            ->assertSessionHasErrors('device_name');
        $this->assertSame(0, User::count());
    }

    public function test_products_can_be_looked_up_by_barcode_searched_and_opened(): void
    {
        $this->seed();
        $milk = Product::where('barcode', 'DEMO001')->firstOrFail();
        $this->getJson('/api/v1/products/barcode/DEMO001')->assertOk()->assertJson(['data' => [
            'id' => $milk->id, 'name_ja' => 'まいにちミルク', 'name_en' => 'Everyday milk', 'information_status' => 'recorded',
            'is_sample' => true, 'allergens' => [['code' => 'milk', 'name_ja' => '乳', 'name_en' => 'Milk']],
        ]]);
        $this->getJson('/api/v1/products/barcode/NOPE')->assertNotFound()
            ->assertJsonPath('message', 'Product not found in our catalog. Try searching by name.');
        $this->withHeader('Accept-Language', 'ja')->getJson('/api/v1/products/barcode/NOPE')
            ->assertJsonPath('message', 'このバーコードの商品は登録されていません。商品名で検索してください。');
        $this->getJson('/api/v1/products?q=milk')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/products?category=Snacks&q=milk')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products?category=Weapons')->assertJsonValidationErrors('category');
        $this->getJson('/api/v1/products/'.$milk->id)->assertOk()->assertJsonPath('data.barcode', 'DEMO001');
        $this->getJson('/api/v1/allergens')->assertOk()->assertJsonCount(Allergen::count(), 'data');

        $milk->update(['is_active' => false]);
        $this->getJson('/api/v1/products/barcode/DEMO001')->assertNotFound();
        $this->getJson('/api/v1/products/'.$milk->id)->assertNotFound();
        $this->getJson('/api/v1/products?q=Everyday')->assertJsonCount(0, 'data');
    }

    public function test_unconfirmed_products_are_never_presented_as_allergen_free(): void
    {
        $this->seed();
        $this->getJson('/api/v1/products/barcode/DEMO009')->assertOk()
            ->assertJsonPath('data.information_status', 'unknown')->assertJsonPath('data.allergens', []);
    }

    public function test_preferences_sync_with_the_same_account_used_on_the_website(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $milk = Product::where('barcode', 'DEMO001')->firstOrFail();
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/me/preferences', ['allergens' => ['milk', 'egg', 'nope'], 'saved_products' => [$milk->id, 999999]])
            ->assertOk()->assertJsonPath('data.allergens', ['egg', 'milk'])->assertJsonPath('data.saved_products', [$milk->id]);
        $this->getJson('/api/v1/me/saved-products')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.barcode', 'DEMO001');
        $this->putJson('/api/v1/me/preferences', ['allergens' => [], 'saved_products' => range(1, 51)])->assertJsonValidationErrors('saved_products');

        $this->actingAs($user)->get('/')->assertSee('"allergens":["egg","milk"]', false);
        $milk->update(['is_active' => false]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/me/saved-products')->assertJsonCount(0, 'data');
    }

    public function test_signed_in_endpoints_reject_requests_without_a_token(): void
    {
        foreach ([['get', '/api/v1/me'], ['put', '/api/v1/me/preferences'], ['get', '/api/v1/me/saved-products'], ['delete', '/api/v1/me'], ['get', '/api/v1/me/suggestions'], ['post', '/api/v1/suggestions'], ['post', '/api/v1/logout']] as [$method, $url]) {
            $this->json($method, $url)->assertUnauthorized();
        }
    }

    public function test_the_app_can_send_suggestions_and_follow_them(): void
    {
        $this->seed();
        Sanctum::actingAs($user = User::factory()->create());
        $this->postJson('/api/v1/suggestions', ['type' => 'product', 'barcode' => '4901234567894', 'name_ja' => '新しい商品', 'allergen_codes' => ['wheat']])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.allergen_codes', ['wheat']);
        $this->postJson('/api/v1/suggestions', ['type' => 'product', 'barcode' => 'DEMO001', 'name_en' => 'Milk'])->assertJsonValidationErrors('barcode');
        $this->postJson('/api/v1/suggestions', ['type' => 'allergen'])->assertJsonValidationErrors(['name_ja', 'name_en']);
        $this->getJson('/api/v1/me/suggestions')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.barcode', '4901234567894');
        Suggestion::factory()->count(Suggestion::PENDING_LIMIT_PER_USER)->create(['user_id' => $user->id]);
        $this->postJson('/api/v1/suggestions', ['type' => 'allergen', 'name_en' => 'Celery'])->assertJsonValidationErrors('type');
    }

    public function test_deleting_an_account_from_the_app_removes_it_and_its_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('phone');
        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/me', ['password' => 'wrong'])->assertJsonValidationErrors('password');
        $this->deleteJson('/api/v1/me', ['password' => 'password'])->assertNoContent();
        $this->assertModelMissing($user);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($admin);
        $this->deleteJson('/api/v1/me', ['password' => 'password'])->assertForbidden();
        $this->assertModelExists($admin);
    }
}
