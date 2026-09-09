<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$etageC    = new EtageC();
$batimentC = new BatimentC();

$erreurs = [];
$donnees = [
    'id_batiment'    => $_GET['batiment'] ?? '',
    'numero_etage'   => '',
    'nom_etage'      => '',
    'accessible_pmr' => 0,
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

        // ---- Contrôles de saisie côté serveur ----
        if (!ctype_digit((string)$donnees['id_batiment']) || (int)$donnees['id_batiment'] < 1) {
            $erreurs[] = 'Merci de choisir un bâtiment.';
        }
        if (!v_requis($donnees['numero_etage'])) {
            $erreurs[] = "Le numéro d'étage est obligatoire.";
        } elseif (!v_entier($donnees['numero_etage'], -5, 100)) {
            $erreurs[] = "Le numéro d'étage doit être un entier entre -5 et 100.";
        }
        if (!v_requis($donnees['nom_etage'])) {
            $erreurs[] = "Le nom de l'étage est obligatoire.";
        } elseif (!v_longueur($donnees['nom_etage'], 2, 80)) {
            $erreurs[] = 'Le nom doit contenir entre 2 et 80 caractères.';
        }

        // Unicité du niveau dans le bâtiment
        if (!$erreurs && $etageC->numeroExiste((int)$donnees['id_batiment'], (int)$donnees['numero_etage'])) {
            $erreurs[] = 'Ce numéro d\'étage existe déjà dans ce bâtiment.';
        }

        if (!$erreurs) {
            try {
                $et = new Etage();
                $et->setIdBatiment((int)$donnees['id_batiment']);
                $et->setNumeroEtage((int)$donnees['numero_etage']);
                $et->setNomEtage($donnees['nom_etage']);
                $et->setAccessiblePmr($donnees['accessible_pmr']);

                $id = $etageC->addEtage($et);
                flash_set('success', 'Étage « ' . $donnees['nom_etage'] . ' » créé. Ajoutez-lui maintenant ses salles.');
                redirect('addSalle.php?etage=' . $id);
            } catch (Throwable $e) {
                $erreurs[] = 'Erreur lors de la création : ' . $e->getMessage();
            }
        }
    }
}

$titrePage  = 'Nouvel étage';
$pageActive = 'etages';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / <a href="listEtage.php">Étages</a> / Nouveau</div>
        <h2><i class="fas fa-layer-group"></i> Nouvel étage</h2>
    </div>
    <a href="listEtage.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Retour</a>
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

<?php if (!$batiments): ?>
    <div class="alert alert-warning">
        <i class="fas fa-triangle-exclamation"></i>
        <span>Aucun bâtiment disponible. <a href="addBatiment.php">Créez d'abord un bâtiment</a>.</span>
    </div>
<?php else: ?>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-pen-to-square"></i> Informations de l'étage</h3></div>
    <div class="card-body">
        <form method="post" id="formEtage" novalidate>
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="id_batiment">Bâtiment <span class="req">*</span></label>
                    <select id="id_batiment" name="id_batiment">
                        <option value="">— Choisir un bâtiment —</option>
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
                           value="<?= e($donnees['numero_etage']) ?>" placeholder="0">
                    <span class="aide">0 pour le rez-de-chaussée, valeurs négatives pour les sous-sols.</span>
                    <span class="erreur-champ" id="err-numero_etage"></span>
                </div>

                <div class="form-group full">
                    <label for="nom_etage">Nom de l'étage <span class="req">*</span></label>
                    <input type="text" id="nom_etage" name="nom_etage" value="<?= e($donnees['nom_etage']) ?>"
                           placeholder="1er étage - Direction">
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
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Créer l'étage</button>
                <a href="listEtage.php" class="btn btn-light">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
Valider.attacher('formEtage', {
    id_batiment: [
        { test: v => Valider.requis(v), message: 'Merci de choisir un bâtiment.' }
    ],
    numero_etage: [
        { test: v => Valider.requis(v),         message: 'Le numéro de niveau est obligatoire.' },
        { test: v => Valider.entier(v, -5, 100),message: 'Entier compris entre -5 et 100.' }
    ],
    nom_etage: [
        { test: v => Valider.requis(v),        message: "Le nom de l'étage est obligatoire." },
        { test: v => Valider.longueur(v, 2, 80), message: 'Entre 2 et 80 caractères.' }
    ]
});
</script>
