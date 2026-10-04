<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Suggestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * A product suggestion needs a barcode and one name; an allergen suggestion needs one name.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['type' => ['required', Rule::in(['product', 'allergen'])], 'note' => ['nullable', 'string', 'max:500']];

        return $rules + ($this->input('type') === 'product' ? [
            'barcode' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+$/'],
            'name_ja' => ['nullable', 'required_without:name_en', 'string', 'max:255'],
            'name_en' => ['nullable', 'required_without:name_ja', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['Food', 'Drinks', 'Snacks', 'Daily'])],
            'allergen_codes' => ['sometimes', 'array', 'max:100'],
            'allergen_codes.*' => ['string', 'distinct', 'exists:allergens,code'],
        ] : [
            'name_ja' => ['nullable', 'required_without:name_en', 'string', 'max:100'],
            'name_en' => ['nullable', 'required_without:name_ja', 'string', 'max:100'],
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                if ($this->input('type') === 'product' && Product::active()->where('barcode', $this->input('barcode'))->exists()) {
                    $validator->errors()->add('barcode', __('This product is already in our catalog. Try looking it up on the Scan page.'));
                }
                if ($this->user()->suggestions()->pending()->count() >= Suggestion::PENDING_LIMIT_PER_USER) {
                    $validator->errors()->add('type', __('You have many suggestions waiting for review. Please wait until our team has looked at them.'));
                }
            },
        ];
    }

    /**
     * The validated fields to store, with the status a new suggestion starts in.
     *
     * @return array<string, mixed>
     */
    public function suggestion(): array
    {
        return $this->validated() + ['status' => 'pending'];
    }
}
