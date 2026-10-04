<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const SAVED_PRODUCT_LIMIT = 50;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // App sign-in tokens are polymorphic rows without a foreign key, so they are removed explicitly.
        static::deleting(fn (User $user) => $user->tokens()->delete());
    }

    /**
     * Create a shopper account from validated registration input.
     *
     * @param  array{name: string, email: string, password: string}  $validated
     */
    public static function createShopper(array $validated): self
    {
        $user = new self;
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'shopper',
        ])->save();

        return $user;
    }

    /**
     * Replace the synced allergens and saved products. Unknown codes and missing products are dropped.
     *
     * @param  list<string>  $allergenCodes
     * @param  list<int>  $productIds
     */
    public function syncShopperPreferences(array $allergenCodes, array $productIds): void
    {
        DB::transaction(function () use ($allergenCodes, $productIds) {
            $this->allergens()->sync(Allergen::whereIn('code', $allergenCodes)->pluck('id'));
            $this->savedProducts()->sync(Product::whereIn('id', $productIds)->pluck('id'));
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Allergens the shopper wants highlighted.
     *
     * @return BelongsToMany<Allergen, $this>
     */
    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class);
    }

    /**
     * Products the shopper saved to come back to.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function savedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * Products and allergens the shopper asked the team to add.
     *
     * @return HasMany<Suggestion, $this>
     */
    public function suggestions(): HasMany
    {
        return $this->hasMany(Suggestion::class);
    }

    /**
     * The synced preferences in the shape the browser stores them.
     *
     * @return array{allergens: list<string>, savedProducts: list<int>}
     */
    public function shopperPreferences(): array
    {
        return [
            'allergens' => $this->allergens()->orderBy('allergens.id')->pluck('code')->all(),
            'savedProducts' => $this->savedProducts()->orderBy('products.id')->pluck('products.id')->all(),
        ];
    }
}
