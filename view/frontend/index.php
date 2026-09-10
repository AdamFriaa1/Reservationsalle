<?php
require_once dirname(__DIR__, 2) . '/init.php';

$salleC       = new SalleC();
$reservationC = new ReservationC();
$batimentC    = new BatimentC();

// Clôture automatique des réservations dont la date est passée
try { $reservationC->cloturerReservationsEchues(); } catch (Throwable $e) { /* silencieux */ }

$erreur = '';
try {
    $compteursSalles = $salleC->compteurs();
    $compteursRes    = $reservationC->compteurs();
    $batiments       = $batimentC->showBatiments();
    $sallesVedette   = array_slice($salleC->getSallesDisponibles(), 0, 6);
    $prochaines      = est_connecte()
        ? array_slice($reservationC->getReservationsUtilisateur(id_courant()), 0, 3)
        : $reservationC->prochaines(3);
} catch (Throwable $e) {
    $erreur = "Impossible de charger les données : " . $e->getMessage()
            . " — vérifiez que la base « reserva_salles » est bien importée dans phpMyAdmin.";
    $compteursSalles = ['total' => 0, 'disponibles' => 0, 'capacite_totale' => 0];
    $compteursRes    = ['validees' => 0, 'en_attente' => 0];
    $batiments = []; $sallesVedette = []; $prochaines = [];
}

$titrePage     = 'Accueil';
$pageActive    = 'accueil';
$pleineLargeur = true;                     // la page gère elle-même ses .wrap
require __DIR__ . '/partials/header.php';
?>

<!-- ================================================== BANNIÈRE -->
<section class="hero">
    <span class="hero-grid" aria-hidden="true"></span>
    <div class="wrap hero-in">
        <div class="enter" style="max-width:44rem">
            <h1>Trouvez la bonne salle, <em>au bon moment</em>.</h1>
            <p class="lede">
                Le planning de chaque salle, créneau par créneau.
            </p>
            <div class="hero-cta">
                <a href="salles.php" class="btn btn-white btn-lg">
                    <i class="fas fa-magnifying-glass"></i> Explorer les salles
                </a>
                <a href="calendrier.php" class="btn btn-glass btn-lg">
                    <i class="fas fa-calendar-days"></i> Voir les disponibilités
                </a>
            </div>
        </div>

        <div class="hero-stats reveal">
            <div>
                <b data-count="<?= (int)$compteursSalles['total'] ?>">0</b>
                <span>Salles référencées</span>
            </div>
            <div>
                <b data-count="<?= (int)$compteursSalles['disponibles'] ?>">0</b>
                <span>Réservables aujourd'hui</span>
            </div>
            <div>
                <b data-count="<?= (int)$compteursSalles['capacite_totale'] ?>">0</b>
                <span>Places au total</span>
            </div>
            <div>
                <b data-count="<?= count($batiments) ?>">0</b>
                <span>Bâtiment<?= count($batiments) > 1 ? 's' : '' ?></span>
            </div>
        </div>
    </div>
</section>

<div class="wrap">
    <?php if ($erreur !== ''): ?>
        <div class="alert alert-bad mt-6">
            <i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span>
        </div>
    <?php endif; ?>
    <div class="mt-6"><?php flash_afficher(); ?></div>
</div>

