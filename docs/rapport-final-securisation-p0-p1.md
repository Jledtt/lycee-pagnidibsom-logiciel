# Rapport final de sécurisation P0-P1

Date du contrôle : 14 et 15 août 2026
Branche locale : `main`  
Référence distante auditée : `origin/main`  
Serveur audité : production LPP

## 1. Périmètre et décisions

Le travail couvre les jalons P0 et P1 validés. Les évolutions P2 n'ont pas été implémentées.

Matrice d'accès appliquée :

- administration et direction : accès global ;
- secrétariat : élèves, inscriptions, responsables, classes, documents administratifs et emplois du temps, sans finances ;
- comptabilité : identité nécessaire et finances, sans notes, discipline ni documents confidentiels ;
- enseignants : uniquement leurs classes et matières affectées pour les élèves, notes, présences et emplois du temps ;
- surveillants : vie scolaire, présences, absences, retards, autorisations et discipline ;
- élèves et parents : aucun compte, aucune connexion et aucun portail.

Les autorisations ont été alignées entre les pages Web, l'API Sanctum, les PDF, les exports et les téléchargements. Les réponses hors périmètre ne doivent pas révéler l'existence d'une ressource confidentielle.

## 2. Résultat des jalons

### P0 - Autorisations

- ajout et renforcement des Policies pour les élèves, documents, évaluations, présences, discipline, sorties et séries/filières ;
- ajout de scopes métier centralisés dans `SchoolAccessService` ;
- restriction des contrôleurs Web et API par rôle, classe et matière affectée ;
- restriction des téléchargements, PDF et exports ;
- ajout de tests positifs et négatifs pour la matrice d'accès Web et API.

### P0 - Qualité immédiate

- correction et simplification de l'en-tête de la carte scolaire ;
- conservation du bloc institutionnel commun et de la devise lorsqu'elle est attendue dans les documents ;
- mise à jour du verrou npm pour supprimer la vulnérabilité élevée liée à `nanoid` ;
- stabilisation des visites guidées sur tablette.

### P1 - Intégrité des données

- commande de lecture seule `php artisan lpp:audit-data-integrity` ;
- refus automatique de la migration si l'audit trouve une anomalie bloquante ;
- unicité de l'année active ;
- cohérence du statut et des dates des années scolaires ;
- prévention des chevauchements d'années ;
- un seul responsable principal par élève ;
- prévention des doublons père, mère ou tuteur ;
- prévention des cellules d'emploi du temps dupliquées et des conflits professeur/créneau ;
- contrôle des heures de cours ;
- contrôle des montants positifs de paiements et de leurs lignes ;
- transactions et verrouillages renforcés dans les services sensibles.

### P1 - Modules

- gestion complète des responsables légaux, de leurs liens avec les élèves et du responsable principal ;
- gestion de la discipline avec états actif, résolu et annulé, motifs et auteurs tracés ;
- gestion configurable des séries et filières avec rattachement facultatif aux classes ;
- journalisation et contrôles d'autorisation associés.

### P1 - Interface et fiabilité

- regroupement des actions secondaires dans des menus d'actions sur les écrans volumineux ;
- amélioration responsive sur ordinateur, tablette et mobile ;
- correction des calculs scolaires et des moyennes annuelles indexées par période ;
- amélioration des types PHP et suppression des suppressions PHPStan devenues inutiles.

## 3. Migrations ajoutées

### `2026_08_14_000000_add_p1_integrity_guards.php`

Tables : `academic_years`, `guardian_student`, `timetable_entries`, `payments`, `payment_lines`.

La migration ajoute des colonnes de garde générées, des index uniques et des déclencheurs compatibles MySQL/MariaDB et SQLite. Elle commence par un audit et s'arrête sans modifier le schéma si des données ambiguës existent. Son `down()` supprime les déclencheurs, index et colonnes de garde.

### `2026_08_14_020000_finish_disciplinary_records.php`

Table : `disciplinary_records`.

La migration ajoute le statut, l'action prise, les informations de résolution et d'annulation, les utilisateurs responsables et les index de consultation. Son `down()` retire ces champs et index.

### `2026_08_14_030000_create_academic_tracks.php`

Tables : `academic_tracks`, `school_classes`.

La migration crée les séries/filières configurables, rattache facultativement une classe et initialise les séries A et C. Son `down()` retire la clé étrangère puis la table.

## 4. Inventaire des fichiers modifiés

