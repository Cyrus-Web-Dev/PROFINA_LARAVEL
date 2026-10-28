<?php

namespace App\Http\Controllers;

use App\Models\PriceLevel;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class PriceLevelController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['code' => 'required|alpha_dash|max:20|unique:price_levels,code', 'name' => 'required|string|max:60', 'discount_pct' => 'required|numeric|min:0|max:100']);
        $l = PriceLevel::create($data + ['sort' => (int) PriceLevel::max('sort') + 1]);
        ActivityLogger::log('level.created', "Level harga baru {$l->name}", $l);

        return back()->with('success', 'Level harga ditambahkan.');
    }

    public function update(Request $request, PriceLevel $level)
    {
        $data = $request->validate(['name' => 'required|string|max:60', 'discount_pct' => 'required|numeric|min:0|max:100']);
        $level->update($data);
        ActivityLogger::log('level.updated', "Mengubah level harga {$level->name}", $level);

        return back()->with('success', 'Level harga diperbarui.');
    }
}
