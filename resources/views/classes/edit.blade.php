@extends('layouts.app', [
    'title' => 'Modifier classe - Lycee Prive Pagnidibsom',
    'active' => 'classes',
    'pageTitle' => 'Modifier la classe',
    'pageSubtitle' => $schoolClass->name . ' - ' . ($academicYear?->name ?? 'Annee active'),
])

@section('content')
    <form method="POST" action="{{ route('classes.update', $schoolClass) }}">
        @csrf
        @method('PUT')
        @include('classes._form', ['submitLabel' => 'Mettre a jour'])
    </form>
@endsection
