<?php

namespace Tests\Feature;

use App\Jobs\ResearchUnregisteredProduct;
use App\Livewire\AiLookupStatus;
use App\Livewire\ProductManager;
use App\Models\AiLookup;
use App\Models\Allergen;
use App\Models\Product;
use App\Models\User;
use App\Services\UnregisteredProductLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AiProductResearchTest extends TestCase
{
    use RefreshDatabase;

    private const BARCODE = '4902201174725';

    /**
     * These tests assert English copy, and never reach the real Gemini API: every request is faked.
     */
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.locale' => 'en',
            'allerscan.ai.enabled' => true,
            'services.gemini.key' => 'test-key',
        ]);
        app()->setLocale('en');
        Http::preventStrayRequests();
        $this->seed();
    }

    /**
     * A generateContent response whose text is the given JSON, wrapped in a fence as models often do.
     *
     * @param  array<string, mixed>  $answer
     * @param  list<array{uri: string, title: string}>  $sources
     */
    private function geminiAnswer(array $answer, array $sources = []): array
    {
        return ['candidates' => [[
            'content' => ['parts' => [['text' => "```json\n".json_encode($answer, JSON_UNESCAPED_UNICODE)."\n```"]]],
            'groundingMetadata' => ['groundingChunks' => array_map(fn (array $source) => ['web' => $source], $sources)],
        ]]];
    }

    public function test_an_unregistered_scan_starts_ai_research_once_per_barcode(): void
    {
        Queue::fake();

        $this->get('/scan/lookup?barcode='.self::BARCODE)->assertRedirect(route('scan.pending', ['barcode' => self::BARCODE]));
        $this->get('/scan/lookup?barcode='.self::BARCODE)->assertRedirect(route('scan.pending', ['barcode' => self::BARCODE]));

        $this->assertSame(1, AiLookup::where('barcode', self::BARCODE)->count());
        Queue::assertPushed(ResearchUnregisteredProduct::class, 1);
        $this->get(route('scan.pending', ['barcode' => self::BARCODE]))->assertOk()
            ->assertSee('AI is looking up this product…')->assertSee('Information found by AI is an estimate.');
    }

    public function test_only_real_product_barcodes_are_sent_to_ai(): void
    {
        Queue::fake();
        foreach (['MISSING', 'DEMO999', '4902201174726', '123'] as $barcode) {
            $this->from('/scan')->get('/scan/lookup?barcode='.$barcode)->assertRedirect('/scan')->assertSessionHasErrors('barcode');
        }
        $this->assertSame(0, AiLookup::count());
        $this->assertTrue(UnregisteredProductLookup::isRetailBarcode('49012347'));
        $this->assertTrue(UnregisteredProductLookup::isRetailBarcode('036000291452'));
    }

    public function test_without_a_key_or_over_the_daily_limit_a_scan_shows_the_usual_not_found_message(): void
    {
        Queue::fake();
        config(['services.gemini.key' => null]);
        $this->from('/scan')->get('/scan/lookup?barcode=4900000000001')->assertRedirect('/scan')->assertSessionHasErrors('barcode');

        config(['services.gemini.key' => 'test-key', 'allerscan.ai.per_person_daily_limit' => 2]);
        $this->get('/scan/lookup?barcode=4900000000016')->assertRedirect(route('scan.pending', ['barcode' => '4900000000016']));
        $this->get('/scan/lookup?barcode=4900000000023')->assertRedirect(route('scan.pending', ['barcode' => '4900000000023']));
        $this->from('/scan')->get('/scan/lookup?barcode=4900000000030')->assertRedirect('/scan')->assertSessionHasErrors('barcode');

        $this->assertSame(['4900000000016', '4900000000023'], AiLookup::orderBy('id')->pluck('barcode')->all());
        Queue::assertPushed(ResearchUnregisteredProduct::class, 2);
    }

    public function test_research_saves_an_ai_draft_with_only_allergens_on_our_list(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiAnswer([
            'found' => true, 'matched_barcode' => self::BARCODE, 'name_ja' => 'キットカット ミニ', 'name_en' => 'KitKat Mini', 'size' => '12枚',
            'category' => 'Snacks', 'allergen_codes' => ['milk', 'wheat', 'unicorn'], 'evidence' => '一部に乳成分・小麦を含む',
        ], [['uri' => 'https://www.nestle.co.jp/kitkat', 'title' => 'nestle.co.jp']]))]);
        $lookup = AiLookup::create(['barcode' => self::BARCODE]);

        ResearchUnregisteredProduct::dispatchSync($lookup);

        $product = Product::where('barcode', self::BARCODE)->firstOrFail();
        $this->assertSame('ai_draft', $product->information_status);
        $this->assertSame(['KitKat Mini', 'Snacks', '🍪'], [$product->name_en, $product->category, $product->icon]);
        $this->assertEqualsCanonicalizing(['milk', 'wheat'], $product->allergens->pluck('code')->all());
        $this->assertSame('AI推定（Gemini・Web検索）: 一部に乳成分・小麦を含む', $product->source);
        $this->assertSame([['title' => 'nestle.co.jp', 'url' => 'https://www.nestle.co.jp/kitkat']], $product->ai_sources);
        $this->assertSame('found', $lookup->fresh()->status);
        $this->assertNotNull(Product::active()->matching('きっとかっと')->first());
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'models/gemini-2.5-flash:generateContent')
            && $request->hasHeader('x-goog-api-key', 'test-key')
            && isset($request['tools'][0]['google_search'])
            && str_contains($request['contents'][0]['parts'][0]['text'], self::BARCODE));
    }

    public function test_when_neither_gemini_nor_open_food_facts_knows_the_barcode_nothing_is_registered(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiAnswer(['found' => false])),
            'world.openfoodfacts.org/*' => Http::response(['status' => 0]),
        ]);
        $lookup = AiLookup::create(['barcode' => self::BARCODE]);

        ResearchUnregisteredProduct::dispatchSync($lookup);

        $this->assertSame('not_found', $lookup->fresh()->status);
        $this->assertFalse(Product::where('barcode', self::BARCODE)->exists());
        $this->get(route('scan.pending', ['barcode' => self::BARCODE]))->assertOk()
            ->assertSee('AI couldn’t find this product on the web.')->assertSee('Sign in to send a photo of the ingredients label');
    }

    public function test_a_gemini_answer_for_a_different_barcode_or_without_sources_is_not_used(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiAnswer(['found' => true, 'matched_barcode' => '4902201999999', 'name_ja' => '別の商品', 'name_en' => 'Another product'], [['uri' => 'https://shop.example/a', 'title' => 'shop.example']]))
                ->push($this->geminiAnswer(['found' => true, 'matched_barcode' => self::BARCODE, 'name_ja' => '出典なし', 'name_en' => 'No source'])),
            'world.openfoodfacts.org/*' => Http::response(['status' => 0]),
        ]);

        foreach ([self::BARCODE, self::BARCODE] as $attempt => $barcode) {
            $lookup = AiLookup::updateOrCreate(['barcode' => $barcode], ['status' => 'pending']);
            ResearchUnregisteredProduct::dispatchSync($lookup);
            $this->assertSame('not_found', $lookup->fresh()->status, "attempt {$attempt}");
        }
        $this->assertFalse(Product::where('barcode', self::BARCODE)->exists());
    }

    public function test_when_gemini_fails_an_open_food_facts_match_is_saved_as_an_ai_draft(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota exceeded']], 429),
            'world.openfoodfacts.org/*' => Http::response(['status' => 1, 'product' => ['product_name' => 'KitKat', 'quantity' => '12 pieces', 'allergens_tags' => ['en:milk']]]),
        ]);
        $lookup = AiLookup::create(['barcode' => self::BARCODE]);

        ResearchUnregisteredProduct::dispatchSync($lookup);

        $product = Product::where('barcode', self::BARCODE)->firstOrFail();
        $this->assertSame(['ai_draft', 'KitKat', 'KitKat', 'AI推定（Open Food Facts の下書き）'], [$product->information_status, $product->name_ja, $product->name_en, $product->source]);
        $this->assertSame(['milk'], $product->allergens->pluck('code')->all());
        $this->assertSame('found', $lookup->fresh()->status);
    }

    public function test_a_product_the_team_recorded_is_never_overwritten_by_ai(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiAnswer([
            'found' => true, 'matched_barcode' => 'DEMO001', 'name_ja' => '別の名前', 'name_en' => 'Other name', 'allergen_codes' => [],
        ], [['uri' => 'https://shop.example/item', 'title' => 'shop.example']]))]);
        $recorded = Product::where('barcode', 'DEMO001')->firstOrFail();
        $lookup = AiLookup::create(['barcode' => 'DEMO001']);

        ResearchUnregisteredProduct::dispatchSync($lookup);

        $this->assertSame(['recorded', 'Everyday milk'], [$recorded->fresh()->information_status, $recorded->fresh()->name_en]);
        $this->assertSame($recorded->id, $lookup->fresh()->product_id);
    }

    public function test_ai_drafts_show_a_warning_and_are_never_shown_as_allergen_free(): void
    {
        $product = Product::factory()->aiDraft()->create(['barcode' => self::BARCODE, 'name_en' => 'AI found snack', 'ai_sources' => [['title' => 'shop.example', 'url' => 'https://shop.example/item']]]);

        $this->get(route('catalog.show', $product))->assertOk()
            ->assertSee('AI estimate (unconfirmed)')
            ->assertSee('AI didn’t find any allergens. This does not mean the product is allergen-free.')
            ->assertSee('https://shop.example/item')
            ->assertDontSee('None recorded');
        $this->get('/?q=AI+found+snack')->assertSee('None found by AI')->assertDontSee('None recorded');
        $this->getJson('/api/v1/products/barcode/'.self::BARCODE)->assertOk()
            ->assertJsonPath('data.information_status', 'ai_draft')
            ->assertJsonPath('data.ai_sources.0.url', 'https://shop.example/item');
    }

    public function test_the_status_checks_while_ai_works_and_then_opens_the_product(): void
    {
        $lookup = AiLookup::create(['barcode' => self::BARCODE, 'status' => 'searching']);
        Livewire::test(AiLookupStatus::class, ['barcode' => self::BARCODE])->assertSee('AI is looking up this product…')->assertNoRedirect();

        $product = Product::factory()->aiDraft()->create(['barcode' => self::BARCODE]);
        $lookup->update(['status' => 'found', 'product_id' => $product->id]);

        Livewire::test(AiLookupStatus::class, ['barcode' => self::BARCODE])->assertRedirect(route('catalog.show', $product));
        $this->get(route('scan.pending', ['barcode' => self::BARCODE]))->assertRedirect(route('catalog.show', $product));
    }

    public function test_a_signed_in_shopper_can_send_a_label_photo_that_ai_reads_and_then_deletes(): void
    {
        Storage::fake('local');
        Http::fake(['generativelanguage.googleapis.com/*gemini-3.8-flash*' => Http::response($this->geminiAnswer([
            'is_label' => true, 'readable' => true, 'name_ja' => 'わさびせんべい', 'allergen_codes' => ['wheat', 'soy'],
            'statement' => '（一部に小麦・大豆を含む）',
        ]))]);
        AiLookup::factory()->notFound()->create(['barcode' => self::BARCODE]);
        $photo = UploadedFile::fake()->image('label.jpg', 800, 600);

        $this->post(route('scan.label', ['barcode' => self::BARCODE]), ['photo' => $photo])->assertRedirect('/login');
        $this->actingAs(User::factory()->create())
            ->post(route('scan.label', ['barcode' => self::BARCODE]), ['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('photo');
        $this->post(route('scan.label', ['barcode' => self::BARCODE]), ['photo' => $photo])
            ->assertRedirect(route('scan.pending', ['barcode' => self::BARCODE]));

        $product = Product::where('barcode', self::BARCODE)->firstOrFail();
        $this->assertSame(['ai_draft', 'わさびせんべい'], [$product->information_status, $product->name_ja]);
        $this->assertEqualsCanonicalizing(['wheat', 'soy'], $product->allergens->pluck('code')->all());
        $this->assertSame('AI推定（Gemini・ラベル写真）: （一部に小麦・大豆を含む）', $product->source);
        $this->assertSame('found', AiLookup::where('barcode', self::BARCODE)->value('label_status'));
        $this->assertSame([], Storage::disk('local')->allFiles());
        Http::assertSent(fn (Request $request) => isset($request['contents'][0]['parts'][0]['inline_data']['data'])
            && $request['generationConfig']['responseMimeType'] === 'application/json');
    }

    public function test_the_team_is_told_about_ai_drafts_and_confirms_them_after_checking_the_label(): void
    {
        $admin = User::where('email', 'admin@allerscan.test')->firstOrFail();
        $draft = Product::factory()->aiDraft()->create(['barcode' => self::BARCODE, 'name_ja' => 'AIの商品']);
        $this->actingAs($admin);

        $this->get('/admin/products')->assertOk()->assertSee('1 AI product waiting for review');
        Livewire::test(ProductManager::class)->set('filter', 'ai')->assertSee('AIの商品')->assertDontSee('まいにちミルク');
        Livewire::test(ProductManager::class)->call('edit', $draft->id)->assertSee('Registered by AI.')
            ->set('form.information_status', 'recorded')->set('form.source', 'Package label')->set('form.verified_at', now()->toDateString())
            ->set('allergenIds', [Allergen::where('code', 'milk')->value('id')])
            ->call('save')->assertHasNoErrors();

        $this->assertSame('recorded', $draft->fresh()->information_status);
        $this->get('/admin/products')->assertDontSee('AI product waiting for review');
    }
}
