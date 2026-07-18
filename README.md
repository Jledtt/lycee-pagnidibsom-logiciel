# LPP Gestion Scolaire

Logiciel de gestion scolaire pour le Lycee Prive Pagnidibsom au Burkina Faso.

L'application est construite avec Laravel, PHP 8.3 et fonctionne en SQLite, MySQL/MariaDB ou PostgreSQL. En production, MySQL/MariaDB ou PostgreSQL est recommande.

## Modules disponibles

- Tableau de bord avec affichage selon les roles
- Eleves, parents, dossiers et fiches d'inscription
- Classes, inscriptions et listes imprimables
- Paiements, recus, impayes et situations financieres
- Tarifs scolaires modifiables par classe et par tranche
- Documents administratifs et certificats PDF
- Absences, retards, justificatifs et exports PDF
- Matieres, coefficients, notes, evaluations et bulletins
- Personnel, roles et permissions
- Parametres de l'etablissement
- Aide et guide utilisateur integres
- Sauvegardes JSON et SQL depuis l'interface ou la console

## Installation locale

```powershell
cd C:\Users\eddyt\Documents\Codex\lycee-pagnidibsom-logiciel\app-source
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Adresse locale :

```text
http://127.0.0.1:8000
```

Compte admin local :

```text
Identifiant : admin
E-mail      : infoslyceepagnidibsom@gmail.com
Mot de passe: Pagnidibsom
```

## Tests

Lancer les tests automatises :

```powershell
php artisan test
```

Les tests Feature servent a verifier les acces par role, les pages principales et les commandes techniques.

## Sauvegarde de la base

Depuis l'interface :

```text
Parametres > Sauvegardes > Exporter une sauvegarde
```

Sauvegarde manuelle :

```powershell
php artisan lpp:backup-database
```

Par defaut, les sauvegardes sont creees dans :

```text
storage/app/backups
```

La commande cree un export JSON portable. En SQLite, elle ajoute aussi une copie du fichier `.sqlite`. En MySQL/MariaDB, elle genere aussi un fichier `.sql` si `mysqldump` est disponible. En PostgreSQL, elle fait pareil avec `pg_dump`.

Parametres disponibles dans `.env` :

```text
LPP_BACKUP_TIME=22:00
LPP_BACKUP_KEEP_DAYS=14
LPP_MYSQLDUMP_PATH=C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe
LPP_PGDUMP_PATH=/usr/bin/pg_dump
```

Pour que la sauvegarde automatique s'execute sur un serveur Linux, ajouter la tache cron Laravel :

```bash
* * * * * cd /chemin/vers/app-source && php artisan schedule:run >> /dev/null 2>&1
```

Sur Windows Server, creer une tache planifiee qui execute toutes les minutes :

```powershell
php C:\chemin\vers\app-source\artisan schedule:run
```

## Restauration

Avant restauration, toujours faire une sauvegarde de la base actuelle et mettre l'application en maintenance :

```powershell
php artisan down
php artisan lpp:backup-database
```

Restaurer MySQL/MariaDB avec HeidiSQL :

1. Creer ou vider la base `lpp_gestion`.
2. Ouvrir le fichier `.sql` de sauvegarde dans HeidiSQL.
3. Executer le script.
4. Lancer :

```powershell
php artisan config:clear
php artisan up
```

Restaurer MySQL/MariaDB en ligne de commande :

```powershell
mysql -h 127.0.0.1 -P 3306 -u root lpp_gestion < storage/app/backups/lpp-mysql-YYYYMMDD-HHMMSS.sql
php artisan config:clear
php artisan up
```

Restaurer SQLite :

1. Arreter l'application.
2. Remplacer `database/database.sqlite` par le fichier `.sqlite` sauvegarde.
3. Redemarrer l'application.

## Passage serveur reel

`.env` production minimal :

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.com
SESSION_SECURE_COOKIE=true
```

Pour MySQL ou MariaDB :

```text
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lpp_gestion
DB_USERNAME=lpp_user
DB_PASSWORD=mot_de_passe
```

Pour PostgreSQL :

```text
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lpp_gestion
DB_USERNAME=lpp_user
DB_PASSWORD=mot_de_passe
```

Puis lancer :

```powershell
php artisan migrate --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Verifier aussi :

- le dossier `storage` est sauvegarde et non public
- `storage/app` conserve les documents scannes des eleves
- `public/images` contient le logo officiel
- les permissions des roles sont relues avant ouverture au personnel
- une sauvegarde automatique est activee et testee

## Commandes utiles

```powershell
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan permission:cache-reset
php artisan lpp:backup-database
php artisan lpp:clean-demo-data
php artisan test
```

## Regle importante

Les acces doivent etre geres par roles. Les informations sensibles comme les encaissements, les rapports financiers et les annulations de paiement ne doivent etre visibles qu'aux roles autorises.
