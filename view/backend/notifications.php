<?php
/**
 * Journal des notifications email envoyées par l'application.
 * Chaque envoi est tracé en base, que le SMTP soit actif ou non.
 */
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin', 'gestionnaire']);

$mailC = new MailC();

$type   = $_GET['type'] ?? 'tous';
$limite = isset($_GET['limite']) && ctype_digit((string)$_GET['limite']) ? (int)$_GET['limite'] : 100;
if ($limite < 10 || $limite > 500) $limite = 100;

$erreur = '';
try {
    $notifications = $mailC->getNotifications($type, $limite);
} catch (Throwable $e) {
    $erreur = 'Erreur lors du chargement : ' . $e->getMessage();
    $notifications = [];
}

$parType = [];
$envoyees = 0;
foreach ($notifications as $n) {
    $parType[$n['type']] = ($parType[$n['type']] ?? 0) + 1;
    if ((int)$n['envoye'] === 1) $envoyees++;
}

$libellesType = [
    'demande'     => 'Nouvelle demande',
    'validation'  => 'Validation',
    'refus'       => 'Refus',
    'annulation'  => 'Annulation',
    'deplacement' => 'Déplacement',
    'rappel'      => 'Rappel',
];

$titrePage  = 'Notifications';
$pageActive = 'notifications';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / Notifications</div>
        <h2><i class="fas fa-envelope"></i> Journal des notifications</h2>
        <p>Historique des emails déclenchés par les demandes, validations et déplacements.</p>
    </div>
</div>

<?php flash_afficher(); ?>
<?php if ($erreur !== ''): ?>
    <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>

<?php if (!MailC::MAIL_ACTIF): ?>
    <div class="alert alert-info">
        <i class="fas fa-circle-info"></i>
        <div>
            <strong>Mode démonstration :</strong> l'envoi SMTP est désactivé, les emails sont uniquement journalisés ici.
            Pour activer les envois réels, renseignez <code>user</code> et <code>pass</code> dans
            <code>config.mail.php</code>, puis passez <code>MAIL_ACTIF</code> à <code>true</code> dans <code>controller/MailC.php</code>.
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <div class="stat-card">
        <div class="stat-icon bleu"><i class="fas fa-envelope"></i></div>
        <div><div class="stat-value"><?= count($notifications) ?></div><div class="stat-label">Notifications affichées</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon vert"><i class="fas fa-paper-plane"></i></div>
        <div><div class="stat-value"><?= (int)$envoyees ?></div><div class="stat-label">Envoyées réellement</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-hourglass-half"></i></div>
        <div><div class="stat-value"><?= (int)($parType['demande'] ?? 0) ?></div><div class="stat-label">Nouvelles demandes</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon violet"><i class="fas fa-arrows-up-down-left-right"></i></div>
        <div><div class="stat-value"><?= (int)($parType['deplacement'] ?? 0) ?></div><div class="stat-label">Déplacements</div></div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" class="filtres-grid">
            <div class="form-group">
                <label for="type">Type de notification</label>
                <select id="type" name="type">
                    <option value="tous">Tous les types</option>
                    <?php foreach ($libellesType as $cle => $lib): ?>
                        <option value="<?= e($cle) ?>" <?= $type === $cle ? 'selected' : '' ?>><?= e($lib) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="limite">Nombre de lignes</label>
                <select id="limite" name="limite">
                    <?php foreach ([50, 100, 200, 500] as $l): ?>
                        <option value="<?= $l ?>" <?= $limite === $l ? 'selected' : '' ?>><?= $l ?> dernières</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtres-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
                <a href="notifications.php" class="btn btn-light"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Historique</h3>
        <span class="text-muted" style="font-size:.86rem;"><?= count($notifications) ?> notification(s)</span>
    </div>

    <?php if (!$notifications): ?>
        <div class="vide">
            <i class="fas fa-envelope-open"></i>
            <h3>Aucune notification</h3>
            <p>Les emails apparaîtront ici dès la première demande de réservation.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Date</th><th>Type</th><th>Destinataire</th><th>Objet</th>
                           <th>Réservation</th><th>Envoi</th></tr></thead>
                <tbody>
                <?php foreach ($notifications as $n): ?>
                    <tr>
                        <td class="mono"><?= e(fmt_datetime($n['date_envoi'])) ?></td>
                        <td>
                            <?php
                            $classesType = [
                                'demande'     => 'badge-warning',
                                'validation'  => 'badge-success',
                                'refus'       => 'badge-danger',
                                'annulation'  => 'badge-muted',
                                'deplacement' => 'badge-violet',
                                'rappel'      => 'badge-info',
                            ];
                            ?>
                            <span class="badge <?= $classesType[$n['type']] ?? 'badge-muted' ?>">
                                <?= e($libellesType[$n['type']] ?? $n['type']) ?>
                            </span>
                        </td>
                        <td><?= e($n['destinataire']) ?></td>
                        <td>
                            <div class="cell-titre"><?= e($n['sujet']) ?></div>
                        </td>
                        <td>
                            <?php if (!empty($n['id_reservation'])): ?>
                                <a href="updateReservation.php?id=<?= (int)$n['id_reservation'] ?>">
                                    #<?= (int)$n['id_reservation'] ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$n['envoye'] === 1): ?>
                                <span class="badge badge-success"><i class="fas fa-check"></i> Envoyé</span>
                            <?php else: ?>
                                <span class="badge badge-muted"><i class="fas fa-clock"></i> Journalisé</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
