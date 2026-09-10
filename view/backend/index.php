<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin', 'gestionnaire']);

$salleC       = new SalleC();
$batimentC    = new BatimentC();
$reservationC = new ReservationC();
$utilisateurC = new UtilisateurC();

try { $reservationC->cloturerReservationsEchues(); } catch (Throwable $e) { /* silencieux */ }

$erreur = '';
try {
    $cSalles      = $salleC->compteurs();
    $cReserv      = $reservationC->compteurs();
    $batiments    = $batimentC->showBatimentsAvecDetails();
    $topSalles    = $reservationC->topSalles(6);
    $prochaines   = $reservationC->prochaines(6);
    $enAttente    = $reservationC->filterReservations(['statut' => 'en_attente', 'orderBy' => 'date', 'orderDir' => 'ASC']);
    $utilisateurs = $utilisateurC->showUtilisateurs();

    // Volume des 14 derniers jours
    $debut14 = date('Y-m-d', strtotime('-13 days'));
    $parJour = $reservationC->reservationsParJour($debut14, date('Y-m-d'));
} catch (Throwable $e) {
    $erreur = "Impossible de charger le tableau de bord : " . $e->getMessage()
            . " — vérifiez que la base « reserva_salles » est importée dans phpMyAdmin.";
    $cSalles = ['total' => 0, 'disponibles' => 0, 'maintenance' => 0, 'capacite_totale' => 0];
    $cReserv = ['total' => 0, 'en_attente' => 0, 'validees' => 0, 'refusees' => 0, 'annulees' => 0, 'terminees' => 0];
    $batiments = []; $topSalles = []; $prochaines = []; $enAttente = []; $utilisateurs = []; $parJour = [];
}

// Série complète sur 14 jours (les jours sans réservation valent zéro)
$serie = [];
for ($i = 13; $i >= 0; $i--) {
    $serie[date('Y-m-d', strtotime("-$i days"))] = 0;
}
foreach ($parJour as $p) {
    if (isset($serie[$p['jour']])) $serie[$p['jour']] = (int)$p['nb'];
}
$maxSerie = max(1, max($serie));
$maxTop   = max(1, max(array_map(fn($s) => (int)$s['nb'], $topSalles ?: [['nb' => 1]])));

// Répartition par statut, pour l'anneau
$statuts = [
    'validee'    => ['Validées',   'var(--ok-500)',    (int)$cReserv['validees']],
    'en_attente' => ['En attente', 'var(--warn-500)',  (int)$cReserv['en_attente']],
    'terminee'   => ['Terminées',  'var(--info-500)',  (int)$cReserv['terminees']],
    'refusee'    => ['Refusées',   'var(--bad-500)',   (int)$cReserv['refusees']],
    'annulees'   => ['Annulées',   'var(--ink-300)',   (int)$cReserv['annulees']],
];
$totalStatuts = max(1, array_sum(array_map(fn($s) => $s[2], $statuts)));

// Taux d'occupation global approximatif (salles disponibles / total)
$tauxDispo = $cSalles['total'] > 0
    ? round(((int)$cSalles['disponibles'] / (int)$cSalles['total']) * 100)
    : 0;

$titrePage  = 'Tableau de bord';
$pageActive = 'dashboard';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow"><?= e(libelle_role(role_courant())) ?></span>
        <h1 class="mt-2">Bonjour <?= e($moi['prenom']) ?> 👋</h1>
        <p>
            <?= (int)$cReserv['en_attente'] > 0
                ? '<strong>' . (int)$cReserv['en_attente'] . ' demande' . ((int)$cReserv['en_attente'] > 1 ? 's' : '') . '</strong> attend' . ((int)$cReserv['en_attente'] > 1 ? 'ent' : '') . ' votre validation.'
                : 'Aucune demande en attente — tout est à jour.' ?>
        </p>
    </div>
    <div class="row g-2 wrapf">
        <a href="addReservation.php" class="btn"><i class="fas fa-square-plus"></i> Réservation manuelle</a>
        <a href="listReservation.php?statut=en_attente" class="btn btn-primary">
            <i class="fas fa-hourglass-half"></i> Traiter les demandes
            <?php if ((int)$cReserv['en_attente'] > 0): ?>
                <span class="badge badge-plain" style="background:rgba(255,255,255,.25);color:#fff">
                    <?= (int)$cReserv['en_attente'] ?>
                </span>
            <?php endif; ?>
        </a>
    </div>
</div>

<?php flash_afficher(); ?>
<?php if ($erreur !== ''): ?>
    <div class="alert alert-bad"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>

