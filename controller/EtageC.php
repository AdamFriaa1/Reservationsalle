<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/model/Etage.php';

/**
 * Contrôleur Étage : CRUD + jointure avec bâtiment et salles.
 */
class EtageC
{
    // =================================================================
    //  CREATE
    // =================================================================
    public function addEtage(Etage $e): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('INSERT INTO etage (id_batiment, numero_etage, nom_etage, accessible_pmr)
                             VALUES (:bat, :num, :nom, :pmr)');
        $req->execute([
            'bat' => $e->getIdBatiment(),
            'num' => $e->getNumeroEtage(),
            'nom' => $e->getNomEtage(),
            'pmr' => $e->getAccessiblePmr(),
        ]);
        return (int)$db->lastInsertId();
    }

    // =================================================================
    //  READ
    // =================================================================
    /** JOINTURE etage -> batiment + comptage des salles. */
    public function showEtages(?int $idBatiment = null): array
    {
        $db  = config::getConnexion();
        $sql = 'SELECT e.*, b.nom AS nom_batiment, b.code_batiment, b.ville,
                       COUNT(s.id_salle) AS nb_salles,
                       COALESCE(SUM(s.capacite), 0) AS capacite_totale
                FROM etage e
                JOIN batiment b ON e.id_batiment = b.id_batiment
                LEFT JOIN salle s ON e.id_etage = s.id_etage';
        $params = [];
        if ($idBatiment !== null) {
            $sql .= ' WHERE e.id_batiment = :bat';
            $params['bat'] = $idBatiment;
        }
        $sql .= ' GROUP BY e.id_etage ORDER BY b.nom, e.numero_etage';

        $req = $db->prepare($sql);
        $req->execute($params);
        return $req->fetchAll();
    }

    public function getEtage(int $id): ?array
    {
        $db  = config::getConnexion();
        $req = $db->prepare('SELECT e.*, b.nom AS nom_batiment, b.code_batiment
                             FROM etage e
                             JOIN batiment b ON e.id_batiment = b.id_batiment
                             WHERE e.id_etage = :id');
        $req->execute(['id' => $id]);
        $res = $req->fetch();
        return $res ?: null;
    }

    /** Liste simplifiée pour les listes déroulantes (formulaire Salle). */
    public function getEtagesPourSelect(): array
    {
        $db = config::getConnexion();
        return $db->query('SELECT e.id_etage, e.numero_etage, e.nom_etage,
                                  b.nom AS nom_batiment, b.code_batiment
                           FROM etage e
                           JOIN batiment b ON e.id_batiment = b.id_batiment
                           ORDER BY b.nom, e.numero_etage')->fetchAll();
    }

    public function filterEtages(string $recherche = '', string $batiment = 'tous',
                                 string $pmr = 'tous', string $orderBy = 'batiment',
                                 string $orderDir = 'ASC'): array
    {
        $db = config::getConnexion();

        $sql = 'SELECT e.*, b.nom AS nom_batiment, b.code_batiment, b.ville,
                       COUNT(s.id_salle) AS nb_salles,
                       COALESCE(SUM(s.capacite), 0) AS capacite_totale
                FROM etage e
                JOIN batiment b ON e.id_batiment = b.id_batiment
                LEFT JOIN salle s ON e.id_etage = s.id_etage';

        $conditions = [];
        $params     = [];

        if ($recherche !== '') {
            $conditions[] = '(e.nom_etage LIKE :q OR b.nom LIKE :q OR b.code_batiment LIKE :q)';
            $params['q']  = '%' . $recherche . '%';
        }
        if ($batiment !== 'tous' && ctype_digit((string)$batiment)) {
            $conditions[]  = 'e.id_batiment = :bat';
            $params['bat'] = (int)$batiment;
        }
        if ($pmr === 'oui') {
            $conditions[] = 'e.accessible_pmr = 1';
        } elseif ($pmr === 'non') {
            $conditions[] = 'e.accessible_pmr = 0';
        }
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' GROUP BY e.id_etage';

        $colonnes = [
            'batiment' => 'b.nom',
            'numero'   => 'e.numero_etage',
            'nom'      => 'e.nom_etage',
            'salles'   => 'nb_salles',
        ];
        $col = $colonnes[$orderBy] ?? 'b.nom';
        $dir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY $col $dir, e.numero_etage ASC";

        $req = $db->prepare($sql);
        $req->execute($params);
        return $req->fetchAll();
    }

    // =================================================================
    //  UPDATE
    // =================================================================
    public function updateEtage(Etage $e, int $id): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('UPDATE etage SET
                                id_batiment = :bat, numero_etage = :num,
                                nom_etage = :nom, accessible_pmr = :pmr
                             WHERE id_etage = :id');
        $req->execute([
            'id'  => $id,
            'bat' => $e->getIdBatiment(),
            'num' => $e->getNumeroEtage(),
            'nom' => $e->getNomEtage(),
            'pmr' => $e->getAccessiblePmr(),
        ]);
        return $req->rowCount();
    }

    // =================================================================
    //  DELETE
    // =================================================================
    public function deleteEtage(int $id): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('DELETE FROM etage WHERE id_etage = :id');
        $req->execute(['id' => $id]);
        return $req->rowCount();
    }

    /** Un même numéro d'étage ne peut pas exister deux fois dans un bâtiment. */
    public function numeroExiste(int $idBatiment, int $numero, ?int $exclureId = null): bool
    {
        $db  = config::getConnexion();
        $sql = 'SELECT COUNT(*) FROM etage WHERE id_batiment = :bat AND numero_etage = :num';
        $params = ['bat' => $idBatiment, 'num' => $numero];
        if ($exclureId !== null) {
            $sql .= ' AND id_etage <> :id';
            $params['id'] = $exclureId;
        }
        $req = $db->prepare($sql);
        $req->execute($params);
        return (int)$req->fetchColumn() > 0;
    }

    public function compterSalles(int $idEtage): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('SELECT COUNT(*) FROM salle WHERE id_etage = :id');
        $req->execute(['id' => $idEtage]);
        return (int)$req->fetchColumn();
    }
}
