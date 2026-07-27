@extends('layouts.app', [
    'title' => 'Aide - Lycée Privé Pagnidibsom',
    'active' => 'help',
    'pageTitle' => 'Aide et guide utilisateur',
    'pageSubtitle' => 'Reperes rapides pour utiliser le logiciel de gestion scolaire',
])

@section('content')
    <section class="grid two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Démarrage rapide</h2>
            </div>

            <div class="ledger-list">
                <div class="detail-item">
                    <span>1. Paramêtrer l’année</span>
                    <strong>Active l’année scolaire, les trimestres, les classes, les tarifs et les matières.</strong>
                </div>
                <div class="detail-item">
                    <span>2. Créer les élèves</span>
                    <strong>Ajoute les dossiers élèves, parents, contacts et informations medicales.</strong>
                </div>
                <div class="detail-item">
                    <span>3. Inscrire dans une classe</span>
                    <strong>Rattache chaque élève à une classe pour l’année active.</strong>
                </div>
                <div class="detail-item">
                    <span>4. Suivre le quotidien</span>
                    <strong>Enregistre paiements, absences, notes, bulletins et documents.</strong>
                </div>
            </div>
        </div>

        <div class="panel help-roles-panel">
            <div class="panel-head">
                <h2>Rôles principaux</h2>
            </div>

            <table class="table help-role-table">
                <thead>
                    <tr>
                        <th>Rôle</th>
                        <th>Utilisation normale</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Direction</strong></td>
                        <td>Suivi global, bulletins, rapports et contrôle.</td>
                    </tr>
                    <tr>
                        <td><strong>Secretariat</strong></td>
                        <td>Dossiers élèves, inscriptions, classes et documents.</td>
                    </tr>
                    <tr>
                        <td><strong>Comptabilité</strong></td>
                        <td>Paiements, reçus, impayés, journal et rapports financiers.</td>
                    </tr>
                    <tr>
                        <td><strong>Enseignant</strong></td>
                        <td>Notes et pointage selon les accès donnés.</td>
                    </tr>
                    <tr>
                        <td><strong>Surveillant</strong></td>
                        <td>Absences, retards, justifications et rapports d’assiduite.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Raccourcis de travail</h2>
        </div>

        <div class="grid modules">
            @can('students.view')
                <a class="module" href="{{ route('students.index') }}">
                    <strong>Rechercher un élève</strong>
                    <span>Ouvre la liste, recherche par nom ou matricule, puis accède à la fiche.</span>
                </a>
            @endcan
            @can('students.export')
                <a class="module" href="{{ route('certificates.index') }}">
                    <strong>Documents officiels</strong>
                    <span>Certificats, fiches d’inscription et impressions PDF.</span>
                </a>
            @endcan
            @can('payments.view')
                <a class="module" href="{{ route('payments.index') }}">
                    <strong>Situation financiere</strong>
                    <span>Consulte les paiements, impayés et reçus selon ton role.</span>
                </a>
            @endcan
            @can('attendance.view')
                <a class="module" href="{{ route('attendance.index') }}">
                    <strong>Absences et retards</strong>
                    <span>Faire l’appel, corriger une absence et exporter les rapports.</span>
                </a>
            @endcan
            @can('grades.view')
                <a class="module" href="{{ route('grades.index') }}">
                    <strong>Notes</strong>
                    <span>Saisir, verifier et verrouiller les evaluations.</span>
                </a>
            @endcan
            @can('report_cards.view')
                <a class="module" href="{{ route('report-cards.index') }}">
                    <strong>Bulletins</strong>
                    <span>Générer les moyennes, décisions et PDF de bulletins.</span>
                </a>
            @endcan
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Maintenance</h2>
        </div>

        <div class="detail-grid">
            <div class="detail-item">
                <span>Sauvegarde manuelle</span>
                <strong>Paramètres puis Sauvegardes, ou php artisan lpp:backup-database</strong>
            </div>
            <div class="detail-item">
                <span>Verification technique</span>
                <strong>php artisan test</strong>
            </div>
            <div class="detail-item">
                <span>Gestion des accès</span>
                <strong>Personnel puis Rôles et accès</strong>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Serveur reel et restauration</h2>
        </div>

        <div class="ledger-list">
            <div class="detail-item">
                <span>Avant de deployer</span>
                <strong>Utiliser MySQL ou PostgreSQL, verifier APP_ENV=production, APP_DEBUG=false et proteger le dossier storage.</strong>
            </div>
            <div class="detail-item">
                <span>Fichiers élèves</span>
                <strong>Les documents scannes et logos doivent rester dans storage/app ou public/images avec une sauvegarde reguliere.</strong>
            </div>
            <div class="detail-item">
                <span>Restauration MySQL</span>
                <strong>Créer une base vide, importer le fichier .sql via HeidiSQL ou mysql, puis lancer php artisan config:clear.</strong>
            </div>
            <div class="detail-item">
                <span>Restauration SQLite</span>
                <strong>Arreter l’application, remplacer database/database.sqlite par la copie sauvegardee, puis redemarrer.</strong>
            </div>
        </div>
    </section>
@endsection