<!-- ============================================== CHIFFRES CLÉS -->
<div class="grid g-cols-4 mb-6">
    <div class="stat reveal">
        <div class="stat-top">
            <span class="stat-label">Réservations</span>
            <span class="stat-ico ico-brand"><i class="fas fa-calendar-check"></i></span>
        </div>
        <span class="stat-value" data-count="<?= (int)$cReserv['total'] ?>">0</span>
        <span class="stat-foot">
            <?= (int)$cReserv['validees'] ?> validées · <?= (int)$cReserv['terminees'] ?> terminées
        </span>
    </div>

    <div class="stat reveal" data-d="1">
        <div class="stat-top">
            <span class="stat-label">En attente</span>
            <span class="stat-ico ico-warn"><i class="fas fa-hourglass-half"></i></span>
        </div>
        <span class="stat-value" data-count="<?= (int)$cReserv['en_attente'] ?>">0</span>
        <span class="stat-foot">
            <?php if ((int)$cReserv['en_attente'] > 0): ?>
                <a href="listReservation.php?statut=en_attente">Traiter maintenant →</a>
            <?php else: ?>Rien à traiter<?php endif; ?>
        </span>
    </div>

    <div class="stat reveal" data-d="2">
        <div class="stat-top">
            <span class="stat-label">Salles réservables</span>
            <span class="stat-ico ico-ok"><i class="fas fa-door-open"></i></span>
        </div>
        <span class="stat-value" data-count="<?= (int)$cSalles['disponibles'] ?>">0</span>
        <div class="meter mt-2"><span data-meter="<?= $tauxDispo ?>"></span></div>
        <span class="stat-foot"><?= $tauxDispo ?> % du parc (<?= (int)$cSalles['total'] ?> salles)</span>
    </div>

    <div class="stat reveal" data-d="3">
        <div class="stat-top">
            <span class="stat-label">Capacité totale</span>
            <span class="stat-ico ico-info"><i class="fas fa-chair"></i></span>
        </div>
        <span class="stat-value" data-count="<?= (int)$cSalles['capacite_totale'] ?>">0</span>
        <span class="stat-foot">
            <?= count($batiments) ?> bâtiment<?= count($batiments) > 1 ? 's' : '' ?> ·
            <?= count($utilisateurs) ?> compte<?= count($utilisateurs) > 1 ? 's' : '' ?>
        </span>
    </div>
</div>

<!-- ============================================== ACTIVITÉ + RÉPARTITION -->
<div class="grid mb-6" style="grid-template-columns: minmax(0,1.55fr) minmax(0,1fr)">

    <div class="card reveal">
        <div class="card-head">
            <h2><i class="fas fa-chart-column"></i> Activité des 14 derniers jours</h2>
            <span class="badge badge-plain badge-brand"><?= array_sum($serie) ?> réservations</span>
        </div>
        <div class="card-body">
            <div class="histo">
                <?php foreach ($serie as $d => $n): ?>
                    <?php $h = max(3, round(($n / $maxSerie) * 100)); ?>
                    <div class="histo-col" title="<?= e(fmt_date($d)) ?> — <?= $n ?> réservation(s)">
                        <span class="histo-val"><?= $n ?: '' ?></span>
                        <span class="histo-barre" style="height:<?= $h ?>%"></span>
                        <span class="histo-label"><?= e(date('d/m', strtotime($d))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card reveal" data-d="1">
        <div class="card-head"><h2><i class="fas fa-chart-pie"></i> Répartition par statut</h2></div>
        <div class="card-body">
            <?php
            // Anneau construit en SVG, sans bibliothèque.
            $r = 56; $circ = 2 * M_PI * $r; $offset = 0;
            ?>
            <div class="donut-wrap">
                <div class="donut" style="width:150px;height:150px">
                    <svg viewBox="0 0 140 140" style="width:100%;height:100%;transform:rotate(-90deg)">
                        <circle cx="70" cy="70" r="<?= $r ?>" fill="none"
                                stroke="var(--surface-3)" stroke-width="16"></circle>
                        <?php foreach ($statuts as [$lib, $col, $n]): ?>
                            <?php if ($n <= 0) continue;
                                  $part = ($n / $totalStatuts) * $circ; ?>
                            <circle cx="70" cy="70" r="<?= $r ?>" fill="none"
                                    stroke="<?= $col ?>" stroke-width="16"
                                    stroke-dasharray="<?= round($part, 2) ?> <?= round($circ - $part, 2) ?>"
                                    stroke-dashoffset="<?= round(-$offset, 2) ?>"
                                    stroke-linecap="butt"></circle>
                            <?php $offset += $part; ?>
                        <?php endforeach; ?>
                    </svg>
                    <div class="donut-centre">
                        <div>
                            <div class="donut-total"><?= (int)$cReserv['total'] ?></div>
                            <div class="donut-sous">au total</div>
                        </div>
                    </div>
                </div>
                <div class="donut-legende">
                    <?php foreach ($statuts as [$lib, $col, $n]): ?>
                        <div class="donut-item">
                            <span class="donut-puce" style="background:<?= $col ?>"></span>
                            <span class="donut-nom"><?= e($lib) ?></span>
                            <span class="donut-val"><?= $n ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================== FILE D'ATTENTE -->
<div class="card mb-6 reveal">
    <div class="card-head">
        <h2><i class="fas fa-hourglass-half"></i> Demandes en attente de validation</h2>
        <a href="listReservation.php?statut=en_attente" class="btn btn-sm">
            Tout voir <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    <?php if (!$enAttente): ?>
        <div class="empty">
            <span class="empty-icon" style="background:var(--ok-50);color:var(--ok-600)">
                <i class="fas fa-circle-check"></i>
            </span>
            <h3>Aucune demande en attente</h3>
            <p>Toutes les demandes ont été traitées.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap" style="border:0;border-radius:0">
            <table class="data">
                <thead>
                    <tr>
                        <th>Objet</th><th>Demandeur</th><th>Salle</th>
                        <th>Créneau</th><th>Pers.</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($enAttente, 0, 6) as $r): ?>
                    <tr>
                        <td>
                            <div class="cell-titre"><?= e($r['titre']) ?></div>
                            <div class="cell-sub">Demandé le <?= e(fmt_date($r['date_creation'])) ?></div>
                        </td>
                        <td>
                            <div class="cell-user">
                                <span class="avatar-mini">
                                    <?= e(mb_strtoupper(mb_substr($r['prenom_utilisateur'], 0, 1) . mb_substr($r['nom_utilisateur'], 0, 1))) ?>
                                </span>
                                <div>
                                    <div class="cell-titre"><?= e($r['prenom_utilisateur'] . ' ' . $r['nom_utilisateur']) ?></div>
                                    <div class="cell-sub"><?= e($r['departement'] ?: '—') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-info badge-plain"><?= e($r['nom_salle']) ?></span>
                            <div class="cell-sub"><?= e($r['nom_batiment']) ?></div>
                        </td>
                        <td>
                            <div class="mono t-sm"><?= e(date('d/m/Y', strtotime($r['date_debut']))) ?></div>
                            <div class="cell-sub mono">
                                <?= e(fmt_heure($r['date_debut'])) ?>–<?= e(fmt_heure($r['date_fin'])) ?>
                            </div>
                        </td>
                        <td><span class="mono"><?= (int)$r['nb_participants'] ?></span></td>
                        <td>
                            <div class="td-actions">
                                <a href="traiterReservation.php?id=<?= (int)$r['id_reservation'] ?>&action=valider"
                                   class="btn btn-success btn-sm" title="Valider la demande"
                                   data-confirm="Valider « <?= e($r['titre']) ?> » ? Le demandeur recevra un email de confirmation.">
                                    <i class="fas fa-check"></i> Valider
                                </a>
                                <a href="listReservation.php?statut=en_attente"
                                   class="btn btn-sm" title="Ouvrir la liste pour refuser ou déplacer">
                                    <i class="fas fa-ellipsis"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================== TOP SALLES + PROCHAINES -->
