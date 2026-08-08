# Dossier projet - LPP Gestion Scolaire

Dernière mise à jour : 6 août 2026

## 1. Résumé exécutif

LPP Gestion Scolaire est une application Laravel destinée à gérer les opérations administratives, financières, pédagogiques et documentaires du Lycée Privé Pagnidibsom.

Le projet est aujourd'hui un logiciel web centralisé, accessible sur :

```text
https://gestion.lyceepagnidibsom.com
```

Les données restent sur le serveur. L'application Windows envisagée avec Tauri n'est pas une base locale séparée : elle sert seulement d'enveloppe autour du site HTTPS pour donner une expérience de logiciel de bureau, avec impression, téléchargements et ouverture plus propre des PDF.

La ligne directrice retenue est simple :

- logiciel en français, adapté au Burkina Faso ;
- montants en FCFA ;
- documents PDF imprimables et proches des pratiques de l'établissement ;
- interface claire pour des utilisateurs non techniciens ;
- séparation stricte des rôles : secrétariat, comptabilité, direction, enseignants, surveillants, administration ;
- données sensibles protégées par Laravel, permissions et journaux d'activité ;
- sauvegardes et restauration vérifiables ;
- évolution progressive vers un logiciel plus général, mais sans casser l'usage actuel de LPP.

## 2. Contexte et objectif

Le projet est né d'un besoin concret : remplacer des traitements manuels, fichiers éparpillés et documents papier par un outil unique capable de suivre la scolarité, les paiements, les notes, les absences, les examens, les professeurs et les documents officiels.

Les priorités métier sont :

- fiabiliser les inscriptions et les dossiers élèves ;
- suivre les paiements, les impayés et les dépenses ;
- produire des reçus, fiches, cartes, certificats, relevés et bulletins ;
- calculer correctement notes, moyennes, rangs et décisions ;
- gérer les professeurs, leurs heures, leurs émargements et leurs honoraires ;
- organiser les emplois du temps manuels ou générés automatiquement ;
- envoyer des notifications par e-mail ;
- garder une trace des actions importantes ;
- sécuriser les données et préparer une remise propre au client.

Le projet vise d'abord le Lycée Privé Pagnidibsom. La généralisation à d'autres établissements est prévue plus tard, une fois les bases métier stabilisées.

## 3. Informations techniques principales

### Dépôt et chemins

Projet local :

```text
C:\Users\eddyt\Documents\Codex\lycee-pagnidibsom-logiciel\app-source
```

Serveur de production :

```text
164.132.198.19
```

Chemin serveur :

```text
/var/www/lycee-pagnidibsom-logiciel/app-source
```

Domaine :

```text
gestion.lyceepagnidibsom.com
```

Dépôt GitHub :

```text
Jledtt/lycee-pagnidibsom-logiciel
```

### Stack utilisée

- Laravel 13 ;
- PHP 8.3+ côté projet, PHP-FPM côté serveur ;
- MySQL/MariaDB en production ;
- SQLite possible pour les tests et certains environnements locaux ;
- Vite et Tailwind CSS pour les assets ;
- Playwright pour les tests navigateur ;
- PHPUnit pour les tests Laravel ;
- Larastan/PHPStan pour l'analyse statique ;
- Laravel Pint pour le formatage ;
- DomPDF pour les documents PDF ;
- Spatie Permission pour les rôles et permissions ;
- Spatie Activitylog pour la traçabilité ;
- Spatie Backup pour les sauvegardes ;
- Spatie Medialibrary disponible dans la stack ;
- Resend pour les e-mails ;
- queue Laravel en mode database ;
- solveur d'emploi du temps basé sur OR-Tools côté environnement Python.

## 4. Ligne directrice produit

### Principe général

L'application doit rester pratique avant d'être spectaculaire. Elle doit aider le personnel à faire plus vite et avec moins d'erreurs, sans imposer une logique technique difficile.

Les écrans doivent donc privilégier :

- des tableaux lisibles ;
- des filtres simples ;
- des boutons d'action explicites ;
- des modales courtes pour les petites opérations ;
- des pages complètes pour les créations détaillées ;
- des PDF propres et imprimables ;
- une navigation qui ne perd pas l'utilisateur.

### Choix visuels

La charte actuelle reprend l'identité LPP :

