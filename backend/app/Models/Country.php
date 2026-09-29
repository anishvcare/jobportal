<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = ['name', 'iso2', 'iso3', 'has_states', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['has_states' => 'boolean', 'is_active' => 'boolean'];
    }

    public function states(): HasMany
    {
        return $this->hasMany(State::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
