<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Exports\SheetBuilder;
use App\Models\CompanyProfile;
use App\Services\CashFlowService;
use App\Services\FinancialReportService;
use App\Services\FinancialRatioService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(
        private FinancialReportService $reportService,
        private CashFlowService $cashFlowService,
        private FinancialRatioService $ratioService
    ) {
    }

    public function trialBalance(Request $request)
    {
        $asOfDate = $request->as_of_date ?? now()->toDateString();

        return Inertia::render('Reports/TrialBalance', [
            'asOfDate' => $asOfDate,
            'report' => $this->reportService->trialBalance($asOfDate),
        ]);
    }

    public function incomeStatement(Request $request)
    {
        $start = $request->start_date ?? now()->startOfYear()->toDateString();
        $end = $request->end_date ?? now()->toDateString();

        return Inertia::render('Reports/IncomeStatement', [
            'startDate' => $start,
            'endDate' => $end,
            'report' => $this->reportService->incomeStatement($start, $end),
        ]);
    }

    public function balanceSheet(Request $request)
    {
        $asOfDate = $request->as_of_date ?? now()->toDateString();

        return Inertia::render('Reports/BalanceSheet', [
            'asOfDate' => $asOfDate,
            'report' => $this->reportService->balanceSheet($asOfDate),
        ]);
    }

    public function cashFlow(Request $request)
    {
        $start = $request->start_date ?? now()->startOfYear()->toDateString();
        $end = $request->end_date ?? now()->toDateString();

        return Inertia::render('Reports/CashFlow', [
            'startDate' => $start,
            'endDate' => $end,
            'report' => $this->cashFlowService->cashFlowStatement($start, $end),
        ]);
    }

    public function ratios(Request $request)
    {
        $asOfDate = $request->as_of_date ?? now()->toDateString();
        $start = $request->start_date ?? now()->startOfYear()->toDateString();

        return Inertia::render('Reports/Ratios', [
            'asOfDate' => $asOfDate,
            'startDate' => $start,
            'ratios' => $this->ratioService->calculate($asOfDate, $start, $asOfDate),
        ]);
    }

    public function exportTrialBalancePdf(Request $request)
    {
        $asOfDate = $request->as_of_date ?? now()->toDateString();
        $pdf = Pdf::loadView('pdf.trial-balance', ['asOfDate' => $asOfDate, 'report' => $this->reportService->trialBalance($asOfDate)]);

        return $pdf->download("neraca-saldo-{$asOfDate}.pdf");
    }

    public function exportIncomeStatementPdf(Request $request)
    {
        $start = $request->start_date ?? now()->startOfYear()->toDateString();
        $end = $request->end_date ?? now()->toDateString();
        $pdf = Pdf::loadView('pdf.income-statement', ['startDate' => $start, 'endDate' => $end, 'report' => $this->reportService->incomeStatement($start, $end)]);

        return $pdf->download("laba-rugi-{$start}-{$end}.pdf");
    }

    public function exportBalanceSheetPdf(Request $request)
    {
        $asOfDate = $request->as_of_date ?? now()->toDateString();
        $pdf = Pdf::loadView('pdf.balance-sheet', ['asOfDate' => $asOfDate, 'report' => $this->reportService->balanceSheet($asOfDate)]);

        return $pdf->download("neraca-{$asOfDate}.pdf");
    }

    // ------------------------------------------------------------------ Ekspor Excel

    public function exportTrialBalanceExcel(Request $request)
    {
        $asOfDate = $request->as_of_date ?? now()->toDateString();
        $report = $this->reportService->trialBalance($asOfDate);

        $b = new SheetBuilder();
        $b->add([CompanyProfile::current()->name], true);
        $b->add(['Neraca Saldo per '.$asOfDate], true);
        $b->add([]);
        $b->add(['Kode', 'Nama Akun', 'Debit', 'Kredit'], true);
        foreach ($report['rows'] as $r) {
            $b->add([$r['code'], $r['name'], (float) $r['debit'], (float) $r['credit']]);
        }
        $b->add(['', 'TOTAL', (float) $report['total_debit'], (float) $report['total_credit']], true);
        $b->add(['', $report['is_balanced'] ? 'Seimbang' : 'TIDAK SEIMBANG']);

        return Excel::download(new ReportExport($b->rows, 'Neraca Saldo', $b->bold), "neraca-saldo-{$asOfDate}.xlsx");
    }

    public function exportIncomeStatementExcel(Request $request)
    {
        $start = $request->start_date ?? now()->startOfYear()->toDateString();
        $end = $request->end_date ?? now()->toDateString();
        $report = $this->reportService->incomeStatement($start, $end);

        $b = new SheetBuilder();
        $b->add([CompanyProfile::current()->name], true);
        $b->add(["Laporan Laba Rugi {$start} s.d. {$end}"], true);
        $b->add([]);
        $b->add(['Kode', 'Nama Akun', 'Jumlah'], true);
        $b->add(['', 'PENDAPATAN'], true);
        foreach ($report['revenues'] as $r) {
            $b->add([$r['code'], $r['name'], (float) $r['amount']]);
        }
        $b->add(['', 'Total Pendapatan', (float) $report['total_revenue']], true);
        $b->add(['', 'BEBAN'], true);
        foreach ($report['expenses'] as $r) {
            $b->add([$r['code'], $r['name'], (float) $r['amount']]);
        }
        $b->add(['', 'Total Beban', (float) $report['total_expense']], true);
        $b->add(['', 'LABA / (RUGI) BERSIH', (float) $report['net_income']], true);

        return Excel::download(new ReportExport($b->rows, 'Laba Rugi', $b->bold, ['C']), "laba-rugi-{$start}-{$end}.xlsx");
    }

    public function exportBalanceSheetExcel(Request $request)
    {
        $asOfDate = $request->as_of_date ?? now()->toDateString();
        $report = $this->reportService->balanceSheet($asOfDate);

        $b = new SheetBuilder();
        $b->add([CompanyProfile::current()->name], true);
        $b->add(['Neraca (Laporan Posisi Keuangan) per '.$asOfDate], true);
        $b->add([]);
        $b->add(['Kode', 'Nama Akun', 'Jumlah'], true);

        $b->add(['', 'ASET'], true);
        foreach ($report['assets'] as $r) {
            $b->add([$r['code'], $r['name'], (float) $r['amount']]);
        }
        $b->add(['', 'Total Aset', (float) $report['total_assets']], true);

        $b->add(['', 'KEWAJIBAN'], true);
        foreach ($report['liabilities'] as $r) {
            $b->add([$r['code'], $r['name'], (float) $r['amount']]);
        }
        $b->add(['', 'Total Kewajiban', (float) $report['total_liabilities']], true);

        $b->add(['', 'EKUITAS'], true);
        foreach ($report['equity'] as $r) {
            $b->add([$r['code'], $r['name'], (float) $r['amount']]);
        }
        $b->add(['', 'Laba Tahun Berjalan', (float) $report['current_year_earnings']]);
        $b->add(['', 'Total Ekuitas', (float) $report['total_equity']], true);
        $b->add(['', 'Total Kewajiban + Ekuitas', (float) ($report['total_liabilities'] + $report['total_equity'])], true);

        return Excel::download(new ReportExport($b->rows, 'Neraca', $b->bold, ['C']), "neraca-{$asOfDate}.xlsx");
    }
}
