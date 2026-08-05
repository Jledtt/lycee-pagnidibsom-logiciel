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

Avant le premier seed, définissez un mot de passe administrateur dans le fichier `.env` :

```text
LPP_ADMIN_PASSWORD=utiliser-un-mot-de-passe-long-et-unique
```

Ne publiez jamais cette valeur dans le dépôt, le README, les journaux ou une capture d’écran. Si la variable reste vide hors production, le seeder génère un mot de passe aléatoire et l’affiche une seule fois dans la console lors de la création du compte.

## Tests

Lancer les tests automatises :

```powershell
php artisan test
vendor\bin\pint --test
npm run build
npm run test:e2e
```

Les tests Feature servent a verifier les acces par role, les pages principales et les commandes techniques. Les tests Playwright recreent une base SQLite isolee et controlent la connexion, le tableau de bord, les modules principaux et l'affichage mobile.

Les workflows GitHub Actions executent automatiquement ces controles a chaque push et pull request sur `main`. Un second workflow verifie toutes les dix minutes la disponibilite HTTPS, la redirection HTTP et la validite du certificat de production.

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

La commande cree une archive `.zip` telechargeable. Cette archive contient un export JSON portable et, selon la base utilisee, une copie `.sqlite` ou un fichier `.sql`. En MySQL/MariaDB, le fichier SQL est genere si `mysqldump` est disponible. En PostgreSQL, il est genere avec `pg_dump`.

Parametres disponibles dans `.env` :

```text
LPP_BACKUP_TIME=22:00
LPP_BACKUP_MONITOR_TIME=22:15
LPP_BACKUP_KEEP_DAYS=14
LPP_BACKUP_DISKS=local
BACKUP_ARCHIVE_PASSWORD=mot-de-passe-long-et-unique
LPP_BACKUP_PATH=/chemin/vers/dossier/sauvegardes
LPP_MYSQLDUMP_PATH=C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe
LPP_PGDUMP_PATH=/usr/bin/pg_dump
```

Pour ajouter une copie distante S3 compatible, renseigner les variables `AWS_*`, puis utiliser :

```text
LPP_BACKUP_DISKS=local,s3
```

Pour OVH Object Storage, `AWS_ENDPOINT` suit généralement le format `https://s3.<region>.io.cloud.ovh.net`. Le script `ops/scripts/verify-latest-backup-restore.sh` restaure chaque mois la dernière archive dans une base temporaire et déclenche une alerte en cas d'échec.

Le workflow `Copie externe chiffree` conserve également chaque jour une archive chiffrée dans les artefacts privés GitHub pendant 30 jours. Il utilise une clé SSH dédiée et restreinte à l'export de la dernière sauvegarde. L'hôte du serveur doit être enregistré dans le secret GitHub `LPP_BACKUP_HOST`, sans être inscrit dans les fichiers versionnés. L'export refuse une archive âgée de plus de quatre heures afin qu'un échec de la sauvegarde quotidienne ne soit pas masqué par la copie de l'archive de la veille.

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

1. Telecharger la sauvegarde `.zip` depuis l'application.
2. Decompresser l'archive.
3. Creer ou vider la base `lpp_gestion`.
4. Ouvrir le fichier `.sql` de sauvegarde dans HeidiSQL.
5. Executer le script.
6. Lancer :

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
LPP_ADMIN_PASSWORD=utiliser-un-mot-de-passe-long-et-unique
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

Puis lancer les migrations :

```powershell
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Lors d’une installation initiale uniquement, les données de référence peuvent ensuite être créées avec `php artisan db:seed --force`. Vérifiez auparavant que `LPP_ADMIN_PASSWORD` est bien défini. Les comptes de démonstration par rôle ne sont jamais créés en production.

### Worker de queue

Les notifications utilisent la queue Laravel `database`. Copiez l’unité fournie, puis remplacez `/CHEMIN/VERS/app-source` dans le fichier installé par le chemin absolu du projet :

```bash
sudo cp ops/systemd/lpp-queue-worker.service /etc/systemd/system/
sudo nano /etc/systemd/system/lpp-queue-worker.service
sudo systemctl daemon-reload
sudo systemctl enable --now lpp-queue-worker.service
sudo systemctl status lpp-queue-worker.service
```

Après chaque déploiement qui modifie le code des jobs, rechargez le worker sans interrompre les tâches en cours :

```bash
php artisan queue:restart
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
