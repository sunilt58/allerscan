<?php

namespace Tests\Feature;

use App\Livewire\ProductManager;
use App\Models\Allergen;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class OpenFoodFactsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.locale' => 'en']);
        app()->setLocale('en');
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin);
    }

    private function allergenIds(array $codes): array
    {
        return Allergen::whereIn('code', $codes)->orderBy('id')->pluck('id')->all();
    }

    public function test_a_found_product_fills_empty_fields_as_an_unconfirmed_draft(): void
    {
        Http::fake(['world.openfoodfacts.org/*' => Http::response(['status' => 1, 'product' => [
            'product_name' => 'ミルクとココアのミニクッキー', 'product_name_en' => 'Cocoa and milk mini cookies', 'quantity' => '120 g',
            'allergens_tags' => ['en:milk', 'en:eggs', 'en:gluten', 'ja:小麦'], 'traces_tags' => ['en:peanuts'],
        ]])]);
        $form = Livewire::test(ProductManager::class)->call('edit')
            ->set('form.barcode', '4901620353247')->set('form.name_en', 'Typed by the team')
            ->set('form.information_status', 'recorded')
            ->call('fetchFromOpenFoodFacts')
            ->assertSet('form.name_ja', 'ミルクとココアのミニクッキー')
            ->assertSet('form.name_en', 'Typed by the team')
            ->assertSet('form.size', '120 g')
            ->assertSet('form.information_status', 'unknown')
            ->assertSee('Also listed there, not selected automatically: gluten')
            ->assertSee('May contain (traces): peanuts');
        $this->assertEqualsCanonicalizing($this->allergenIds(['milk', 'egg', 'wheat']), $form->get('allergenIds'));
        $this->assertSame(0, Product::count());
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v2/product/4901620353247.json')
            && str_starts_with($request->header('User-Agent')[0], 'AllerScan/'));

        $form->call('save')->assertHasNoErrors();
        $product = Product::where('barcode', '4901620353247')->firstOrFail();
        $this->assertSame('unknown', $product->information_status);
    }

    public function test_missing_products_and_connection_failures_are_reported_without_changing_the_form(): void
    {
        Http::fake(['world.openfoodfacts.org/*' => Http::response(['status' => 0, 'status_verbose' => 'product not found'])]);
        Livewire::test(ProductManager::class)->call('edit')->set('form.barcode', '4900000000000')
            ->call('fetchFromOpenFoodFacts')->assertSee('This barcode is not in Open Food Facts.')->assertSet('form.name_ja', '');

        Http::fake(['world.openfoodfacts.org/*' => fn () => throw new ConnectionException('timeout')]);
        Livewire::test(ProductManager::class)->call('edit')->set('form.barcode', '4900000000000')
            ->call('fetchFromOpenFoodFacts')->assertSee('Could not reach Open Food Facts.')->assertSet('allergenIds', []);
    }

    public function test_a_lookup_needs_a_valid_barcode_and_catalog_access(): void
    {
        Http::fake();
        Livewire::test(ProductManager::class)->call('edit')->set('form.barcode', 'not a barcode/../x')
            ->call('fetchFromOpenFoodFacts')->assertHasErrors('form.barcode');
        Http::assertNothingSent();

        $this->actingAs(User::factory()->create());
        Livewire::test(ProductManager::class)->assertForbidden();
    }
}
