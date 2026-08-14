<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\PaymentLine;
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
            ->with([
                'student:id,matricule,first_name,last_name,gender,status',
                'lines.feeType',
                'receiver:id,name',
                'canceller:id,name',
            ])
            ->when($request->integer('student_id'), fn ($query, int $studentId) => $query->where('student_id', $studentId))
            ->latest('paid_at')
            ->paginate($request->integer('per_page', 20));

        $payments->through(fn (Payment $payment): array => $this->paymentPayload($payment));

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
            'lines.*.amount' => ['required', 'integer', 'min:1'],
        ]);

        $student = Student::findOrFail($data['student_id']);

        $payment = $paymentService->createPayment(
            $student,
            $academicYear,
            $request->user(),
            $data['lines'],
            $data
        );

        return response()->json($this->paymentPayload($payment), 201);
    }

    public function cancel(Request $request, Payment $payment, PaymentService $paymentService): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        return response()->json($this->paymentPayload(
            $paymentService->cancel($payment, $request->user(), $data['reason'])
        ));
    }

    /** @return array<string, mixed> */
    private function paymentPayload(Payment $payment): array
    {
        $payment->loadMissing(['student', 'lines.feeType', 'receiver', 'canceller']);

        return [
            'id' => $payment->id,
            'receipt_number' => $payment->receipt_number,
            'academic_year_id' => $payment->academic_year_id,
            'student_id' => $payment->student_id,
            'enrollment_id' => $payment->enrollment_id,
            'paid_at' => $payment->paid_at->toIso8601String(),
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
            'status' => $payment->status,
            'cancelled_at' => $payment->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $payment->cancellationReasonForDisplay(),
            'notes' => $payment->notes,
            'student' => $payment->student ? [
                'id' => $payment->student->id,
                'matricule' => $payment->student->matricule,
                'first_name' => $payment->student->first_name,
                'last_name' => $payment->student->last_name,
                'full_name' => $payment->student->full_name,
                'gender' => $payment->student->gender,
                'status' => $payment->student->status,
            ] : null,
            'lines' => $payment->lines
                ->map(fn (PaymentLine $line): array => [
                    'id' => $line->id,
                    'fee_type_id' => $line->fee_type_id,
                    'fee_schedule_id' => $line->fee_schedule_id,
                    'amount' => $line->amount,
                    'fee_type' => $line->feeType ? [
                        'id' => $line->feeType->id,
                        'name' => $line->feeType->name,
                        'code' => $line->feeType->code,
                    ] : null,
                ])
                ->values()
                ->all(),
            'receiver' => $payment->receiver ? [
                'id' => $payment->receiver->id,
                'name' => $payment->receiver->name,
            ] : null,
            'canceller' => $payment->canceller ? [
                'id' => $payment->canceller->id,
                'name' => $payment->canceller->name,
            ] : null,
            'created_at' => $payment->created_at?->toIso8601String(),
            'updated_at' => $payment->updated_at?->toIso8601String(),
        ];
    }
}
