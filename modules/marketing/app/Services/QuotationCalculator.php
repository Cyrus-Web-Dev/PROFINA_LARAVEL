<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Menormalkan baris penawaran di SERVER (klien tidak dipercaya untuk harga):
 *  - tanpa izin prices.override: harga = hasil PriceResolver, diskon <= batas, wajib produk katalog;
 *  - MOQ selalu diperiksa.
 */
class QuotationCalculator
{
    public function __construct(private PriceResolver $prices)
    {
    }

    /** @return array{items: array<int,array<string,mixed>>, subtotal:string, tax_pct:float, tax_amount:string, total:string} */
    public function calculate(Customer $customer, array $rawItems, User $user, ?float $taxPct = null): array
    {
        $override = $user->can('prices.override');
        $maxDisc = (float) config('marketing.max_discount_pct', 5);
        $errors = [];
        $items = [];
        $subtotal = 0;

        foreach (array_values($rawItems) as $i => $row) {
            $qty = (float) ($row['qty'] ?? 0);
            $disc = (float) ($row['discount_pct'] ?? 0);
            $product = ! empty($row['product_id']) ? Product::where('is_active', true)->find($row['product_id']) : null;

            if ($qty <= 0) {
                $errors["items.$i.qty"] = 'Qty harus lebih dari 0.';

                continue;
            }
            if ($disc < 0 || $disc > 100) {
                $errors["items.$i.discount_pct"] = 'Diskon 0–100%.';

                continue;
            }

            if ($product) {
                $res = $this->prices->resolve($customer, $product);
                if ($qty + 1e-9 < $res['moq']) {
                    $errors["items.$i.qty"] = "Qty minimal (MOQ) untuk {$product->name} adalah ".rtrim(rtrim(number_format($res['moq'], 3, '.', ''), '0'), '.').'.';

                    continue;
                }
                if ($override && isset($row['unit_price']) && $row['unit_price'] !== '') {
                    $price = Money::fmt(Money::cents($row['unit_price']));
                } else {
                    $price = $res['price'];
                    if ($disc > $maxDisc) {
                        $errors["items.$i.discount_pct"] = "Diskon maksimum {$maxDisc}% tanpa izin penyesuaian harga.";

                        continue;
                    }
                }
                $description = ($row['description'] ?? '') ?: $product->name;
                $unit = $product->unit;
            } else {
                if (! $override) {
                    $errors["items.$i.product_id"] = 'Pilih produk dari katalog (item bebas butuh izin penyesuaian harga).';

                    continue;
                }
                $price = Money::fmt(Money::cents($row['unit_price'] ?? 0));
                $description = (string) ($row['description'] ?? '');
                $unit = (string) ($row['unit'] ?? 'pcs');
                if ($description === '') {
                    $errors["items.$i.description"] = 'Deskripsi wajib diisi.';

                    continue;
                }
            }

            $lineCents = Money::line($qty, Money::net(Money::cents($price), $disc));
            $subtotal += $lineCents;
            $items[] = [
                'product_id' => $product?->id,
                'description' => $description,
                'unit' => $unit,
                'qty' => number_format($qty, 3, '.', ''),
                'unit_price' => $price,
                'discount_pct' => number_format($disc, 2, '.', ''),
                'line_total' => Money::fmt($lineCents),
                'sort' => $i,
            ];
        }

        if (! $items && ! $errors) {
            $errors['items'] = 'Tambahkan minimal satu item.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $taxPct = $taxPct ?? (float) config('marketing.tax_pct', 11);
        $tax = Money::pct($subtotal, $taxPct);

        return [
            'items' => $items,
            'subtotal' => Money::fmt($subtotal),
            'tax_pct' => $taxPct,
            'tax_amount' => Money::fmt($tax),
            'total' => Money::fmt($subtotal + $tax),
        ];
    }
}
