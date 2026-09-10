# Conformité au cahier des charges

Chaque exigence de l'énoncé est mise en regard du code qui la réalise, avec le
résultat des tests. Dernière vérification : 10 septembre 2026 — PHP 8.2, MySQL (XAMPP).

---

## 1. Contraintes techniques

| Exigence | État | Où c'est fait |
|---|---|---|
| Développé en **PHP 8** | ✅ | Testé sous PHP 8.2 (`match`, `str_ends_with`, types nullables, `finfo`). |
| Structure **MVC** | ✅ | `model/` (5 entités), `controller/` (6 contrôleurs), `view/` (front + back). Bootstrap commun `init.php`. |
| **PDO uniquement**, pas de MySQLi | ✅ | `config.php` : singleton `PDO`, `ERRMODE_EXCEPTION`. Recherche `mysqli` dans tout le code → **0 occurrence**. Requêtes **préparées** partout. |
| **CRUD** sur toutes les entités | ✅ | `add* / update* / delete* / list*` pour Bâtiment, Étage, Salle, Utilisateur, Réservation. |
| Contrôles de saisie **JS + PHP**, pas de HTML | ✅ | Client : `assets/js/valider.js` (objet `Valider`). Serveur : `init.php` (`v_requis`, `v_longueur`, `v_entier`, `v_email`, `v_datetime`…). Tous les formulaires sont en `novalidate` ; **aucun attribut `required`**. |
| **Jointures** entre tables | ✅ | `SalleC::SELECT_BASE` : salle ⋈ étage ⋈ bâtiment. `ReservationC::SELECT_BASE` : réservation ⋈ salle ⋈ étage ⋈ bâtiment ⋈ utilisateur (+ LEFT JOIN validateur). |
| **Calendrier interactif** | ✅ | `view/frontend/calendrier.php` + `assets/js/calendrier.js` + `view/frontend/ajax/disponibilites.php`. Voyant d'occupation par jour, créneaux chargés en AJAX, choix d'une plage en deux clics. |
| Gestion des **conflits / chevauchements** | ✅ | `ReservationC::detecterConflits()` (règle `d1 < f2 AND f1 > d2`), `validerCreneau()` (dates, horaires d'ouverture, état salle, capacité, chevauchement). Page dédiée `view/backend/conflits.php` + écran de déplacement assisté. |
| **Templates responsifs** front + back | ✅ | Système de design maison : `assets/css/app.css`, `shell.css`, `compat.css`. Du mobile au grand écran. |
| **Historique Git** avec commits réguliers | ⚠️ | Dépôt `github.com/AdamFriaa1/Reservationsalle` — à alimenter par des commits fréquents (voir §6). |
| **Notifications par email** | ✅ | `MailC` (PHPMailer en SMTP) + journalisation systématique en table `notification`. Envoi réel testé (`235 Accepted`, `250 OK`). |
| **Aucun framework** | ✅ | PHP, CSS et JavaScript écrits à la main. Seules dépendances externes : Google Fonts et Font Awesome (icônes). PHPMailer est une librairie SMTP, pas un framework. |

---

## 2. Rôle « Administrateur Bâtiments »

| Action de l'énoncé | État | Fichiers / méthodes |
|---|---|---|
| Crée et gère **bâtiments** et **étages** | ✅ | `addBatiment` · `updateBatiment` · `deleteBatiment` · `listBatiment` ; idem pour les étages. `BatimentC`, `EtageC`. |
| Ajoute et configure les **salles** | ✅ | `addSalle` · `updateSalle` : capacité, type, équipements, localisation, horaires, délai d'annulation, **photo**. |
| Gère **maintenance et disponibilité** | ✅ | Colonne `salle.etat` = `disponible` / `maintenance` / `indisponible` ; `SalleC::changerEtat()`. Seules les salles disponibles sont réservables. |
| Visualise les **statistiques d'utilisation** | ✅ | `view/backend/statistiques.php` ; `SalleC::statistiquesUtilisation()` (taux d'occupation sur les heures d'ouverture réelles), `topSalles()`, `repartitionParBatiment()`. |
| Génère des **rapports par période** | ✅ | `view/backend/rapport.php` ; `ReservationC::rapportPeriode()`, `reservationsParJour()`. |

---

## 3. Rôle « Gestionnaire de Réservations »

| Action de l'énoncé | État | Fichiers / méthodes |
|---|---|---|
| **Valide ou refuse** les demandes | ✅ | `traiterReservation.php` ; `ReservationC::changerStatut()` + email `notifierValidation` / `notifierRefus`. |
| **Réservations manuelles** | ✅ | `view/backend/addReservation.php` (accès `admin` + `gestionnaire`). |
| Gère **conflits** et **déplacements** | ✅ | `conflits.php` liste les paires qui se chevauchent. L'écran de déplacement affiche **créneau actuel / créneau visé / réservation à ne pas croiser** côte à côte, plus une bande de disponibilités cliquable. `deplacerReservation()` + email `notifierDeplacement`. |
| **Recherches multicritères** | ✅ | `listReservation.php` ; `ReservationC::filterReservations()` — 7 critères + tri. |

---

## 4. Rôle « Utilisateur »

| Action de l'énoncé | État | Fichiers / méthodes |
|---|---|---|
| Consulte les salles avec **calendrier et filtres** | ✅ | `salles.php` (recherche, bâtiment, type, capacité, équipement, PMR, créneau libre) ; `calendrier.php`. |
| **Soumet une demande** | ✅ | `reserver.php` — jour unique + deux heures, caractéristiques de la salle affichées immédiatement, **vérification de disponibilité en direct** avant envoi. |
| **Modifie ou annule** avant la date limite | ✅ | `modifierReservation.php`, `annulerReservation.php` ; `peutEtreModifiee()` s'appuie sur `salle.delai_annulation`. |
| Visualise son **historique** | ✅ | `mesReservations.php` ; `getReservationsUtilisateur()`. Onglets par statut, motif de refus, journal des emails. |
| Reçoit **confirmations et notifications** | ✅ | `notifierDemande`, `notifierValidation`, `notifierRefus`, `notifierAnnulation`, `alerterGestionnaires`. |

---

## 5. Entités développées

| Entité demandée | Modèle | Contrôleur | Table |
|---|---|---|---|
| **Utilisateur** | `model/Utilisateur.php` | `UtilisateurC` | `utilisateur` (rôle, statut, `password_hash`) |
| **Bâtiment** | `model/Batiment.php` | `BatimentC` | `batiment` (+ `Etage` / `EtageC` / `etage`) |
| **Salle** | `model/Salle.php` | `SalleC` | `salle` |
| **Réservation** | `model/Reservation.php` | `ReservationC` | `reservation` (+ `notification` pour le journal des emails) |

---

## 6. Points à finaliser

1. **Git — commits réguliers** d'ici la validation.
2. **`config.mail.php`** : y mettre votre adresse et un **mot de passe d'application** Google. Ce fichier n'est jamais versionné.
3. **Antivirus Avast** : ajouter le dossier du projet en exception. Il a déjà mis `config.php` en quarantaine et intercepte le TLS SMTP.
4. **Photos** : les images actuelles sont des illustrations vectorielles. Les remplacer par de vraies photos via le back-office.
5. **Mots de passe de démonstration** : tous les comptes utilisent `123456`. À changer au moins pour l'administrateur si le site doit être présenté comme prêt.
