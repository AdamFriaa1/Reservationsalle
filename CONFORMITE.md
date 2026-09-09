# Conformité au cahier des charges — ReservaSalles

Projet « Technologies Web » — système de réservation de salles en PHP 8, MVC, PDO, sans framework.
Chaque exigence de l'énoncé est mise en regard du code qui la réalise, avec le résultat des tests.

Dernière vérification : 9 septembre 2026 — PHP 8.2.12, MySQL (XAMPP).

---

## 1. Contraintes techniques

| Exigence | État | Où c'est fait |
|---|---|---|
| Développé en **PHP 8** | ✅ | Testé sous PHP 8.2.12 (`str_ends_with`, `enum`, types nullables, `finfo`). |
| Structure **MVC** | ✅ | `model/` (5 entités), `controller/` (6 contrôleurs), `view/` (front + back). Bootstrap commun `init.php`. |
| **PDO uniquement**, pas de MySQLi | ✅ | `config.php` : singleton `PDO`, `ERRMODE_EXCEPTION`. Recherche `mysqli` / `mysql_*` dans tout le code → **0 occurrence**. Requêtes **préparées** partout. |
| **CRUD** sur toutes les entités | ✅ | `add* / update* / delete* / list*` pour Bâtiment, Étage, Salle, Utilisateur, Réservation (back-office). |
| Contrôles de saisie **JS + PHP**, pas de HTML | ✅ | Côté client `assets/js/validation.js` (objet `Valider`) ; côté serveur `init.php` (`v_requis`, `v_longueur`, `v_entier`, `v_email`, `v_datetime`…). Les 20 formulaires sont en `novalidate` ; **aucun attribut `required`** natif. |
| **Jointures** entre tables | ✅ | `SalleC::SELECT_BASE` : salle ⋈ étage ⋈ bâtiment. `ReservationC::SELECT_BASE` : réservation ⋈ salle ⋈ étage ⋈ bâtiment ⋈ utilisateur (+ LEFT JOIN validateur). |
| **Calendrier interactif** | ✅ | `view/frontend/calendrier.php` + `assets/js/calendrier.js` + `view/frontend/ajax/disponibilites.php` (chargement AJAX des créneaux, choix début/fin au clic, sans rechargement). |
| Gestion des **conflits / chevauchements** | ✅ | `ReservationC::detecterConflits()` (règle `d1 < f2 AND f1 > d2`), `validerCreneau()` (dates, horaires d'ouverture, état salle, capacité, chevauchement). **Testé** : créneau chevauchant → refusé avec le détail du conflit. |
| **Templates responsifs** front + back | ✅ | Back-office : template Gentelella (MIT). Front-office : `assets/css/front.css` sur mesure, `@media` (grilles, sidebar). |
| **Historique Git** avec commits réguliers | ⚠️ | Dépôt `github.com/AdamFriaa1/Reservationsalle`. À **alimenter par des commits fréquents** d'ici la soutenance (voir §5). |
| **Notifications par email** | ✅ | `MailC` (PHPMailer en SMTP Gmail) + journalisation systématique en table `notification`. Envoi réel **testé OK** (`235 Accepted`, `250 OK`). |
| **Aucun framework** | ✅ | Vanilla PHP/JS/CSS. PHPMailer est une librairie d'envoi SMTP, pas un framework. |

---

## 2. Rôle « Administrateur Bâtiments »

| Action de l'énoncé | État | Fichiers / méthodes |
|---|---|---|
| Crée et gère les **bâtiments** et les **étages** | ✅ | `addBatiment` · `updateBatiment` · `deleteBatiment` · `listBatiment` ; `addEtage` · `updateEtage` · `deleteEtage` · `listEtage`. `BatimentC`, `EtageC`. |
| Ajoute et configure les **salles** (capacité, équipements, localisation) | ✅ | `addSalle` · `updateSalle` : capacité, type, équipements (cases + champ libre), localisation, horaires, délai d'annulation, **photo**. |
| Gère **maintenance et disponibilité** des salles | ✅ | Colonne `salle.etat` = `disponible` / `maintenance` / `indisponible` ; `SalleC::changerEtat()`. Seules les salles `disponible` sont réservables (contrôlé dans `validerCreneau`). |
| Visualise les **statistiques d'utilisation** | ✅ | `view/backend/statistiques.php` ; `SalleC::statistiquesUtilisation()` (taux d'occupation calculé sur les heures d'ouverture réelles), `ReservationC::topSalles()`, `repartitionParBatiment()`. |
| Génère des **rapports par période** | ✅ | `view/backend/rapport.php` ; `ReservationC::rapportPeriode($debut,$fin,$batiment)`, `reservationsParJour()`. |

---

## 3. Rôle « Gestionnaire de Réservations »

| Action de l'énoncé | État | Fichiers / méthodes |
|---|---|---|
| **Valide ou refuse** les demandes | ✅ | `view/backend/traiterReservation.php` ; `ReservationC::changerStatut($id,$statut,$validateur,$motif)` + email `notifierValidation` / `notifierRefus`. |
| Crée des **réservations manuelles** pour les utilisateurs | ✅ | `view/backend/addReservation.php` (accès `admin` + `gestionnaire`). |
| Gère les **conflits** et les **déplacements** de réunions | ✅ | `view/backend/conflits.php` ; `ReservationC::deplacerReservation()` + email `notifierDeplacement($ancienCreneau,$ancienneSalle)`. |
| Effectue des **recherches multicritères** | ✅ | `view/backend/listReservation.php` ; `ReservationC::filterReservations()` — 7 critères (texte, statut, bâtiment, salle, utilisateur, dates, participants) + tri. |

---

## 4. Rôle « Utilisateur »

| Action de l'énoncé | État | Fichiers / méthodes |
|---|---|---|
| Consulte les salles disponibles avec **calendrier et filtres** | ✅ | `view/frontend/salles.php` (recherche, bâtiment, type, capacité, équipement, PMR, créneau libre) ; `view/frontend/calendrier.php`. |
| **Soumet une demande** de réservation | ✅ | `view/frontend/reserver.php` — saisie jour + heure début + heure fin, caractéristiques de la salle affichées immédiatement. **Testé** : réservation créée en `en_attente`. |
| **Modifie ou annule** ses réservations avant la date limite | ✅ | `modifierReservation.php`, `annulerReservation.php` ; `ReservationC::peutEtreModifiee()` s'appuie sur `salle.delai_annulation` (heures). |
| Visualise son **historique** | ✅ | `view/frontend/mesReservations.php` ; `ReservationC::getReservationsUtilisateur($id,$statut)`. |
| Reçoit **confirmations et notifications** par email | ✅ | `notifierDemande` (accusé), `notifierValidation`, `notifierRefus`, `notifierAnnulation`, `alerterGestionnaires`. |

---

## 5. Entités développées

| Entité demandée | Modèle | Contrôleur | Table |
|---|---|---|---|
| **Utilisateur** | `model/Utilisateur.php` | `UtilisateurC` | `utilisateur` (rôle, statut, mot de passe `password_hash`) |
| **Bâtiment** | `model/Batiment.php` | `BatimentC` | `batiment` (+ `model/Etage.php` / `EtageC` / `etage`) |
| **Salle** | `model/Salle.php` | `SalleC` | `salle` |
| **Réservation** | `model/Reservation.php` | `ReservationC` | `reservation` (+ `notification` pour le journal des emails) |

---

## 6. Tests réalisés (9 sept. 2026)

| Test | Résultat |
|---|---|
| Import `database/reserva_salles.sql` dans une base neuve | ✅ 2 bâtiments, 4 étages, 4 salles, 4 utilisateurs, 8 réservations, 4 notifications |
| Connexion des 3 rôles (`admin` / `gestionnaire` / `utilisateur`), mot de passe `123456` | ✅ redirections correctes |
| Chargement de 14 pages front + back | ✅ HTTP 200, **0 erreur/avertissement PHP** |
| Soumission d'une réservation (jour + heures recomposés côté serveur) | ✅ enregistrée avec `date_debut` / `date_fin` corrects |
| Créneau chevauchant une réservation existante | ✅ refusé : « Conflit avec … » |
| Participants > capacité de la salle | ✅ refusé : « au maximum 12 personnes » |
| Calendrier — endpoint AJAX `ajax/disponibilites.php` | ✅ JSON des créneaux, créneaux occupés marqués |
| Affichage des photos (salle + bâtiment) front et back | ✅ images de démonstration servies |
| Envoi email SMTP réel | ✅ `235 Accepted` / `250 OK` |

---

## 7. Points à finaliser avant la soutenance

1. **Git — commits réguliers.** Le dépôt est récent ; committer à chaque étape (petits commits datés) d'ici la validation.
2. **`config.mail.php`.** Il utilise des identifiants Gmail empruntés. Mettre **ton** adresse + un **mot de passe d'application** Google.
3. **Antivirus Avast.** Ajouter le dossier du projet en **exception** : Avast a déjà mis `config.php` en quarantaine et intercepte le TLS SMTP (Menu → Paramètres → Général → Exceptions).
4. **Photos définitives.** Les images actuelles sont des placeholders SVG (`assets/uploads/batiments/`, `assets/uploads/salles/`). Les remplacer via le back-office ou en déposant un fichier de même nom.
5. Comptes de démo : mot de passe commun `123456` — acceptable pour la démonstration.
