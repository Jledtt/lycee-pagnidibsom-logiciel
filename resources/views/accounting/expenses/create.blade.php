@extends('layouts.app', [
    'title' => 'Nouvelle depense - Lycee Prive Pagnidibsom',
    'active' => 'accounting',
    'pageTitle' => 'Nouvelle depense',
    'pageSubtitle' => 'Enregistrer une sortie de caisse',
])

@section('content')
    <form method="POST" action="{{ route('accounting.expenses.store') }}">
        @csrf

        @include('accounting.expenses._form', ['submitLabel' => 'Enregistrer la depense'])
    </form>
@endsection