Le diff complet entre l'ancien état de production `3e7c813` et le dernier commit applicatif déployé contient 137 fichiers :

- `app/` : 77 fichiers ;
- `resources/` : 30 fichiers ;
- `tests/` : 21 fichiers ;
- `database/` : 5 fichiers ;
- `docs/rapport-final-securisation-p0-p1.md` : 1 fichier ;
- `routes/web.php` : 1 fichier ;
- `package-lock.json` : 1 fichier ;
- `phpstan-baseline.neon` : 1 fichier.

Les zones concernées sont :

- commandes : audit d'intégrité et audit des coefficients ;
- contrôleurs : années, élèves, documents, responsables, classes, notes, présences, paiements, discipline, examens blancs, exports et paramètres ;
- requêtes : présences, évaluations, notes et paiements ;
- modèles : scolarité, finances, notes, examens, responsables et séries/filières ;
- Policies : élèves, documents, notes, présences, discipline, sorties et séries/filières ;
- services : accès, calculs, bulletins, paiements, responsables, emplois du temps et intégrité ;
- vues : élèves, classes, responsables, discipline, paiements, enseignants, rapports, composants et mise en page ;
- tests : matrice d'accès, intégrité, modules, calculs, documents, dialogues, parcours métier et visites guidées.

L'inventaire exhaustif et reproductible avant le présent ajustement documentaire est obtenu avec :

```bash
git diff --name-status 3e7c813..f538ef2
```

## 5. Commits livrés

Les 19 commits livrés après l'ancien état de production sont, dans l'ordre :

1. `279060e` - `fix: restreindre les données selon les rôles`
2. `5b90d90` - `fix: cloisonner les autorisations de sortie`
3. `19793af` - `style: épurer l'en-tête de la carte scolaire`
4. `dd06a06` - `test: stabiliser les visites guidées sur tablette`
5. `5130ea5` - `fix: mettre à jour nanoid vers une version sûre`
6. `73cca77` - `feat: ajouter l'audit préalable d'intégrité`
7. `53ed7d4` - `fix: renforcer l'intégrité des données métier`
8. `09ba08b` - `feat: terminer la gestion des responsables légaux`
9. `d179b1b` - `feat: terminer la gestion de la discipline`
10. `04a2b9f` - `feat: ajouter les séries et filières configurables`
11. `9f82f28` - `fix: aligner les accès Web et API par rôle`
12. `20d0e85` - `style: simplifier les actions des grands écrans`
13. `f047e78` - `refactor: fiabiliser les types et les calculs scolaires`
14. `ec0be39` - `docs: ajouter le rapport final de sécurisation`
15. `d6d9e54` - `docs: consigner l'audit de production`
16. `dbfa04a` - `docs: documenter le test des migrations MySQL`
17. `6fab8c9` - `docs: valider la copie externe chiffrée`
18. `511cdfb` - `fix: fiabiliser la migration des contraintes MySQL`
19. `f538ef2` - `fix: protéger les archives après sauvegarde`

Ces commits ont été poussés sur `origin/main` puis déployés en production après autorisation explicite.

## 6. Vérifications exécutées

### Application locale

- tests Laravel ciblés : 47 tests, 322 assertions, succès ;
- suite Laravel complète : 324 tests, 2 232 assertions, succès ;
- Laravel Pint complet : succès ;
- PHPStan niveau 6 : 0 erreur ;
- build Vite : succès, avec un avertissement facultatif `fontaine` sans incidence ;
- `npm audit` : 0 vulnérabilité ;
- Playwright : 70 succès et 17 tests ignorés volontairement lors de la suite complète, puis 9 tests de visites guidées sur 9 réussis après correction de la réinitialisation du stockage de test ;
- contrôles Playwright sur ordinateur, tablette et mobile ;
- parcours métier et génération PDF exécutés sur le profil ordinateur.

Les tests destructifs sont volontairement ignorés sur tablette et mobile. Cette décision évite de répéter les créations et annulations trois fois tout en conservant les tests de rendu responsive sur ces formats.

### Production, en lecture seule sauf réparation d'exploitation documentée

