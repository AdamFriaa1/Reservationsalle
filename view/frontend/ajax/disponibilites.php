<?php
/**
 * Point d'entrée AJAX : renvoie les créneaux d'une salle pour une journée.
 * Utilisé par le calendrier interactif (assets/js/calendrier.js).
 *
 * GET  salle = id de la salle
 *      date  = journée au format Y-m-d
 */
require_once dirname(__DIR__, 3) . '/init.php';

header('Content-Type: application/json; charset=utf-8');

$idSalle = $_GET['salle'] ?? '';
$date    = $_GET['date'] ?? '';
// Réservation à ignorer (déplacement : elle ne doit pas se bloquer elle-même)
$exclure = (isset($_GET['exclure']) && ctype_digit((string)$_GET['exclure']))
    ? (int)$_GET['exclure'] : null;

// ---- Contrôles de saisie ----
if (!ctype_digit((string)$idSalle)) {
    echo json_encode(['erreur' => 'Identifiant de salle invalide.']);
    exit;
}
$d = DateTime::createFromFormat('Y-m-d', $date);
if ($d === false || $d->format('Y-m-d') !== $date) {
    echo json_encode(['erreur' => 'Date invalide.']);
    exit;
}

try {
    $salleC       = new SalleC();
    $reservationC = new ReservationC();

    $salle = $salleC->getSalle((int)$idSalle);
    if ($salle === null) {
        echo json_encode(['erreur' => 'Salle introuvable.']);
        exit;
    }
    if ($salle['etat'] !== 'disponible') {
        echo json_encode([
            'erreur' => 'Cette salle est actuellement ' . libelle_etat_salle($salle['etat']) . '.'
        ]);
        exit;
    }

    $creneaux = $reservationC->getCreneauxJour($salle, $date, $exclure);

    // On n'expose que ce dont le client a besoin
    $sortie = array_map(static function (array $c): array {
        return [
            'debut'  => $c['debut'],
            'fin'    => $c['fin'],
            'occupe' => $c['occupe'],
            'passe'  => $c['passe'],
            'titre'  => $c['occupe'] ? $c['reservation']['titre'] : null,
        ];
    }, $creneaux);

    echo json_encode([
        'salle'    => [
            'id'       => (int)$salle['id_salle'],
            'nom'      => $salle['nom'],
            'capacite' => (int)$salle['capacite'],
            'horaires' => substr($salle['heure_ouverture'], 0, 5) . '–' . substr($salle['heure_fermeture'], 0, 5),
        ],
        'date'     => $date,
        'creneaux' => $sortie,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['erreur' => 'Erreur serveur : ' . $e->getMessage()]);
}
