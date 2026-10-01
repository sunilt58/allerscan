<?php

namespace App\Livewire;

use App\Models\Allergen;
use App\Models\Suggestion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SuggestionReview extends Component
{
    #[Locked]
    public ?int $allergenSuggestionId = null;

    /**
     * @var array{code: string, name_ja: string, name_en: string}
     */
    public array $allergenForm = ['code' => '', 'name_ja' => '', 'name_en' => ''];

    public function boot(): void
    {
        abort_unless(auth()->user()?->can('manage-catalog'), 403);
    }

    public function dismiss(int $id): void
    {
        DB::transaction(function () use ($id) {
            Suggestion::pending()->lockForUpdate()->findOrFail($id)->markReviewed('dismissed', auth()->user());
        });
        if ($this->allergenSuggestionId === $id) {
            $this->allergenSuggestionId = null;
        }
        session()->flash('message', __('Suggestion dismissed.'));
    }

    public function startAllergen(int $id): void
    {
        $this->resetValidation();
        $suggestion = Suggestion::pending()->where('type', 'allergen')->findOrFail($id);
        $this->allergenSuggestionId = $id;
        $this->allergenForm = [
            'code' => Str::of((string) $suggestion->name_en)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString(),
            'name_ja' => (string) $suggestion->name_ja,
            'name_en' => (string) $suggestion->name_en,
        ];
    }

    public function addAllergen(): void
    {
        $validated = $this->validate([
            'allergenForm.code' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:allergens,code'],
            'allergenForm.name_ja' => ['required', 'string', 'max:100'],
            'allergenForm.name_en' => ['required', 'string', 'max:100'],
        ]);
        DB::transaction(function () use ($validated) {
            $suggestion = Suggestion::pending()->where('type', 'allergen')->lockForUpdate()->findOrFail($this->allergenSuggestionId);
            Allergen::create($validated['allergenForm']);
            $suggestion->markReviewed('approved', auth()->user());
        });
        $this->allergenSuggestionId = null;
        session()->flash('message', __('Allergen added to the list.'));
    }

    /**
     * Field names used in validation messages, in the current display language.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'allergenForm.code' => __('Code'),
            'allergenForm.name_ja' => __('Japanese name'),
            'allergenForm.name_en' => __('English name'),
        ];
    }

    public function render(): View
    {
        return view('livewire.suggestion-review', [
            'pending' => Suggestion::pending()->with('user')->oldest('id')->get(),
            'reviewed' => Suggestion::where('status', '!=', 'pending')->with(['user', 'product'])->latest('reviewed_at')->limit(20)->get(),
            'allergenNames' => Allergen::all()->keyBy('code'),
        ]);
    }
}
