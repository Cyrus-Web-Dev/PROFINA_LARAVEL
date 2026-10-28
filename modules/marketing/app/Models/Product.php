<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = ['sku', 'name', 'unit', 'description', 'base_price', 'cost_price', 'min_order_qty', 'is_active'];

    // HPP tidak pernah ikut serial otomatis; controller menambahkannya HANYA untuk pemegang izin cost.view.
    protected $hidden = ['cost_price'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }
}