<div class="grid" style="grid-template-columns: minmax(0,1fr) minmax(0,1fr)">

    <div class="card reveal">
        <div class="card-head">
            <h2><i class="fas fa-ranking-star"></i> Salles les plus réservées</h2>
        </div>
        <div class="card-body">
            <?php if (!$topSalles): ?>
                <div class="empty" style="padding:var(--s-7) 0">
                    <span class="empty-icon"><i class="fas fa-chart-simple"></i></span>
                    <p class="t-sm">Pas encore de données d'utilisation.</p>
                </div>
            <?php else: ?>
                <div class="barres">
                    <?php foreach ($topSalles as $s): ?>
                        <div class="barre-ligne">
                            <span class="barre-label">
                                <?= e($s['nom']) ?>
                                <span class="muted">· <?= e($s['nom_batiment']) ?></span>
                            </span>
                            <span class="barre-valeur"><?= (int)$s['nb'] ?></span>
                            <span class="barre-piste">
                                <span class="barre-remplie" data-meter="<?= round(((int)$s['nb'] / $maxTop) * 100) ?>"
                                      style="width:0"></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card reveal" data-d="1">
        <div class="card-head">
            <h2><i class="fas fa-forward"></i> Prochaines réunions</h2>
            <a href="calendrier.php" class="btn btn-sm">Planning <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="card-body">
            <?php if (!$prochaines): ?>
                <div class="empty" style="padding:var(--s-7) 0">
                    <span class="empty-icon"><i class="fas fa-calendar-day"></i></span>
                    <p class="t-sm">Aucune réunion planifiée.</p>
                </div>
            <?php else: ?>
                <div class="tl">
                    <?php foreach ($prochaines as $r): ?>
                        <div class="tl-item <?= $r['statut'] === 'validee' ? 'ok' : 'warn' ?>">
                            <div style="min-width:0">
                                <div class="tl-when">
                                    <?= e(fmt_datetime($r['date_debut'])) ?> → <?= e(fmt_heure($r['date_fin'])) ?>
                                </div>
                                <div class="tl-what"><?= e($r['titre']) ?></div>
                                <div class="row g-2 mt-2 wrapf">
                                    <span class="badge badge-plain badge-info"><?= e($r['nom_salle']) ?></span>
                                    <span class="badge <?= e(classe_statut($r['statut'])) ?>">
                                        <?= e(libelle_statut($r['statut'])) ?>
                                    </span>
                                    <span class="muted t-xs">
                                        <?= e($r['prenom_utilisateur'] . ' ' . $r['nom_utilisateur']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
