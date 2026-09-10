# ReservaSalles

Application de réservation de salles de réunion : **PHP 8, MVC, PDO, sans
framework**. Disponibilités en temps réel, détection des chevauchements,
validation par un gestionnaire et notifications par email.

---

## Démarrer

1. XAMPP → démarrer **Apache** et **MySQL**.
2. Si la base n'est pas encore importée : phpMyAdmin → Importer →
   `database/reserva_salles.sql`.
3. Ouvrir **<http://localhost/ReservaSalles/>**

Comptes de démonstration (mot de passe commun `123456`) :

| Rôle | Email |
|---|---|
| Administrateur bâtiments | `admin@reserva.tn` |
| Gestionnaire de réservations | `gestionnaire@reserva.tn` |
| Utilisateur | `yassine@reserva.tn` · `ines@reserva.tn` |

---

## L'interface

### Système de design
Aucun framework CSS. Trois feuilles écrites à la main :

| Fichier | Rôle |
|---|---|
| `assets/css/app.css` | jetons (couleurs, type, espacement, ombres, courbes), réinitialisation, composants de base |
| `assets/css/shell.css` | gabarits : barre du site, rail d'administration, bannière, cartes de salle, calendrier, palette de commandes |
| `assets/css/compat.css` | passerelle : raccorde les anciens noms de classes des vues héritées au nouveau système |

- **Palette** : cobalt `#2454ff` comme couleur de marque, corail `#ff6b5a` en
  accent ponctuel, neutres légèrement bleutés (jamais de gris pur). Les couleurs
  d'état (vert / ambre / rouge) sont indépendantes de la marque.
- **Typographie** : *Bricolage Grotesque* pour les titres, *Figtree* pour le
  texte, *IBM Plex Mono* pour les chiffres et les heures.
- **Thème clair / sombre** complet, avec bascule en haut à droite et mémorisation
  dans le navigateur. Sans choix explicite, le thème suit le système.

### Front-office
- Coque « site produit » : barre supérieure translucide, plus de rail latéral.
- **Page d'accueil** : bannière avec trame de plan animée, compteurs qui
  s'incrémentent, catalogue des salles et bâtiments.
- **Calendrier** : chaque jour porte un voyant *Libre / Chargé / Complet* calculé
  sur les minutes réservées par rapport aux heures d'ouverture réelles. Les jours
  passés sont inertes. Un clic ouvre le volet des créneaux, chargé en AJAX ; deux
  clics choisissent une plage, et le lien de réservation est pré-rempli.
- **Réservation** : le jour se choisit une fois, puis seulement deux heures. Un
  panneau vivant affiche le créneau, sa durée, et **vérifie en direct** qu'il est
  libre avant l'envoi. Les caractéristiques de la salle s'affichent dès la
  sélection, photo comprise.
- **Mes réservations** : onglets par statut, motif de refus visible, délai de
  modification affiché en clair, journal des emails reçus.

### Back-office
- Rail latéral sombre repliable (l'état est mémorisé), groupé par rôle, avec un
  compteur des demandes en attente.
- **Tableau de bord** : quatre indicateurs animés, histogramme des 14 derniers
  jours, anneau de répartition par statut dessiné en SVG, file d'attente des
  demandes, classement des salles, prochaines réunions.

### Interactions (JavaScript natif, aucune bibliothèque)
| Fichier | Rôle |
|---|---|
| `assets/js/app.js` | thème, apparition au défilement, compteurs, onde au clic, notifications, rail, **palette de commandes** |
| `assets/js/valider.js` | contrôles de saisie : au blur, correction en direct, blocage de l'envoi et défilement vers la première erreur |
| `assets/js/calendrier.js` | calendrier : sélection du jour, chargement AJAX, choix d'une plage, récapitulatif |

- **Palette de commandes** : `Ctrl` + `K` ouvre une recherche qui saute vers
  n'importe quelle page ou salle. Flèches pour naviguer, `Entrée` pour ouvrir,
  `Échap` pour fermer. La recherche ignore les accents.
- Transitions de page natives (`@view-transition`) sur les navigateurs
  compatibles, squelettes de chargement, notifications flottantes.
- Toutes les animations sont désactivées si le système demande
  `prefers-reduced-motion`.

---

## Arborescence

```
ReservaSalles/
├── assets/
│   ├── css/     app.css · shell.css · compat.css
│   ├── js/      app.js · valider.js · calendrier.js
│   └── uploads/ batiments/ · salles/        (photos)
├── config.php            connexion PDO
├── config.mail.php       identifiants SMTP (non versionné)
├── controller/           BatimentC · EtageC · SalleC · ReservationC · UtilisateurC · MailC
├── database/             reserva_salles.sql
├── init.php              session, autoload, helpers, validation serveur
├── model/                Batiment · Etage · Salle · Reservation · Utilisateur
├── PHPMailer/            librairie SMTP
└── view/
    ├── frontend/         accueil · salles · calendrier · reserver · mesReservations · login · register
    └── backend/          tableau de bord · CRUD · statistiques · rapports · conflits · notifications
```

---

## Raccourcis utiles

| Touche | Effet |
|---|---|
| `Ctrl` + `K` | palette de commandes (aller à une page ou une salle) |
| `Échap` | fermer la palette ou le rail mobile |
| `↑` `↓` `Entrée` | naviguer et valider dans la palette |
