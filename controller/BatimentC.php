<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/model/Batiment.php';

/**
 * Contrôleur Bâtiment : CRUD + statistiques par bâtiment (jointures).
 */
class BatimentC
{
    // =================================================================
    //  CREATE
    // =================================================================
    public function addBatiment(Batiment $b): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('INSERT INTO batiment (nom, code_batiment, adresse, ville, description, image)
                             VALUES (:nom, :code, :adresse, :ville, :desc, :image)');
        $req->execute([
            'nom'     => $b->getNom(),
            'code'    => $b->getCodeBatiment(),
            'adresse' => $b->getAdresse(),
            'ville'   => $b->getVille(),
            'desc'    => $b->getDescription(),
            'image'   => $b->getImage(),
        ]);
        return (int)$db->lastInsertId();
    }

    // =================================================================
    //  READ
    // =================================================================
    public function showBatiments(): array
    {
        $db = config::getConnexion();
        return $db->query('SELECT * FROM batiment ORDER BY nom')->fetchAll();
    }

    public function getBatiment(int $id): ?array
    {
        $db  = config::getConnexion();
        $req = $db->prepare('SELECT * FROM batiment WHERE id_batiment = :id');
        $req->execute(['id' => $id]);
        $res = $req->fetch();
        return $res ?: null;
    }

    /**
     * JOINTURE batiment -> etage -> salle : compte les étages et les salles.
     */
    public function showBatimentsAvecDetails(): array
    {
        $db = config::getConnexion();
        $sql = 'SELECT b.*,
                       COUNT(DISTINCT e.id_etage) AS nb_etages,
                       COUNT(DISTINCT s.id_salle) AS nb_salles,
                       COALESCE(SUM(DISTINCT 0) + SUM(s.capacite), 0) AS capacite_totale
                FROM batiment b
                LEFT JOIN etage e ON b.id_batiment = e.id_batiment
                LEFT JOIN salle s ON e.id_etage = s.id_etage
                GROUP BY b.id_batiment
                ORDER BY b.nom';
        return $db->query($sql)->fetchAll();
    }

    /** Recherche et tri multicritères sur les bâtiments. */
    public function filterBatiments(string $recherche = '', string $ville = 'toutes',
                                    string $orderBy = 'nom', string $orderDir = 'ASC'): array
    {
        $db = config::getConnexion();

        $sql = 'SELECT b.*,
                       COUNT(DISTINCT e.id_etage) AS nb_etages,
                       COUNT(DISTINCT s.id_salle) AS nb_salles,
                       COALESCE(SUM(s.capacite), 0) AS capacite_totale
                FROM batiment b
                LEFT JOIN etage e ON b.id_batiment = e.id_batiment
                LEFT JOIN salle s ON e.id_etage = s.id_etage';

        $conditions = [];
        $params     = [];

        if ($recherche !== '') {
            // Placeholders distincts (émulation PDO désactivée : pas de réutilisation).
            $conditions[] = '(b.nom LIKE :q1 OR b.code_batiment LIKE :q2 OR b.adresse LIKE :q3 OR b.ville LIKE :q4)';
            $motif = '%' . $recherche . '%';
            foreach (['q1','q2','q3','q4'] as $ph) {
                $params[$ph] = $motif;
            }
        }
        if ($ville !== 'toutes') {
            $conditions[]    = 'b.ville = :ville';
            $params['ville'] = $ville;
        }
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' GROUP BY b.id_batiment';

        $colonnes = [
            'nom'      => 'b.nom',
            'ville'    => 'b.ville',
            'salles'   => 'nb_salles',
            'capacite' => 'capacite_totale',
            'date'     => 'b.date_creation',
        ];
        $col = $colonnes[$orderBy] ?? 'b.nom';
        $dir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY $col $dir";

        $req = $db->prepare($sql);
        $req->execute($params);
        return $req->fetchAll();
    }

    public function getVilles(): array
    {
        $db = config::getConnexion();
        return $db->query('SELECT DISTINCT ville FROM batiment ORDER BY ville')->fetchAll(PDO::FETCH_COLUMN);
    }

    // =================================================================
    //  UPDATE
    // =================================================================
    public function updateBatiment(Batiment $b, int $id): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('UPDATE batiment SET
                                nom = :nom, code_batiment = :code, adresse = :adresse,
                                ville = :ville, description = :desc, image = :image
                             WHERE id_batiment = :id');
        $req->execute([
            'id'      => $id,
            'nom'     => $b->getNom(),
            'code'    => $b->getCodeBatiment(),
            'adresse' => $b->getAdresse(),
            'ville'   => $b->getVille(),
            'desc'    => $b->getDescription(),
            'image'   => $b->getImage(),
        ]);
        return $req->rowCount();
    }

    // =================================================================
    //  DELETE
    // =================================================================
    public function deleteBatiment(int $id): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('DELETE FROM batiment WHERE id_batiment = :id');
        $req->execute(['id' => $id]);
        return $req->rowCount();
    }

    /** Unicité du code bâtiment. */
    public function codeExiste(string $code, ?int $exclureId = null): bool
    {
        $db  = config::getConnexion();
        $sql = 'SELECT COUNT(*) FROM batiment WHERE code_batiment = :code';
        $params = ['code' => strtoupper(trim($code))];
        if ($exclureId !== null) {
            $sql .= ' AND id_batiment <> :id';
            $params['id'] = $exclureId;
        }
        $req = $db->prepare($sql);
        $req->execute($params);
        return (int)$req->fetchColumn() > 0;
    }

    /** Nombre de salles rattachées : sert d'avertissement avant suppression. */
    public function compterSalles(int $idBatiment): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('SELECT COUNT(s.id_salle)
                             FROM salle s
                             JOIN etage e ON s.id_etage = e.id_etage
                             WHERE e.id_batiment = :id');
        $req->execute(['id' => $idBatiment]);
        return (int)$req->fetchColumn();
    }
}
