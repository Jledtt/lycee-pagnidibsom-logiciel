<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Expense;
use App\Models\SchoolSetting;
use App\Models\TeacherFeeStatement;
use App\Models\TeacherWorkSession;
use App\Models\User;
use App\Services\FrenchAmountInWordsService;
use App\Services\TeacherFeeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeacherFeeStatementWebController extends Controller
{
    public function __construct(
        private readonly TeacherFeeService $teacherFeeService,
        private readonly FrenchAmountInWordsService $amountInWordsService,
    ) {}

    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $filters = $request->validate([
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['draft', 'approved', 'paid', 'cancelled'])],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $query = TeacherFeeStatement::query()
            ->with('teacher')
            ->where('academic_year_id', $academicYear?->id)
            ->when($filters['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['month'] ?? null, fn (Builder $query, string $month) => $query->whereDate('period_month', Carbon::createFromFormat('Y-m', $month)->startOfMonth()));

        if (! $request->user()->can('teacher_fees.manage')) {
            $query->where('teacher_id', $request->user()->id);
        }

        return view('teacher-fees.index', [
            'academicYear' => $academicYear,
            'filters' => $filters,
            'statements' => $query->latest('period_month')->latest()->paginate(30)->withQueryString(),
            'teachers' => $this->visibleTeachers($request, 'teacher_fees.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        abort_unless($academicYear, 422, 'Configure une année scolaire active.');
        $filters = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $teacher = User::query()->role('enseignant')->with('teacherProfile')->findOrFail($filters['teacher_id']);
        $periodMonth = Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth();
        $sessions = TeacherWorkSession::query()
            ->with(['schoolClass', 'subject'])
            ->where('academic_year_id', $academicYear->id)
            ->where('teacher_id', $teacher->id)
            ->where('status', 'validated')
            ->whereBetween('session_date', [$periodMonth->copy()->startOfMonth(), $periodMonth->copy()->endOfMonth()])
            ->whereDoesntHave('feeLine')
            ->orderBy('session_date')
            ->get();

        return view('teacher-fees.create', [
            'academicYear' => $academicYear,
            'periodMonth' => $periodMonth,
            'sessions' => $sessions,
            'teacher' => $teacher,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $academicYear = $this->activeAcademicYear();
        abort_unless($academicYear, 422, 'Configure une année scolaire active.');

        $data = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'period_month' => ['required', 'date_format:Y-m'],
            'session_ids' => ['required', 'array', 'min:1'],
            'session_ids.*' => ['integer', 'exists:teacher_work_sessions,id'],
            'rates' => ['required', 'array'],
            'rates.*' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'withholding_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'advance_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'other_deduction_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $teacher = User::query()->role('enseignant')->with('teacherProfile')->findOrFail($data['teacher_id']);
        $statement = $this->teacherFeeService->create(
            $academicYear,
            $teacher,
            Carbon::createFromFormat('Y-m', $data['period_month'])->startOfMonth(),
            $data['session_ids'],
            $data['rates'],
            $data,
            $request->user(),
        );

        return redirect()->route('teacher-fees.show', $statement)->with('success', 'Ordre de paiement créé.');
    }

    public function show(Request $request, TeacherFeeStatement $teacherFee): View
    {
        $this->authorizeStatementAccess($request, $teacherFee);
        $teacherFee->load(['teacher.teacherProfile', 'lines.schoolClass', 'lines.subject', 'approver', 'payer']);

        return view('teacher-fees.show', [
            'academicYear' => $teacherFee->academicYear,
            'statement' => $teacherFee,
        ]);
    }

    public function approve(Request $request, TeacherFeeStatement $teacherFee): RedirectResponse
    {
        abort_unless($teacherFee->status === 'draft', 422, 'Seul un brouillon peut être validé.');
        $teacherFee->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Ordre de paiement validé par l’administration.');
    }

    public function markPaid(Request $request, TeacherFeeStatement $teacherFee): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['Espèces', 'Virement', 'Mobile Money', 'Chèque'])],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($teacherFee, $data, $request): void {
            $statement = TeacherFeeStatement::query()
                ->with('teacher')
                ->lockForUpdate()
                ->findOrFail($teacherFee->id);

            abort_unless($statement->status === 'approved', 422, 'Valide d’abord l’ordre de paiement.');

            $statement->update([
                ...$data,
                'status' => 'paid',
                'paid_by' => $request->user()->id,
            ]);

            Expense::query()->create([
                'teacher_fee_statement_id' => $statement->id,
                'academic_year_id' => $statement->academic_year_id,
                'spent_at' => Carbon::parse($data['paid_at'])->toDateString(),
                'category' => 'salaries',
                'beneficiary' => $statement->beneficiary_name,
                'payment_method' => $this->accountingPaymentMethod($data['payment_method']),
                'amount' => $statement->net_amount,
                'proof_reference' => $data['payment_reference'] ?: $statement->reference,
                'status' => 'valid',
                'notes' => 'Générée automatiquement depuis l’ordre d’honoraires '.$statement->reference.'.',
                'created_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Paiement des honoraires enregistré.');
    }

    public function destroy(TeacherFeeStatement $teacherFee): RedirectResponse
    {
        abort_unless($teacherFee->status === 'draft', 422, 'Seul un brouillon peut être supprimé.');
        $teacherFee->delete();

        return redirect()->route('teacher-fees.index')->with('success', 'Brouillon supprimé. Les heures sont de nouveau disponibles.');
    }

    public function pdf(Request $request, TeacherFeeStatement $teacherFee)
    {
        $this->authorizeStatementAccess($request, $teacherFee);
        $teacherFee->load(['academicYear', 'teacher.teacherProfile', 'lines.schoolClass', 'lines.subject']);
        $groupedLines = $teacherFee->lines
            ->groupBy(fn ($line) => implode('|', [$line->school_class_id, $line->subject_id, $line->hourly_rate]))
            ->map(fn ($lines) => [
                'class' => $lines->first()->schoolClass?->name ?? '-',
                'subject' => $lines->first()->subject?->name ?? 'Cours',
                'hours' => $lines->sum(fn ($line) => (float) $line->hours),
                'rate' => (float) $lines->first()->hourly_rate,
                'amount' => $lines->sum(fn ($line) => (float) $line->amount),
            ])
            ->values();

        return Pdf::loadView('teacher-fees.pdf', [
            'amountInWords' => $this->amountInWordsService->convert($teacherFee->net_amount),
            'groupedLines' => $groupedLines,
            'school' => SchoolSetting::query()->first(),
            'statement' => $teacherFee,
        ])->setPaper('a4')->stream('honoraires-'.$teacherFee->reference.'.pdf');
    }

    private function authorizeStatementAccess(Request $request, TeacherFeeStatement $statement): void
    {
        abort_unless($request->user()->can('teacher_fees.manage') || $request->user()->is($statement->teacher), 403);
    }

    private function visibleTeachers(Request $request, string $managePermission)
    {
        $query = User::query()->role('enseignant')->where('status', 'active')->orderBy('name');

        if (! $request->user()->can($managePermission)) {
            $query->whereKey($request->user()->id);
        }

        return $query->get();
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function accountingPaymentMethod(string $method): string
    {
        return match ($method) {
            'Espèces' => 'cash',
            'Virement', 'Chèque' => 'bank_transfer',
            'Mobile Money' => 'mobile_money',
            default => 'other',
        };
    }
}