- bordeaux comme couleur principale ;
- doré pour les accents ;
- blanc et fonds clairs pour la lisibilité ;
- typographie sobre ;
- peu d'effets décoratifs ;
- priorité aux documents, tableaux et formulaires.

Les interfaces opérationnelles ne doivent pas ressembler à une page marketing. Elles doivent rester denses mais respirables, comme un outil de travail.

### Choix métier

Plusieurs règles ont été posées :

- un reçu ne doit jamais être modifié discrètement après validation ;
- une erreur de paiement se corrige par annulation motivée ;
- les notes doivent pouvoir être verrouillées ;
- les conseils de classe doivent être verrouillables ;
- les actions sensibles doivent être confirmées proprement ;
- les documents téléversés doivent passer par les autorisations Laravel ;
- les exports contenant des données personnelles doivent rester sous contrôle ;
- les comptes doivent être individuels, pas partagés ;
- le logiciel doit fonctionner avec des connexions modestes, sans dépendre d'une interface lourde.

## 5. Architecture applicative

### Organisation Laravel

Le projet suit une architecture Laravel classique :

- `app/Models` : entités métier ;
- `app/Http/Controllers` : contrôleurs web ;
- `app/Services` : logique métier réutilisable ;
- `database/migrations` : structure de la base ;
- `database/seeders` : données de départ et données de test ;
- `resources/views` : écrans Blade et modèles PDF ;
- `routes/web.php` : routes principales ;
- `routes/console.php` : commandes et planification ;
- `tests/Feature`, `tests/Unit`, `tests/e2e` : tests automatisés ;
- `docs` : documentation technique et décisions de format.

Le code sépare progressivement les calculs métier dans des services dédiés. C'est important pour éviter de laisser trop de logique dans les contrôleurs.

### Services importants

Les services les plus structurants sont :

- `PaymentService` : enregistrement des paiements ;
- `PaymentFinancialProfileService` : situation financière d'un élève ;
- `ReceiptNumberService` et `OfficialNumberService` : numérotation ;
- `ReportCardService` : bulletins ;
- `GradeCalculationService` et `GradeEntryService` : notes et moyennes ;
- `CompetitionRankingService` : classements et ex aequo ;
- `MockExamService` : examens blancs ;
- `TimetableGenerationService` : génération automatique d'emploi du temps ;
- `TeacherAvailabilityService` et `TeacherAvailabilityImportService` : disponibilités des enseignants ;
- `TeacherFeeService` : honoraires ;
- `CommunicationService` et `CommunicationQuotaService` : notifications ;
- `ResendWebhookService` : suivi des événements d'e-mail ;
- `DatabaseBackupService` : sauvegardes ;
- `AuditTrailService` et `UserAuditService` : journalisation.

## 6. Rôles et permissions

Le logiciel repose sur une séparation forte des accès. La règle importante est qu'un rôle ne doit voir que les modules nécessaires à son travail.

### Administrateur

Accès complet. Il gère :

- utilisateurs ;
- rôles ;
- paramètres ;
- années scolaires ;
- matières ;
- tarifs ;
- sauvegardes ;
- tous les modules métier.

### Direction

Rôle de contrôle et validation. Elle peut suivre :

- élèves ;
- inscriptions ;
- paiements et rapports ;
- notes ;
- examens blancs ;
- bulletins ;
- absences ;
- emplois du temps ;
- professeurs ;
- honoraires ;
- communications ;
- journaux d'activité.

### Secrétariat

Rôle centré sur l'administratif :

- dossiers élèves ;
- inscriptions ;
- classes ;
- documents ;
- imports/exports élèves ;
- examens blancs selon permissions ;
- emplois du temps ;
- professeurs et pièces administratives.

Le secrétariat ne doit pas avoir les mêmes pouvoirs que la comptabilité.

### Comptabilité

Rôle centré sur l'argent :

- paiements ;
- reçus ;
- impayés ;
- rapports financiers ;
- dépenses ;
- honoraires à payer ;
- consultation des élèves nécessaire à l'encaissement.

La comptabilité ne doit pas gérer les notes, les décisions pédagogiques ou les paramètres sensibles sans besoin explicite.

### Enseignant

Rôle centré sur la pédagogie :

