<?php

namespace Tests\Feature;

use App\Livewire\ProductManager;
use App\Models\Allergen;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertSee('Fictional graduation demo')->assertHeader('Cache-Control', 'no-store, private');
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
        $this->actingAs(User::where('email', 'demo@allerscan.test')->firstOrFail());
        $this->get('/admin/products')->assertOk()->assertSee('Product catalog');
    }

    public function test_admin_can_add_a_catalog_product_without_any_payment_fields(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'demo@allerscan.test')->firstOrFail());
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

    public function test_admin_validation_requires_evidence_and_valid_allergen_records(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'demo@allerscan.test')->firstOrFail());
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
        $this->actingAs(User::where('email', 'demo@allerscan.test')->firstOrFail());
        $product = Product::firstOrFail();
        Livewire::test(ProductManager::class)->call('toggleActive', $product->id)->assertHasNoErrors();
        $this->assertFalse($product->fresh()->is_active);
        Livewire::test(ProductManager::class)->call('toggleActive', $product->id)->assertHasNoErrors();
        $this->assertTrue($product->fresh()->is_active);
    }
}
