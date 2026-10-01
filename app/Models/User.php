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

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
