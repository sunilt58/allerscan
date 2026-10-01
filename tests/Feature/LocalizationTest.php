<?php

namespace Tests\Feature;

use App\Livewire\ProductManager;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_japanese_is_the_default_language(): void
    {
        $this->seed();

        $this->get('/')->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('医療・食事の助言ではありません。')
            ->assertSeeInOrder(['<h3>まいにちミルク</h3>', 'Everyday milk'], false)
            ->assertSee('"Save product":'.json_encode('商品を保存'), false)
            ->assertSee(route('language', 'en'));
    }

    public function test_visitors_can_switch_language_and_it_is_remembered(): void
    {
        $this->seed();
        $milk = Product::where('barcode', 'DEMO001')->firstOrFail();

        $this->from('/scan')->get('/language/en')
            ->assertRedirect('/scan')
            ->assertPlainCookie('locale', 'en');

        $this->withUnencryptedCookie('locale', 'en')->get(route('catalog.show', $milk))->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('<h1>Everyday milk</h1>', false)
            ->assertSee('Recorded allergens')
            ->assertSee(route('language', 'ja'));

        $this->from('/')->get('/language/ja')->assertPlainCookie('locale', 'ja');
    }

    public function test_unsupported_languages_are_rejected_and_ignored(): void
    {
        $this->get('/language/fr')->assertNotFound();
        $this->withUnencryptedCookie('locale', 'fr')->get('/')->assertOk()->assertSee('<html lang="ja">', false);
    }

    public function test_server_messages_follow_the_chosen_language(): void
    {
        $this->seed();

        $this->get('/scan/lookup?barcode=MISSING')->assertSessionHasErrors([
            'barcode' => 'このバーコードの商品は登録されていません。商品名で検索してください。',
        ]);

        app()->setLocale('ja');
        $this->actingAs(User::where('email', 'admin@allerscan.test')->firstOrFail());
        Livewire::test(ProductManager::class)->call('edit')->set('form.barcode', '')->call('save')
            ->assertHasErrors(['form.barcode' => 'バーコードを入力してください。']);
    }

    public function test_every_interface_string_has_a_japanese_translation(): void
    {
        $translations = json_decode(File::get(lang_path('ja.json')), true, flags: JSON_THROW_ON_ERROR);
        $sources = collect(File::allFiles(resource_path('views')))->merge(File::allFiles(app_path()))
            ->map(fn ($file) => $file->getContents())->implode("\n");
        preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)*)'/", $sources, $matches);

        $translationKeys = array_filter($matches[1], fn (string $key): bool => ! str_contains($key, '.') || str_ends_with($key, '.') || str_contains($key, ' '));
        $missing = array_diff(array_unique($translationKeys), array_keys($translations));

        $this->assertSame([], array_values($missing));
    }
}