- nginx : actif ;
- worker `lpp-queue-worker.service` : actif et activé au démarrage ;
- tâches échouées en queue : 0 ;
- scheduler Laravel : installé dans `/etc/cron.d/lpp-laravel-scheduler` ;
- sauvegarde, contrôle et nettoyage : 22 h 00, 22 h 15 et 22 h 30 ;
- `APP_ENV=production`, `APP_DEBUG=false`, MySQL, queue et sessions en base ;
- cookie de session : Secure, HttpOnly et SameSite Lax ;
- clé d'application, clé Resend, secret du webhook et destinataire des alertes présents dans la configuration mise en cache ;
- `.env` et ses deux anciennes copies : mode `600` ;
- production déployée sur `f538ef2`, sans migration en attente ;
- archive complète créée le 15 août 2026 à 00 h 45 UTC : `lpp-2026-08-15-00-45-28.zip`, 543 entrées, 892 106 octets, mode `640` et groupe restreint `lpp-backup-export` ;
- déchiffrement PHP : succès ;
- export par le compte SSH restreint : succès ;
- restauration MySQL temporaire : succès, 68 tables, 56 migrations et 8 déclencheurs, base temporaire supprimée ;
- audit P1 exécuté en lecture seule sur la base MySQL de production avec le nouveau service : 0 anomalie bloquante, 0 avertissement, contraintes applicables ;
- fichier de service temporaire supprimé du serveur après l'audit ;
- migrations P1 testées sur une restauration MySQL isolée : 3 migrations appliquées, 8 déclencheurs et 3 colonnes de garde vérifiés ;
- rollback MySQL testé : 3 migrations retirées, aucun déclencheur ni table `academic_tracks` résiduel ;
- nettoyage vérifié après l'exercice : 0 base et 0 dossier temporaire restant ;
- `log_bin_trust_function_creators` remis à `0` immédiatement après la migration ;
- HTTPS répond en `200`, HTTP redirige vers HTTPS et les en-têtes HSTS, CSP, anti-cadrage, `nosniff`, référent et permissions sont présents ;
- version nginx masquée, nginx actif, application sortie du mode maintenance.

### Régression MySQL détectée avant mise en production

La première tentative de déploiement s'est arrêtée sans enregistrer la migration P1. Deux particularités MySQL ont été identifiées :

- certains index et colonnes générées peuvent rester présents après un échec, car les opérations DDL sont validées immédiatement ;
- avec les journaux binaires actifs, l'utilisateur Laravel ne peut pas créer les déclencheurs tant que `log_bin_trust_function_creators` vaut `0`.

La migration P1 a été rendue réexécutable : elle réutilise les colonnes et index déjà présents, recrée proprement les déclencheurs et conserve un index explicite sur `guardian_student.student_id` pour la clé étrangère. Le nettoyage automatique qui masquait l'erreur initiale a été retiré.

Le scénario réel a ensuite été rejoué sur une nouvelle copie MySQL de la production contenant les éléments partiels :

- application des 3 migrations : succès ;
- 8 déclencheurs et les index attendus : présents ;
- rollback des 3 migrations : succès, aucun déclencheur ou champ de garde résiduel ;
- nouvelle application après rollback : succès.

Pendant le déploiement, `log_bin_trust_function_creators` doit être activé uniquement autour de `php artisan migrate`, puis remis immédiatement à sa valeur initiale. Aucun privilège permanent supplémentaire ne doit être accordé au compte Laravel.

## 7. Réparation d'exploitation appliquée au serveur

La copie externe échouait avant l'ouverture de la connexion SSH :

- `/usr/local/sbin/lpp-export-latest-encrypted-backup` était en `750 root:root` ;
- les archives étaient en `640 www-data:www-data` ;
- le compte `lpp-backup-export` ne pouvait donc exécuter le script ni lire l'archive.

Correction ciblée appliquée :

- script : `750 root:lpp-backup-export` ;
- dossier d'archives : `2750 www-data:lpp-backup-export` ;
- archives existantes : `640 www-data:lpp-backup-export`.

Le bit setgid du dossier transmet le groupe restreint aux futures archives. Le compte n'a pas été ajouté au groupe `www-data` et ne gagne donc aucun accès au `.env` ou aux autres fichiers privés.

## 8. État des derniers points d'exploitation

### Copie externe GitHub validée

Le secret Actions `LPP_BACKUP_HOST` a été créé après autorisation explicite. Les secrets `LPP_BACKUP_KNOWN_HOSTS` et `LPP_BACKUP_SSH_KEY` sont également présents.

Le workflow final `31854698136`, lancé après déploiement et restauration, est terminé avec succès :

