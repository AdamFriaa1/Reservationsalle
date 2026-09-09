<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$salleC = new SalleC();
$etageC = new EtageC();

/** Équipements proposés sous forme de cases à cocher. */
const EQUIPEMENTS_PROPOSES = [
    'Vidéoprojecteur', 'Écran TV', 'Tableau blanc', 'Paperboard',
    'Visioconférence', 'Sonorisation', 'Wifi', 'Climatisation',
    'Prises multiples', 'Écran tactile',
];

$erreurs = [];
$donnees = [
    'id_etage'         => $_GET['etage'] ?? '',
    'code_salle'       => '',
    'nom'              => '',
    'capacite'         => '',
    'type_salle'       => 'reunion',
    'equipements'      => [],
    'equipements_sup'  => '',
    'localisation'     => '',
    'etat'             => 'disponible',
    'heure_ouverture'  => '08:00',
    'heure_fermeture'  => '19:00',
    'delai_annulation' => 24,
];

try { $etages = $etageC->getEtagesPourSelect(); } catch (Throwable $e) { $etages = []; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valide()) {
        $erreurs[] = 'Session expirée, merci de renvoyer le formulaire.';
    } else {
        $donnees['id_etage']         = $_POST['id_etage'] ?? '';
        $donnees['code_salle']       = trim($_POST['code_salle'] ?? '');
        $donnees['nom']              = trim($_POST['nom'] ?? '');
        $donnees['capacite']         = trim($_POST['capacite'] ?? '');
        $donnees['type_salle']       = $_POST['type_salle'] ?? 'reunion';
        $donnees['equipements']      = $_POST['equipements'] ?? [];
        $donnees['equipements_sup']  = trim($_POST['equipements_sup'] ?? '');
        $donnees['localisation']     = trim($_POST['localisation'] ?? '');
        $donnees['etat']             = $_POST['etat'] ?? 'disponible';
        $donnees['heure_ouverture']  = trim($_POST['heure_ouverture'] ?? '');
        $donnees['heure_fermeture']  = trim($_POST['heure_fermeture'] ?? '');
        $donnees['delai_annulation'] = trim($_POST['delai_annulation'] ?? '');

        // ---- Contrôles de saisie côté serveur ----
        if (!ctype_digit((string)$donnees['id_etage']) || (int)$donnees['id_etage'] < 1) {
            $erreurs[] = 'Merci de choisir un étage.';
        }
        if (!v_requis($donnees['code_salle'])) {
            $erreurs[] = 'Le code de la salle est obligatoire.';
        } elseif (!preg_match('/^[A-Za-z0-9\-_]{2,20}$/', $donnees['code_salle'])) {
            $erreurs[] = 'Le code accepte lettres, chiffres et tirets (2 à 20 caractères).';
        } elseif ($salleC->codeExiste($donnees['code_salle'])) {
            $erreurs[] = 'Ce code de salle est déjà utilisé.';
        }
        if (!v_requis($donnees['nom']))               $erreurs[] = 'Le nom de la salle est obligatoire.';
        elseif (!v_longueur($donnees['nom'], 2, 100)) $erreurs[] = 'Le nom doit contenir entre 2 et 100 caractères.';

        if (!v_entier($donnees['capacite'], 1, 1000)) {
            $erreurs[] = 'La capacité doit être un entier entre 1 et 1000.';
        }
        if (!v_dans(Salle::TYPES, $donnees['type_salle'])) $erreurs[] = 'Type de salle invalide.';
        if (!v_dans(Salle::ETATS, $donnees['etat']))       $erreurs[] = 'État de salle invalide.';

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $donnees['heure_ouverture'])) {
            $erreurs[] = "L'heure d'ouverture est invalide (format HH:MM).";
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $donnees['heure_fermeture'])) {
            $erreurs[] = "L'heure de fermeture est invalide (format HH:MM).";
        }
        if (!$erreurs && $donnees['heure_fermeture'] <= $donnees['heure_ouverture']) {
            $erreurs[] = "L'heure de fermeture doit être postérieure à l'heure d'ouverture.";
        }
        if (!v_entier($donnees['delai_annulation'], 0, 720)) {
            $erreurs[] = "Le délai d'annulation doit être un entier entre 0 et 720 heures.";
        }
        if ($donnees['localisation'] !== '' && !v_longueur($donnees['localisation'], 0, 150)) {
            $erreurs[] = 'La localisation ne doit pas dépasser 150 caractères.';
        }

        if (!$erreurs) {
            try {
                // Fusion des cases cochées et des équipements libres
                $equip = array_filter(array_map('trim', (array)$donnees['equipements']));
                if ($donnees['equipements_sup'] !== '') {
                    foreach (explode(',', $donnees['equipements_sup']) as $sup) {
                        $sup = trim($sup);
                        if ($sup !== '') $equip[] = $sup;
                    }
                }
                $equipStr = $equip ? implode(', ', array_unique($equip)) : null;

                $s = new Salle();
                $s->setIdEtage((int)$donnees['id_etage']);
                $s->setCodeSalle($donnees['code_salle']);
                $s->setNom($donnees['nom']);
                $s->setCapacite((int)$donnees['capacite']);
                $s->setTypeSalle($donnees['type_salle']);
                $s->setEquipements($equipStr);
                $s->setLocalisation($donnees['localisation'] !== '' ? $donnees['localisation'] : null);
                $s->setEtat($donnees['etat']);
                $s->setHeureOuverture($donnees['heure_ouverture'] . ':00');
                $s->setHeureFermeture($donnees['heure_fermeture'] . ':00');
                $s->setDelaiAnnulation((int)$donnees['delai_annulation']);

                $salleC->addSalle($s);
                flash_set('success', 'Salle « ' . $donnees['nom'] . ' » créée.');
                redirect('listSalle.php');
            } catch (Throwable $e) {
                $erreurs[] = 'Erreur lors de la création : ' . $e->getMessage();
            }
        }
    }
}

