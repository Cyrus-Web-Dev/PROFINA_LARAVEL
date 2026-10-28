<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Support\Money;

/**
 * Urutan penentuan harga & MOQ (yang pertama ada menang):
 *   1. harga khusus pelanggan  2. harga produk untuk level pelanggan
 *   3. harga dasar dikurangi diskon level  (4. harga dasar bila level 0%)
 */
class PriceResolver
{
    /** @return array{price:string, moq:float, source:string} */
    public function resolve(Customer $customer, Product $product): array
    {
        $special = CustomerPrice::where('customer_id', $customer->id)->where('product_id', $product->id)->first();
        $level = ProductPrice::where('product_id', $product->id)->where('price_level_id', $customer->price_level_id)->first();

        if ($special) {
            $price = Money::fmt(Money::cents($special->price));
            $source = 'khusus pelanggan';
        } elseif ($level) {
            $price = Money::fmt(Money::cents($level->price));
            $source = 'harga level '.$customer->level?->name;
        } else {
            $disc = (float) ($customer->level?->discount_pct ?? 0);
            $price = Money::fmt(Money::net(Money::cents($product->base_price), $disc));
            $source = $disc > 0 ? "harga dasar − {$disc}% level ".$customer->level?->name : 'harga dasar';
        }

        $moq = $special?->min_qty ?? $level?->min_qty ?? $product->min_order_qty ?? 1;

        return ['price' => $price, 'moq' => (float) $moq, 'source' => $source];
    }
}
