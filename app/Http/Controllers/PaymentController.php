<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\Student;
use App\Rules\ValidPaymentDate;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->with(['student', 'lines.feeType', 'receiver', 'canceller'])
            ->when($request->integer('student_id'), fn ($query, int $studentId) => $query->where('student_id', $studentId))
            ->latest('paid_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($payments);
    }

    public function store(Request $request, PaymentService $paymentService): JsonResponse
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'payment_method' => ['nullable', 'in:cash,mobile_money,bank_transfer,other'],
            'paid_at' => ['nullable', 'date', new ValidPaymentDate($request->user())],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.fee_type_id' => ['required', 'exists:fee_types,id'],
            'lines.*.fee_schedule_id' => [
                'nullable',
                Rule::exists('fee_schedules', 'id')
                    ->where('academic_year_id', $academicYear->id),
            ],
            'lines.*.amount' => ['required', 'integer', 'min:0'],
        ]);

        $student = Student::findOrFail($data['student_id']);

        $payment = $paymentService->createPayment(
            $student,
            $academicYear,
            $request->user(),
            $data['lines'],
            $data
        );

        return response()->json($payment, 201);
    }

    public function cancel(Request $request, Payment $payment, PaymentService $paymentService): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        return response()->json(
            $paymentService->cancel($payment, $request->user(), $data['reason'])
        );
    }
}
