@extends('layouts.app', [
    'title' => 'Nouveau paiement - Lycée Privé Pagnidibsom',
    'active' => 'payments',
    'pageTitle' => 'Nouveau paiement',
    'pageSubtitle' => 'Enregistrer un encaissement et générer un reçu',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('payments.index') }}">Retour aux paiements</a>
@endsection

@section('content')
    <section class="panel payment-page-form">
        @include('payments.partials.form', [
            'paymentForm' => $paymentForm,
            'formId' => 'payment-create-page-form',
            'cancelUrl' => route('payments.index'),
        ])
    </section>
@endsection
