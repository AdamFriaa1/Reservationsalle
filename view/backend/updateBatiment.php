<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$batimentC = new BatimentC();

$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($id === 0) { flash_set('error', 'Bâtiment introuvable.'); redirect('listBatiment.php'); }

$existant = $batimentC->getBatiment($id);
if ($existant === null) { flash_set('error', 'Bâtiment introuvable.'); redirect('listBatiment.php'); }

$erreurs = [];
$donnees = [
    'nom'           => $existant['nom'],
    'code_batiment' => $existant['code_batiment'],
    'adresse'       => $existant['adresse'],
    'ville'         => $existant['ville'],
    'description'   => (string)$existant['description'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valide()) {
        $erreurs[] = 'Session expirée, merci de renvoyer le formulaire.';
    } else {
        foreach ($donnees as $cle => $_) {
            $donnees[$cle] = trim($_POST[$cle] ?? '');
        }

        if (!v_requis($donnees['nom']))               $erreurs[] = 'Le nom est obligatoire.';
        elseif (!v_longueur($donnees['nom'], 3, 100)) $erreurs[] = 'Le nom doit contenir entre 3 et 100 caractères.';

        if (!v_requis($donnees['code_batiment'])) {
            $erreurs[] = 'Le code est obligatoire.';
        } elseif (!preg_match('/^[A-Za-z0-9\-_]{2,20}$/', $donnees['code_batiment'])) {
            $erreurs[] = 'Le code accepte lettres, chiffres et tirets (2 à 20 caractères).';
        } elseif ($batimentC->codeExiste($donnees['code_batiment'], $id)) {
            $erreurs[] = 'Ce code est déjà utilisé par un autre bâtiment.';
        }

        if (!v_requis($donnees['adresse']))               $erreurs[] = "L'adresse est obligatoire.";
        elseif (!v_longueur($donnees['adresse'], 5, 200)) $erreurs[] = "L'adresse doit contenir entre 5 et 200 caractères.";

        if (!v_requis($donnees['ville']))              $erreurs[] = 'La ville est obligatoire.';
        elseif (!v_longueur($donnees['ville'], 2, 80)) $erreurs[] = 'La ville doit contenir entre 2 et 80 caractères.';

        if (mb_strlen($donnees['description']) > 1000) {
            $erreurs[] = 'La description ne doit pas dépasser 1000 caractères.';
        }

        if (!$erreurs) {
            try {
                $b = new Batiment();
                $b->setNom($donnees['nom']);
                $b->setCodeBatiment($donnees['code_batiment']);
                $b->setAdresse($donnees['adresse']);
                $b->setVille($donnees['ville']);
                $b->setDescription($donnees['description'] !== '' ? $donnees['description'] : null);
                $b->setImage($existant['image']);

                $batimentC->updateBatiment($b, $id);
                flash_set('success', 'Bâtiment « ' . $donnees['nom'] . ' » mis à jour.');
                redirect('listBatiment.php');
            } catch (Throwable $e) {
                $erreurs[] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
            }
        }
    }
}

$nbSalles = $batimentC->compterSalles($id);

$titrePage  = 'Modifier le bâtiment';
$pageActive = 'batiments';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / <a href="listBatiment.php">Bâtiments</a> / Modifier</div>
        <h2><i class="fas fa-pen-to-square"></i> Modifier « <?= e($existant['nom']) ?> »</h2>
        <p>Créé le <?= e(fmt_date($existant['date_creation'])) ?> · <?= $nbSalles ?> salle(s) rattachée(s)</p>
    </div>
    <a href="listBatiment.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Retour</a>
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
    <div class="card-header"><h3><i class="fas fa-building"></i> Informations</h3></div>
    <div class="card-body">
        <form method="post" id="formBatiment" novalidate>
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="nom">Nom du bâtiment <span class="req">*</span></label>
                    <input type="text" id="nom" name="nom" value="<?= e($donnees['nom']) ?>">
                    <span class="erreur-champ" id="err-nom"></span>
                </div>
                <div class="form-group">
                    <label for="code_batiment">Code <span class="req">*</span></label>
                    <input type="text" id="code_batiment" name="code_batiment" value="<?= e($donnees['code_batiment']) ?>">
                    <span class="erreur-champ" id="err-code_batiment"></span>
                </div>
                <div class="form-group">
                    <label for="adresse">Adresse <span class="req">*</span></label>
                    <input type="text" id="adresse" name="adresse" value="<?= e($donnees['adresse']) ?>">
                    <span class="erreur-champ" id="err-adresse"></span>
                </div>
                <div class="form-group">
                    <label for="ville">Ville <span class="req">*</span></label>
                    <input type="text" id="ville" name="ville" value="<?= e($donnees['ville']) ?>">
                    <span class="erreur-champ" id="err-ville"></span>
                </div>
                <div class="form-group full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?= e($donnees['description']) ?></textarea>
                    <span class="erreur-champ" id="err-description"></span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Enregistrer</button>
                <a href="listEtage.php?batiment=<?= (int)$id ?>" class="btn btn-light">
                    <i class="fas fa-layer-group"></i> Gérer les étages
                </a>
                <a href="listBatiment.php" class="btn btn-light">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
Valider.attacher('formBatiment', {
    nom: [
        { test: v => Valider.requis(v),        message: 'Le nom est obligatoire.' },
        { test: v => Valider.texte(v, 3, 100), message: 'Entre 3 et 100 caractères.' }
    ],
    code_batiment: [
        { test: v => Valider.requis(v), message: 'Le code est obligatoire.' },
        { test: v => Valider.code(v),   message: 'Lettres, chiffres et tirets (2 à 20 caractères).' }
    ],
    adresse: [
        { test: v => Valider.requis(v),           message: "L'adresse est obligatoire." },
        { test: v => Valider.longueur(v, 5, 200), message: 'Entre 5 et 200 caractères.' }
    ],
    ville: [
        { test: v => Valider.requis(v),       message: 'La ville est obligatoire.' },
        { test: v => Valider.texte(v, 2, 80), message: 'Entre 2 et 80 caractères.' }
    ],
    description: [{ test: v => v.trim().length <= 1000, message: '1000 caractères maximum.' }]
});
</script>
