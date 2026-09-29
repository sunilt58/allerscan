<?php

namespace App\Livewire;

use App\Models\Allergen;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class ProductManager extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $editing = false;

    #[Locked]
    public ?int $productId = null;

    public array $form = [];

    public array $allergenIds = [];

    public function boot(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function edit(?int $id = null): void
    {
        $this->resetValidation();
        $product = $id ? Product::with('allergens')->findOrFail($id) : null;
        $this->productId = $id;
        $this->form = $product ? $product->only(['barcode', 'name_ja', 'name_en', 'size', 'category', 'icon', 'information_status', 'source', 'is_demo']) + ['verified_at' => $product->verified_at?->format('Y-m-d')] : [
            'barcode' => '', 'name_ja' => '', 'name_en' => '', 'size' => '', 'category' => 'Food',
            'icon' => '📦', 'information_status' => 'unknown',
            'source' => '', 'verified_at' => '', 'is_demo' => true,
        ];
        $this->allergenIds = $product ? $product->allergens->pluck('id')->all() : [];
        $this->editing = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.barcode' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('products', 'barcode')->ignore($this->productId)],
            'form.name_ja' => ['required', 'string', 'max:255'],
            'form.name_en' => ['required', 'string', 'max:255'],
            'form.size' => ['required', 'string', 'max:100'],
            'form.category' => ['required', Rule::in(['Food', 'Drinks', 'Snacks', 'Daily'])],
            'form.icon' => ['required', Rule::in(['📦', '🥛', '🍞', '🍙', '🍵', '🍫', '🍜', '🍎', '🍪', '🧃', '🥗', '🧴'])],
            'form.information_status' => ['required', Rule::in(['unknown', 'recorded'])],
            'form.source' => ['nullable', 'required_if:form.information_status,recorded', 'string', 'max:255'],
            'form.verified_at' => ['nullable', 'required_if:form.information_status,recorded', 'date', 'before_or_equal:today'],
            'form.is_demo' => ['required', 'boolean'],
            'allergenIds' => ['array'],
            'allergenIds.*' => ['integer', 'distinct', 'exists:allergens,id'],
        ]);
        // Livewire bypasses ConvertEmptyStringsToNull, and MySQL rejects '' for a DATE column.
        foreach (['source', 'verified_at'] as $optionalField) {
            $validated['form'][$optionalField] = $validated['form'][$optionalField] ?: null;
        }
        DB::transaction(function () use ($validated) {
            $product = $this->productId ? Product::lockForUpdate()->findOrFail($this->productId) : new Product;
            if (! $product->exists) {
                $product->price = 0;
                $product->tax_rate = 8;
            }
            $product->fill($validated['form']);
            $product->save();
            $product->allergens()->sync($validated['allergenIds']);
        });
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
            'form.size' => __('Size'),
            'form.category' => __('Category'),
            'form.icon' => __('Icon'),
            'form.information_status' => __('Information status'),
            'form.source' => __('Source'),
            'form.verified_at' => __('Checked date'),
            'form.is_demo' => __('Fictional demo product'),
            'allergenIds' => __('Allergen information'),
            'allergenIds.*' => __('Allergen information'),
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
        $products = Product::with('allergens')->where(function ($q) {
            $q->where('name_ja', 'like', '%'.$this->search.'%')->orWhere('name_en', 'like', '%'.$this->search.'%')->orWhere('barcode', 'like', '%'.$this->search.'%');
        })->orderByDesc('is_active')->orderBy('id')->paginate(15);

        return view('livewire.product-manager', ['products' => $products, 'allergens' => Allergen::all()]);
    }
}
