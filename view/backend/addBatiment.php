<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$batimentC = new BatimentC();
$erreurs   = [];
$donnees   = ['nom' => '', 'code_batiment' => '', 'adresse' => '', 'ville' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valide()) {
        $erreurs[] = 'Session expirée, merci de renvoyer le formulaire.';
    } else {
        foreach ($donnees as $cle => $_) {
            $donnees[$cle] = trim($_POST[$cle] ?? '');
        }

        // ---- Contrôles de saisie côté serveur ----
        if (!v_requis($donnees['nom']))               $erreurs[] = 'Le nom du bâtiment est obligatoire.';
        elseif (!v_longueur($donnees['nom'], 3, 100)) $erreurs[] = 'Le nom doit contenir entre 3 et 100 caractères.';

        if (!v_requis($donnees['code_batiment'])) {
            $erreurs[] = 'Le code du bâtiment est obligatoire.';
        } elseif (!preg_match('/^[A-Za-z0-9\-_]{2,20}$/', $donnees['code_batiment'])) {
            $erreurs[] = 'Le code accepte lettres, chiffres et tirets (2 à 20 caractères).';
        } elseif ($batimentC->codeExiste($donnees['code_batiment'])) {
            $erreurs[] = 'Ce code de bâtiment est déjà utilisé.';
        }

        if (!v_requis($donnees['adresse']))               $erreurs[] = "L'adresse est obligatoire.";
        elseif (!v_longueur($donnees['adresse'], 5, 200)) $erreurs[] = "L'adresse doit contenir entre 5 et 200 caractères.";

        if (!v_requis($donnees['ville']))               $erreurs[] = 'La ville est obligatoire.';
        elseif (!v_longueur($donnees['ville'], 2, 80))  $erreurs[] = 'La ville doit contenir entre 2 et 80 caractères.';

        if ($donnees['description'] !== '' && mb_strlen($donnees['description']) > 1000) {
            $erreurs[] = 'La description ne doit pas dépasser 1000 caractères.';
        }

        // ---- Photo (facultative), traitée seulement si le reste est valide ----
        $photoNom = null;
        if (!$erreurs) {
            try {
                $photoNom = photo_batiment_enregistrer($_FILES['photo'] ?? [], $donnees['code_batiment']);
            } catch (RuntimeException $e) {
                $erreurs[] = $e->getMessage();
            }
        }

        if (!$erreurs) {
            try {
                $b = new Batiment();
                $b->setNom($donnees['nom']);
                $b->setCodeBatiment($donnees['code_batiment']);
                $b->setAdresse($donnees['adresse']);
                $b->setVille($donnees['ville']);
                $b->setDescription($donnees['description'] !== '' ? $donnees['description'] : null);
                $b->setImage($photoNom);

                $id = $batimentC->addBatiment($b);
                flash_set('success', 'Bâtiment « ' . $donnees['nom'] . ' » créé. Ajoutez-lui maintenant ses étages.');
                redirect('addEtage.php?batiment=' . $id);
            } catch (Throwable $e) {
                $erreurs[] = 'Erreur lors de la création : ' . $e->getMessage();
            }
        }
    }
}

$titrePage  = 'Nouveau bâtiment';
$pageActive = 'batiments';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / <a href="listBatiment.php">Bâtiments</a> / Nouveau</div>
        <h2><i class="fas fa-building-circle-check"></i> Nouveau bâtiment</h2>
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
    <div class="card-header"><h3><i class="fas fa-pen-to-square"></i> Informations du bâtiment</h3></div>
    <div class="card-body">
        <form method="post" id="formBatiment" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="nom">Nom du bâtiment <span class="req">*</span></label>
                    <input type="text" id="nom" name="nom" value="<?= e($donnees['nom']) ?>" placeholder="Siège Social">
                    <span class="erreur-champ" id="err-nom"></span>
                </div>

                <div class="form-group">
                    <label for="code_batiment">Code <span class="req">*</span></label>
                    <input type="text" id="code_batiment" name="code_batiment"
                           value="<?= e($donnees['code_batiment']) ?>" placeholder="BAT-A">
                    <span class="aide">Identifiant court et unique, ex. BAT-A.</span>
                    <span class="erreur-champ" id="err-code_batiment"></span>
                </div>

                <div class="form-group">
                    <label for="adresse">Adresse <span class="req">*</span></label>
                    <input type="text" id="adresse" name="adresse" value="<?= e($donnees['adresse']) ?>"
                           placeholder="Rue du Lac Léman, Les Berges du Lac">
                    <span class="erreur-champ" id="err-adresse"></span>
                </div>

                <div class="form-group">
                    <label for="ville">Ville <span class="req">*</span></label>
                    <input type="text" id="ville" name="ville" value="<?= e($donnees['ville']) ?>" placeholder="Tunis">
                    <span class="erreur-champ" id="err-ville"></span>
                </div>

                <div class="form-group full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"
                              placeholder="Rôle du bâtiment, services hébergés…"><?= e($donnees['description']) ?></textarea>
                    <span class="aide">Facultatif — 1000 caractères maximum.</span>
                    <span class="erreur-champ" id="err-description"></span>
                </div>

                <div class="form-group full">
                    <label for="photo">Photo du bâtiment</label>
                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                    <span class="aide">Facultative — JPG, PNG ou WebP, 3 Mo maximum.</span>
                    <span class="erreur-champ" id="err-photo"></span>
                    <img id="apercuPhoto" alt="" style="display:none;max-width:280px;border-radius:8px;margin-top:8px;border:1px solid var(--gris-200);">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Créer le bâtiment</button>
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
        { test: v => Valider.texte(v, 3, 100), message: 'Entre 3 et 100 caractères, pas uniquement des chiffres.' }
    ],
    code_batiment: [
        { test: v => Valider.requis(v), message: 'Le code est obligatoire.' },
        { test: v => Valider.code(v),   message: 'Lettres, chiffres et tirets uniquement (2 à 20 caractères).' }
    ],
    adresse: [
        { test: v => Valider.requis(v),          message: "L'adresse est obligatoire." },
        { test: v => Valider.longueur(v, 5, 200),message: 'Entre 5 et 200 caractères.' }
    ],
    ville: [
        { test: v => Valider.requis(v),        message: 'La ville est obligatoire.' },
        { test: v => Valider.texte(v, 2, 80),  message: 'Entre 2 et 80 caractères, pas uniquement des chiffres.' }
    ],
    description: [
        { test: v => v.trim().length <= 1000, message: '1000 caractères maximum.' }
    ]
});

(function () {
    const champ = document.getElementById('photo'), apercu = document.getElementById('apercuPhoto');
    if (!champ || !apercu) return;
    champ.addEventListener('change', () => {
        const f = champ.files && champ.files[0];
        if (!f) { apercu.style.display = 'none'; apercu.removeAttribute('src'); return; }
        apercu.src = URL.createObjectURL(f); apercu.style.display = 'block';
    });
})();
</script>
