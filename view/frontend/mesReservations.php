<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_connexion();

$reservationC = new ReservationC();
$mailC        = new MailC();

try { $reservationC->cloturerReservationsEchues(); } catch (Throwable $e) { /* silencieux */ }

$statut = $_GET['statut'] ?? 'tous';

$erreur = '';
try {
    $reservations  = $reservationC->getReservationsUtilisateur(id_courant(), $statut);
    $toutes        = $reservationC->getReservationsUtilisateur(id_courant());
    $notifications = $mailC->getNotificationsUtilisateur(id_courant(), 8);
} catch (Throwable $e) {
    $erreur = 'Erreur lors du chargement de vos réservations : ' . $e->getMessage();
    $reservations = []; $toutes = []; $notifications = [];
}

// Compteurs par statut
$compteurs = ['tous' => count($toutes)];
foreach (Reservation::STATUTS as $s) {
    $compteurs[$s] = count(array_filter($toutes, fn($r) => $r['statut'] === $s));
}

$titrePage  = 'Mes réservations';
$pageActive = 'mes-reservations';
require __DIR__ . '/partials/header.php';
?>

<div class="page container">

    <div class="page-header">
        <h1><i class="fas fa-clock-rotate-left"></i> Mes réservations</h1>
        <p>Historique complet, avec modification et annulation avant la date limite.</p>
    </div>

    <?php flash_afficher(); ?>
    <?php if ($erreur !== ''): ?>
        <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
    <?php endif; ?>

    <div class="grid grid-4 mb-3">
        <div class="stat-card">
            <div class="stat-icon bleu"><i class="fas fa-list"></i></div>
            <div><div class="stat-value"><?= $compteurs['tous'] ?></div><div class="stat-label">Au total</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-hourglass-half"></i></div>
            <div><div class="stat-value"><?= $compteurs['en_attente'] ?></div><div class="stat-label">En attente</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon vert"><i class="fas fa-circle-check"></i></div>
            <div><div class="stat-value"><?= $compteurs['validee'] ?></div><div class="stat-label">Validées</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon rouge"><i class="fas fa-circle-xmark"></i></div>
            <div><div class="stat-value"><?= $compteurs['refusee'] + $compteurs['annulee'] ?></div><div class="stat-label">Refusées / annulées</div></div>
        </div>
    </div>

    <div class="filtres">
        <div class="d-flex gap-2 flex-wrap align-center">
            <span style="font-weight:600;font-size:.87rem;"><i class="fas fa-filter"></i> Filtrer :</span>
            <a href="?statut=tous" class="btn btn-sm <?= $statut === 'tous' ? 'btn-primary' : 'btn-light' ?>">
                Toutes (<?= $compteurs['tous'] ?>)
            </a>
            <?php foreach (Reservation::STATUTS as $s): ?>
                <a href="?statut=<?= e($s) ?>" class="btn btn-sm <?= $statut === $s ? 'btn-primary' : 'btn-light' ?>">
                    <?= e(libelle_statut($s)) ?> (<?= $compteurs[$s] ?>)
                </a>
            <?php endforeach; ?>
            <a href="reserver.php" class="btn btn-primary btn-sm" style="margin-left:auto;">
                <i class="fas fa-plus"></i> Nouvelle réservation
            </a>
        </div>
    </div>

    <?php if (!$reservations): ?>
        <div class="card"><div class="vide">
            <i class="fas fa-calendar-xmark"></i>
            <h3>Aucune réservation</h3>
            <p>Vous n'avez pas encore de réservation avec ce filtre.</p>
            <a href="salles.php" class="btn btn-primary mt-3"><i class="fas fa-magnifying-glass"></i> Trouver une salle</a>
        </div></div>
    <?php else: ?>
        <div class="card">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Objet</th>
                            <th>Salle</th>
                            <th>Créneau</th>
                            <th>Participants</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reservations as $r): ?>
                        <?php $modifiable = $reservationC->peutEtreModifiee($r); ?>
                        <tr>
                            <td>
                                <div class="cell-titre"><?= e($r['titre']) ?></div>
                                <?php if (!empty($r['description'])): ?>
                                    <div class="cell-sub"><?= e(mb_substr($r['description'], 0, 60)) ?><?= mb_strlen($r['description']) > 60 ? '…' : '' ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($r['nom_salle']) ?>
                                <div class="cell-sub"><?= e($r['nom_batiment']) ?> · <?= e($r['code_salle']) ?></div>
                            </td>
                            <td>
                                <?= e(fmt_date($r['date_debut'])) ?>
                                <div class="cell-sub">
                                    <?= e(fmt_heure($r['date_debut'])) ?> – <?= e(fmt_heure($r['date_fin'])) ?>
                                    (<?= e(fmt_duree($r['date_debut'], $r['date_fin'])) ?>)
                                </div>
                            </td>
                            <td><?= (int)$r['nb_participants'] ?></td>
                            <td>
                                <span class="badge <?= classe_statut($r['statut']) ?>"><?= e(libelle_statut($r['statut'])) ?></span>
                                <?php if ($r['statut'] === 'refusee' && !empty($r['motif_refus'])): ?>
                                    <div class="cell-sub" title="<?= e($r['motif_refus']) ?>">
                                        <i class="fas fa-comment"></i> <?= e(mb_substr($r['motif_refus'], 0, 40)) ?>…
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="td-actions">
                                    <?php if ($modifiable): ?>
                                        <a href="modifierReservation.php?id=<?= (int)$r['id_reservation'] ?>"
                                           class="btn btn-light btn-sm" title="Modifier">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <a href="annulerReservation.php?id=<?= (int)$r['id_reservation'] ?>"
                                           class="btn btn-danger btn-sm" title="Annuler"
                                           onclick="return confirmerSuppression('Annuler la réservation « <?= e(addslashes($r['titre'])) ?> » ?');">
                                            <i class="fas fa-xmark"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="cell-sub" title="Délai limite dépassé ou réservation close">
                                            <i class="fas fa-lock"></i> Verrouillée
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($notifications): ?>
        <div class="card mt-3">
            <div class="card-header"><h2><i class="fas fa-envelope"></i> Vos dernières notifications</h2></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Date</th><th>Sujet</th><th>Type</th></tr></thead>
                    <tbody>
                    <?php foreach ($notifications as $n): ?>
                        <tr>
                            <td class="cell-sub"><?= e(date('d/m/Y H:i', strtotime($n['date_envoi']))) ?></td>
                            <td class="cell-titre"><?= e($n['sujet']) ?></td>
                            <td><span class="badge badge-info"><?= e($n['type']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
