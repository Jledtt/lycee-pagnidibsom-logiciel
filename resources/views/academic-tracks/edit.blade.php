@extends('layouts.app', [
    'title' => $academicTrack->name . ' - Lycée Privé Pagnidibsom',
    'active' => 'academic-tracks',
    'pageTitle' => $academicTrack->name,
    'pageSubtitle' => 'Modifier le référentiel sans supprimer les classes déjà associées',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('academic-tracks.index') }}">Retour</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('academic-tracks.update', $academicTrack) }}" data-prevent-double-submit>
        @csrf
        @method('PUT')
        @include('academic-tracks._form', ['submitLabel' => 'Mettre à jour'])
    </form>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Classes associées</h2>
            <span class="badge">{{ $academicTrack->schoolClasses->count() }} classe(s)</span>
        </div>

        @if ($academicTrack->schoolClasses->isEmpty())
            <div class="empty">Cette série ou filière n’est encore affectée à aucune classe.</div>
        @else
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Niveau</th>
                            <th>Année scolaire</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($academicTrack->schoolClasses as $schoolClass)
                            <tr>
                                <td><a href="{{ route('classes.show', $schoolClass) }}"><strong>{{ $schoolClass->name }}</strong></a></td>
                                <td>{{ $schoolClass->level?->name ?? '-' }}</td>
                                <td>{{ $schoolClass->academicYear?->name ?? '-' }}</td>
                                <td>{{ $schoolClass->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
