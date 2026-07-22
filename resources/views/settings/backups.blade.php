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
                La sauvegarde créé un fichier JSON portable. Avec MySQL/Laragon, un fichier SQL est aussi généré si
                <strong>mysqldump</strong> est disponible.
            </p>

            <form method="POST" action="{{ route('settings.backups.store') }}">
                @csrf
                <button class="btn btn-primary" type="submit">Exporter une sauvegarde</button>
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
                    <strong>LPP_BACKUP_TIME / LPP_BACKUP_KEEP_DAYS dans .env</strong>
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
                <strong>Faire une copie de la base actuelle et mettre le site en maintenance.</strong>
            </div>
            <div class="detail-item">
                <span>MySQL / MariaDB</span>
                <strong>Importer le fichier .sql avec HeidiSQL ou mysql, puis relancer php artisan config:clear.</strong>
            </div>
            <div class="detail-item">
                <span>SQLite</span>
                <strong>Remplacer database/database.sqlite par la copie .sqlite sauvegardee.</strong>
            </div>
        </div>
    </section>
@endsection
