@extends('layouts.app', [
    'title' => 'Nouvelle inscription - Lycée Privé Pagnidibsom',
    'active' => 'enrollments',
    'pageTitle' => 'Nouvelle inscription',
    'pageSubtitle' => 'Inscrire un élève dans une classe pour ' . ($academicYear?->name ?? 'l\'année active'),
])

@section('content')
    @if ($students->isEmpty())
        <div class="empty">Aucun élève disponible à inscrire. Tous les élèves actifs sont déjà inscrits pour cette année.</div>
    @elseif ($classes->isEmpty())
        <div class="empty">Aucune classe active. Crée d’abord une classe avant d’inscrire un élève.</div>
    @else
        <form method="POST" action="{{ route('enrollments.store') }}">
            @csrf
            @include('enrollments._form', ['submitLabel' => 'Enregistrer l\'inscription'])
        </form>
    @endif
@endsection
