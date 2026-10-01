<?php

namespace Tests\Feature;

use App\Livewire\ProductManager;
use App\Livewire\SuggestionReview;
use App\Models\Allergen;
use App\Models\Product;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuggestionTest extends TestCase
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

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    public function test_guests_see_a_sign_in_prompt_and_cannot_send_suggestions(): void
    {
        $this->get('/suggest')->assertOk()->assertSee('Sign in to suggest.')->assertDontSee('Send product suggestion');
        $this->post('/suggest', ['type' => 'allergen', 'name_en' => 'Celery'])->assertRedirect('/login');
        $this->assertSame(0, Suggestion::count());
    }

    public function test_a_shopper_can_suggest_a_product_and_follow_its_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/suggest', [
            'type' => 'product', 'barcode' => '4901234567894', 'name_ja' => '新しいクッキー', 'size' => '120g',
            'category' => 'Snacks', 'allergen_codes' => ['wheat', 'milk'], 'note' => 'Found at my local shop',
        ])->assertRedirect('/suggest')->assertSessionHasNoErrors();
        $suggestion = Suggestion::firstOrFail();
        $this->assertSame(['product', 'pending', $user->id, ['wheat', 'milk']], [$suggestion->type, $suggestion->status, $suggestion->user_id, $suggestion->allergen_codes]);
        $this->assertSame(0, Product::count());
        $this->get('/suggest')->assertSee('新しいクッキー')->assertSee('Waiting for review');
        $this->actingAs(User::factory()->create())->get('/suggest')->assertDontSee('新しいクッキー');
    }

    public function test_a_shopper_can_suggest_an_allergen_that_is_not_on_the_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/suggest', ['type' => 'allergen', 'name_en' => 'Celery', 'barcode' => 'IGNORED'])->assertSessionHasNoErrors();
        $suggestion = Suggestion::firstOrFail();
        $this->assertSame(['allergen', 'Celery', null], [$suggestion->type, $suggestion->name_en, $suggestion->barcode]);
        $this->assertDatabaseMissing('allergens', ['name_en' => 'Celery']);
    }

    public function test_suggestions_are_validated_and_limited(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post('/suggest', ['type' => 'other'])->assertSessionHasErrors('type');
        $this->post('/suggest', ['type' => 'product', 'barcode' => '4900000000001'])->assertSessionHasErrors(['name_ja', 'name_en']);
        $this->post('/suggest', ['type' => 'product', 'barcode' => 'bad code', 'name_en' => 'X'])->assertSessionHasErrors('barcode');
        $this->post('/suggest', ['type' => 'product', 'barcode' => 'DEMO001', 'name_en' => 'Milk'])->assertSessionHasErrors('barcode');
        $this->post('/suggest', ['type' => 'product', 'barcode' => '4900000000001', 'name_en' => 'X', 'allergen_codes' => ['nope']])->assertSessionHasErrors('allergen_codes.0');
        $this->post('/suggest', ['type' => 'allergen'])->assertSessionHasErrors(['name_ja', 'name_en']);
        $this->assertSame(0, Suggestion::count());
        Suggestion::factory()->count(Suggestion::PENDING_LIMIT_PER_USER)->create(['user_id' => $user->id]);
        $this->post('/suggest', ['type' => 'allergen', 'name_en' => 'Celery'])->assertSessionHasErrors('type');
    }

    public function test_only_the_team_can_open_the_admin_area_and_it_shows_waiting_suggestions(): void
    {
        Suggestion::factory()->count(2)->create();
        $this->actingAs(User::factory()->create());
        $this->get('/admin/suggestions')->assertForbidden();
        $this->get('/admin/products')->assertForbidden();
        Livewire::test(SuggestionReview::class)->assertForbidden();
        $this->get('/')->assertDontSee('suggestions waiting for review');

        $this->actingAs($this->admin());
        $this->get('/admin/products')->assertOk()->assertSee('2 suggestions waiting for review')->assertSee('Review now')
            ->assertSee('CATALOG TEAM')->assertDontSee('My allergens');
        $this->get('/admin/suggestions')->assertOk()->assertSee('Suggested product')->assertSee('Check and add product');
    }

    public function test_team_sign_in_lands_in_the_admin_area_and_shoppers_on_the_site(): void
    {
        $admin = $this->admin();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin/products');
        $this->post('/logout');
        $this->post('/login', ['email' => User::factory()->create()->email, 'password' => 'password'])->assertRedirect('/');
    }

    public function test_a_suggested_product_is_only_published_after_the_team_checks_and_saves_it(): void
    {
        $admin = $this->admin();
        $suggestion = Suggestion::factory()->create(['barcode' => '4901234567894', 'allergen_codes' => ['milk', 'egg']]);
        $this->actingAs($admin);
        $form = Livewire::withQueryParams(['suggestion' => $suggestion->id])->test(ProductManager::class)
            ->assertSet('editing', true)->assertSet('form.barcode', '4901234567894')->assertSet('form.name_en', 'Suggested product')
            ->assertSet('form.information_status', 'unknown')->assertSet('form.is_demo', false)
            ->assertSet('allergenIds', Allergen::whereIn('code', ['milk', 'egg'])->pluck('id')->all())
            ->assertSee('Filled in from a shopper suggestion.');
        $this->assertSame(0, Product::count());
        $form->set('form.information_status', 'recorded')->set('form.source', 'Package label')->set('form.verified_at', now()->toDateString())
            ->call('save')->assertHasNoErrors();
        $product = Product::where('barcode', '4901234567894')->firstOrFail();
        $suggestion->refresh();
        $this->assertSame(['approved', $product->id, $admin->id], [$suggestion->status, $suggestion->product_id, $suggestion->reviewed_by]);
        $this->actingAs($suggestion->user)->get('/suggest')->assertSee('Added')->assertSee(route('catalog.show', $product));
    }

    public function test_editing_another_product_does_not_approve_an_open_suggestion(): void
    {
        $this->seed();
        $suggestion = Suggestion::factory()->create();
        $this->actingAs($this->admin());
        Livewire::withQueryParams(['suggestion' => $suggestion->id])->test(ProductManager::class)
            ->call('edit', Product::firstOrFail()->id)->call('save')->assertHasNoErrors();
        $this->assertSame('pending', $suggestion->fresh()->status);
    }

    public function test_the_team_can_add_a_suggested_allergen_or_dismiss_a_suggestion(): void
    {
        $admin = $this->admin();
        $celery = Suggestion::factory()->allergen()->create();
        $unwanted = Suggestion::factory()->create();
        $this->actingAs($admin);
        Livewire::test(SuggestionReview::class)
            ->call('startAllergen', $celery->id)->assertSet('allergenForm.code', 'celery')->assertSet('allergenForm.name_ja', 'セロリ')
            ->set('allergenForm.code', 'milk')->call('addAllergen')->assertHasErrors('allergenForm.code')
            ->set('allergenForm.code', 'celery')->call('addAllergen')->assertHasNoErrors()
            ->call('dismiss', $unwanted->id);
        $this->assertDatabaseHas('allergens', ['code' => 'celery', 'name_ja' => 'セロリ', 'name_en' => 'Celery']);
        $this->assertSame('approved', $celery->fresh()->status);
        $this->assertSame(['dismissed', $admin->id], [$unwanted->fresh()->status, $unwanted->fresh()->reviewed_by]);
        $this->assertSame(0, Product::count());
        $this->get('/admin/products')->assertDontSee('waiting for review');
        $this->get('/settings')->assertSee('セロリ');
    }

    public function test_deleting_an_account_deletes_its_suggestions(): void
    {
        $user = User::factory()->create();
        Suggestion::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->delete('/account', ['password' => 'password'])->assertRedirect('/');
        $this->assertSame(0, Suggestion::count());
    }
}
