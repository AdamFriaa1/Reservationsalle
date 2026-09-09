<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$etageC    = new EtageC();
$batimentC = new BatimentC();

$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($id === 0) { flash_set('error', 'Étage introuvable.'); redirect('listEtage.php'); }

$existant = $etageC->getEtage($id);
if ($existant === null) { flash_set('error', 'Étage introuvable.'); redirect('listEtage.php'); }

$erreurs = [];
$donnees = [
    'id_batiment'    => (int)$existant['id_batiment'],
    'numero_etage'   => (int)$existant['numero_etage'],
    'nom_etage'      => $existant['nom_etage'],
    'accessible_pmr' => (int)$existant['accessible_pmr'],
];

try { $batiments = $batimentC->showBatiments(); } catch (Throwable $e) { $batiments = []; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valide()) {
        $erreurs[] = 'Session expirée, merci de renvoyer le formulaire.';
    } else {
        $donnees['id_batiment']    = $_POST['id_batiment'] ?? '';
        $donnees['numero_etage']   = trim($_POST['numero_etage'] ?? '');
        $donnees['nom_etage']      = trim($_POST['nom_etage'] ?? '');
        $donnees['accessible_pmr'] = isset($_POST['accessible_pmr']) ? 1 : 0;

        if (!ctype_digit((string)$donnees['id_batiment'])) $erreurs[] = 'Merci de choisir un bâtiment.';
        if (!v_entier($donnees['numero_etage'], -5, 100))  $erreurs[] = "Le numéro d'étage doit être un entier entre -5 et 100.";
        if (!v_requis($donnees['nom_etage']))              $erreurs[] = "Le nom de l'étage est obligatoire.";
        elseif (!v_longueur($donnees['nom_etage'], 2, 80)) $erreurs[] = 'Le nom doit contenir entre 2 et 80 caractères.';

        if (!$erreurs && $etageC->numeroExiste((int)$donnees['id_batiment'], (int)$donnees['numero_etage'], $id)) {
            $erreurs[] = 'Ce numéro d\'étage existe déjà dans ce bâtiment.';
        }

        if (!$erreurs) {
            try {
                $et = new Etage();
                $et->setIdBatiment((int)$donnees['id_batiment']);
                $et->setNumeroEtage((int)$donnees['numero_etage']);
                $et->setNomEtage($donnees['nom_etage']);
                $et->setAccessiblePmr($donnees['accessible_pmr']);

                $etageC->updateEtage($et, $id);
                flash_set('success', 'Étage « ' . $donnees['nom_etage'] . ' » mis à jour.');
                redirect('listEtage.php?batiment=' . (int)$donnees['id_batiment']);
            } catch (Throwable $e) {
                $erreurs[] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
            }
        }
    }
}

$nbSalles = $etageC->compterSalles($id);

$titrePage  = 'Modifier l\'étage';
$pageActive = 'etages';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / <a href="listEtage.php">Étages</a> / Modifier</div>
        <h2><i class="fas fa-pen-to-square"></i> Modifier « <?= e($existant['nom_etage']) ?> »</h2>
        <p><?= e($existant['nom_batiment']) ?> · <?= $nbSalles ?> salle(s) rattachée(s)</p>
    </div>
    <a href="listEtage.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<?php if ($erreurs): ?>
    <div class="alert alert-danger">
        <i class="fas fa-circle-exclamation"></i>
        <div>
            <strong>Merci de corriger :</strong>
            <ul><?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-layer-group"></i> Informations</h3></div>
    <div class="card-body">
        <form method="post" id="formEtage" novalidate>
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="id_batiment">Bâtiment <span class="req">*</span></label>
                    <select id="id_batiment" name="id_batiment">
                        <?php foreach ($batiments as $b): ?>
                            <option value="<?= (int)$b['id_batiment'] ?>"
                                <?= (string)$donnees['id_batiment'] === (string)$b['id_batiment'] ? 'selected' : '' ?>>
                                <?= e($b['nom']) ?> (<?= e($b['code_batiment']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="erreur-champ" id="err-id_batiment"></span>
                </div>

                <div class="form-group">
                    <label for="numero_etage">Numéro de niveau <span class="req">*</span></label>
                    <input type="number" id="numero_etage" name="numero_etage" min="-5" max="100"
                           value="<?= e($donnees['numero_etage']) ?>">
                    <span class="erreur-champ" id="err-numero_etage"></span>
                </div>

                <div class="form-group full">
                    <label for="nom_etage">Nom de l'étage <span class="req">*</span></label>
                    <input type="text" id="nom_etage" name="nom_etage" value="<?= e($donnees['nom_etage']) ?>">
                    <span class="erreur-champ" id="err-nom_etage"></span>
                </div>

                <div class="form-group full">
                    <label class="checkbox-line">
                        <input type="checkbox" name="accessible_pmr" value="1" <?= $donnees['accessible_pmr'] ? 'checked' : '' ?>>
                        Étage accessible aux personnes à mobilité réduite
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Enregistrer</button>
                <a href="listSalle.php?etage=<?= (int)$id ?>" class="btn btn-light">
                    <i class="fas fa-door-open"></i> Voir les salles
                </a>
                <a href="listEtage.php" class="btn btn-light">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
Valider.attacher('formEtage', {
    id_batiment:  [{ test: v => Valider.requis(v), message: 'Merci de choisir un bâtiment.' }],
    numero_etage: [
        { test: v => Valider.requis(v),          message: 'Le numéro de niveau est obligatoire.' },
        { test: v => Valider.entier(v, -5, 100), message: 'Entier compris entre -5 et 100.' }
    ],
    nom_etage: [
        { test: v => Valider.requis(v),          message: "Le nom de l'étage est obligatoire." },
        { test: v => Valider.longueur(v, 2, 80), message: 'Entre 2 et 80 caractères.' }
    ]
});
</script>
