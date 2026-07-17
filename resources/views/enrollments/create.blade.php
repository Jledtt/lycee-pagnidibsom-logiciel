@extends('layouts.app', [
    'title' => 'Nouvelle inscription - Lycee Prive Pagnidibsom',
    'active' => 'enrollments',
    'pageTitle' => 'Nouvelle inscription',
    'pageSubtitle' => 'Inscrire un eleve dans une classe pour ' . ($academicYear?->name ?? 'l\'annee active'),
])

@section('content')
    @if ($students->isEmpty())
        <div class="empty">Aucun eleve disponible a inscrire. Tous les eleves actifs sont deja inscrits pour cette annee.</div>
    @elseif ($classes->isEmpty())
        <div class="empty">Aucune classe active. Cree d'abord une classe avant d'inscrire un eleve.</div>
    @else
        <form method="POST" action="{{ route('enrollments.store') }}">
            @csrf
            @include('enrollments._form', ['submitLabel' => 'Enregistrer l\'inscription'])
        </form>
    @endif
@endsection
