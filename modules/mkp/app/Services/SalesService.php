<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesService
{
    public function __construct(private JournalService $journalService)
    {
    }

    /**
     * Buat invoice penjualan. Jurnal otomatis: Debit Piutang Usaha, Kredit Pendapatan (per item).
     * Jurnal langsung diposting karena invoice mencatat transaksi yang sudah terjadi (bukan draft manual).
     */
    public function createInvoice(array $header, array $items, int $userId): Invoice
    {
        return DB::transaction(function () use ($header, $items, $userId) {
            // Bulatkan tiap baris ke 2 desimal SEBELUM dijumlah, supaya total header = jumlah baris jurnal (tanpa selisih 0,01).
            $subtotal = round(array_sum(array_map(fn ($i) => round($i['qty'] * $i['price'], 2), $items)), 2);
            $taxAmount = round((float) ($header['tax_amount'] ?? 0), 2);
            $total = round($subtotal + $taxAmount, 2);

            if ($taxAmount > 0 && empty($header['tax_account_id'])) {
                throw ValidationException::withMessages(['tax_account_id' => 'Pilih akun pajak karena nilai pajak lebih dari 0.']);
            }

            $invoice = Invoice::create([
                'invoice_number' => $header['invoice_number'] ?? Invoice::generateInvoiceNumber(),
                'customer_id' => $header['customer_id'],
                'invoice_date' => $header['invoice_date'],
                'due_date' => $header['due_date'],
                'description' => $header['description'] ?? null,
                'receivable_account_id' => $header['receivable_account_id'],
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'status' => 'unpaid',
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                $invoice->items()->create([
                    'revenue_account_id' => $item['revenue_account_id'],
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'amount' => round($item['qty'] * $item['price'], 2),
                ]);
            }

            // Susun baris jurnal: Debit Piutang Usaha sebesar total, Kredit tiap akun pendapatan
            $lines = [['account_id' => $header['receivable_account_id'], 'debit' => $total, 'credit' => 0, 'description' => "Invoice {$invoice->invoice_number}"]];
            foreach ($invoice->items as $item) {
                $lines[] = ['account_id' => $item->revenue_account_id, 'debit' => 0, 'credit' => $item->amount, 'description' => $item->description];
            }
            // Jika ada pajak (PPN Keluaran), tambahkan baris kredit Utang Pajak jika akun disediakan
            if ($taxAmount > 0 && ! empty($header['tax_account_id'])) {
                $lines[] = ['account_id' => $header['tax_account_id'], 'debit' => 0, 'credit' => $taxAmount, 'description' => 'PPN Keluaran'];
            }

            $journal = $this->journalService->create([
                'journal_date' => $header['invoice_date'],
                'type' => 'umum',
                'reference' => $invoice->invoice_number,
                'description' => "Penjualan - {$invoice->invoice_number}",
                'created_by' => $userId,
            ], $lines, true);
            $this->journalService->post($journal, $userId);

            $invoice->update(['journal_id' => $journal->id]);

            ActivityLogger::log('invoice.created', "Membuat invoice {$invoice->invoice_number} untuk {$invoice->customer->name} senilai Rp".number_format($total, 0, ',', '.'), $invoice, $userId);

            return $invoice;
        });
    }

    /**
     * Catat pembayaran dari pelanggan. Jurnal: Debit Kas/Bank, Kredit Piutang Usaha.
     */
    public function recordPayment(Invoice $invoice, array $data, int $userId): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $data, $userId) {
            // Kunci baris dokumen agar dua pembayaran bersamaan tidak saling menimpa saldo.
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->remainingBalance() <= 0) {
                throw ValidationException::withMessages(['amount' => 'Invoice ini sudah lunas.']);
            }
            if ($data['amount'] > $invoice->remainingBalance()) {
                throw ValidationException::withMessages(['amount' => 'Jumlah pembayaran melebihi sisa piutang ('.$invoice->remainingBalance().').']);
            }

            $lines = [
                ['account_id' => $data['cash_account_id'], 'debit' => $data['amount'], 'credit' => 0, 'description' => "Pembayaran {$invoice->invoice_number}"],
                ['account_id' => $invoice->receivable_account_id, 'debit' => 0, 'credit' => $data['amount'], 'description' => "Pembayaran {$invoice->invoice_number}"],
            ];

            $journal = $this->journalService->create([
                'journal_date' => $data['payment_date'],
                'type' => 'umum',
                'reference' => $invoice->invoice_number,
                'description' => "Penerimaan pembayaran - {$invoice->invoice_number}",
                'created_by' => $userId,
            ], $lines, true);
            $this->journalService->post($journal, $userId);

            $payment = $invoice->payments()->create([
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'cash_account_id' => $data['cash_account_id'],
                'note' => $data['note'] ?? null,
                'journal_id' => $journal->id,
                'created_by' => $userId,
            ]);

            $newPaid = round($invoice->paid_amount + $data['amount'], 2);
            $invoice->update([
                'paid_amount' => $newPaid,
                'status' => $newPaid >= round((float) $invoice->total, 2) ? 'paid' : 'partial',
            ]);

            ActivityLogger::log('invoice.payment_recorded', "Mencatat pembayaran Rp".number_format($data['amount'], 0, ',', '.')." untuk invoice {$invoice->invoice_number}", $invoice, $userId);

            return $payment;
        });
    }

    /**
     * Batalkan invoice yang SALAH input (belum ada pembayaran sama sekali).
     * Dibalik lewat jurnal pembalik, bukan mengedit/menghapus histori asli.
     */
    public function voidInvoice(Invoice $invoice, int $userId): Invoice
    {
        if ($invoice->status === 'void') {
            throw ValidationException::withMessages(['status' => 'Invoice ini sudah void.']);
        }
        if ($invoice->paid_amount > 0) {
            throw ValidationException::withMessages(['status' => 'Invoice ini sudah ada pembayaran tercatat. Tidak bisa langsung di-void - hubungi admin untuk proses pembalikan pembayaran terlebih dahulu.']);
        }

        return DB::transaction(function () use ($invoice, $userId) {
            if ($invoice->journal) {
                $this->journalService->reverse($invoice->journal, now()->toDateString(), $userId, "Pembatalan invoice {$invoice->invoice_number}");
            }
            $invoice->update(['status' => 'void']);

            ActivityLogger::log('invoice.voided', "Membatalkan invoice {$invoice->invoice_number}", $invoice, $userId);

            return $invoice;
        });
    }
}