- consultation limitée des élèves ;
- saisie des notes selon permissions ;
- absences selon usage ;
- emplois du temps ;
- heures effectuées ;
- consultation de ses honoraires si autorisé.

### Surveillant

Rôle centré sur la vie scolaire :

- absences ;
- retards ;
- justifications ;
- rapports d'assiduité ;
- emplois du temps ;
- émargements.

## 7. Modules actuellement couverts

### Tableau de bord

Le tableau de bord affiche les indicateurs utiles selon le rôle connecté. Les blocs financiers ne doivent pas apparaître à un utilisateur qui n'a pas les droits correspondants.

### Élèves et dossiers

Le module élèves permet :

- création et modification d'un dossier ;
- matricule ;
- informations d'identité ;
- parents et tuteurs ;
- informations médicales ;
- documents joints ;
- fiche d'inscription ;
- carte scolaire ;
- rapports de données incomplètes ;
- rapports de pièces manquantes ;
- import/export.

Les documents élèves doivent rester protégés et consultés via Laravel.

### Années scolaires, classes et inscriptions

Le système gère :

- années scolaires ;
- trimestres et périodes ;
- classes ;
- inscriptions et réinscriptions ;
- annulation d'inscription ;
- cohérence entre année scolaire, classe, échéancier et opérations associées.

La décision prise est de ne pas mélanger des éléments appartenant à des années scolaires différentes.

### Finances

Le module finances couvre :

- tarifs par classe ;
- frais et échéances ;
- paiements ;
- reçus ;
- impayés ;
- relevé financier d'un élève ;
- journal de caisse ;
- dépenses ;
- bilan ;
- exports et PDF ;
- annulation motivée des paiements.

Les montants sont traités en entiers FCFA afin d'éviter les erreurs d'arrondi.

### Notes, évaluations et bulletins

Le module pédagogique couvre :

- matières ;
- coefficients ;
- évaluations ;
- modes de saisie classiques ou adaptés aux matières orales/sportives ;
- import/export de notes ;
- verrouillage des évaluations ;
- calculs des moyennes ;
- rangs et ex aequo ;
- bulletins ;
- relevés individuels ;
- conseil de classe ;
- décisions et PV.

Une attention particulière a été portée aux pondérations, aux rangs ex aequo et aux bulletins du troisième trimestre.

### Vie scolaire

Le module vie scolaire couvre :

- absences ;
- retards ;
- justifications ;
- historique par élève ;
- exports et PDF ;
- autorisations de sortie.

Les actions comme justification, suppression ou correction doivent passer par des interfaces propres et traçables.

### Professeurs

Le module professeurs couvre :

- dossiers professeurs ;
- spécialité ;
- taux horaire ;
- pièce d'identité ;
- documents ;
- disponibilités ;
- heures effectuées ;
- émargements ;
- honoraires ;
- ordre de paiement ;
- lien avec la comptabilité.

Les heures peuvent être saisies par professeur, classe, matière, date, début, fin et quantité. Plusieurs classes peuvent donc être comptabilisées pour le même professeur sur une période.

### Emploi du temps

Le module emploi du temps existe en deux approches :

- saisie manuelle ;
- génération automatique à partir des matières, volumes horaires, professeurs et disponibilités.

Les éléments mis en place récemment :

- périodes de cours paramétrables ;
- disponibilités des professeurs ;
- import et prévisualisation des disponibilités ;
- génération automatique ;
- contrôle des conflits ;
- sélection d'une classe cible ;
- proposition puis application d'un brouillon ;
- écran de revue ;
- verrouillage de créneaux ;
- publication ;
- PDF ;
- commande de démonstration pour préparer un exemple en 3e.

Commande de démonstration :

```powershell
php artisan lpp:prepare-timetable-demo --class=3E --apply
```

Cette commande crée des professeurs de démonstration, leurs disponibilités, puis génère un emploi du temps brouillon pour la classe ciblée. Elle refuse d'écraser un emploi du temps actif ou des affectations non démo.

### Examens blancs

Le module examens blancs couvre :

- création d'une session ;
- classes concernées ;
- matières ;
- candidats ;
- anonymats ;
- salles ;
- listes PDF ;
- PV ;
- bordereau de copies ;
- saisie des notes ;
- résultats provisoires et définitifs ;
- listes d'admis, second tour et ajournés ;
- décision du jury.

