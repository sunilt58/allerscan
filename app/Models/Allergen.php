<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Allergen extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * The allergen name in the current display language.
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => app()->getLocale() === 'en' ? $this->name_en : $this->name_ja);
    }
}
