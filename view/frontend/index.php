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
    $nbBatiments     = count($batimentC->showBatiments());
    $sallesVedette   = array_slice($salleC->getSallesDisponibles(), 0, 6);
    $prochaines      = est_connecte()
        ? array_slice($reservationC->getReservationsUtilisateur(id_courant()), 0, 3)
        : [];
} catch (Throwable $e) {
    $erreur = "Impossible de charger les données : " . $e->getMessage()
            . " — vérifiez que la base « reserva_salles » est bien importée dans phpMyAdmin.";
    $compteursSalles = ['total' => 0, 'disponibles' => 0, 'capacite_totale' => 0];
    $nbBatiments = 0; $sallesVedette = []; $prochaines = [];
}

$titrePage  = 'Accueil';
$pageActive = 'accueil';
require __DIR__ . '/partials/header.php';
?>

<section class="hero">
    <div class="container hero-content">
        <h1>Trouvez la bonne salle,<br>au bon moment.</h1>
        <p>Consultez les disponibilités en temps réel, réservez en quelques clics
           et laissez le système gérer les conflits d'horaires pour vous.</p>
        <div class="hero-actions">
            <a href="salles.php" class="btn btn-white"><i class="fas fa-magnifying-glass"></i> Explorer les salles</a>
            <a href="calendrier.php" class="btn btn-outline"><i class="fas fa-calendar-days"></i> Voir le calendrier</a>
        </div>
    </div>
</section>

<div class="container">
    <div class="hero-stats">
        <div class="stat-card">
            <div class="stat-icon bleu"><i class="fas fa-building"></i></div>
            <div>
                <div class="stat-value"><?= (int)$nbBatiments ?></div>
                <div class="stat-label">Bâtiments</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon vert"><i class="fas fa-door-open"></i></div>
            <div>
                <div class="stat-value"><?= (int)$compteursSalles['total'] ?></div>
                <div class="stat-label">Salles au total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-circle-check"></i></div>
            <div>
                <div class="stat-value"><?= (int)$compteursSalles['disponibles'] ?></div>
                <div class="stat-label">Salles réservables</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon rouge"><i class="fas fa-users"></i></div>
            <div>
                <div class="stat-value"><?= (int)$compteursSalles['capacite_totale'] ?></div>
                <div class="stat-label">Places assises</div>
            </div>
        </div>
    </div>
</div>

<div class="page container">

    <?php flash_afficher(); ?>
    <?php if ($erreur !== ''): ?>
        <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
    <?php endif; ?>

    <?php if (est_connecte() && $prochaines): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h2><i class="fas fa-clock"></i> Vos dernières réservations</h2>
                <a href="mesReservations.php" class="btn btn-light btn-sm">Tout voir <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>Objet</th><th>Salle</th><th>Créneau</th><th>Statut</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($prochaines as $r): ?>
                        <tr>
                            <td class="cell-titre"><?= e($r['titre']) ?></td>
                            <td>
                                <?= e($r['nom_salle']) ?>
                                <div class="cell-sub"><?= e($r['nom_batiment']) ?></div>
                            </td>
                            <td>
                                <?= e(fmt_date($r['date_debut'])) ?>
                                <div class="cell-sub"><?= e(fmt_heure($r['date_debut'])) ?> – <?= e(fmt_heure($r['date_fin'])) ?></div>
                            </td>
                            <td><span class="badge <?= classe_statut($r['statut']) ?>"><?= e(libelle_statut($r['statut'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1><i class="fas fa-star"></i> Salles disponibles</h1>
        <p>Un aperçu des espaces que vous pouvez réserver dès maintenant.</p>
    </div>

    <?php if (!$sallesVedette): ?>
        <div class="card"><div class="vide">
            <i class="fas fa-door-closed"></i>
            <h3>Aucune salle disponible</h3>
            <p>Les salles doivent d'abord être créées par l'administrateur des bâtiments.</p>
        </div></div>
    <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($sallesVedette as $s): ?>
                <?php $photo = photo_salle_url($s['image'] ?? null); ?>
                <div class="salle-card">
                    <div class="salle-visuel<?= $photo ? ' a-photo' : '' ?>">
                        <?php if ($photo): ?>
                            <img class="salle-photo" src="<?= e($photo) ?>" alt="Photo de <?= e($s['nom']) ?>" loading="lazy">
                        <?php endif; ?>
                        <span class="code"><?= e($s['code_salle']) ?></span>
                        <?php if (!$photo): ?>
                            <i class="fas fa-<?= $s['type_salle'] === 'conference' ? 'chalkboard-user'
                                : ($s['type_salle'] === 'visio' ? 'video'
                                : ($s['type_salle'] === 'formation' ? 'graduation-cap'
                                : ($s['type_salle'] === 'coworking' ? 'laptop-code' : 'users'))) ?>"></i>
                        <?php endif; ?>
                    </div>
                    <div class="salle-body">
                        <h3><?= e($s['nom']) ?></h3>
                        <div class="salle-lieu">
                            <i class="fas fa-location-dot"></i>
                            <?= e($s['nom_batiment']) ?> · <?= e($s['nom_etage']) ?>
                        </div>
                        <div class="salle-meta">
                            <span><i class="fas fa-users"></i> <?= (int)$s['capacite'] ?> places</span>
                            <span><i class="fas fa-tag"></i> <?= e(libelle_type_salle($s['type_salle'])) ?></span>
                            <span><i class="fas fa-clock"></i> <?= e(substr($s['heure_ouverture'], 0, 5)) ?>–<?= e(substr($s['heure_fermeture'], 0, 5)) ?></span>
                        </div>
                        <?php $equipements = liste_equipements($s['equipements']); ?>
                        <?php if ($equipements): ?>
                            <div class="equip-list">
                                <?php foreach (array_slice($equipements, 0, 4) as $eq): ?>
                                    <span class="equip"><?= e($eq) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="salle-actions">
                            <a href="calendrier.php?salle=<?= (int)$s['id_salle'] ?>" class="btn btn-light btn-sm">
                                <i class="fas fa-calendar"></i> Disponibilités
                            </a>
                            <a href="reserver.php?salle=<?= (int)$s['id_salle'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Réserver
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-3">
            <a href="salles.php" class="btn btn-light">Voir toutes les salles <i class="fas fa-arrow-right"></i></a>
        </div>
    <?php endif; ?>

    <div class="grid grid-3 mt-3" style="margin-top:44px;">
        <div class="card"><div class="card-body">
            <div class="stat-icon bleu mb-2"><i class="fas fa-magnifying-glass"></i></div>
            <h3 style="font-size:1.02rem;margin-bottom:6px;">1. Cherchez</h3>
            <p class="text-muted" style="font-size:.9rem;">Filtrez par bâtiment, capacité, type de salle ou équipement.</p>
        </div></div>
        <div class="card"><div class="card-body">
            <div class="stat-icon orange mb-2"><i class="fas fa-calendar-plus"></i></div>
            <h3 style="font-size:1.02rem;margin-bottom:6px;">2. Réservez</h3>
            <p class="text-muted" style="font-size:.9rem;">Choisissez un créneau libre : les conflits sont détectés automatiquement.</p>
        </div></div>
        <div class="card"><div class="card-body">
            <div class="stat-icon vert mb-2"><i class="fas fa-envelope-circle-check"></i></div>
            <h3 style="font-size:1.02rem;margin-bottom:6px;">3. Recevez la confirmation</h3>
            <p class="text-muted" style="font-size:.9rem;">Un email vous prévient dès que le gestionnaire valide la demande.</p>
        </div></div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
