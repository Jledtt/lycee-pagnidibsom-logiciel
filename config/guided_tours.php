<?php

return [
    'tours' => [
        'dashboard' => [
            'title' => 'Découvrir le tableau de bord',
            'description' => 'Repérez les actions, alertes et indicateurs utiles à votre rôle.',
            'route' => 'dashboard',
            'permissions' => [],
            'auto_prompt' => true,
            'steps' => [
                [
                    'target' => 'main-navigation',
                    'title' => 'Navigation principale',
                    'description' => 'Le menu affiche uniquement les modules autorisés pour votre compte. Les rubriques peuvent être ouvertes ou refermées.',
                ],
                [
                    'target' => 'dashboard-quick-actions',
                    'title' => 'Actions rapides',
                    'description' => 'Ces raccourcis ouvrent directement les opérations que vous utilisez le plus souvent.',
                ],
                [
                    'target' => 'dashboard-alerts',
                    'title' => 'Alertes importantes',
                    'description' => 'Commencez ici pour voir les paiements, absences, bulletins ou réglages qui demandent votre attention.',
                    'permissions' => ['students.view', 'classes.manage', 'enrollments.view', 'payments.reports', 'attendance.view', 'settings.manage', 'grades.view', 'report_cards.view'],
                ],
                [
                    'target' => 'dashboard-indicators',
                    'title' => 'Chiffres utiles',
                    'description' => 'Les indicateurs résument l’année active. Leur contenu dépend de vos permissions.',
                    'permissions' => ['students.view', 'classes.manage', 'enrollments.view', 'payments.reports', 'attendance.view', 'grades.view'],
                ],
                [
                    'target' => 'documentation-link',
                    'title' => 'Documentation',
                    'description' => 'Vous pouvez relire les guides et relancer une visite à tout moment depuis la documentation.',
                ],
            ],
        ],
        'timetable-planning' => [
            'title' => 'Créer un emploi du temps automatique',
            'description' => 'Ajoutez les disponibilités, corrigez les informations manquantes et créez un brouillon contrôlable.',
            'route' => 'timetables.planning',
            'permissions' => ['timetables.manage'],
            'auto_prompt' => true,
            'steps' => [
                [
                    'target' => 'timetable-overview',
                    'title' => 'Les trois étapes',
                    'description' => 'L’assistant suit un ordre simple : choisir, compléter les informations, puis créer un essai sans publier automatiquement.',
                ],
                [
                    'target' => 'timetable-availability-actions',
                    'title' => 'Disponibilités des professeurs',
                    'description' => 'Vous pouvez remplir les créneaux professeur par professeur ou télécharger le modèle CSV à compléter.',
                ],
                [
                    'target' => 'timetable-import',
                    'title' => 'Importer un fichier',
                    'description' => 'Choisissez un fichier CSV, Excel, PDF ou Word, puis analysez-le avant d’enregistrer les disponibilités.',
                ],
                [
                    'target' => 'timetable-class-selection',
                    'title' => 'Choisir une classe',
                    'description' => 'Pour les premiers essais, sélectionnez une seule classe afin de contrôler facilement le résultat.',
                ],
                [
                    'target' => 'timetable-blockers',
                    'title' => 'Corriger ce qui manque',
                    'description' => 'Ce bloc indique si des professeurs, matières, heures ou disponibilités empêchent encore la génération.',
                ],
                [
                    'target' => 'timetable-generate',
                    'title' => 'Créer un essai',
                    'description' => 'Le bouton devient disponible lorsque la sélection est prête. L’essai produit reste un brouillon à vérifier.',
                ],
                [
                    'target' => 'timetable-result',
                    'title' => 'Contrôler et appliquer',
                    'description' => 'Vérifiez chaque classe proposée avant d’utiliser le brouillon. Les emplois du temps actifs restent protégés.',
                ],
            ],
        ],
        'payments' => [
            'title' => 'Enregistrer et suivre les paiements',
            'description' => 'Retrouvez un élève, encaissez un montant et contrôlez les reçus.',
            'route' => 'payments.index',
            'permissions' => ['payments.view'],
            'auto_prompt' => true,
            'steps' => [
                [
                    'target' => 'payments-summary',
                    'title' => 'Résumé des encaissements',
                    'description' => 'Ces indicateurs présentent le total encaissé, le nombre de paiements affichés et l’année scolaire concernée.',
                ],
                [
                    'target' => 'payments-create',
                    'title' => 'Nouveau paiement',
                    'description' => 'Ouvrez cette fenêtre pour choisir l’élève, les frais, le montant et le mode de paiement.',
                    'permissions' => ['payments.create'],
                ],
                [
                    'target' => 'payments-search',
                    'title' => 'Retrouver un paiement',
                    'description' => 'Recherchez par numéro de reçu, nom, matricule ou statut sans perdre les autres paiements.',
                ],
                [
                    'target' => 'payments-list',
                    'title' => 'Paiements et reçus',
                    'description' => 'Ouvrez une ligne pour consulter le détail, le reçu et les actions autorisées, notamment l’annulation contrôlée.',
                ],
            ],
        ],
        'students' => [
            'title' => 'Gérer les dossiers élèves',
            'description' => 'Créez un élève, retrouvez son dossier et poursuivez son inscription.',
            'route' => 'students.index',
            'permissions' => ['students.view'],
            'auto_prompt' => true,
            'steps' => [
                [
                    'target' => 'students-create',
                    'title' => 'Créer un dossier élève',
                    'description' => 'Utilisez ce bouton pour saisir l’identité, les contacts, les parents et les informations scolaires.',
                    'permissions' => ['students.create'],
                ],
                [
                    'target' => 'students-import',
                    'title' => 'Importer plusieurs élèves',
                    'description' => 'L’import permet de préparer plusieurs dossiers à partir du modèle prévu par l’application.',
                    'permissions' => ['students.import'],
                ],
                [
                    'target' => 'students-search',
                    'title' => 'Rechercher un élève',
                    'description' => 'Filtrez par nom, prénom, matricule ou statut pour retrouver rapidement un dossier.',
                ],
                [
                    'target' => 'students-list',
                    'title' => 'Consulter le dossier',
                    'description' => 'Le bouton Voir ouvre le dossier complet avec l’inscription, les documents et les actions autorisées.',
                ],
            ],
        ],
    ],
];
