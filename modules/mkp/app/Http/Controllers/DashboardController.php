<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Journal;
use App\Services\FinancialReportService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(private FinancialReportService $reportService)
    {
    }

    public function index()
    {
        $today = now()->toDateString();
        $yearStart = now()->startOfYear()->toDateString();
        $reminderUntil = now()->addDays((int) config('mkp.due_reminder_days'))->toDateString();

        $balanceSheet = $this->reportService->balanceSheet($today);
        $incomeStatement = $this->reportService->incomeStatement($yearStart, $today);

        // Tren laba/rugi 6 bulan terakhir untuk grafik dashboard
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth()->toDateString();
            $monthEnd = now()->subMonths($i)->endOfMonth()->toDateString();
            $result = $this->reportService->incomeStatement($monthStart, $monthEnd);
            $trend[] = [
                'label' => now()->subMonths($i)->translatedFormat('M Y'),
                'revenue' => $result['total_revenue'],
                'expense' => $result['total_expense'],
                'net' => $result['net_income'],
            ];
        }

        // Pengingat jatuh tempo: invoice/bill yang overdue atau akan jatuh tempo dalam N hari (lihat config/mkp.php)
        $overdueInvoices = Invoice::with('customer')->whereIn('status', ['unpaid', 'partial'])
            ->whereDate('due_date', '<', $today)->orderBy('due_date')->limit(5)->get();
        $upcomingInvoices = Invoice::with('customer')->whereIn('status', ['unpaid', 'partial'])
            ->whereDate('due_date', '>=', $today)->whereDate('due_date', '<=', $reminderUntil)->orderBy('due_date')->limit(5)->get();
        $overdueBills = Bill::with('vendor')->whereIn('status', ['unpaid', 'partial'])
            ->whereDate('due_date', '<', $today)->orderBy('due_date')->limit(5)->get();
        $upcomingBills = Bill::with('vendor')->whereIn('status', ['unpaid', 'partial'])
            ->whereDate('due_date', '>=', $today)->whereDate('due_date', '<=', $reminderUntil)->orderBy('due_date')->limit(5)->get();

        return Inertia::render('Dashboard/Index', [
            'kpi' => [
                'total_assets' => $balanceSheet['total_assets'],
                'total_liabilities' => $balanceSheet['total_liabilities'],
                'total_equity' => $balanceSheet['total_equity'],
                'net_income_ytd' => $incomeStatement['net_income'],
            ],
            'trend' => $trend,
            'draftJournalsCount' => Journal::where('status', 'draft')->count(),
            'pendingApprovalCount' => Journal::where('approval_status', 'pending')->count(),
            'recentJournals' => Journal::orderByDesc('id')->limit(6)->get(['id', 'journal_number', 'journal_date', 'description', 'status', 'total_debit']),
            'reminders' => [
                'overdue_invoices' => $overdueInvoices->map(fn ($i) => ['id' => $i->id, 'label' => "{$i->invoice_number} - {$i->customer->name}", 'due_date' => $i->due_date->toDateString(), 'amount' => $i->total - $i->paid_amount]),
                'upcoming_invoices' => $upcomingInvoices->map(fn ($i) => ['id' => $i->id, 'label' => "{$i->invoice_number} - {$i->customer->name}", 'due_date' => $i->due_date->toDateString(), 'amount' => $i->total - $i->paid_amount]),
                'overdue_bills' => $overdueBills->map(fn ($b) => ['id' => $b->id, 'label' => "{$b->bill_number} - {$b->vendor->name}", 'due_date' => $b->due_date->toDateString(), 'amount' => $b->total - $b->paid_amount]),
                'upcoming_bills' => $upcomingBills->map(fn ($b) => ['id' => $b->id, 'label' => "{$b->bill_number} - {$b->vendor->name}", 'due_date' => $b->due_date->toDateString(), 'amount' => $b->total - $b->paid_amount]),
            ],
        ]);
    }
}
