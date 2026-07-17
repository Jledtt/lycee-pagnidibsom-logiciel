<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\FeeSchedule;
use App\Models\Payment;
use App\Models\PaymentLine;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReportWebController extends Controller
{
    public function classList(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        if ($schoolClass) {
            $schoolClass = $this->loadClassList($schoolClass);
        }

        return view('reports.class-list', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'filters' => $request->only(['school_class_id']),
            'schoolClass' => $schoolClass,
            'summary' => $this->classSummary($schoolClass),
        ]);
    }

    public function classListPdf(Request $request)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        abort_if(! $schoolClass, 404, 'Classe introuvable.');

        $schoolClass = $this->loadClassList($schoolClass);
        $filename = 'liste-eleves-' . Str::slug($schoolClass->name . '-' . ($academicYear?->name ?? 'annee')) . '.pdf';

        return Pdf::loadView('reports.class-list-pdf', [
            'academicYear' => $academicYear,
            'school' => SchoolSetting::query()->first(),
            'schoolClass' => $schoolClass,
            'summary' => $this->classSummary($schoolClass),
        ])
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    public function paymentSituation(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        if ($schoolClass) {
            $schoolClass = $this->loadClassList($schoolClass);
        }

        $rows = $this->paymentRows($schoolClass, $academicYear);

        return view('reports.payment-situation', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'filters' => $request->only(['school_class_id']),
            'rows' => $rows,
            'schoolClass' => $schoolClass,
            'summary' => $this->paymentSummary($rows),
        ]);
    }

    public function paymentSituationPdf(Request $request)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        abort_if(! $schoolClass, 404, 'Classe introuvable.');

        $schoolClass = $this->loadClassList($schoolClass);
        $rows = $this->paymentRows($schoolClass, $academicYear);
        $filename = 'situation-paiements-' . Str::slug($schoolClass->name . '-' . ($academicYear?->name ?? 'annee')) . '.pdf';

        return Pdf::loadView('reports.payment-situation-pdf', [
            'academicYear' => $academicYear,
            'rows' => $rows,
            'school' => SchoolSetting::query()->first(),
            'schoolClass' => $schoolClass,
            'summary' => $this->paymentSummary($rows),
        ])
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    public function installmentSituation(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        if ($schoolClass) {
            $schoolClass = $this->loadClassList($schoolClass);
        }

        $rows = $this->installmentRows($schoolClass, $academicYear);

        return view('reports.installments', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'filters' => $request->only(['school_class_id']),
            'rows' => $rows,
            'schoolClass' => $schoolClass,
            'summary' => $this->installmentSummary($rows),
        ]);
    }

    public function installmentSituationPdf(Request $request)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        abort_if(! $schoolClass, 404, 'Classe introuvable.');

        $schoolClass = $this->loadClassList($schoolClass);
        $rows = $this->installmentRows($schoolClass, $academicYear);
        $filename = 'tranches-paiement-' . Str::slug($schoolClass->name . '-' . ($academicYear?->name ?? 'annee')) . '.pdf';

        return Pdf::loadView('reports.installments-pdf', [
            'academicYear' => $academicYear,
            'rows' => $rows,
            'school' => SchoolSetting::query()->first(),
            'schoolClass' => $schoolClass,
            'summary' => $this->installmentSummary($rows),
        ])
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function classes(?AcademicYear $academicYear)
    {
        return SchoolClass::query()
            ->with('level')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function selectedClass(Request $request, $classes): ?SchoolClass
    {
        $selectedId = $request->integer('school_class_id');

        if ($selectedId > 0) {
            return $classes->firstWhere('id', $selectedId);
        }

        return $classes->first();
    }

    private function loadClassList(SchoolClass $schoolClass): SchoolClass
    {
        return $schoolClass->load([
            'level',
            'academicYear',
            'enrollments' => fn ($query) => $query
                ->with(['student.guardians'])
                ->where('enrollments.status', 'active')
                ->whereHas('student', fn ($studentQuery) => $studentQuery->where('status', 'active'))
                ->join('students', 'students.id', '=', 'enrollments.student_id')
                ->orderBy('students.last_name')
                ->orderBy('students.first_name')
                ->select('enrollments.*'),
        ]);
    }

    private function classSummary(?SchoolClass $schoolClass): array
    {
        if (! $schoolClass) {
            return [
                'total' => 0,
                'girls' => 0,
                'boys' => 0,
            ];
        }

        $students = $schoolClass->enrollments->pluck('student')->filter();

        return [
            'total' => $students->count(),
            'girls' => $students->where('gender', 'female')->count(),
            'boys' => $students->where('gender', 'male')->count(),
        ];
    }

    private function paymentRows(?SchoolClass $schoolClass, ?AcademicYear $academicYear): Collection
    {
        if (! $schoolClass) {
            return collect();
        }

        $expected = $this->expectedAmount($schoolClass, $academicYear);
        $studentIds = $schoolClass->enrollments->pluck('student_id')->all();

        $paidByStudent = Payment::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->whereIn('student_id', $studentIds)
            ->where('status', 'valid')
            ->selectRaw('student_id, sum(amount) as total_paid')
            ->groupBy('student_id')
            ->pluck('total_paid', 'student_id');

        return $schoolClass->enrollments
            ->map(function ($enrollment) use ($expected, $paidByStudent) {
                $paid = (float) ($paidByStudent[$enrollment->student_id] ?? 0);
                $balance = is_null($expected) ? null : max($expected - $paid, 0);

                return [
                    'enrollment' => $enrollment,
                    'student' => $enrollment->student,
                    'expected' => $expected,
                    'paid' => $paid,
                    'balance' => $balance,
                    'status' => $this->paymentStatus($expected, $paid),
                ];
            })
            ->values();
    }

    private function expectedAmount(SchoolClass $schoolClass, ?AcademicYear $academicYear): ?float
    {
        $amount = FeeSchedule::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('school_class_id', $schoolClass->id)
            ->sum('amount');

        return $amount > 0 ? (float) $amount : null;
    }

    private function paymentStatus(?float $expected, float $paid): array
    {
        if (is_null($expected)) {
            return ['label' => 'Tarif a configurer', 'class' => 'badge-warning'];
        }

        if ($paid <= 0) {
            return ['label' => 'Impaye', 'class' => 'badge-warning'];
        }

        if ($paid < $expected) {
            return ['label' => 'Partiel', 'class' => 'badge-warning'];
        }

        return ['label' => 'A jour', 'class' => ''];
    }

    private function paymentSummary(Collection $rows): array
    {
        $expectedTotal = $rows->contains(fn (array $row) => is_null($row['expected']))
            ? null
            : $rows->sum('expected');

        $paidTotal = $rows->sum('paid');

        return [
            'expected' => $expectedTotal,
            'paid' => $paidTotal,
            'balance' => is_null($expectedTotal) ? null : max($expectedTotal - $paidTotal, 0),
            'up_to_date' => $rows->filter(fn (array $row) => $row['status']['label'] === 'A jour')->count(),
            'partial' => $rows->filter(fn (array $row) => $row['status']['label'] === 'Partiel')->count(),
            'unpaid' => $rows->filter(fn (array $row) => $row['status']['label'] === 'Impaye')->count(),
        ];
    }

    private function installmentRows(?SchoolClass $schoolClass, ?AcademicYear $academicYear): Collection
    {
        if (! $schoolClass) {
            return collect();
        }

        $schedules = FeeSchedule::query()
            ->with('feeType')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('school_class_id', $schoolClass->id)
            ->orderBy('period')
            ->orderBy('id')
            ->get();
        $studentIds = $schoolClass->enrollments->pluck('student_id')->all();

        $paidByStudentAndSchedule = PaymentLine::query()
            ->join('payments', 'payments.id', '=', 'payment_lines.payment_id')
            ->when($academicYear, fn ($query) => $query->where('payments.academic_year_id', $academicYear->id))
            ->where('payments.status', 'valid')
            ->whereIn('payments.student_id', $studentIds)
            ->whereNotNull('payment_lines.fee_schedule_id')
            ->selectRaw('payments.student_id, payment_lines.fee_schedule_id, sum(payment_lines.amount) as total_paid')
            ->groupBy('payments.student_id', 'payment_lines.fee_schedule_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->student_id . ':' . $row->fee_schedule_id => (float) $row->total_paid]);

        return $schoolClass->enrollments
            ->flatMap(function ($enrollment) use ($paidByStudentAndSchedule, $schedules) {
                return $schedules->map(function (FeeSchedule $schedule) use ($enrollment, $paidByStudentAndSchedule) {
                    $expected = (float) $schedule->amount;
                    $paid = (float) ($paidByStudentAndSchedule[$enrollment->student_id . ':' . $schedule->id] ?? 0);
                    $balance = max($expected - $paid, 0);

                    return [
                        'student' => $enrollment->student,
                        'schedule' => $schedule,
                        'expected' => $expected,
                        'paid' => $paid,
                        'balance' => $balance,
                        'status' => $this->paymentStatus($expected, $paid),
                    ];
                });
            })
            ->values();
    }

    private function installmentSummary(Collection $rows): array
    {
        return [
            'expected' => (float) $rows->sum('expected'),
            'paid' => (float) $rows->sum('paid'),
            'balance' => (float) $rows->sum('balance'),
            'up_to_date' => $rows->filter(fn (array $row) => $row['status']['label'] === 'A jour')->count(),
            'partial' => $rows->filter(fn (array $row) => $row['status']['label'] === 'Partiel')->count(),
            'unpaid' => $rows->filter(fn (array $row) => $row['status']['label'] === 'Impaye')->count(),
        ];
    }
}