- téléchargement et vérification de l'archive chiffrée : succès ;
- création du SHA-256 : succès ;
- artefact `lpp-backup-31854698136` : 826 670 octets ;
- commit associé : `f538ef2abe9e9ce9c6a0653d0fb04940fd352bb8` ;
- conservation jusqu'au 14 septembre 2026 ;
- artefact non expiré au moment du contrôle.

### Sessions non chiffrées en base

La production utilise `SESSION_ENCRYPT=false`. Le cookie est correctement protégé, mais les aperçus d'import conservés en session ne bénéficient pas du chiffrement applicatif au repos.

Action recommandée pendant une courte maintenance : définir `SESSION_ENCRYPT=true`, vider les anciennes sessions et reconstruire le cache de configuration. Cette opération déconnectera les utilisateurs actifs et nécessite donc une validation explicite.

### Production synchronisée

Le serveur, le dépôt local et `origin/main` ont été synchronisés sur `f538ef2` après le déploiement applicatif. Les trois migrations P1 sont enregistrées, les huit déclencheurs sont présents et l'audit de données reste vert.

Le déploiement a aussi permis d'identifier une règle d'exploitation importante : Composer doit être exécuté par le compte de déploiement avec `--no-scripts`, puis `package:discover` et les commandes de cache doivent être exécutés comme `www-data`. Cette procédure évite de modifier les propriétaires de `storage` et de `bootstrap/cache`.

Le service `BackupArchivePermissionService` remet désormais chaque nouvelle archive locale en mode `640`. Le dossier d'archives reste en `2750` avec le groupe `lpp-backup-export`, même après un déploiement.

Deux fichiers non suivis existent sur le serveur :

- `.env.bak.pre-dialog-fd90912` ;
- `.env.bak.pre-https-env`.

Ils sont protégés en `600` et hors de la racine Web, mais ils dupliquent des secrets anciens. Leur suppression ou archivage chiffré doit être validé avant toute action.

## 9. Risques résiduels et contrôles futurs

- `SESSION_ENCRYPT=false` reste volontairement inchangé tant qu'une maintenance avec déconnexion des utilisateurs n'est pas autorisée ;
- les deux anciennes copies `.env.bak.*`, protégées en `600`, contiennent potentiellement d'anciens secrets et nécessitent une autorisation de suppression ou d'archivage chiffré ;
- la restauration complète et la copie externe doivent continuer à être contrôlées périodiquement, même si l'exercice final est réussi ;
- toute future migration créant des déclencheurs MySQL doit conserver l'activation temporaire et le retour à `0` de `log_bin_trust_function_creators` ;
- les tests de documents réels en production restent volontairement en lecture seule et ne doivent pas créer ou annuler de données client.

## 10. Retour arrière

### Code

Revenir par commits inverses avec `git revert`, du plus récent au plus ancien. Ne pas utiliser `git reset --hard` sur le serveur.

### Base de données

Après sauvegarde et en mode maintenance :

```bash
php artisan migrate:rollback --step=3
```

Attention : le retrait des migrations de discipline et de séries/filières supprime les nouvelles colonnes et tables. Il faut exporter ces données avant un rollback si elles ont déjà été utilisées.

### Réparation de l'export hors serveur

Retour à l'état précédent, uniquement si nécessaire :

```bash
sudo chown root:root /usr/local/sbin/lpp-export-latest-encrypted-backup
sudo chmod 750 /usr/local/sbin/lpp-export-latest-encrypted-backup
sudo chown ubuntu:www-data storage/app/private/lpp-gestion-scolaire
sudo chmod 2775 storage/app/private/lpp-gestion-scolaire
```

Ce retour arrière réintroduit l'échec de la copie externe et n'est donc pas recommandé.

## 11. Critères de clôture

Les critères P0-P1 sont satisfaits :

1. code poussé et déployé avec audit préalable vert ;
2. migrations, déclencheurs et rôles vérifiés en production ;
3. sauvegarde chiffrée créée, exportée, restaurée et copiée hors serveur ;
4. site HTTPS, nginx, queue, scheduler et caches contrôlés ;
5. tests Laravel, analyse statique, formatage, dépendances, build et parcours navigateur exécutés ;
6. permissions Web, API, PDF, exports et documents confidentiels couvertes par les tests automatisés.

Le chiffrement des sessions reste une amélioration de défense en profondeur explicitement différée, car son activation déconnectera les utilisateurs actuels. Les anciennes copies de `.env` restent également en attente d'une décision de conservation ou de suppression.
