# Standard des documents PDF LPP

## Objectif

Ce document définit le format de référence des PDF générés par le logiciel.
Il s'appuie sur les sept documents historiques transmis par l'établissement :

1. reçu de paiement ;
2. feuille de relevé de notes d'une classe ;
3. relevé individuel d'une évaluation ;
4. ordre de paiement des honoraires ;
5. bulletin du premier trimestre ;
6. bulletin du deuxième trimestre ;
7. bulletin du troisième trimestre.

Le nouveau format conserve les informations administratives utiles de ces
documents, mais corrige leurs défauts de mise en page, leurs libellés
irréguliers et leurs informations parfois incomplètes.

## Principes communs

### Identité de l'établissement

Tous les documents officiels utilisent le même en-tête :

- logo à gauche, entre 22 et 24 mm ;
- devise de l'établissement sous le logo ;
- nom de l'établissement en capitales ;
- adresse, téléphone et e-mail sous le nom ;
- informations du document à droite : année scolaire, classe, période,
  numéro, date ou session selon le contexte.

L'en-tête doit provenir de `resources/views/pdf/partials/school-header.blade.php`.
Les valeurs viennent des paramètres de l'établissement et ne doivent pas être
recopiées en dur dans chaque modèle.

### Présentation

- Police principale : DejaVu Sans.
- Couleur principale : noir.
- Gris clair réservé aux en-têtes et sous-totaux des tableaux.
- Le document doit rester parfaitement lisible sur une imprimante noir et blanc.
- Aucun fond bordeaux ou doré de grande surface.
- Marges A4 : 12 à 16 mm.
- Marges A5 : 8 à 10 mm.
- Corps courant : 9 à 11 points.
- Titre : 14 à 18 points, centré, gras et souligné.
- Bordures : entre 0,6 et 1 point.
- Montants : alignés à droite et formatés avec des espaces, par exemple
  `85 000 FCFA`.
- Dates : `jj/mm/aaaa`.
- Heures : `HH:mm`.
- Notes, moyennes, points et bilans : point décimal et deux décimales, par
  exemple `13.50`. Les coefficients entiers sont affichés sans décimales.
- Les libellés techniques ne doivent jamais apparaître : `cash` devient
  `Espèces`, `valid` devient `Validé`, etc.

### Pagination

- Les en-têtes de tableaux sont répétés sur chaque page.
- Une ligne de tableau ne doit jamais être divisée entre deux pages.
- Les documents multi-pages affichent `Page X / Y`.
- Le titre, l'identité de l'élève et le premier tableau restent ensemble.
- Une page presque vide ou créée uniquement pour une signature est interdite.

### Signatures et traçabilité

Selon le document, la dernière zone contient :

- signature du parent ou du bénéficiaire ;
- signature de la caisse ou de la comptabilité ;
- visa de la direction ;
- nom et fonction du signataire configuré ;
- date de génération ;
- utilisateur ayant généré ou encaissé l'opération ;
- numéro unique du document.

Les signatures disposent d'une hauteur raisonnable de 25 à 35 mm.

## Famille 1 - Documents financiers

### Reçu de paiement

**Format :** A5 paysage, une seule page.

**En-tête droit :**

- numéro du reçu ;
- date et heure ;
- année scolaire.

**Identité :**

- nom complet de l'élève ;
- matricule ;
- classe ;
- mode de paiement traduit ;
- référence du paiement, lorsqu'elle existe ;
- nom du caissier.

**Tableau :**

- désignation du frais ;
- montant payé sur ce reçu ;
- total du reçu.

Le reçu ne doit pas afficher toutes les échéances non payées sur plusieurs
pages. La situation globale est résumée sous le tableau :

- total attendu ;
- total déjà payé ;
- reste global.

**Pied :**

- montant du reçu en lettres lorsque le service de conversion est disponible ;
- mention de non-remboursement configurable ;
- signature du parent ;
- signature et cachet de la caisse.

### Situation financière d'un élève

**Format :** A4 portrait.

Elle peut être multi-page et contient :

- identité de l'élève et classe ;
- synthèse attendu, payé et reste ;
- détail des échéances ;
- historique des paiements ;
- numéro des reçus ;
- statut des opérations.

### Journaux et rapports financiers

**Format :** A4 paysage.

Sont concernés :

- journal de caisse ;
- bilan de caisse ;
- dépenses ;
- situation des paiements ;
- situation des échéances.

Chaque rapport contient les filtres appliqués, les totaux en haut, le tableau
détaillé, puis les signatures de contrôle.

### Ordre de paiement des honoraires

**Format :** A4 portrait pour un bénéficiaire, A4 paysage pour un bordereau
regroupant plusieurs bénéficiaires.

**Informations attendues :**

- période ;
- professeur ou intervenant ;
- discipline ;
- classe ou activité ;
- quantité : heures, copies ou séances ;
- taux ;
- montant brut ;
- retenue à la source ;
- avance ;
- autre retenue ;
- net à payer ;
- montant net en lettres ;
- identité du bénéficiaire ;
- signature du bénéficiaire ;
- contrôle de l'intendance ;
- visa de la direction.

Les retenues, avances, heures et informations d'identité ne sont pas encore
toutes stockées dans le logiciel. Elles ne doivent pas être inventées dans le
PDF : le modèle complet sera activé après ajout de ces champs.

## Famille 2 - Documents pédagogiques

### Feuille de saisie ou relevé de notes d'une classe

**Format :** A4 portrait jusqu'à six colonnes de notes, sinon A4 paysage.

**En-tête droit :**

- année scolaire ;
- classe ;
- trimestre et période ;
- matière ;
- coefficient.

