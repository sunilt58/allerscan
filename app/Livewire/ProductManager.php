<?php

namespace App\Livewire;

use App\Models\Allergen;
use App\Models\Product;
use App\Models\Suggestion;
use App\Services\OpenFoodFacts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ProductManager extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    /**
     * "ai" lists only products registered by AI that wait for the team's check.
     */
    #[Url]
    public string $filter = '';

    /**
     * Pages the AI draft being edited was based on, shown for the review.
     *
     * @var list<array{title: string, url: string}>
     */
    #[Locked]
    public array $aiSources = [];

    public bool $editing = false;

    #[Locked]
    public ?int $productId = null;

    public array $form = [];

    public array $allergenIds = [];

    /**
     * A newly chosen product photo, uploaded temporarily until the product is saved.
     */
    public ?TemporaryUploadedFile $photo = null;

    public bool $removePhoto = false;

    #[Locked]
    public ?string $currentPhotoUrl = null;

    /**
     * The shopper suggestion this form was opened from, marked approved when the product is saved.
     */
    #[Locked]
    public ?int $suggestionId = null;

    /**
     * The last Open Food Facts lookup, shown under the barcode field.
     *
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $openFoodFacts = null;

    public function mount(): void
    {
        if (request()->integer('suggestion') > 0) {
            $this->reviewSuggestion(request()->integer('suggestion'));
        } elseif (preg_match('/^[A-Za-z0-9-]{1,50}$/', (string) request()->query('barcode'))) {
            $this->openBarcode(request()->query('barcode'));
        }
    }

    /**
     * A scanned or typed barcode opens its product, including an archived one, or a new product form for it.
     */
    public function openBarcode(string $barcode): void
    {
        $product = Product::where('barcode', $barcode)->first();
        if ($product) {
            $this->edit($product->id);
            session()->flash('message', $product->is_active
                ? __('This barcode is already registered. Opened it for editing.')
                : __('This barcode belongs to an archived product. Opened it for editing; restore it from the list to show it again.'));

            return;
        }
        $this->edit();
        $this->form['barcode'] = $barcode;
        session()->flash('message', __('This barcode is not registered yet. Add the product below, or fill it in from Open Food Facts.'));
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function edit(?int $id = null): void
    {
        $this->resetValidation();
        $this->suggestionId = null;
        $this->openFoodFacts = null;
        $this->photo = null;
        $this->removePhoto = false;
        $product = $id ? Product::with('allergens')->findOrFail($id) : null;
        $this->productId = $id;
        $this->form = $product ? $product->only(['barcode', 'name_ja', 'name_en', 'keywords', 'size', 'category', 'icon', 'information_status', 'source', 'is_demo']) + ['verified_at' => $product->verified_at?->format('Y-m-d')] : [
            'barcode' => '', 'name_ja' => '', 'name_en' => '', 'keywords' => '', 'size' => '', 'category' => 'Food',
            'icon' => '📦', 'information_status' => 'unknown',
            'source' => '', 'verified_at' => '', 'is_demo' => false,
        ];
        $this->allergenIds = $product ? $product->allergens->pluck('id')->all() : [];
        $this->currentPhotoUrl = $product?->image_url;
        $this->aiSources = $product?->ai_sources ?? [];
        $this->editing = true;
    }

    /**
     * Open the add-product form filled in from a pending suggestion, for the team to check against the label.
     */
    public function reviewSuggestion(int $id): void
    {
        $suggestion = Suggestion::pending()->where('type', 'product')->find($id);
        if (! $suggestion) {
            session()->flash('message', __('That suggestion has already been reviewed.'));

            return;
        }
        $this->edit();
        $this->form = [
            'barcode' => (string) $suggestion->barcode,
            'name_ja' => (string) $suggestion->name_ja,
            'name_en' => (string) $suggestion->name_en,
            'size' => (string) $suggestion->size,
            'category' => $suggestion->category ?: 'Food',
        ] + $this->form;
        $this->allergenIds = Allergen::whereIn('code', $suggestion->allergen_codes ?? [])->pluck('id')->all();
        $this->suggestionId = $suggestion->id;
    }

    /**
     * Fill empty fields from Open Food Facts as a draft. The status is set to Unknown so the team
     * must check the package label before the record counts as recorded.
     */
    public function fetchFromOpenFoodFacts(OpenFoodFacts $openFoodFacts): void
    {
        $this->validateOnly('form.barcode', ['form.barcode' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+$/']]);
        $result = $openFoodFacts->lookup($this->form['barcode']);
        $this->openFoodFacts = $result ?? ['found' => null];
        if (! ($result['found'] ?? false)) {
            return;
        }
        foreach (['name_ja', 'name_en', 'size'] as $field) {
            if (blank($this->form[$field] ?? null) && filled($result[$field])) {
                $this->form[$field] = mb_substr($result[$field], 0, $field === 'size' ? 100 : 255);
            }
        }
        $this->allergenIds = array_values(array_unique(array_merge(
            array_map('intval', $this->allergenIds),
            Allergen::whereIn('code', $result['allergen_codes'])->pluck('id')->all(),
        )));
        $this->form['information_status'] = 'unknown';
    }

    /**
     * Check a chosen photo straight away, before the rest of the form is saved.
     */
    public function updatedPhoto(): void
    {
        $this->validateOnly('photo', ['photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);
    }

    /**
     * Drop a newly chosen photo and remove the saved one when the product is saved.
     */
    public function clearPhoto(): void
    {
        $this->photo = null;
        $this->removePhoto = true;
        $this->resetValidation('photo');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.barcode' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('products', 'barcode')->ignore($this->productId)],
            'form.name_ja' => ['required', 'string', 'max:255'],
            'form.name_en' => ['required', 'string', 'max:255'],
            'form.keywords' => ['nullable', 'string', 'max:255'],
            'form.size' => ['required', 'string', 'max:100'],
            'form.category' => ['required', Rule::in(['Food', 'Drinks', 'Snacks', 'Daily'])],
            'form.icon' => ['required', Rule::in(['📦', '🥛', '🍞', '🍙', '🍵', '🍫', '🍜', '🍎', '🍪', '🧃', '🥗', '🧴'])],
            'form.information_status' => ['required', Rule::in(['unknown', 'ai_draft', 'recorded'])],
            'form.source' => ['nullable', 'required_if:form.information_status,recorded', 'string', 'max:255'],
            'form.verified_at' => ['nullable', 'required_if:form.information_status,recorded', 'date', 'before_or_equal:today'],
            'form.is_demo' => ['required', 'boolean'],
            'allergenIds' => ['array'],
            'allergenIds.*' => ['integer', 'distinct', 'exists:allergens,id'],
            // SVG is not accepted: it can carry scripts.
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        // Livewire bypasses ConvertEmptyStringsToNull, and MySQL rejects '' for a DATE column.
        foreach (['keywords', 'source', 'verified_at'] as $optionalField) {
            $validated['form'][$optionalField] = $validated['form'][$optionalField] ?: null;
        }
        $newPhoto = $this->photo?->store('products', 'public');
        try {
            $oldPhoto = DB::transaction(function () use ($validated, $newPhoto) {
                $product = $this->productId ? Product::lockForUpdate()->findOrFail($this->productId) : new Product;
                if (! $product->exists) {
                    $product->price = 0;
                    $product->tax_rate = 8;
                }
                $oldPhoto = $product->image_path;
                $product->fill($validated['form']);
                if ($newPhoto || $this->removePhoto) {
                    $product->image_path = $newPhoto;
                }
                $product->save();
                $product->allergens()->sync($validated['allergenIds']);
                if ($this->suggestionId) {
                    Suggestion::pending()->lockForUpdate()->find($this->suggestionId)?->markReviewed('approved', auth()->user(), $product);
                }

                return $product->image_path === $oldPhoto ? null : $oldPhoto;
            });
        } catch (\Throwable $exception) {
            if ($newPhoto) {
                Storage::disk('public')->delete($newPhoto);
            }
            throw $exception;
        }
        // The replaced or removed photo is deleted only once the new record is saved.
        if ($oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }
        $this->photo = null;
        $this->removePhoto = false;
        $this->suggestionId = null;
        $this->editing = false;
        session()->flash('message', __('Product saved.'));
    }

    /**
     * Field names used in validation messages, in the current display language.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'form.barcode' => __('Barcode'),
            'form.name_ja' => __('Japanese name'),
            'form.name_en' => __('English name'),
            'form.keywords' => __('Search keywords'),
            'form.size' => __('Size'),
            'form.category' => __('Category'),
            'form.icon' => __('Icon'),
            'form.information_status' => __('Information status'),
            'form.source' => __('Source'),
            'form.verified_at' => __('Checked date'),
            'form.is_demo' => __('Sample record'),
            'allergenIds' => __('Allergen information'),
            'allergenIds.*' => __('Allergen information'),
            'photo' => __('Product photo'),
        ];
    }

    public function toggleActive(int $id): void
    {
        DB::transaction(function () use ($id) {
            $product = Product::lockForUpdate()->findOrFail($id);
            $product->update(['is_active' => ! $product->is_active]);
        });
    }

    public function render()
    {
        $products = Product::with('allergens')->matching($this->search)
            ->when($this->filter === 'ai', fn ($query) => $query->where('information_status', 'ai_draft'))
            ->orderByDesc('is_active')->orderBy('id')->paginate(15);

        return view('livewire.product-manager', ['products' => $products, 'allergens' => Allergen::all()]);
    }
}
