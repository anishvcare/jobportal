<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EducationLevel extends Model
{
    protected $fillable = ['name', 'slug', 'rank', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'rank' => 'integer'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
