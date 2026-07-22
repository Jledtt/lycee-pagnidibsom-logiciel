@extends('layouts.app', [
    'title' => 'Modifier inscription - Lycée Privé Pagnidibsom',
    'active' => 'enrollments',
    'pageTitle' => 'Modifier inscription',
    'pageSubtitle' => $enrollment->student?->full_name ?? 'Inscription',
])

@section('content')
    <form method="POST" action="{{ route('enrollments.update', $enrollment) }}">
        @csrf
        @method('PUT')
        @include('enrollments._form', ['submitLabel' => 'Mettre à jour'])
    </form>
@endsection
