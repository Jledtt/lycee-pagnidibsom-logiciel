@extends('layouts.app', [
    'title' => 'Sauvegardes - Lycée Privé Pagnidibsom',
    'active' => 'settings',
    'pageTitle' => 'Sauvegardes',
    'pageSubtitle' => 'Exporter la base et préparer une restauration en cas de panne',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('settings.edit') }}">Paramètres école</a>
    <a class="btn btn-subtle" href="{{ route('help.index') }}">Guide utilisateur</a>
@endsection

@section('content')
    <section class="grid two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Nouvelle sauvegarde</h2>
            </div>

            <p style="margin:0 0 16px;color:var(--muted)">
                La sauvegarde crée une archive ZIP facile à télécharger. Elle contient un export JSON portable et,
                selon la base utilisée, une copie SQLite ou un fichier SQL MySQL/PostgreSQL.
            </p>

            <form method="POST" action="{{ route('settings.backups.store') }}">
                @csrf
                <button class="btn btn-primary" type="submit">Créer une sauvegarde maintenant</button>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Automatique</h2>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <span>Commande</span>
                    <strong>php artisan lpp:backup-database</strong>
                </div>
                <div class="detail-item">
                    <span>Dossier</span>
                    <strong>{{ $directory }}</strong>
                </div>
                <div class="detail-item">
                    <span>Planification</span>
                    <strong>Chaque jour à {{ env('LPP_BACKUP_TIME', '22:00') }} si le planificateur Laravel est actif</strong>
                </div>
                <div class="detail-item">
                    <span>Conservation</span>
                    <strong>{{ max((int) env('LPP_BACKUP_KEEP_DAYS', 14), 1) }} jour(s), réglable dans .env</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Fichiers disponibles</h2>
            <span class="badge">{{ $backups->count() }} fichier(s)</span>
        </div>

        @if ($backups->isEmpty())
            <div class="empty">Aucune sauvegarde pour le moment.</div>
        @else
            <div class="subject-list-scroll">
                <table class="table" style="min-width:820px">
                    <thead>
                        <tr>
                            <th>Fichier</th>
                            <th>Type</th>
                            <th>Taille</th>
                            <th>Date</th>
                            <th>Conseil</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($backups as $backup)
                            <tr>
                                <td><strong>{{ $backup['name'] }}</strong></td>
                                <td>{{ strtoupper($backup['extension']) }}</td>
                                <td>{{ number_format($backup['size'] / 1024, 1, ',', ' ') }} Ko</td>
                                <td>{{ \Carbon\Carbon::createFromTimestamp($backup['created_at'])->format('d/m/Y H:i') }}</td>
                                <td>
                                    @if ($backup['extension'] === 'zip')
                                        <span class="badge">Recommandé</span>
                                    @else
                                        <span class="muted">Technique</span>
                                    @endif
                                </td>
                                <td>
                                    <a class="btn btn-subtle" href="{{ route('settings.backups.download', $backup['name']) }}">Télécharger</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Restauration</h2>
        </div>

        <div class="ledger-list">
            <div class="detail-item">
                <span>Avant toute restauration</span>
                <strong>Faire une nouvelle sauvegarde, prévenir les utilisateurs et mettre le site en maintenance.</strong>
            </div>
            <div class="detail-item">
                <span>Archive ZIP</span>
                <strong>Décompresser l'archive, puis utiliser le fichier .sql ou .sqlite adapté à la base installée.</strong>
            </div>
            <div class="detail-item">
                <span>MySQL / MariaDB</span>
                <strong>Importer le fichier .sql avec HeidiSQL ou mysql, puis relancer php artisan config:clear.</strong>
            </div>
            <div class="detail-item">
                <span>SQLite</span>
                <strong>Remplacer database/database.sqlite par la copie .sqlite sauvegardée.</strong>
            </div>
        </div>
    </section>
@endsection