Une amélioration visuelle a été demandée : éviter d'empiler trop de boutons sur une seule page. La ligne directrice est de regrouper les actions par étapes ou panneaux.

### Communications

Le module communication couvre :

- modèles ;
- annonces ;
- envoi par e-mail ;
- historique ;
- reprise des envois ;
- quota ;
- suivi Resend.

Resend est utilisé pour l'envoi. La queue Laravel permet de ne pas bloquer l'interface pendant les envois. Le suivi final dépend des webhooks Resend configurés côté production.

### Documentation utilisateur

Une section documentation existe dans l'application. Elle explique les procédures métier par thème :

- démarrage ;
- élèves et inscriptions ;
- finances ;
- vie scolaire ;
- professeurs ;
- notes et examens ;
- documents ;
- administration ;
- emploi du temps automatique.

Cette documentation doit être maintenue en même temps que les fonctionnalités.

## 8. Standard PDF

Un document de référence existe :

```text
docs/standard-pdf-lpp.md
```

Les décisions importantes sont :

- format portrait par défaut ;
- fiche d'émargement et grands tableaux en paysage si nécessaire ;
- en-tête institutionnel partagé ;
- logo avec la devise "Bâtir l'excellence" sous le logo ;
- montants alignés et formatés en FCFA ;
- dates au format `jj/mm/aaaa` ;
- heures au format `HH:mm` ;
- aucun libellé technique visible ;
- une page presque vide est interdite ;
- les signatures doivent rester raisonnables et ne pas créer de grands espaces inutiles.

Les familles de documents couvertes :

- reçus ;
- situations financières ;
- journaux et rapports financiers ;
- ordres de paiement des honoraires ;
- feuilles de notes ;
- relevés individuels ;
- bulletins ;
- certificats ;
- fiches d'inscription ;
- cartes scolaires ;
- autorisations ;
- documents collectifs et examens.

## 9. Application Windows

La décision actuelle est de considérer l'application Windows comme une enveloppe autour du site :

```text
Application Windows
        -> HTTPS
gestion.lyceepagnidibsom.com
        -> Laravel + MySQL sur VPS
```

Ce que cela apporte :

- icône sur le bureau ;
- fenêtre dédiée ;
- installateur Windows ;
- expérience plus proche d'un logiciel ;
- meilleure gestion possible des PDF, impressions et téléchargements ;
- messages propres en cas de perte de connexion.

Ce que cela ne change pas :

- pas de base locale ;
- pas de fonctionnement complet hors ligne ;
- les corrections Laravel apparaissent après déploiement serveur ;
- l'installateur non signé peut encore déclencher SmartScreen.

Décision importante : une vraie synchronisation hors ligne est trop risquée pour les paiements, notes et dossiers élèves. Elle n'est donc pas retenue pour cette phase.

## 10. Sécurité

Mesures déjà présentes ou décidées :

- HTTPS actif sur le domaine ;
- `APP_URL` en HTTPS ;
- cookies sécurisés à activer en production ;
- permissions par rôle ;
- limitation des tentatives de connexion ;
- journaux de connexion ;
- journal d'activité ;
- documents à protéger via Laravel ;
- annulations sensibles motivées ;
- numéros officiels uniques ;
- queue pour les e-mails ;
- sauvegardes programmées ;
- contrôle des exports CSV pour éviter les formules dangereuses ;
- séparation des responsabilités entre secrétariat, finance, direction et enseignants.

Points à garder en tête :

- changer les mots de passe finaux avant remise au client ;
- ne pas partager les clés Resend ou secrets serveur ;
- vérifier régulièrement les permissions réelles des rôles ;
- tester les comptes non admin avant ouverture au personnel ;
- vérifier que les documents téléversés ne sont pas accessibles publiquement ;
- garder les sauvegardes hors serveur.

## 11. Sauvegardes et exploitation

Le système prévoit :

- sauvegarde Laravel programmée ;
- contrôle de santé ;
- nettoyage des anciennes sauvegardes ;
- archive chiffrée si `BACKUP_ARCHIVE_PASSWORD` est configuré ;
- copie distante possible vers S3/OVH Object Storage ;
- workflow GitHub pour copie externe chiffrée ;
- exercice de restauration périodique.

Commandes utiles :