**Informations pédagogiques :**

- nom de l'enseignant ;
- titre de l'évaluation ;
- type d'évaluation ;
- note maximale.

**Tableau :**

- numéro ;
- matricule ;
- nom et prénom(s) ;
- classe redoublée si utile ;
- note ou colonnes d'évaluations ;
- statut ;
- commentaire ou appréciation.

Le modèle actuel imprime une évaluation à la fois. Une feuille récapitulative
avec les mois du trimestre, `Composition`, `Moyenne` et `Note pondérée`
nécessite un nouvel export multi-évaluations.

### Relevé individuel d'une période

**Format :** A4 portrait, un élève par page.

**Identité :**

- matricule ;
- nom et prénom(s) ;
- date et lieu de naissance ;
- classe ;
- effectif ;
- statut ;
- classe redoublée.

**Tableau :**

- matières regroupées par famille ;
- moyenne ;
- coefficient ;
- note pondérée ;
- bilan de chaque famille.

**Synthèse :**

- total pondéré ;
- moyenne ;
- rang ;
- moyenne de classe ;
- meilleure moyenne ;
- moyenne la plus faible ;
- signatures des parents et de la direction.

### Bulletin trimestriel

**Format :** A4 portrait, exactement un élève par page.

Le bulletin reprend :

- identité complète de l'élève ;
- année, classe, effectif et matricule ;
- matières regroupées par famille ;
- moyenne des devoirs ;
- moyenne de composition ;
- moyenne générale de la matière ;
- coefficient ;
- points pondérés ;
- appréciation ;
- professeur ;
- emplacement de signature.

La synthèse contient :

- total des coefficients et points ;
- absences ;
- conduite ;
- moyenne trimestrielle ;
- rang ;
- appréciation générale ;
- moyenne de classe ;
- meilleure et plus faible moyenne ;
- sanctions ou distinctions ;
- observation du proviseur ;
- nom et signature du proviseur.

Pour le troisième trimestre, ajouter :

- moyennes des deux trimestres précédents ;
- moyenne annuelle ;
- rang annuel ;
- moyenne annuelle de la classe ;
- décision de fin d'année.

La phrase sur l'unicité du bulletin et la devise de l'établissement restent en
pied de page.

## Famille 3 - Documents officiels individuels

### Certificats et attestations

**Format :** A4 portrait.

Sont concernés :

- certificat de scolarité ;
- certificat d'inscription ;
- certificat de non-redevance.

Ils utilisent un texte administratif aéré, un numéro unique, l'identité
complète de l'élève, l'année scolaire, le lieu et la date, puis la signature
du responsable compétent.

### Fiche d'inscription

**Format :** A4 portrait.

Elle regroupe l'identité, les responsables, les contacts, les informations
médicales, la classe demandée, les documents déposés et les signatures.

### Autorisation d'entrée et de sortie

**Format :** A4 portrait.

Le document contient l'élève, la classe, la période autorisée, la matière,
le lieu, le motif, les observations et les signatures.

### Carte scolaire

**Format :** carte paysage imprimée sur une feuille A4.

La carte comporte la photo, l'identité, le matricule, la classe, l'année
scolaire, le logo et la signature de la direction.

## Famille 4 - Documents collectifs et examens

Les listes de classes, absences, salles, candidats, anonymats, résultats,
procès-verbaux et fiches d'émargement utilisent le format A4 paysage lorsque
le tableau dépasse six colonnes.

Chaque document indique :

- son objet précis ;
- la classe, la session ou la période ;
- les filtres appliqués ;
- l'effectif total ;
- une numérotation continue ;
- les emplacements de signature nécessaires.

## Écarts relevés dans les documents historiques

Les éléments suivants ne doivent pas être reproduits :

- adresse e-mail tronquée ;
- références laissées vides sans libellé de remplacement ;
- écritures comme `3eme`, `6eme`, `Nbre heure` ou `quatre vingt` ;
- tailles de police variables d'un document à l'autre ;
- bulletin sans en-tête institutionnel complet ;
- zones de signature occupant une demi-page ;
- tableaux inclinés, trop serrés ou sans répétition d'en-tête ;
- montants sans devise ou sans séparateur de milliers ;
- doublons et libellés techniques non traduits.

## Priorités d'implémentation

### Priorité 1

- appliquer l'en-tête partagé aux PDF de notes, relevés, bulletins et honoraires ;
- limiter le reçu A5 aux lignes réellement payées et à une synthèse globale ;
- harmoniser les polices, bordures, gris et signatures ;
- traduire tous les modes et statuts visibles.

### Priorité 2

- enrichir le relevé individuel avec les statistiques de classe ;
- compléter le bulletin du troisième trimestre avec le bilan annuel ;
- ajouter les numéros de page aux rapports multi-pages ;
- ajouter des tests de rendu pour les tableaux longs.

### Priorité 3

- ajouter le registre multi-évaluations d'une classe ;
- ajouter les heures, retenues, avances et informations du bénéficiaire pour
  les ordres de paiement ;
- ajouter un service fiable de conversion des montants en lettres.

## Critères de validation

Un modèle n'est considéré comme terminé que si :

- il se génère sans erreur avec des données réelles ;
- il reste lisible en noir et blanc ;
- aucun texte ni montant n'est coupé ;
- un nom long ne déforme pas le tableau ;
- les tableaux de 10, 30 et 60 lignes sont vérifiés ;
- la pagination et les répétitions d'en-tête sont correctes ;
- les accents français sont correctement rendus ;
- le PDF a été contrôlé visuellement après rendu en image.
