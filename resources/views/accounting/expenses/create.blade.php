@extends('layouts.app', [
    'title' => 'Nouvelle dépense - Lycée Privé Pagnidibsom',
    'active' => 'accounting',
    'pageTitle' => 'Nouvelle dépense',
    'pageSubtitle' => 'Enregistrer une sortie de caisse',
])

@section('content')
    <form method="POST" action="{{ route('accounting.expenses.store') }}">
        @csrf

        @include('accounting.expenses._form', ['submitLabel' => 'Enregistrer la dépense'])
    </form>
@endsection
