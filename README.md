# ReservaSalles — Système de Réservation de Salles de Réunion

Projet Technologies Web 2A — ESPRIT, session Crédits 2025-2026.
Application PHP 8 en architecture **MVC**, sans aucun framework, accès base de données en **PDO** uniquement.

---

## 1. Installation avec XAMPP

### Étape 1 — Copier le projet

Placez le dossier `ReservaSalles` dans le répertoire web de XAMPP :

```
C:\xampp\htdocs\ReservaSalles\
```

### Étape 2 — Démarrer Apache et MySQL

Ouvrez le **XAMPP Control Panel** et cliquez sur `Start` pour **Apache** puis pour **MySQL**.

### Étape 3 — Importer la base de données

1. Ouvrez `http://localhost/phpmyadmin`
2. Onglet **Importer**
3. Bouton **Choisir un fichier** → sélectionnez `database/reserva_salles.sql`
4. Cliquez sur **Exécuter** en bas de page

Le script crée la base `reserva_salles`, ses 6 tables et un jeu de données de démonstration.
Il commence par `DROP DATABASE IF EXISTS`, vous pouvez donc le réimporter à volonté pour repartir de zéro.

### Étape 4 — Ouvrir l'application

```
http://localhost/ReservaSalles/
```

> **Configuration de la connexion** — par défaut `config.php` utilise l'utilisateur `root` sans mot de passe,
> ce qui correspond à une installation XAMPP standard. Si votre MySQL a un mot de passe, modifiez-le dans `config.php`.

---

## 2. Comptes de démonstration

Tous ces comptes utilisent le même mot de passe : **`123456`**

| Rôle | Email | Ce qu'il peut faire |
|------|-------|---------------------|
| Administrateur bâtiments | `admin@reserva.tn` | Bâtiments, étages, salles, comptes, statistiques, rapports |
| Gestionnaire de réservations | `gestionnaire@reserva.tn` | Valider / refuser / déplacer, réservations manuelles, conflits |
| Utilisateur | `yassine@reserva.tn` | Consulter, réserver, modifier et annuler ses réservations |
| Utilisateur | `ines@reserva.tn` | idem |
| Utilisateur | `nour@reserva.tn` | idem |
| Utilisateur (compte désactivé) | `mehdi@reserva.tn` | Sert à montrer le refus de connexion d'un compte inactif |

Sur la page de connexion, un encart liste ces comptes : un clic pré-remplit le formulaire.

---

## 3. Arborescence du projet

```
ReservaSalles/
├── index.php                  Point d'entrée (redirige vers le FrontOffice)
├── config.php                 Connexion PDO (singleton)
├── README.md
├── .gitignore
│
├── core/
│   ├── init.php               Session, autoload des classes, constantes
│   └── helpers.php            Échappement, CSRF, validateurs, formatage, libellés
│
├── database/
│   └── reserva_salles.sql     Schéma complet + données de démonstration
│
├── model/                     LE MODÈLE — une classe par entité
│   ├── Utilisateur.php
│   ├── Batiment.php
│   ├── Etage.php
│   ├── Salle.php
│   └── Reservation.php
│
├── controller/                LE CONTRÔLEUR — lien modèle ↔ vue, requêtes PDO
│   ├── UtilisateurC.php
│   ├── BatimentC.php
│   ├── EtageC.php
│   ├── SalleC.php
│   ├── ReservationC.php
│   └── MailC.php              Notifications email (PHPMailer + journalisation)
│
├── view/                      LA VUE — affichage
│   ├── frontend/              FrontOffice (utilisateurs)
│   │   ├── index.php          Accueil
│   │   ├── login.php  register.php  logout.php
│   │   ├── salles.php         Catalogue avec recherche multicritère
│   │   ├── calendrier.php     Calendrier interactif des disponibilités
│   │   ├── reserver.php       Demande de réservation
│   │   ├── mesReservations.php
│   │   ├── modifierReservation.php
│   │   ├── annulerReservation.php
│   │   ├── ajax/disponibilites.php   Créneaux d'une salle en JSON
│   │   ├── partials/          En-tête et pied de page
│   │   └── assets/            CSS et JS du FrontOffice
│   │
│   └── backend/               BackOffice (administrateur et gestionnaire)
│       ├── index.php          Tableau de bord
│       ├── listBatiment.php   addBatiment.php   updateBatiment.php   deleteBatiment.php
│       ├── listEtage.php      addEtage.php      updateEtage.php      deleteEtage.php
│       ├── listSalle.php      addSalle.php      updateSalle.php      deleteSalle.php
│       ├── listUtilisateur.php  addUtilisateur.php  updateUtilisateur.php  deleteUtilisateur.php
│       ├── listReservation.php  addReservation.php  updateReservation.php  deleteReservation.php
│       ├── traiterReservation.php   Validation / refus motivé
│       ├── calendrier.php     Planning global
│       ├── conflits.php       Détection des chevauchements
│       ├── statistiques.php   Taux d'occupation, graphiques
│       ├── rapport.php        Rapport imprimable par période
│       ├── notifications.php  Journal des emails
│       ├── partials/
│       └── assets/
│
├── PHPMailer/                 Librairie d'envoi d'emails
└── uploads/                   Dossier pour d'éventuelles images
```

