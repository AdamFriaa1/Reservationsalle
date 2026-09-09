<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/model/Salle.php';

/**
 * Contrôleur Salle : CRUD, maintenance/disponibilité, recherche de
 * créneaux libres et statistiques d'utilisation.
 */
class SalleC
{
    /** Bloc SELECT réutilisé : salle + étage + bâtiment (triple jointure). */
    private const SELECT_BASE = '
        SELECT s.*,
               e.numero_etage, e.nom_etage, e.accessible_pmr,
               b.id_batiment, b.nom AS nom_batiment, b.code_batiment, b.ville, b.adresse
        FROM salle s
        JOIN etage e    ON s.id_etage = e.id_etage
        JOIN batiment b ON e.id_batiment = b.id_batiment';

    // =================================================================
    //  CREATE
    // =================================================================
    public function addSalle(Salle $s): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('INSERT INTO salle
            (id_etage, code_salle, nom, capacite, type_salle, equipements, localisation,
             etat, heure_ouverture, heure_fermeture, delai_annulation, image)
            VALUES (:etage, :code, :nom, :cap, :type, :equip, :loc,
                    :etat, :ouv, :ferm, :delai, :image)');
        $req->execute([
            'etage' => $s->getIdEtage(),
            'code'  => $s->getCodeSalle(),
            'nom'   => $s->getNom(),
            'cap'   => $s->getCapacite(),
            'type'  => $s->getTypeSalle(),
            'equip' => $s->getEquipements(),
            'loc'   => $s->getLocalisation(),
            'etat'  => $s->getEtat(),
            'ouv'   => $s->getHeureOuverture(),
            'ferm'  => $s->getHeureFermeture(),
            'delai' => $s->getDelaiAnnulation(),
            'image' => $s->getImage(),
        ]);
        return (int)$db->lastInsertId();
    }

    // =================================================================
    //  READ
    // =================================================================
    public function showSalles(): array
    {
        $db = config::getConnexion();
        return $db->query(self::SELECT_BASE . ' ORDER BY b.nom, e.numero_etage, s.nom')->fetchAll();
    }

    public function getSalle(int $id): ?array
    {
        $db  = config::getConnexion();
        $req = $db->prepare(self::SELECT_BASE . ' WHERE s.id_salle = :id');
        $req->execute(['id' => $id]);
        $res = $req->fetch();
        return $res ?: null;
    }

