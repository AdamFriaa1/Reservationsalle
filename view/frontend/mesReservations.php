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

$onglets = [
    'tous'       => ['Toutes',     'fa-list'],
    'en_attente' => ['En attente', 'fa-hourglass-half'],
    'validee'    => ['Validées',   'fa-circle-check'],
    'terminee'   => ['Terminées',  'fa-flag-checkered'],
    'refusee'    => ['Refusées',   'fa-circle-xmark'],
    'annulee'    => ['Annulées',   'fa-ban'],
];

$titrePage  = 'Mes réservations';
$pageActive = 'mes-reservations';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">Mon compte</span>
        <h1 class="mt-2">Mes réservations</h1>
        <p>Historique complet. Vous pouvez modifier ou annuler tant que le délai de la salle n'est pas dépassé.</p>
    </div>
    <a href="reserver.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvelle demande</a>
</div>

<?php flash_afficher(); ?>
<?php if ($erreur !== ''): ?>
    <div class="alert alert-bad"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>

<!-- ============================================== ONGLETS DE STATUT -->
<div class="row g-2 wrapf mb-6">
    <?php foreach ($onglets as $cle => [$lib, $ico]): ?>
        <?php $actif = $statut === $cle; ?>
        <a class="chip-action" href="mesReservations.php?statut=<?= e($cle) ?>"
           <?= $actif ? 'style="background:var(--brand-500);border-color:var(--brand-500);color:#fff"' : '' ?>>
            <i class="fas <?= $ico ?>"></i> <?= e($lib) ?>
            <span class="badge badge-plain"
                  style="<?= $actif ? 'background:rgba(255,255,255,.25);color:#fff' : '' ?>">
                <?= (int)($compteurs[$cle] ?? 0) ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid" style="grid-template-columns: minmax(0,1fr) 320px; align-items:start">

    <!-- ============================================== LISTE -->
    <div>
        <?php if (!$reservations): ?>
            <div class="card"><div class="empty">
                <span class="empty-icon"><i class="fas fa-calendar-xmark"></i></span>
                <h3>Aucune réservation<?= $statut !== 'tous' ? ' dans cette catégorie' : '' ?></h3>
                <p>
                    <?= $statut !== 'tous'
                        ? 'Essayez un autre filtre, ou consultez toutes vos réservations.'
                        : 'Consultez les salles disponibles et envoyez votre première demande.' ?>
                </p>
                <div class="row g-2 mt-3 wrapf" style="justify-content:center">
                    <?php if ($statut !== 'tous'): ?>
                        <a href="mesReservations.php" class="btn btn-sm">Toutes mes réservations</a>
                    <?php endif; ?>
                    <a href="salles.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-door-open"></i> Voir les salles
                    </a>
                </div>
            </div></div>
        <?php else: ?>
            <?php foreach ($reservations as $i => $r): ?>
                <?php
                $ts        = strtotime($r['date_debut']);
                $modifiable = $reservationC->peutEtreModifiee($r);
                $limite     = $ts - ((int)$r['delai_annulation'] * 3600);
                ?>
                <article class="res-row reveal" data-d="<?= min(5, $i + 1) ?>">
                    <div class="res-date">
                        <b><?= e(date('d', $ts)) ?></b>
                        <span><?= e(mb_substr(MOIS_FR[(int)date('n', $ts)], 0, 4)) ?></span>
                    </div>

                    <div style="min-width:0">
                        <div class="row g-2 wrapf mb-2">
                            <span class="badge <?= e(classe_statut($r['statut'])) ?>">
                                <?= e(libelle_statut($r['statut'])) ?>
                            </span>
                            <span class="badge badge-plain badge-info"><?= e($r['nom_salle']) ?></span>
                            <span class="muted t-xs"><?= e($r['nom_batiment']) ?> · <?= e($r['nom_etage']) ?></span>
                        </div>

                        <h3 style="font-size:var(--t-md)"><?= e($r['titre']) ?></h3>

                        <div class="row g-4 wrapf muted t-sm mt-2">
                            <span><i class="fas fa-clock"></i>
                                <span class="mono"><?= e(fmt_heure($r['date_debut'])) ?>–<?= e(fmt_heure($r['date_fin'])) ?></span>
                                (<?= e(fmt_duree($r['date_debut'], $r['date_fin'])) ?>)
                            </span>
                            <span><i class="fas fa-users"></i> <?= (int)$r['nb_participants'] ?> pers.</span>
                            <span><i class="fas fa-calendar"></i> <?= e(fmt_date($r['date_debut'])) ?></span>
                        </div>

                        <?php if ($r['statut'] === 'refusee' && !empty($r['motif_refus'])): ?>
                            <div class="alert alert-bad mt-3" style="margin-bottom:0;padding:10px 14px">
                                <i class="fas fa-circle-info"></i>
                                <span><strong>Motif du refus :</strong> <?= e($r['motif_refus']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (in_array($r['statut'], ['en_attente', 'validee'], true)): ?>
                            <p class="hint mt-2">
                                <i class="fas fa-<?= $modifiable ? 'unlock' : 'lock' ?>"></i>
                                <?= $modifiable
                                    ? 'Modifiable jusqu\'au ' . e(date('d/m/Y à H:i', $limite))
                                      . ' (' . (int)$r['delai_annulation'] . ' h avant).'
                                    : 'Le délai de modification est dépassé — contactez le gestionnaire.' ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="td-actions" style="flex-direction:column;align-items:stretch;gap:7px">
                        <?php if ($modifiable): ?>
                            <a href="modifierReservation.php?id=<?= (int)$r['id_reservation'] ?>" class="btn btn-sm">
                                <i class="fas fa-pen"></i> Modifier
                            </a>
                            <a href="annulerReservation.php?id=<?= (int)$r['id_reservation'] ?>"
                               class="btn btn-danger btn-sm"
                               data-confirm="Annuler « <?= e($r['titre']) ?> » ? Cette action est définitive.">
                                <i class="fas fa-ban"></i> Annuler
                            </a>
                        <?php endif; ?>
                        <a href="calendrier.php?salle=<?= (int)$r['id_salle'] ?>&jour=<?= e(date('Y-m-d', $ts)) ?>"
                           class="btn btn-ghost btn-sm">
                            <i class="fas fa-calendar-days"></i> Planning
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ============================================== JOURNAL DES EMAILS -->
    <div class="card" style="position:sticky;top:calc(var(--topbar-h) + var(--s-5))">
        <div class="card-head"><h2><i class="fas fa-envelope"></i> Mes notifications</h2></div>
        <div class="card-body">
            <?php if (!$notifications): ?>
                <div class="empty" style="padding:var(--s-6) 0">
                    <span class="empty-icon"><i class="fas fa-inbox"></i></span>
                    <p class="t-sm">Aucun email envoyé pour l'instant.</p>
                </div>
            <?php else: ?>
                <div class="tl">
                    <?php foreach ($notifications as $n): ?>
                        <?php
                        $ton = match ($n['type']) {
                            'validation' => 'ok',
                            'refus', 'annulation' => 'bad',
                            default => 'warn',
                        };
                        ?>
                        <div class="tl-item <?= $ton ?>">
                            <div style="min-width:0">
                                <div class="tl-when">
                                    <?= e(fmt_datetime($n['date_envoi'])) ?>
                                    <?php if ((int)$n['envoye'] === 1): ?>
                                        <i class="fas fa-paper-plane" title="Email envoyé"
                                           style="color:var(--ok-500);margin-left:4px"></i>
                                    <?php else: ?>
                                        <i class="fas fa-clock" title="Journalisé, non envoyé"
                                           style="color:var(--ink-400);margin-left:4px"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="tl-what t-sm"><?= e($n['sujet']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="hint mt-4">
                    Chaque email est aussi journalisé en base — utile si un message n'arrive pas.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
