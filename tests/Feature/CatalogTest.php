<?php

namespace Tests\Feature;

use App\Livewire\ProductManager;
use App\Models\Allergen;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogTest extends TestCase
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

    public static function searches(): array
    {
        return [
            'English name' => ['milk', 'Everyday milk'],
            'Japanese name' => ['まいにち', 'Everyday milk'],
            'barcode' => ['DEMO001', 'Everyday milk'],
        ];
    }

    #[DataProvider('searches')]
    public function test_shoppers_find_products_by_name_or_barcode(string $query, string $name): void
    {
        $this->seed();
        $this->get('/?'.http_build_query(['q' => $query]))->assertOk()->assertSee($name)->assertDontSee('Salted rice ball');
    }

    public function test_category_and_search_combine_and_missing_search_has_an_empty_state(): void
    {
        $this->seed();
        $this->get('/?category=Snacks&q=milk')->assertOk()->assertSee('Milk chocolate')->assertDontSee('Everyday milk');
        $this->get('/?q=missing-product')->assertOk()->assertSee('No products found.');
        $this->getJson('/?category=Invalid')->assertUnprocessable();
        $this->getJson('/?q='.str_repeat('a', 101))->assertUnprocessable();
    }

    public function test_barcode_lookup_opens_a_product_and_missing_codes_return_a_useful_error(): void
    {
        $this->seed();
        $product = Product::where('barcode', 'DEMO001')->firstOrFail();
        $this->get('/scan/lookup?barcode=DEMO001')->assertRedirect(route('catalog.show', $product));
        $this->get('/scan/lookup?barcode=UNKNOWN')->assertRedirect('/scan')->assertSessionHasErrors('barcode');
        $this->getJson('/scan/lookup?barcode[]=DEMO001')->assertUnprocessable();
    }

    public function test_archived_products_are_unavailable_in_all_public_entry_points(): void
    {
        $this->seed();
        $product = Product::where('barcode', 'DEMO001')->firstOrFail();
        $product->update(['is_active' => false]);
        $this->get('/')->assertDontSee('Everyday milk');
        $this->get(route('catalog.show', $product))->assertNotFound();
        $this->get('/scan/lookup?barcode=DEMO001')->assertRedirect('/scan')->assertSessionHasErrors('barcode');
        $this->get('/saved/items?'.http_build_query(['ids' => [$product->id]]))->assertOk()->assertDontSee('Everyday milk');
    }

    public function test_recorded_and_unknown_information_remain_distinct_and_product_data_is_not_cached(): void
    {
        $this->seed();
        $milk = Product::where('barcode', 'DEMO001')->firstOrFail();
        $salad = Product::where('barcode', 'DEMO009')->firstOrFail();
        $this->get(route('catalog.show', $milk))->assertOk()->assertSee('Milk')->assertSee('乳')
            ->assertSee('Sample record — not real label information')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('catalog.show', $salad))->assertOk()->assertSee('Information unconfirmed')->assertDontSee('None recorded');
    }

    public function test_saved_products_are_filtered_to_requested_ids_and_requests_are_bounded(): void
    {
        $this->seed();
        $product = Product::where('barcode', 'DEMO001')->firstOrFail();
        $this->get('/saved/items?'.http_build_query(['ids' => [$product->id, 99999]]))->assertOk()
            ->assertSee('Everyday milk')->assertDontSee('Soft white bread')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/saved/items')->assertOk()->assertSee('A little space for your favorites.');
        $this->getJson('/saved/items?'.http_build_query(['ids' => range(1, 51)]))->assertUnprocessable();
        $this->getJson('/saved/items?ids[]=text')->assertUnprocessable();
    }

    public function test_public_product_fields_are_escaped_in_details_and_saved_results(): void
    {
        $this->seed();
        $product = Product::firstOrFail();
        $payload = '<script>alert("xss")</script>';
        $product->update(['name_en' => $payload, 'name_ja' => $payload, 'size' => $payload, 'source' => $payload]);
        foreach ([route('catalog.show', $product), '/saved/items?'.http_build_query(['ids' => [$product->id]])] as $url) {
            $this->get($url)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee($payload, false);
        }
    }

    public function test_only_admins_can_manage_products_including_livewire_actions(): void
    {
        $this->seed();
        Livewire::test(ProductManager::class)->assertForbidden();
        $this->actingAs(User::factory()->create());
        $this->get('/admin/products')->assertForbidden();
        Livewire::test(ProductManager::class)->assertForbidden();
        $this->actingAs(User::where('email', 'admin@allerscan.test')->firstOrFail());
        $this->get('/admin/products')->assertOk()->assertSee('Product catalog');
    }

    public function test_admin_can_add_a_catalog_product_without_any_payment_fields(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'admin@allerscan.test')->firstOrFail());
        $allergen = Allergen::where('code', 'milk')->firstOrFail();
        Livewire::test(ProductManager::class)->call('edit')
            ->set('form.barcode', 'DEMO-NEW')->set('form.name_ja', '新しい商品')->set('form.name_en', 'New demo product')
            ->set('form.size', '1 pack')->set('form.information_status', 'recorded')
            ->set('form.source', 'Fictional test record')->set('form.verified_at', now()->toDateString())
            ->set('allergenIds', [$allergen->id])->call('save')->assertHasNoErrors();
        $product = Product::where('barcode', 'DEMO-NEW')->firstOrFail();
        $this->assertSame(['milk'], $product->allergens->pluck('code')->all());
        $this->get(route('catalog.show', $product))->assertOk()->assertSee('New demo product');
    }

    public function test_admin_can_add_an_unverified_product_with_blank_evidence_fields(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'admin@allerscan.test')->firstOrFail());
        Livewire::test(ProductManager::class)->call('edit')
            ->set('form.barcode', '4901620353247')->set('form.name_ja', '未確認の商品')->set('form.name_en', 'Unverified product')
            ->set('form.size', '120g')->set('form.information_status', 'unknown')
            ->set('form.source', '')->set('form.verified_at', '')
            ->call('save')->assertHasNoErrors();
        $product = Product::where('barcode', '4901620353247')->firstOrFail();
        $this->assertNull($product->getRawOriginal('source'));
        $this->assertNull($product->getRawOriginal('verified_at'));
    }

    public function test_admin_validation_requires_evidence_and_valid_allergen_records(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'admin@allerscan.test')->firstOrFail());
        $product = Product::where('barcode', 'DEMO001')->firstOrFail();
        Livewire::test(ProductManager::class)->call('edit', $product->id)->set('form.source', '')->call('save')->assertHasErrors('form.source');
        Livewire::test(ProductManager::class)->call('edit', $product->id)->set('form.verified_at', '')->call('save')->assertHasErrors('form.verified_at');
        Livewire::test(ProductManager::class)->call('edit', $product->id)->set('allergenIds', [9999])->call('save')->assertHasErrors('allergenIds.0');
        Livewire::test(ProductManager::class)->call('edit', $product->id)->set('form.barcode', 'DEMO002')->call('save')->assertHasErrors('form.barcode');
        $this->assertSame('DEMO001', $product->fresh()->barcode);
    }

    public function test_admin_can_archive_and_restore_a_product(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'admin@allerscan.test')->firstOrFail());
        $product = Product::firstOrFail();
        Livewire::test(ProductManager::class)->call('toggleActive', $product->id)->assertHasNoErrors();
        $this->assertFalse($product->fresh()->is_active);
        Livewire::test(ProductManager::class)->call('toggleActive', $product->id)->assertHasNoErrors();
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_every_public_page_shows_the_safety_notice(): void
    {
        $this->seed();
        $product = Product::firstOrFail();
        foreach (['/', '/scan', '/saved', '/settings', '/privacy', route('catalog.show', $product)] as $url) {
            $this->get($url)->assertOk()->assertSee('Not medical or dietary advice.');
        }
    }

    public function test_privacy_page_is_linked_and_shows_contact_only_when_configured(): void
    {
        $this->get('/')->assertSee(route('privacy'));
        $this->get('/privacy')->assertOk()->assertSee('Camera images are not uploaded')->assertDontSee('mailto:');
        config(['allerscan.contact_email' => 'team@example.com']);
        $this->get('/privacy')->assertSee('mailto:team@example.com');
    }

    public function test_responses_send_security_headers_and_noindex_only_while_enabled(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeaderMissing('Strict-Transport-Security');
        $this->assertStringContainsString("frame-ancestors 'none'", $this->get('/')->headers->get('Content-Security-Policy'));

        config(['allerscan.noindex' => false]);
        $this->app['env'] = 'production';
        $this->get('https://localhost/')->assertHeaderMissing('X-Robots-Tag')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_japans_labeling_allergens_exist_without_seeding(): void
    {
        $codes = Allergen::pluck('code')->all();
        foreach (['shrimp', 'crab', 'walnut', 'wheat', 'buckwheat', 'egg', 'milk', 'peanut', 'soy', 'sesame', 'almond', 'gelatin'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertCount(29, $codes);
    }

    public function test_seeding_a_deployed_site_requires_a_new_password_and_adds_no_sample_products(): void
    {
        $this->app['env'] = 'production';
        config(['allerscan.admin.email' => 'owner@example.com']);
        $this->assertSeederRefuses('ADMIN_PASSWORD');
        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
        config(['allerscan.admin.password' => 'a-new-secret']);
        $this->app->make(DatabaseSeeder::class)->run();
        $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'role' => 'admin']);
        $this->assertSame(0, Product::count());
    }

    public function test_new_catalog_entries_are_real_records_unless_marked_as_samples(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'admin@allerscan.test')->firstOrFail());
        Livewire::test(ProductManager::class)->call('edit')
            ->set('form.barcode', '4901234567894')->set('form.name_ja', '本物の商品')->set('form.name_en', 'Real product')
            ->set('form.size', '100g')->call('save')->assertHasNoErrors();
        $product = Product::where('barcode', '4901234567894')->firstOrFail();
        $this->assertFalse($product->is_demo);
        $this->get(route('catalog.show', $product))->assertOk()->assertDontSee('SAMPLE RECORD')->assertSee('Catalog entry');
    }

    private function assertSeederRefuses(string $reason): void
    {
        try {
            $this->app->make(DatabaseSeeder::class)->run();
            $this->fail('Seeder should have refused to run.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString($reason, $exception->getMessage());
        }
    }
}
