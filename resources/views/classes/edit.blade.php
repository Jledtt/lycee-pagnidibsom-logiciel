@extends('layouts.app', [
    'title' => 'Modifier classe - Lycée Privé Pagnidibsom',
    'active' => 'classes',
    'pageTitle' => 'Modifier la classe',
    'pageSubtitle' => $schoolClass->name . ' - ' . ($academicYear?->name ?? 'Année active'),
])

@section('content')
    <form method="POST" action="{{ route('classes.update', $schoolClass) }}">
        @csrf
        @method('PUT')
        @include('classes._form', ['submitLabel' => 'Mettre à jour'])
    </form>
@endsection
