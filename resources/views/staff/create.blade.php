@extends('layouts.app', [
    'title' => 'Nouvel utilisateur - Lycee Prive Pagnidibsom',
    'active' => 'staff',
    'pageTitle' => 'Nouvel utilisateur',
    'pageSubtitle' => 'Creation d un compte pour le personnel de l etablissement',
])

@section('content')
    <form method="POST" action="{{ route('staff.store') }}">
        @csrf

        @include('staff._form', ['submitLabel' => 'Creer le compte'])
    </form>
@endsection