<!-- ================================================== SALLES EN VEDETTE -->
<section class="section">
    <div class="wrap">
        <div class="row-between wrapf mb-6 reveal">
            <div class="section-head" style="margin-bottom:0">
                <span class="eyebrow">Catalogue</span>
                <h2>Salles disponibles</h2>
            </div>
            <a href="salles.php" class="btn">
                Toutes les salles <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <?php if (!$sallesVedette): ?>
            <div class="card"><div class="empty">
                <span class="empty-icon"><i class="fas fa-door-closed"></i></span>
                <h3>Aucune salle disponible</h3>
                <p>L'administrateur des bâtiments doit d'abord créer des salles.</p>
            </div></div>
        <?php else: ?>
            <div class="grid g-auto">
                <?php foreach ($sallesVedette as $i => $s): ?>
                    <?php
                    $photo = photo_salle_url($s['image'] ?? null);
                    $ico = match ($s['type_salle']) {
                        'conference' => 'fa-chalkboard-user',
                        'visio'      => 'fa-video',
                        'formation'  => 'fa-graduation-cap',
                        'coworking'  => 'fa-laptop-code',
                        default      => 'fa-users',
                    };
                    ?>
                    <article class="room reveal" data-d="<?= min(5, ($i % 3) + 1) ?>">
                        <div class="room-media">
                            <?php if ($photo): ?>
                                <img src="<?= e($photo) ?>" alt="Photo de <?= e($s['nom']) ?>" loading="lazy">
                            <?php else: ?>
                                <i class="fas <?= $ico ?>"></i>
                            <?php endif; ?>
                            <span class="room-code"><?= e($s['code_salle']) ?></span>
                            <span class="room-state">
                                <span class="badge badge-ok">Disponible</span>
                            </span>
                        </div>
                        <div class="room-body">
                            <h3><?= e($s['nom']) ?></h3>
                            <div class="room-where">
                                <i class="fas fa-location-dot"></i>
                                <?= e($s['nom_batiment']) ?> · <?= e($s['nom_etage']) ?>
                                <?php if ((int)$s['accessible_pmr'] === 1): ?>
                                    <i class="fas fa-wheelchair" title="Accessible PMR"></i>
                                <?php endif; ?>
                            </div>
                            <div class="room-meta">
                                <span><i class="fas fa-users"></i> <?= (int)$s['capacite'] ?> places</span>
                                <span><i class="fas fa-tag"></i> <?= e(libelle_type_salle($s['type_salle'])) ?></span>
                                <span><i class="fas fa-clock"></i>
                                    <?= e(substr($s['heure_ouverture'], 0, 5)) ?>–<?= e(substr($s['heure_fermeture'], 0, 5)) ?>
                                </span>
                            </div>
                            <?php $eq = liste_equipements($s['equipements']); ?>
                            <?php if ($eq): ?>
                                <div class="tags">
                                    <?php foreach (array_slice($eq, 0, 3) as $x): ?>
                                        <span class="tag"><?= e($x) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($eq) > 3): ?>
                                        <span class="tag">+<?= count($eq) - 3 ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="room-actions">
                                <a href="calendrier.php?salle=<?= (int)$s['id_salle'] ?>" class="btn btn-sm">
                                    <i class="fas fa-calendar"></i> Disponibilités
                                </a>
                                <a href="reserver.php?salle=<?= (int)$s['id_salle'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Réserver
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ================================================== BÂTIMENTS + ACTIVITÉ -->
<section class="section" style="padding-top:0">
    <div class="wrap">
        <div class="grid" style="grid-template-columns: minmax(0,1.35fr) minmax(0,1fr)">

            <div class="reveal">
                <div class="section-head">
                    <span class="eyebrow">Implantations</span>
                    <h2>Nos bâtiments</h2>
                </div>
                <div class="grid g-cols-2">
                    <?php foreach ($batiments as $b): ?>
                        <?php $bp = photo_batiment_url($b['image'] ?? null); ?>
                        <a class="room card-lift" href="salles.php?batiment=<?= (int)$b['id_batiment'] ?>">
                            <div class="room-media" style="aspect-ratio:16/10">
                                <?php if ($bp): ?>
                                    <img src="<?= e($bp) ?>" alt="<?= e($b['nom']) ?>" loading="lazy">
                                <?php else: ?>
                                    <i class="fas fa-building"></i>
                                <?php endif; ?>
                                <span class="room-code"><?= e($b['code_batiment']) ?></span>
                            </div>
                            <div class="room-body">
                                <h3><?= e($b['nom']) ?></h3>
                                <div class="room-where">
                                    <i class="fas fa-location-dot"></i>
                                    <?= e($b['ville']) ?>
                                </div>
                                <p class="muted t-sm" style="margin-top:-4px">
                                    <?= e($b['adresse']) ?>
                                </p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$batiments): ?>
                        <div class="card"><div class="empty">
                            <span class="empty-icon"><i class="fas fa-building"></i></span>
                            <p class="t-sm">Aucun bâtiment enregistré.</p>
                        </div></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="reveal" data-d="2">
                <div class="section-head">
                    <span class="eyebrow"><?= est_connecte() ? 'Mon compte' : 'Activité' ?></span>
                    <h2><?= est_connecte() ? 'Mes dernières demandes' : 'Prochaines réunions' ?></h2>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if (!$prochaines): ?>
                            <div class="empty" style="padding:var(--s-7) 0">
                                <span class="empty-icon"><i class="fas fa-calendar-day"></i></span>
                                <p class="t-sm">
                                    <?= est_connecte()
                                        ? "Vous n'avez pas encore de réservation."
                                        : "Aucune réunion planifiée pour l'instant." ?>
                                </p>
                                <?php if (est_connecte()): ?>
                                    <a href="reserver.php" class="btn btn-primary btn-sm mt-2">
                                        <i class="fas fa-plus"></i> Faire une demande
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="tl">
                                <?php foreach ($prochaines as $r): ?>
                                    <?php
                                    $ton = match ($r['statut']) {
                                        'validee', 'terminee' => 'ok',
                                        'en_attente'          => 'warn',
                                        'refusee', 'annulee'  => 'bad',
                                        default               => '',
                                    };
                                    ?>
                                    <div class="tl-item <?= $ton ?>">
                                        <div style="min-width:0">
                                            <div class="tl-when">
                                                <?= e(fmt_datetime($r['date_debut'])) ?>
                                                → <?= e(fmt_heure($r['date_fin'])) ?>
                                            </div>
                                            <div class="tl-what"><?= e($r['titre']) ?></div>
                                            <div class="row g-2 mt-2 wrapf">
                                                <span class="badge badge-plain badge-info">
                                                    <?= e($r['nom_salle']) ?>
                                                </span>
                                                <span class="badge <?= e(classe_statut($r['statut'])) ?>">
                                                    <?= e(libelle_statut($r['statut'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (est_connecte()): ?>
                                <a href="mesReservations.php" class="btn btn-block mt-5">
                                    Tout l'historique <i class="fas fa-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!est_connecte()): ?>
                    <div class="card mt-5" style="background:var(--brand-50); border-color:var(--brand-100)">
                        <div class="card-body">
                            <h3>Prêt à réserver ?</h3>
                            <p class="muted t-sm mt-2">
                                Créez un compte pour envoyer des demandes, suivre leur statut
                                et recevoir les confirmations par email.
                            </p>
                            <div class="row g-2 mt-5 wrapf">
                                <a href="register.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-user-plus"></i> Créer un compte
                                </a>
                                <a href="login.php" class="btn btn-sm">Se connecter</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
