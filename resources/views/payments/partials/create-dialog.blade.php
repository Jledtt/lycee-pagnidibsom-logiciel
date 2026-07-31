@php
    $paymentFields = [
        'student_id',
        'paid_at',
        'payment_method',
        'notes',
        'lines',
        'lines.*.fee_schedule_id',
        'lines.*.amount',
    ];
    $openPaymentDialog = session('payment_form_open')
        || collect($paymentFields)->contains(fn (string $field) => $errors->has($field));
@endphp

<x-ui.modal
    :id="$dialogId"
    class="payment-dialog"
    title="Nouveau paiement"
    description="Sélectionnez l’élève et les frais réellement encaissés."
    size="large"
    :open="$openPaymentDialog"
>
    @include('payments.partials.form', [
        'paymentForm' => $paymentForm,
        'formId' => $formId,
        'cancelUrl' => $cancelUrl,
        'dialogId' => $dialogId,
    ])
</x-ui.modal>