```powershell
php artisan lpp:backup-database
php artisan backup:run
php artisan backup:monitor
php artisan backup:clean
```

La règle d'exploitation est claire : une sauvegarde n'est réellement fiable que si une restauration a été testée.

## 12. Tests et qualité

Le projet dispose de plusieurs niveaux de contrôle :

- tests PHPUnit unitaires ;
- tests Feature Laravel ;
- tests Playwright ;
- tests Python pour le solveur d'emploi du temps ;
- PHPStan/Larastan ;
- Laravel Pint ;
- build Vite ;
- workflows GitHub Actions.

Les tests couvrent notamment :

- permissions ;
- paiements ;
- documents ;
- notes ;
- bulletins ;
- examens blancs ;
- communications ;
- Resend ;
- sauvegardes ;
- emplois du temps ;
- documentation ;
- validations métier.

La stratégie retenue est de tester plus fortement les zones à risque :

- argent ;
- notes ;
- documents officiels ;
- permissions ;
- sauvegardes ;
- génération automatique.

## 13. Déploiement

Le flux normal est :

1. modifier localement ;
2. tester localement ;
3. commit ;
4. push GitHub ;
5. tirer les changements sur le VPS ;
6. installer les dépendances si nécessaire ;
7. migrations ;
8. cache Laravel ;
9. redémarrage queue si jobs modifiés ;
10. vérification HTTPS.

Commandes typiques côté serveur :

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

À chaque changement d'asset, il faut vérifier que `public/build` est lisible par `www-data`.

## 14. Décisions importantes déjà prises

### Produit

- Construire d'abord pour LPP, généraliser plus tard.
- Garder l'interface sobre et efficace.
- Mettre les documents PDF au centre du produit.
- Préférer des modules bien finis à des fonctions très nombreuses mais confuses.
- Utiliser des modales pour les petites actions, des pages complètes pour les formulaires longs.

### Sécurité et rôles

- Aucun compte partagé en principe.
- Les rôles ne doivent pas se mélanger.
- La finance n'est pas le secrétariat.
- La direction valide et contrôle.
- Les enseignants ne doivent accéder qu'aux espaces pédagogiques et informations utiles.

### Finances

- Montants en entiers FCFA.
- Reçus non modifiés après validation.
- Annulation motivée obligatoire.
- Honoraires professeurs reliés à la comptabilité.

### Pédagogie

- Notes verrouillables.
- Ex aequo correctement gérés.
- Bulletins et relevés doivent respecter les usages de l'établissement.
- Les décisions de conseil de classe doivent être tracées.

### Documents

- PDF uniformisés.
- Logo et devise visibles.
- Portrait par défaut, paysage seulement quand le tableau l'impose.
- Éviter les grandes zones vides.
- Ne pas inventer les données manquantes dans les PDF.

### Emploi du temps

- Disponibilités des enseignants prises en compte.
- Génération automatique proposée comme aide, pas comme vérité absolue.
- Revue humaine avant publication.
- Démonstration possible pour la classe de 3e.

### Application Windows

- Tauri est une enveloppe.
- Les données restent sur le VPS.
- Pas de vrai mode hors ligne dans cette phase.
- PDF, impression et téléchargements sont prioritaires pour l'expérience Windows.

## 15. Points de vigilance actuels

### Orthographe et encodage

Certains fichiers historiques affichent encore des caractères mal encodés dans certaines sorties console, par exemple des accents transformés. L'interface visible doit rester relue régulièrement, surtout les libellés officiels et PDF.

À surveiller :

- accents ;
- apostrophes ;
- espaces autour de la ponctuation française ;
- termes administratifs ;
- cohérence entre "relevé", "bulletin", "fiche", "reçu", "émargement".

### PDF

Le chantier PDF reste sensible. Il faut continuer à vérifier visuellement :

- reçus ;
- bulletins ;
- relevés ;
- examens blancs ;
- fiches d'émargement ;
- honoraires ;
- cartes et certificats.

Les PDF doivent être testés avec des noms longs, des montants élevés, des tableaux longs et des cas incomplets.

### Emploi du temps

La génération automatique fonctionne comme un assistant. Elle dépend de données propres :

- volumes horaires par matière ;
- professeur affecté ;
- disponibilité validée ;
- périodes de cours ;
- contraintes réalistes.

