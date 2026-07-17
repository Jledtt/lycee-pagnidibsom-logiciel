@extends('layouts.app', [
    'title' => 'Nouvelle classe - Lycee Prive Pagnidibsom',
    'active' => 'classes',
    'pageTitle' => 'Nouvelle classe',
    'pageSubtitle' => 'Creation d\'une classe pour l\'annee ' . ($academicYear?->name ?? 'active'),
])

@section('content')
    <form method="POST" action="{{ route('classes.store') }}">
        @csrf
        @include('classes._form', ['submitLabel' => 'Creer la classe'])
    </form>
@endsection