---

## 4. Notifications par email

Par défaut l'application tourne en **mode démonstration** : aucun email n'est réellement envoyé,
mais chaque notification est **enregistrée en base** et consultable dans
*BackOffice → Suivi → Notifications*. Vous pouvez donc démontrer la fonctionnalité sans configurer de serveur SMTP.

Pour activer les envois réels, ouvrez `controller/MailC.php` et renseignez :

```php
public  const MAIL_ACTIF = true;                       // au lieu de false
private const SMTP_USER  = 'votre.adresse@gmail.com';
private const SMTP_PASS  = 'mot_de_passe_application'; // PAS votre mot de passe Gmail habituel
```

> Pour Gmail il faut créer un **mot de passe d'application** depuis les paramètres de sécurité
> de votre compte Google (l'authentification à deux facteurs doit être activée).

Les notifications couvrent : nouvelle demande, validation, refus motivé, annulation et déplacement de réunion.

---

## 5. Correspondance avec le cahier des charges

### Architecture et technique

| Exigence | Où c'est réalisé |
|----------|------------------|
| PHP 8 | Typage des propriétés, paramètres et retours, types nullables (`?string`), opérateur `??` |
| Architecture MVC | Dossiers `model/`, `view/`, `controller/` |
| PDO uniquement (pas de MySQLi) | `config.php` — singleton PDO, requêtes préparées partout |
| `view` séparée en frontend / backend | `view/frontend/` et `view/backend/` |
| Aucun framework | CSS et JavaScript écrits à la main, zéro Bootstrap, zéro jQuery |
| Jointures entre tables | `SELECT_BASE` dans `SalleC` (3 tables) et `ReservationC` (5 tables) |

### Les trois profils

**Administrateur Bâtiments**

| Exigence | Page |
|----------|------|
| Gérer bâtiments et étages | `listBatiment.php`, `listEtage.php` |
| Gérer salles et caractéristiques | `listSalle.php`, `addSalle.php` (capacité, type, équipements, horaires) |
| Maintenance et disponibilité | Bouton d'état dans `listSalle.php`, champ `etat` de la salle |
| Statistiques d'utilisation | `statistiques.php` — taux d'occupation, graphiques |
| Rapports par période | `rapport.php` — imprimable, filtrable par bâtiment |

**Gestionnaire de Réservations**

| Exigence | Page |
|----------|------|
| Valider / refuser les demandes | `listReservation.php` + `traiterReservation.php` (refus avec motif obligatoire) |
| Réservations manuelles | `addReservation.php` — au nom d'un utilisateur |
| Gérer conflits et déplacements | `conflits.php`, `updateReservation.php` |
| Recherche multicritère | Filtres de `listReservation.php` : objet, statut, bâtiment, salle, demandeur, période |

**Utilisateur**

| Exigence | Page |
|----------|------|
| Consulter avec calendrier et filtres | `calendrier.php`, `salles.php` |
| Soumettre une demande | `reserver.php` |
| Modifier / annuler avant la date limite | `modifierReservation.php`, `annulerReservation.php` |
| Historique | `mesReservations.php` |
| Notifications email | Automatiques à chaque changement de statut |

### Fonctionnalités transverses

| Exigence | Réalisation |
|----------|-------------|
| CRUD sur toutes les entités | Utilisateur, Bâtiment, Étage, Salle, Réservation |
| Contrôles de saisie en JS **et** PHP | `validation.js` côté client, fonctions `v_*()` de `helpers.php` côté serveur |
| Pas de validation HTML native | Tous les formulaires portent `novalidate`, aucun attribut `required` |
| Calendrier interactif | `calendrier.php` + `calendrier.js` + endpoint AJAX `ajax/disponibilites.php` |
| Gestion des chevauchements | `detecterConflits()` — règle `debut < fin_autre AND fin > debut_autre` |
| Templates responsifs | Points de rupture à 992 px et 640 px, menu mobile |
| Historique Git | Voir la section 7 |

---

## 6. Sécurité mise en place

- **Mots de passe hachés** avec `password_hash()` / `password_verify()` — jamais stockés en clair
- **Jeton CSRF** sur tous les formulaires en POST
- **Requêtes préparées PDO** partout, aucune concaténation de variable dans du SQL
- **Échappement systématique** des affichages via la fonction `e()` (protection XSS)
- **Colonnes de tri validées par liste blanche** — un paramètre d'URL ne peut jamais devenir un nom de colonne
- **Contrôle d'accès par rôle** via `exiger_role()` en tête de chaque page protégée
- **Un administrateur ne peut ni se désactiver ni se retirer ses propres droits**

---

## 7. Historique Git

Le cahier des charges demande des commits réguliers. Si le projet n'est pas encore versionné :

```bash
cd C:\xampp\htdocs\ReservaSalles
git init
git add .
git commit -m "Initialisation du projet ReservaSalles"
```

Puis committez au fil de vos modifications, par exemple :

```bash
git add view/backend/statistiques.php
git commit -m "Ajout du taux d'occupation par salle"
```

---

## 8. Règles métier appliquées

Une demande de réservation n'est acceptée que si **toutes** ces conditions sont réunies :

- le créneau n'est pas dans le passé ;
- l'heure de fin est postérieure à l'heure de début ;
- la durée est comprise entre **15 minutes et 12 heures** ;
- le début et la fin tombent le **même jour** ;
- le créneau tient dans les **horaires d'ouverture** de la salle ;
- la salle est à l'état **disponible** (ni en maintenance, ni indisponible) ;
- le nombre de participants ne dépasse pas la **capacité** de la salle ;
- **aucun chevauchement** avec une réservation en attente ou validée.

Si un conflit est détecté, l'application propose automatiquement les **salles libres**
sur le même créneau et de capacité suffisante.

Un utilisateur ne peut modifier ou annuler sa réservation que dans la limite du **délai d'annulation**
défini salle par salle (24 heures par défaut). Passé ce délai, la réservation est verrouillée.

---

## 9. En cas de problème

| Symptôme | Cause probable et solution |
|----------|---------------------------|
| « Base de données introuvable » | Le fichier SQL n'a pas été importé — reprenez l'étape 3 |
| Page blanche | Regardez `C:\xampp\apache\logs\error.log` |
| « Access denied for user root » | Votre MySQL a un mot de passe : renseignez-le dans `config.php` |
| Les emails ne partent pas | Normal en mode démonstration : consultez le journal des notifications (section 4) |
| Les icônes ne s'affichent pas | Font Awesome est chargé depuis Internet : vérifiez votre connexion |
