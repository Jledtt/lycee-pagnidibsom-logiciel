# LPP Gestion Scolaire

Logiciel de gestion scolaire pour le Lycee Prive Pagnidibsom au Burkina Faso.

L'application est construite avec Laravel, PHP 8.3 et une base SQLite en local. Elle est preparee pour passer plus tard sur MySQL, MariaDB ou PostgreSQL sur un serveur reel.

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

Sauvegarde manuelle :

```powershell
php artisan lpp:backup-database
```

Par defaut, les sauvegardes sont creees dans :

```text
storage/app/backups
```

La commande cree un export JSON portable. En SQLite, elle ajoute aussi une copie du fichier `.sqlite`.

Parametres disponibles dans `.env` :

```text
LPP_BACKUP_TIME=22:00
LPP_BACKUP_KEEP_DAYS=14
```

Pour que la sauvegarde automatique s'execute sur un serveur, il faudra activer le planificateur Laravel avec une tache cron.

## Passage serveur reel

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

## Commandes utiles

```powershell
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan permission:cache-reset
php artisan lpp:backup-database
php artisan test
```

## Regle importante

Les acces doivent etre geres par roles. Les informations sensibles comme les encaissements, les rapports financiers et les annulations de paiement ne doivent etre visibles qu'aux roles autorises.
