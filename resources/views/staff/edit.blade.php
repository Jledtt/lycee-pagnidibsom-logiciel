@extends('layouts.app', [
    'title' => 'Modifier utilisateur - Lycee Prive Pagnidibsom',
    'active' => 'staff',
    'pageTitle' => 'Modifier utilisateur',
    'pageSubtitle' => $user->name,
])

@section('content')
    <form method="POST" action="{{ route('staff.update', $user) }}">
        @csrf
        @method('PUT')

        @include('staff._form', ['submitLabel' => 'Enregistrer'])
    </form>
@endsection
