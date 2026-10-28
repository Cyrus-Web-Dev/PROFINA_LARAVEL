<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(private JournalService $journalService)
    {
    }

    /**
     * Buat bill dari vendor. Jurnal otomatis: Debit Beban/Persediaan (per item), Kredit Utang Usaha.
     */
    public function createBill(array $header, array $items, int $userId): Bill
    {
        return DB::transaction(function () use ($header, $items, $userId) {
            // Bulatkan tiap baris ke 2 desimal SEBELUM dijumlah, supaya total header = jumlah baris jurnal (tanpa selisih 0,01).
            $subtotal = round(array_sum(array_map(fn ($i) => round($i['qty'] * $i['price'], 2), $items)), 2);
            $taxAmount = round((float) ($header['tax_amount'] ?? 0), 2);
            $total = round($subtotal + $taxAmount, 2);

            if ($taxAmount > 0 && empty($header['tax_account_id'])) {
                throw ValidationException::withMessages(['tax_account_id' => 'Pilih akun pajak karena nilai pajak lebih dari 0.']);
            }

            $bill = Bill::create([
                'bill_number' => $header['bill_number'] ?? Bill::generateBillNumber(),
                'vendor_id' => $header['vendor_id'],
                'bill_date' => $header['bill_date'],
                'due_date' => $header['due_date'],
                'description' => $header['description'] ?? null,
                'payable_account_id' => $header['payable_account_id'],
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'status' => 'unpaid',
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                $bill->items()->create([
                    'expense_account_id' => $item['expense_account_id'],
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'amount' => round($item['qty'] * $item['price'], 2),
                ]);
            }

            $lines = [];
            foreach ($bill->items as $item) {
                $lines[] = ['account_id' => $item->expense_account_id, 'debit' => $item->amount, 'credit' => 0, 'description' => $item->description];
            }
            if ($taxAmount > 0 && ! empty($header['tax_account_id'])) {
                $lines[] = ['account_id' => $header['tax_account_id'], 'debit' => $taxAmount, 'credit' => 0, 'description' => 'PPN Masukan'];
            }
            $lines[] = ['account_id' => $header['payable_account_id'], 'debit' => 0, 'credit' => $total, 'description' => "Bill {$bill->bill_number}"];

            $journal = $this->journalService->create([
                'journal_date' => $header['bill_date'],
                'type' => 'umum',
                'reference' => $bill->bill_number,
                'description' => "Pembelian - {$bill->bill_number}",
                'created_by' => $userId,
            ], $lines, true);
            $this->journalService->post($journal, $userId);

            $bill->update(['journal_id' => $journal->id]);

            ActivityLogger::log('bill.created', "Membuat bill {$bill->bill_number} dari {$bill->vendor->name} senilai Rp".number_format($total, 0, ',', '.'), $bill, $userId);

            return $bill;
        });
    }

    /**
     * Catat pembayaran ke vendor. Jurnal: Debit Utang Usaha, Kredit Kas/Bank.
     */
    public function recordPayment(Bill $bill, array $data, int $userId): BillPayment
    {
        return DB::transaction(function () use ($bill, $data, $userId) {
            // Kunci baris dokumen agar dua pembayaran bersamaan tidak saling menimpa saldo.
            $bill = Bill::lockForUpdate()->findOrFail($bill->id);

            if ($bill->remainingBalance() <= 0) {
                throw ValidationException::withMessages(['amount' => 'Bill ini sudah lunas.']);
            }
            if ($data['amount'] > $bill->remainingBalance()) {
                throw ValidationException::withMessages(['amount' => 'Jumlah pembayaran melebihi sisa utang ('.$bill->remainingBalance().').']);
            }

            $lines = [
                ['account_id' => $bill->payable_account_id, 'debit' => $data['amount'], 'credit' => 0, 'description' => "Pembayaran {$bill->bill_number}"],
                ['account_id' => $data['cash_account_id'], 'debit' => 0, 'credit' => $data['amount'], 'description' => "Pembayaran {$bill->bill_number}"],
            ];

            $journal = $this->journalService->create([
                'journal_date' => $data['payment_date'],
                'type' => 'umum',
                'reference' => $bill->bill_number,
                'description' => "Pembayaran utang - {$bill->bill_number}",
                'created_by' => $userId,
            ], $lines, true);
            $this->journalService->post($journal, $userId);

            $payment = $bill->payments()->create([
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'cash_account_id' => $data['cash_account_id'],
                'note' => $data['note'] ?? null,
                'journal_id' => $journal->id,
                'created_by' => $userId,
            ]);

            $newPaid = round($bill->paid_amount + $data['amount'], 2);
            $bill->update([
                'paid_amount' => $newPaid,
                'status' => $newPaid >= round((float) $bill->total, 2) ? 'paid' : 'partial',
            ]);

            ActivityLogger::log('bill.payment_recorded', "Mencatat pembayaran Rp".number_format($data['amount'], 0, ',', '.')." untuk bill {$bill->bill_number}", $bill, $userId);

            return $payment;
        });
    }

    /**
     * Batalkan bill yang SALAH input (belum ada pembayaran sama sekali).
     */
    public function voidBill(Bill $bill, int $userId): Bill
    {
        if ($bill->status === 'void') {
            throw ValidationException::withMessages(['status' => 'Bill ini sudah void.']);
        }
        if ($bill->paid_amount > 0) {
            throw ValidationException::withMessages(['status' => 'Bill ini sudah ada pembayaran tercatat. Tidak bisa langsung di-void - hubungi admin untuk proses pembalikan pembayaran terlebih dahulu.']);
        }

        return DB::transaction(function () use ($bill, $userId) {
            if ($bill->journal) {
                $this->journalService->reverse($bill->journal, now()->toDateString(), $userId, "Pembatalan bill {$bill->bill_number}");
            }
            $bill->update(['status' => 'void']);

            ActivityLogger::log('bill.voided', "Membatalkan bill {$bill->bill_number}", $bill, $userId);

            return $bill;
        });
    }
}
