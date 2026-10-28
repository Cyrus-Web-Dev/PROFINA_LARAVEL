<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\ProcurementFunding;
use App\Models\ProcurementReport;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function summary(Request $request)
    {
        $today = now()->toDateString();
        $reminderUntil = now()->addDays((int) config('mkp.due_reminder_days'))->toDateString();
        $user = $request->user();

        $items = [];

        if ($user->hasRole('admin')) {
            $pendingUsers = User::where('status', 'pending')->count();
            if ($pendingUsers > 0) {
                $items[] = [
                    'type' => 'warning',
                    'text' => "{$pendingUsers} pendaftar menunggu persetujuan",
                    'url' => route('admin.users.index', ['status' => 'pending']),
                ];
            }
        }

        if ($user->can('journal.approve')) {
            $pendingApproval = Journal::where('approval_status', 'pending')->count();
            if ($pendingApproval > 0) {
                $items[] = [
                    'type' => 'warning',
                    'text' => "{$pendingApproval} jurnal menunggu approval",
                    'url' => route('journals.index', ['status' => 'draft']),
                ];
            }
        }

        try {
            if ($user->can('procurement.decide') && ($n = ProcurementFunding::where('status', 'submitted')->count()) > 0) {
                $items[] = ['type' => 'warning', 'text' => "{$n} pengajuan dana dari Pengadaan menunggu keputusan", 'url' => route('procurement.index', ['tab' => 'waiting'])];
            }
            if ($user->can('procurement.disburse')) {
                if (($n = ProcurementFunding::where('status', 'approved')->count()) > 0) {
                    $items[] = ['type' => 'info', 'text' => "{$n} dana pengadaan disetujui, belum dicairkan", 'url' => route('procurement.index', ['tab' => 'cash'])];
                }
                if (($n = ProcurementFunding::where('status', 'settled')->count()) > 0) {
                    $items[] = ['type' => 'info', 'text' => "{$n} LPJ pengadaan siap dijurnal", 'url' => route('procurement.index', ['tab' => 'settle'])];
                }
            }
            if (($n = ProcurementReport::unread()) > 0 && $user->hasAnyPermission(['procurement.decide', 'procurement.disburse'])) {
                $items[] = ['type' => 'info', 'text' => "{$n} laporan baru dari Pengadaan", 'url' => route('procurement.index', ['tab' => 'reports'])];
            }
        } catch (\Throwable $e) {
            // tabel integrasi belum dimigrasi: jangan ganggu notifikasi lain
        }

        $overdueInvoices = Invoice::whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', $today)->count();
        if ($overdueInvoices > 0) {
            $items[] = [
                'type' => 'danger',
                'text' => "{$overdueInvoices} invoice sudah lewat jatuh tempo",
                'url' => route('invoices.index', ['status' => 'unpaid']),
            ];
        }

        $upcomingInvoices = Invoice::whereIn('status', ['unpaid', 'partial'])
            ->whereDate('due_date', '>=', $today)->whereDate('due_date', '<=', $reminderUntil)->count();
        if ($upcomingInvoices > 0) {
            $items[] = [
                'type' => 'info',
                'text' => "{$upcomingInvoices} invoice akan jatuh tempo dalam ".config('mkp.due_reminder_days')." hari",
                'url' => route('invoices.index', ['status' => 'unpaid']),
            ];
        }

        $overdueBills = Bill::whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', $today)->count();
        if ($overdueBills > 0) {
            $items[] = [
                'type' => 'danger',
                'text' => "{$overdueBills} bill sudah lewat jatuh tempo",
                'url' => route('bills.index', ['status' => 'unpaid']),
            ];
        }

        $draftJournals = Journal::where('status', 'draft')->where('approval_status', '!=', 'pending')->count();
        if ($draftJournals > 0) {
            $items[] = [
                'type' => 'info',
                'text' => "{$draftJournals} jurnal draft belum diposting",
                'url' => route('journals.index', ['status' => 'draft']),
            ];
        }

        return response()->json([
            'count' => count($items),
            'items' => $items,
        ]);
    }
}