Si les données d'entrée sont faibles, le résultat sera faible. La revue humaine reste obligatoire.

### Examens blancs

La section est riche. Il faut la rendre plus progressive pour éviter une accumulation de boutons. Une organisation par étapes est préférable :

1. préparation ;
2. candidats et anonymats ;
3. salles et surveillance ;
4. copies et notes ;
5. résultats et jury ;
6. documents PDF.

### Généralisation future

Pour vendre ou remettre à d'autres établissements, il faudra séparer :

- identité LPP ;
- tarifs ;
- classes ;
- matières ;
- règles de documents ;
- textes officiels ;
- logo ;
- signatures ;
- contacts ;
- domaine ;
- paramètres d'e-mail.

Cette phase n'est pas prioritaire tant que LPP n'est pas stabilisé.

## 16. Reste à faire recommandé

### Priorité haute

- finaliser les derniers formats PDF ;
- tester tous les documents avec un compte réel ;
- vérifier le comportement PDF dans l'application Windows ;
- vérifier téléchargement et impression ;
- contrôler les permissions par rôle avec des comptes séparés ;
- continuer la correction orthographique des libellés visibles ;
- vérifier les sauvegardes et restaurations sur un calendrier régulier.

### Priorité moyenne

- améliorer l'ergonomie des examens blancs ;
- enrichir les tests navigateur de parcours complets ;
- finaliser les messages et états de connexion dans l'application Windows ;
- documenter les procédures internes de remise au client ;
- préparer une checklist de mise en production finale.

### Priorité plus tard

- généraliser le logiciel pour plusieurs établissements ;
- prévoir une configuration multi-école ou multi-client ;
- rendre les textes PDF personnalisables ;
- envisager SMS/WhatsApp selon coût et besoin réel ;
- signature Windows officielle de l'installateur.

## 17. Procédure de remise au client

Avant remise officielle :

1. changer tous les mots de passe temporaires ;
2. créer les vrais comptes du personnel ;
3. attribuer les bons rôles ;
4. vérifier les permissions avec chaque type de compte ;
5. contrôler le logo, les contacts et les signatures ;
6. générer un reçu, une fiche, une carte, un certificat, un bulletin et un honoraire ;
7. tester une sauvegarde et une restauration ;
8. vérifier Resend et les e-mails ;
9. tester l'application Windows si elle est fournie ;
10. préparer une courte formation utilisateur ;
11. remettre les accès de manière confidentielle.

## 18. Commandes utiles

Tests Laravel :

```powershell
php artisan test
```

Formatage :

```powershell
vendor\bin\pint
```

Analyse statique :

```powershell
vendor\bin\phpstan analyse
```

Build frontend :

```powershell
npm run build
```

Tests navigateur :

```powershell
npm run test:e2e
```

Préparer une démonstration d'emploi du temps pour la classe de 3e :

```powershell
php artisan lpp:prepare-timetable-demo --class=3E --apply
```

Sauvegarde manuelle :

```powershell
php artisan lpp:backup-database
```

Nettoyage de données de démonstration historiques :

```powershell
php artisan lpp:clean-demo-data
```

## 19. Lecture rapide pour reprendre le projet

Pour comprendre rapidement le projet, lire dans cet ordre :

1. `README.md` pour l'installation et l'exploitation ;
2. `docs/standard-pdf-lpp.md` pour les décisions PDF ;
3. `docs/dossier-projet-lpp.md` pour la vision globale ;
4. `routes/web.php` pour voir les modules exposés ;
5. `database/seeders/RolesAndPermissionsSeeder.php` pour comprendre les accès ;
6. `app/Services` pour la logique métier ;
7. `tests/Feature` pour les règles métier vérifiées ;
8. `tests/e2e` pour les parcours navigateur.

## 20. Conclusion

Le projet est déjà bien avancé : il ne s'agit plus seulement d'un prototype. Les fondations métier sont là, les rôles existent, les modules essentiels sont en place, les documents PDF sont en cours d'uniformisation, la génération d'emploi du temps est opérationnelle en démonstration, les sauvegardes et la production sont structurées.

La prochaine étape n'est pas d'ajouter beaucoup de fonctionnalités au hasard. La bonne direction est de stabiliser les parcours critiques, relire les textes visibles, durcir les PDF, tester chaque rôle, puis préparer une remise propre au client.