    /** Salles utilisables pour une nouvelle réservation. */
    public function getSallesDisponibles(): array
    {
        $db = config::getConnexion();
        return $db->query(self::SELECT_BASE . " WHERE s.etat = 'disponible'
                           ORDER BY b.nom, e.numero_etage, s.nom")->fetchAll();
    }

    /**
     * Recherche multicritère (front-office et back-office).
     *
     * @param array $f Clés possibles : recherche, batiment, type, etat,
     *                 capacite_min, equipement, pmr, orderBy, orderDir
     */
    public function filterSalles(array $f = []): array
    {
        $db = config::getConnexion();

        $sql = self::SELECT_BASE;
        $conditions = [];
        $params     = [];

        if (!empty($f['recherche'])) {
            // Placeholders distincts (émulation PDO désactivée : pas de réutilisation).
            $conditions[] = '(s.nom LIKE :q1 OR s.code_salle LIKE :q2 OR s.equipements LIKE :q3
                              OR s.localisation LIKE :q4 OR b.nom LIKE :q5)';
            $motif = '%' . trim($f['recherche']) . '%';
            foreach (['q1','q2','q3','q4','q5'] as $ph) {
                $params[$ph] = $motif;
            }
        }
        if (!empty($f['batiment']) && $f['batiment'] !== 'tous' && ctype_digit((string)$f['batiment'])) {
            $conditions[]  = 'b.id_batiment = :bat';
            $params['bat'] = (int)$f['batiment'];
        }
        if (!empty($f['etage']) && $f['etage'] !== 'tous' && ctype_digit((string)$f['etage'])) {
            $conditions[]    = 's.id_etage = :etage';
            $params['etage'] = (int)$f['etage'];
        }
        if (!empty($f['type']) && $f['type'] !== 'tous' && in_array($f['type'], Salle::TYPES, true)) {
            $conditions[]   = 's.type_salle = :type';
            $params['type'] = $f['type'];
        }
        if (!empty($f['etat']) && $f['etat'] !== 'tous' && in_array($f['etat'], Salle::ETATS, true)) {
            $conditions[]   = 's.etat = :etat';
            $params['etat'] = $f['etat'];
        }
        if (!empty($f['capacite_min']) && is_numeric($f['capacite_min'])) {
            $conditions[]  = 's.capacite >= :cap';
            $params['cap'] = (int)$f['capacite_min'];
        }
        if (!empty($f['equipement'])) {
            $conditions[]     = 's.equipements LIKE :equip';
            $params['equip']  = '%' . trim($f['equipement']) . '%';
        }
        if (!empty($f['pmr']) && $f['pmr'] === 'oui') {
            $conditions[] = 'e.accessible_pmr = 1';
        }
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $colonnes = [
            'nom'      => 's.nom',
            'code'     => 's.code_salle',
            'capacite' => 's.capacite',
            'type'     => 's.type_salle',
            'batiment' => 'b.nom',
            'etat'     => 's.etat',
        ];
        $col = $colonnes[$f['orderBy'] ?? ''] ?? 'b.nom';
        $dir = strtoupper($f['orderDir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY $col $dir, s.nom ASC";

        $req = $db->prepare($sql);
        $req->execute($params);
        return $req->fetchAll();
    }

    /**
     * Salles réellement libres sur un créneau donné.
     * Une salle est exclue si une réservation en attente ou validée
     * chevauche le créneau demandé.
     */
    public function getSallesLibres(string $debut, string $fin, int $capaciteMin = 0): array
    {
        $db  = config::getConnexion();
        $sql = self::SELECT_BASE . "
                WHERE s.etat = 'disponible'
                  AND s.capacite >= :cap
                  AND s.id_salle NOT IN (
                        SELECT r.id_salle FROM reservation r
                        WHERE r.statut IN ('en_attente','validee')
                          AND r.date_debut < :fin
                          AND r.date_fin   > :debut
                  )
                ORDER BY b.nom, s.capacite";
        $req = $db->prepare($sql);
        $req->execute(['debut' => $debut, 'fin' => $fin, 'cap' => $capaciteMin]);
        return $req->fetchAll();
    }

    /** Une salle donnée est-elle libre sur ce créneau ? */
    public function estLibre(int $idSalle, string $debut, string $fin, ?int $exclureReservation = null): bool
    {
        $db  = config::getConnexion();
        $sql = "SELECT COUNT(*) FROM reservation
                WHERE id_salle = :salle
                  AND statut IN ('en_attente','validee')
                  AND date_debut < :fin
                  AND date_fin   > :debut";
        $params = ['salle' => $idSalle, 'debut' => $debut, 'fin' => $fin];
        if ($exclureReservation !== null) {
            $sql .= ' AND id_reservation <> :exclu';
            $params['exclu'] = $exclureReservation;
        }
        $req = $db->prepare($sql);
        $req->execute($params);
        return (int)$req->fetchColumn() === 0;
    }

    // =================================================================
    //  UPDATE
    // =================================================================
    public function updateSalle(Salle $s, int $id): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('UPDATE salle SET
                                id_etage = :etage, code_salle = :code, nom = :nom,
                                capacite = :cap, type_salle = :type, equipements = :equip,
                                localisation = :loc, etat = :etat,
                                heure_ouverture = :ouv, heure_fermeture = :ferm,
                                delai_annulation = :delai, image = :image
                             WHERE id_salle = :id');
        $req->execute([
            'id'    => $id,
            'etage' => $s->getIdEtage(),
            'code'  => $s->getCodeSalle(),
            'nom'   => $s->getNom(),
            'cap'   => $s->getCapacite(),
            'type'  => $s->getTypeSalle(),
            'equip' => $s->getEquipements(),
            'loc'   => $s->getLocalisation(),
            'etat'  => $s->getEtat(),
            'ouv'   => $s->getHeureOuverture(),
            'ferm'  => $s->getHeureFermeture(),
            'delai' => $s->getDelaiAnnulation(),
            'image' => $s->getImage(),
        ]);
        return $req->rowCount();
    }

    /** Bascule rapide de l'état (disponible / maintenance / indisponible). */
    public function changerEtat(int $id, string $etat): int
    {
        if (!in_array($etat, Salle::ETATS, true)) {
            throw new InvalidArgumentException('État de salle invalide.');
        }
        $db  = config::getConnexion();
        $req = $db->prepare('UPDATE salle SET etat = :etat WHERE id_salle = :id');
        $req->execute(['id' => $id, 'etat' => $etat]);
        return $req->rowCount();
    }

    // =================================================================
    //  DELETE
    // =================================================================
    public function deleteSalle(int $id): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('DELETE FROM salle WHERE id_salle = :id');
        $req->execute(['id' => $id]);
        return $req->rowCount();
    }

    public function codeExiste(string $code, ?int $exclureId = null): bool
    {
        $db  = config::getConnexion();
        $sql = 'SELECT COUNT(*) FROM salle WHERE code_salle = :code';
        $params = ['code' => strtoupper(trim($code))];
        if ($exclureId !== null) {
            $sql .= ' AND id_salle <> :id';
            $params['id'] = $exclureId;
        }
        $req = $db->prepare($sql);
        $req->execute($params);
        return (int)$req->fetchColumn() > 0;
    }

    // =================================================================
    //  STATISTIQUES D'UTILISATION
    // =================================================================
    /**
     * Taux d'occupation par salle sur une période.
     * Le taux est calculé sur les heures d'ouverture réelles de la salle.
     */
    public function statistiquesUtilisation(string $dateDebut, string $dateFin): array
    {
        $db  = config::getConnexion();
        $sql = "SELECT s.id_salle, s.code_salle, s.nom, s.capacite, s.etat,
                       b.nom AS nom_batiment,
                       COUNT(r.id_reservation) AS nb_reservations,
                       COALESCE(SUM(CASE WHEN r.statut IN ('validee','terminee')
                            THEN TIMESTAMPDIFF(MINUTE, r.date_debut, r.date_fin) ELSE 0 END), 0) AS minutes_occupees,
                       COALESCE(AVG(r.nb_participants), 0) AS moyenne_participants,
                       TIME_TO_SEC(TIMEDIFF(s.heure_fermeture, s.heure_ouverture)) / 60 AS minutes_par_jour
                FROM salle s
                JOIN etage e    ON s.id_etage = e.id_etage
                JOIN batiment b ON e.id_batiment = b.id_batiment
                LEFT JOIN reservation r
                       ON s.id_salle = r.id_salle
                      AND r.date_debut >= :debut
                      AND r.date_debut <  :fin
                      AND r.statut IN ('validee','terminee')
                GROUP BY s.id_salle
                ORDER BY minutes_occupees DESC";
        $req = $db->prepare($sql);
        $req->execute(['debut' => $dateDebut, 'fin' => $dateFin]);
        $lignes = $req->fetchAll();

        // Nombre de jours ouvrés approximatif de la période
        $nbJours = max(1, (int)ceil((strtotime($dateFin) - strtotime($dateDebut)) / 86400));

        foreach ($lignes as &$l) {
            $capaciteMinutes = max(1, (float)$l['minutes_par_jour'] * $nbJours);
            $l['taux_occupation'] = round(((float)$l['minutes_occupees'] / $capaciteMinutes) * 100, 1);
            $l['heures_occupees'] = round((float)$l['minutes_occupees'] / 60, 1);
        }
        unset($l);

        return $lignes;
    }

    /** Compteurs globaux pour le tableau de bord. */
    public function compteurs(): array
    {
        $db = config::getConnexion();
        return $db->query("SELECT
                    COUNT(*) AS total,
                    SUM(etat = 'disponible')   AS disponibles,
                    SUM(etat = 'maintenance')  AS maintenance,
                    SUM(etat = 'indisponible') AS indisponibles,
                    COALESCE(SUM(capacite), 0) AS capacite_totale
                FROM salle")->fetch();
    }
}