$titrePage  = 'Nouvelle salle';
$pageActive = 'salles';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / <a href="listSalle.php">Salles</a> / Nouvelle</div>
        <h2><i class="fas fa-door-open"></i> Nouvelle salle</h2>
    </div>
    <a href="listSalle.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<?php flash_afficher(); ?>

<?php if ($erreurs): ?>
    <div class="alert alert-danger">
        <i class="fas fa-circle-exclamation"></i>
        <div>
            <strong>Merci de corriger :</strong>
            <ul><?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
<?php endif; ?>

<?php if (!$etages): ?>
    <div class="alert alert-warning">
        <i class="fas fa-triangle-exclamation"></i>
        <span>Aucun étage disponible. <a href="addEtage.php">Créez d'abord un étage</a>.</span>
    </div>
<?php else: ?>

<form method="post" id="formSalle" novalidate>
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header"><h3><i class="fas fa-circle-info"></i> Identification</h3></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label for="id_etage">Étage <span class="req">*</span></label>
                    <select id="id_etage" name="id_etage">
                        <option value="">— Choisir un étage —</option>
                        <?php foreach ($etages as $et): ?>
                            <option value="<?= (int)$et['id_etage'] ?>"
                                <?= (string)$donnees['id_etage'] === (string)$et['id_etage'] ? 'selected' : '' ?>>
                                <?= e($et['nom_batiment']) ?> — <?= e($et['nom_etage']) ?> (niveau <?= (int)$et['numero_etage'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="erreur-champ" id="err-id_etage"></span>
                </div>

                <div class="form-group">
                    <label for="code_salle">Code de la salle <span class="req">*</span></label>
                    <input type="text" id="code_salle" name="code_salle" value="<?= e($donnees['code_salle']) ?>"
                           placeholder="A-RDC-01">
                    <span class="aide">Identifiant unique, ex. A-RDC-01.</span>
                    <span class="erreur-champ" id="err-code_salle"></span>
                </div>

                <div class="form-group">
                    <label for="nom">Nom de la salle <span class="req">*</span></label>
                    <input type="text" id="nom" name="nom" value="<?= e($donnees['nom']) ?>" placeholder="Salle Carthage">
                    <span class="erreur-champ" id="err-nom"></span>
                </div>

                <div class="form-group">
                    <label for="capacite">Capacité <span class="req">*</span></label>
                    <input type="number" id="capacite" name="capacite" min="1" max="1000"
                           value="<?= e($donnees['capacite']) ?>" placeholder="12">
                    <span class="aide">Nombre de personnes assises.</span>
                    <span class="erreur-champ" id="err-capacite"></span>
                </div>

                <div class="form-group">
                    <label for="type_salle">Type de salle <span class="req">*</span></label>
                    <select id="type_salle" name="type_salle">
                        <?php foreach (Salle::TYPES as $t): ?>
                            <option value="<?= e($t) ?>" <?= $donnees['type_salle'] === $t ? 'selected' : '' ?>>
                                <?= e(libelle_type_salle($t)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="localisation">Localisation précise</label>
                    <input type="text" id="localisation" name="localisation" value="<?= e($donnees['localisation']) ?>"
                           placeholder="Aile Est, face à l'accueil">
                    <span class="erreur-champ" id="err-localisation"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3><i class="fas fa-screwdriver-wrench"></i> Équipements</h3></div>
        <div class="card-body">
            <div class="chips mb-3">
                <?php foreach (EQUIPEMENTS_PROPOSES as $eq): ?>
                    <label class="chip">
                        <input type="checkbox" name="equipements[]" value="<?= e($eq) ?>"
                            <?= in_array($eq, (array)$donnees['equipements'], true) ? 'checked' : '' ?>>
                        <?= e($eq) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="form-group">
                <label for="equipements_sup">Autres équipements</label>
                <input type="text" id="equipements_sup" name="equipements_sup"
                       value="<?= e($donnees['equipements_sup']) ?>"
                       placeholder="Micros, Estrade, Cafetière">
                <span class="aide">Séparez les éléments par une virgule.</span>
                <span class="erreur-champ" id="err-equipements_sup"></span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3><i class="fas fa-clock"></i> Disponibilité et maintenance</h3></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label for="heure_ouverture">Heure d'ouverture <span class="req">*</span></label>
                    <input type="time" id="heure_ouverture" name="heure_ouverture" value="<?= e($donnees['heure_ouverture']) ?>">
                    <span class="erreur-champ" id="err-heure_ouverture"></span>
                </div>
                <div class="form-group">
                    <label for="heure_fermeture">Heure de fermeture <span class="req">*</span></label>
                    <input type="time" id="heure_fermeture" name="heure_fermeture" value="<?= e($donnees['heure_fermeture']) ?>">
                    <span class="erreur-champ" id="err-heure_fermeture"></span>
                </div>
                <div class="form-group">
                    <label for="delai_annulation">Délai d'annulation (heures) <span class="req">*</span></label>
                    <input type="number" id="delai_annulation" name="delai_annulation" min="0" max="720"
                           value="<?= e($donnees['delai_annulation']) ?>">
                    <span class="aide">Au-delà de ce délai, l'utilisateur ne peut plus modifier sa réservation.</span>
                    <span class="erreur-champ" id="err-delai_annulation"></span>
                </div>
                <div class="form-group">
                    <label for="etat">État <span class="req">*</span></label>
                    <select id="etat" name="etat">
                        <?php foreach (Salle::ETATS as $et): ?>
                            <option value="<?= e($et) ?>" <?= $donnees['etat'] === $et ? 'selected' : '' ?>>
                                <?= e(libelle_etat_salle($et)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="aide">Seules les salles « disponibles » sont réservables.</span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Créer la salle</button>
                <a href="listSalle.php" class="btn btn-light">Annuler</a>
            </div>
        </div>
    </div>
</form>

<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
Valider.attacher('formSalle', {
    id_etage: [{ test: v => Valider.requis(v), message: 'Merci de choisir un étage.' }],
    code_salle: [
        { test: v => Valider.requis(v), message: 'Le code est obligatoire.' },
        { test: v => Valider.code(v),   message: 'Lettres, chiffres et tirets (2 à 20 caractères).' }
    ],
    nom: [
        { test: v => Valider.requis(v),        message: 'Le nom est obligatoire.' },
        { test: v => Valider.texte(v, 2, 100), message: 'Entre 2 et 100 caractères, pas uniquement des chiffres.' }
    ],
    capacite: [
        { test: v => Valider.requis(v),          message: 'La capacité est obligatoire.' },
        { test: v => Valider.entier(v, 1, 1000), message: 'Entier entre 1 et 1000.' }
    ],
    localisation: [
        { test: v => v.trim().length <= 150, message: '150 caractères maximum.' }
    ],
    equipements_sup: [
        { test: v => v.trim().length <= 200, message: '200 caractères maximum.' }
    ],
    heure_ouverture: [
        { test: v => Valider.requis(v), message: "L'heure d'ouverture est obligatoire." }
    ],
    heure_fermeture: [
        { test: v => Valider.requis(v), message: "L'heure de fermeture est obligatoire." },
        { test: (v, f) => v > f.querySelector('#heure_ouverture').value,
          message: "La fermeture doit être après l'ouverture." }
    ],
    delai_annulation: [
        { test: v => Valider.requis(v),        message: 'Le délai est obligatoire.' },
        { test: v => Valider.entier(v, 0, 720),message: 'Entier entre 0 et 720 heures.' }
    ]
});
</script>
