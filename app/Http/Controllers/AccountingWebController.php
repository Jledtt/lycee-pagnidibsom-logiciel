<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountingWebController extends Controller
{
    public function cashJournal(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $filters = $this->filters($request);
        $payments = $this->cashJournalQuery($filters, $academicYear)
            ->latest('paid_at')
            ->paginate(20)
            ->withQueryString();

        $allRows = $this->cashJournalQuery($filters, $academicYear)->get();

        return view('accounting.cash-journal', [
            'academicYear' => $academicYear,
            'cashiers' => $this->cashiers(),
            'classes' => $this->classes($academicYear),
            'filters' => $filters,
            'methodLabels' => $this->methodLabels(),
            'payments' => $payments,
            'summary' => $this->cashSummary($allRows),
        ]);
    }

    public function cashJournalPdf(Request $request)
    {
        $academicYear = $this->activeAcademicYear();
        $filters = $this->filters($request);
        $payments = $this->cashJournalQuery($filters, $academicYear)
            ->latest('paid_at')
            ->get();

        $filename = 'journal-caisse-'.Str::slug($filters['date_from'].'-'.$filters['date_to']).'.pdf';

        return Pdf::loadView('accounting.cash-journal-pdf', [
            'academicYear' => $academicYear,
            'filters' => $filters,
            'methodLabels' => $this->methodLabels(),
            'payments' => $payments,
            'school' => SchoolSetting::query()->first(),
            'summary' => $this->cashSummary($payments),
        ])
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    public function balanceSheet(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $filters = $this->balanceFilters($request);
        $payments = $this->balancePaymentQuery($filters, $academicYear)->get();
        $expenses = $this->balanceExpenseQuery($filters, $academicYear)->get();

        return view('accounting.balance-sheet', [
            'academicYear' => $academicYear,
            'categoryLabels' => $this->expenseCategoryLabels(),
            'expenseSummary' => $this->expenseSummary($expenses),
            'filters' => $filters,
            'methodLabels' => $this->methodLabels(),
            'paymentSummary' => $this->cashSummary($payments),
            'summary' => $this->balanceSummary($payments, $expenses),
        ]);
    }

    public function balanceSheetPdf(Request $request)
    {
        $academicYear = $this->activeAcademicYear();
        $filters = $this->balanceFilters($request);
        $payments = $this->balancePaymentQuery($filters, $academicYear)->get();
        $expenses = $this->balanceExpenseQuery($filters, $academicYear)->get();
        $filename = 'bilan-caisse-'.Str::slug($filters['date_from'].'-'.$filters['date_to']).'.pdf';

        return Pdf::loadView('accounting.balance-sheet-pdf', [
            'academicYear' => $academicYear,
            'categoryLabels' => $this->expenseCategoryLabels(),
            'expenseSummary' => $this->expenseSummary($expenses),
            'filters' => $filters,
            'methodLabels' => $this->methodLabels(),
            'paymentSummary' => $this->cashSummary($payments),
            'school' => SchoolSetting::query()->first(),
            'summary' => $this->balanceSummary($payments, $expenses),
        ])
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }

    public function expenses(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $filters = $this->expenseFilters($request);
        $expenses = $this->expenseQuery($filters, $academicYear)
            ->latest('spent_at')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $allRows = $this->expenseQuery($filters, $academicYear)->get();

        return view('accounting.expenses.index', [
            'academicYear' => $academicYear,
            'categoryLabels' => $this->expenseCategoryLabels(),
            'expenses' => $expenses,
            'filters' => $filters,
            'methodLabels' => $this->methodLabels(),
            'summary' => $this->expenseSummary($allRows),
        ]);
    }

    public function createExpense(): View
    {
        return view('accounting.expenses.create', [
            'academicYear' => $this->activeAcademicYear(),
            'categoryLabels' => $this->expenseCategoryLabels(),
            'expense' => new Expense([
                'spent_at' => now()->toDateString(),
                'payment_method' => 'cash',
                'status' => 'valid',
            ]),
            'methodLabels' => $this->methodLabels(),
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $academicYear = $this->activeAcademicYear();
        $data = $this->validateExpense($request);

        $expense = Expense::create($data + [
            'academic_year_id' => $academicYear?->id,
            'created_by' => $request->user()->id,
            'status' => 'valid',
        ]);

        return redirect()
            ->route('accounting.expenses.show', $expense)
            ->with('success', 'Dépense enregistrée avec succès.');
    }

    public function showExpense(Expense $expense): View
    {
        $expense->load(['academicYear', 'creator', 'canceller']);

        return view('accounting.expenses.show', [
            'academicYear' => $this->activeAcademicYear(),
            'categoryLabels' => $this->expenseCategoryLabels(),
            'expense' => $expense,
            'methodLabels' => $this->methodLabels(),
        ]);
    }

    public function cancelExpense(Request $request, Expense $expense): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        if ($expense->status !== 'cancelled') {
            $expense->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()->id,
                'cancellation_reason' => $data['reason'],
            ]);
        }

        return redirect()
            ->route('accounting.expenses.show', $expense)
            ->with('success', 'Dépense annulée.');
    }

    public function expensesPdf(Request $request)
    {
        $academicYear = $this->activeAcademicYear();
        $filters = $this->expenseFilters($request);
        $expenses = $this->expenseQuery($filters, $academicYear)
            ->latest('spent_at')
            ->latest()
            ->get();
        $filename = 'depenses-'.Str::slug($filters['date_from'].'-'.$filters['date_to']).'.pdf';

        return Pdf::loadView('accounting.expenses.pdf', [
            'academicYear' => $academicYear,
            'categoryLabels' => $this->expenseCategoryLabels(),
            'expenses' => $expenses,
            'filters' => $filters,
            'methodLabels' => $this->methodLabels(),
            'school' => SchoolSetting::query()->first(),
            'summary' => $this->expenseSummary($expenses),
        ])
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function cashJournalQuery(array $filters, ?AcademicYear $academicYear): Builder
    {
        return Payment::query()
            ->with(['student', 'enrollment.schoolClass', 'lines.feeType', 'receiver'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->whereDate('paid_at', '>=', $filters['date_from'])
            ->whereDate('paid_at', '<=', $filters['date_to'])
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['payment_method'], fn ($query, string $method) => $query->where('payment_method', $method))
            ->when($filters['received_by'], fn ($query, int $userId) => $query->where('received_by', $userId))
            ->when($filters['school_class_id'], function ($query, int $classId) {
                $query->whereHas('enrollment', fn ($enrollmentQuery) => $enrollmentQuery->where('school_class_id', $classId));
            });
    }

    private function expenseQuery(array $filters, ?AcademicYear $academicYear): Builder
    {
        return Expense::query()
            ->with(['creator', 'canceller'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->whereDate('spent_at', '>=', $filters['date_from'])
            ->whereDate('spent_at', '<=', $filters['date_to'])
            ->when($filters['category'], fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['payment_method'], fn ($query, string $method) => $query->where('payment_method', $method))
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status));
    }

    private function balancePaymentQuery(array $filters, ?AcademicYear $academicYear): Builder
    {
        return Payment::query()
            ->with(['lines.feeType'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->whereDate('paid_at', '>=', $filters['date_from'])
            ->whereDate('paid_at', '<=', $filters['date_to'])
            ->where('status', 'valid');
    }

    private function balanceExpenseQuery(array $filters, ?AcademicYear $academicYear): Builder
    {
        return Expense::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->whereDate('spent_at', '>=', $filters['date_from'])
            ->whereDate('spent_at', '<=', $filters['date_to'])
            ->where('status', 'valid');
    }

    private function filters(Request $request): array
    {
        $today = now()->toDateString();

        return [
            'date_from' => $request->date('date_from')?->toDateString() ?? $today,
            'date_to' => $request->date('date_to')?->toDateString() ?? $today,
            'payment_method' => $request->string('payment_method')->toString() ?: null,
            'received_by' => $request->integer('received_by') ?: null,
            'school_class_id' => $request->integer('school_class_id') ?: null,
            'status' => $request->string('status')->toString() ?: 'valid',
        ];
    }

    private function expenseFilters(Request $request): array
    {
        $today = now()->toDateString();

        return [
            'date_from' => $request->date('date_from')?->toDateString() ?? $today,
            'date_to' => $request->date('date_to')?->toDateString() ?? $today,
            'category' => $request->string('category')->toString() ?: null,
            'payment_method' => $request->string('payment_method')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: 'valid',
        ];
    }

    private function balanceFilters(Request $request): array
    {
        $today = now()->toDateString();

        return [
            'date_from' => $request->date('date_from')?->toDateString() ?? $today,
            'date_to' => $request->date('date_to')?->toDateString() ?? $today,
        ];
    }

    private function cashSummary(Collection $payments): array
    {
        $validPayments = $payments->where('status', 'valid');
        $cancelledPayments = $payments->where('status', 'cancelled');
        $byMethod = $validPayments
            ->groupBy('payment_method')
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        $byFeeType = $validPayments
            ->flatMap(fn (Payment $payment) => $payment->lines)
            ->groupBy(fn ($line) => $line->feeType?->name ?? 'Frais non precise')
            ->map(fn (Collection $lines) => (float) $lines->sum('amount'))
            ->sortKeys();

        return [
            'valid_count' => $validPayments->count(),
            'cancelled_count' => $cancelledPayments->count(),
            'total_valid' => (float) $validPayments->sum('amount'),
            'total_cancelled' => (float) $cancelledPayments->sum('amount'),
            'by_method' => $byMethod,
            'by_fee_type' => $byFeeType,
        ];
    }

    private function expenseSummary(Collection $expenses): array
    {
        $validExpenses = $expenses->where('status', 'valid');
        $cancelledExpenses = $expenses->where('status', 'cancelled');

        return [
            'valid_count' => $validExpenses->count(),
            'cancelled_count' => $cancelledExpenses->count(),
            'total_valid' => (float) $validExpenses->sum('amount'),
            'total_cancelled' => (float) $cancelledExpenses->sum('amount'),
            'by_category' => $validExpenses
                ->groupBy('category')
                ->map(fn (Collection $rows) => (float) $rows->sum('amount'))
                ->sortKeys(),
            'by_method' => $validExpenses
                ->groupBy('payment_method')
                ->map(fn (Collection $rows) => (float) $rows->sum('amount'))
                ->sortKeys(),
        ];
    }

    private function balanceSummary(Collection $payments, Collection $expenses): array
    {
        $income = (float) $payments->sum('amount');
        $expenseTotal = (float) $expenses->sum('amount');

        return [
            'income' => $income,
            'expenses' => $expenseTotal,
            'balance' => $income - $expenseTotal,
            'payment_count' => $payments->count(),
            'expense_count' => $expenses->count(),
        ];
    }

    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'spent_at' => ['required', 'date'],
            'category' => ['required', Rule::in(array_keys($this->expenseCategoryLabels()))],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', Rule::in(array_keys($this->methodLabels()))],
            'amount' => ['required', 'numeric', 'min:1'],
            'proof_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function cashiers(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'comptable']))
            ->orderBy('name')
            ->get();
    }

    private function classes(?AcademicYear $academicYear): Collection
    {
        return SchoolClass::query()
            ->with('level')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function methodLabels(): array
    {
        return [
            'cash' => 'Especes',
            'mobile_money' => 'Mobile money',
            'bank_transfer' => 'Virement',
            'other' => 'Autre',
        ];
    }

    private function expenseCategoryLabels(): array
    {
        return [
            'supplies' => 'Fournitures',
            'salaries' => 'Salaires',
            'maintenance' => 'Entretien',
            'transport' => 'Transport',
            'utilities' => 'Eau / electricite',
            'administration' => 'Administration',
            'other' => 'Autre',
        ];
    }
}
