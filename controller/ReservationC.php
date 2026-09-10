<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/model/Reservation.php';

/**
 * Contrôleur Réservation.
 *
 * Contient la gestion des conflits / chevauchements :
 *   deux créneaux [d1,f1] et [d2,f2] se chevauchent si  d1 < f2  ET  f1 > d2.
 */
class ReservationC
{
    /** SELECT réutilisé : réservation + salle + étage + bâtiment + utilisateur. */
    private const SELECT_BASE = '
        SELECT r.*,
               s.code_salle, s.nom AS nom_salle, s.capacite, s.type_salle,
               s.etat AS etat_salle, s.delai_annulation,
               s.heure_ouverture, s.heure_fermeture,
               e.numero_etage, e.nom_etage,
               b.id_batiment, b.nom AS nom_batiment, b.code_batiment, b.ville,
               u.nom AS nom_utilisateur, u.prenom AS prenom_utilisateur,
               u.email AS email_utilisateur, u.departement,
               v.nom AS nom_validateur, v.prenom AS prenom_validateur
        FROM reservation r
        JOIN salle s        ON r.id_salle = s.id_salle
        JOIN etage e        ON s.id_etage = e.id_etage
        JOIN batiment b     ON e.id_batiment = b.id_batiment
        JOIN utilisateur u  ON r.id_utilisateur = u.id_utilisateur
        LEFT JOIN utilisateur v ON r.id_validateur = v.id_utilisateur';

    // =================================================================
    //  CREATE
    // =================================================================
    public function addReservation(Reservation $r): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('INSERT INTO reservation
            (id_salle, id_utilisateur, titre, description, date_debut, date_fin,
             nb_participants, statut, id_validateur, date_validation)
            VALUES (:salle, :user, :titre, :desc, :debut, :fin,
                    :nb, :statut, :valid, :date_valid)');
        $req->execute([
            'salle'      => $r->getIdSalle(),
            'user'       => $r->getIdUtilisateur(),
            'titre'      => $r->getTitre(),
            'desc'       => $r->getDescription(),
            'debut'      => $r->getDateDebut(),
            'fin'        => $r->getDateFin(),
            'nb'         => $r->getNbParticipants(),
            'statut'     => $r->getStatut(),
            'valid'      => $r->getIdValidateur(),
            'date_valid' => $r->getDateValidation(),
        ]);
        return (int)$db->lastInsertId();
    }

    // =================================================================
    //  READ
    // =================================================================
    public function showReservations(): array
    {
        $db = config::getConnexion();
        return $db->query(self::SELECT_BASE . ' ORDER BY r.date_debut DESC')->fetchAll();
    }

    public function getReservation(int $id): ?array
    {
        $db  = config::getConnexion();
        $req = $db->prepare(self::SELECT_BASE . ' WHERE r.id_reservation = :id');
        $req->execute(['id' => $id]);
        $res = $req->fetch();
        return $res ?: null;
    }

    /** Historique d'un utilisateur donné. */
    public function getReservationsUtilisateur(int $idUtilisateur, string $statut = 'tous'): array
    {
        $db  = config::getConnexion();
        $sql = self::SELECT_BASE . ' WHERE r.id_utilisateur = :user';
        $params = ['user' => $idUtilisateur];
        if ($statut !== 'tous' && in_array($statut, Reservation::STATUTS, true)) {
            $sql .= ' AND r.statut = :statut';
            $params['statut'] = $statut;
        }
        $sql .= ' ORDER BY r.date_debut DESC';

        $req = $db->prepare($sql);
        $req->execute($params);
        return $req->fetchAll();
    }

    /**
     * Recherche multicritère du gestionnaire.
     *
     * @param array $f recherche, statut, batiment, salle, utilisateur,
     *                 date_min, date_max, orderBy, orderDir
     */
    public function filterReservations(array $f = []): array
    {
        $db = config::getConnexion();

        $sql = self::SELECT_BASE;
        $conditions = [];
        $params     = [];

        if (!empty($f['recherche'])) {
            // Placeholders distincts : en prepared statements natifs (émulation
            // désactivée), un même paramètre nommé ne peut pas être réutilisé.
            $conditions[] = '(r.titre LIKE :q1 OR r.description LIKE :q2
                              OR s.nom LIKE :q3 OR s.code_salle LIKE :q4
                              OR u.nom LIKE :q5 OR u.prenom LIKE :q6 OR u.email LIKE :q7)';
            $motif = '%' . trim($f['recherche']) . '%';
            foreach (['q1','q2','q3','q4','q5','q6','q7'] as $ph) {
                $params[$ph] = $motif;
            }
        }
        if (!empty($f['statut']) && $f['statut'] !== 'tous'
            && in_array($f['statut'], Reservation::STATUTS, true)) {
            $conditions[]     = 'r.statut = :statut';
            $params['statut'] = $f['statut'];
        }
        if (!empty($f['batiment']) && $f['batiment'] !== 'tous' && ctype_digit((string)$f['batiment'])) {
            $conditions[]  = 'b.id_batiment = :bat';
            $params['bat'] = (int)$f['batiment'];
        }
        if (!empty($f['salle']) && $f['salle'] !== 'toutes' && ctype_digit((string)$f['salle'])) {
            $conditions[]    = 'r.id_salle = :salle';
            $params['salle'] = (int)$f['salle'];
        }
        if (!empty($f['utilisateur']) && $f['utilisateur'] !== 'tous' && ctype_digit((string)$f['utilisateur'])) {
            $conditions[]   = 'r.id_utilisateur = :user';
            $params['user'] = (int)$f['utilisateur'];
        }
        if (!empty($f['date_min'])) {
            $conditions[]       = 'r.date_debut >= :dmin';
            $params['dmin']     = $f['date_min'] . ' 00:00:00';
        }
        if (!empty($f['date_max'])) {
            $conditions[]       = 'r.date_debut <= :dmax';
            $params['dmax']     = $f['date_max'] . ' 23:59:59';
        }
        if (!empty($f['participants_min']) && is_numeric($f['participants_min'])) {
            $conditions[]  = 'r.nb_participants >= :nbp';
            $params['nbp'] = (int)$f['participants_min'];
        }
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $colonnes = [
            'date'         => 'r.date_debut',
            'titre'        => 'r.titre',
            'salle'        => 's.nom',
            'statut'       => 'r.statut',
            'utilisateur'  => 'u.nom',
            'participants' => 'r.nb_participants',
            'creation'     => 'r.date_creation',
        ];
        $col = $colonnes[$f['orderBy'] ?? ''] ?? 'r.date_debut';
        $dir = strtoupper($f['orderDir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY $col $dir";

        $req = $db->prepare($sql);
        $req->execute($params);
        return $req->fetchAll();
    }

    // =================================================================
    //  GESTION DES CONFLITS ET CHEVAUCHEMENTS
    // =================================================================
    /**
     * Retourne les réservations qui chevauchent le créneau demandé
     * pour une salle donnée (tableau vide = créneau libre).
     */
    public function detecterConflits(int $idSalle, string $debut, string $fin,
                                     ?int $exclureReservation = null,
                                     array $statuts = ['en_attente', 'validee']): array
    {
        // Liste blanche : on n'injecte jamais directement un statut venu de l'appelant.
        $statuts = array_values(array_intersect($statuts, Reservation::STATUTS));
        if (!$statuts) {
            $statuts = ['en_attente', 'validee'];
        }
        $placeholders = [];
        $params       = ['salle' => $idSalle, 'debut' => $debut, 'fin' => $fin];
        foreach ($statuts as $i => $st) {
            $placeholders[]      = ':st' . $i;
            $params['st' . $i]   = $st;
        }

        $db  = config::getConnexion();
        $sql = self::SELECT_BASE . '
                WHERE r.id_salle = :salle
                  AND r.statut IN (' . implode(', ', $placeholders) . ')
                  AND r.date_debut < :fin
                  AND r.date_fin   > :debut';
        if ($exclureReservation !== null) {
            $sql .= ' AND r.id_reservation <> :exclu';
            $params['exclu'] = $exclureReservation;
        }
        $sql .= ' ORDER BY r.date_debut';

        $req = $db->prepare($sql);
        $req->execute($params);
        return $req->fetchAll();
    }

    /**
     * Validation métier complète d'un créneau.
     * Retourne un tableau d'erreurs (vide si tout est correct).
     */
    public function validerCreneau(array $salle, string $debut, string $fin,
                                   int $nbParticipants, ?int $exclureReservation = null): array
    {
        $erreurs = [];

        $tsDebut = strtotime($debut);
        $tsFin   = strtotime($fin);

        if ($tsDebut === false || $tsFin === false) {
            return ['Les dates saisies sont invalides.'];
        }
        if ($tsFin <= $tsDebut) {
            $erreurs[] = "L'heure de fin doit être postérieure à l'heure de début.";
        }
        if ($tsDebut < time()) {
            $erreurs[] = 'Impossible de réserver un créneau dans le passé.';
        }
        if (($tsFin - $tsDebut) < 900) {
            $erreurs[] = 'La durée minimale d\'une réservation est de 15 minutes.';
        }
        if (($tsFin - $tsDebut) > 12 * 3600) {
            $erreurs[] = 'La durée maximale d\'une réservation est de 12 heures.';
        }
        if (date('Y-m-d', $tsDebut) !== date('Y-m-d', $tsFin)) {
            $erreurs[] = 'Une réservation doit commencer et se terminer le même jour.';
        }

        // Horaires d'ouverture de la salle
        $ouverture = substr($salle['heure_ouverture'], 0, 5);
        $fermeture = substr($salle['heure_fermeture'], 0, 5);
        if (date('H:i', $tsDebut) < $ouverture || date('H:i', $tsFin) > $fermeture) {
            $erreurs[] = "La salle est ouverte de $ouverture à $fermeture.";
        }

        // État de la salle
        if ($salle['etat'] !== 'disponible') {
            $erreurs[] = 'Cette salle n\'est pas réservable (' . $salle['etat'] . ').';
        }

        // Capacité
        if ($nbParticipants < 1) {
            $erreurs[] = 'Le nombre de participants doit être supérieur à zéro.';
        } elseif ($nbParticipants > (int)$salle['capacite']) {
            $erreurs[] = 'La salle accueille au maximum ' . (int)$salle['capacite'] . ' personnes.';
        }

        // Chevauchement
        if (!$erreurs) {
            $conflits = $this->detecterConflits((int)$salle['id_salle'], $debut, $fin, $exclureReservation);
            foreach ($conflits as $c) {
                $erreurs[] = 'Conflit avec « ' . $c['titre'] . ' » du '
                    . date('d/m/Y H:i', strtotime($c['date_debut'])) . ' au '
                    . date('H:i', strtotime($c['date_fin'])) . '.';
            }
        }

        return $erreurs;
    }

    // =================================================================
    //  UPDATE
    // =================================================================
    public function updateReservation(Reservation $r, int $id): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('UPDATE reservation SET
                                id_salle = :salle, titre = :titre, description = :desc,
                                date_debut = :debut, date_fin = :fin,
                                nb_participants = :nb
                             WHERE id_reservation = :id');
        $req->execute([
            'id'    => $id,
            'salle' => $r->getIdSalle(),
            'titre' => $r->getTitre(),
            'desc'  => $r->getDescription(),
            'debut' => $r->getDateDebut(),
            'fin'   => $r->getDateFin(),
            'nb'    => $r->getNbParticipants(),
        ]);
        return $req->rowCount();
    }

    /** Validation ou refus par le gestionnaire. */
    public function changerStatut(int $id, string $statut, ?int $idValidateur = null,
                                  ?string $motifRefus = null): int
    {
        if (!in_array($statut, Reservation::STATUTS, true)) {
            throw new InvalidArgumentException('Statut de réservation invalide.');
        }
        $db  = config::getConnexion();
        $req = $db->prepare('UPDATE reservation SET
                                statut = :statut,
                                motif_refus = :motif,
                                id_validateur = :valid,
                                date_validation = NOW()
                             WHERE id_reservation = :id');
        $req->execute([
            'id'     => $id,
            'statut' => $statut,
            'motif'  => $motifRefus,
            'valid'  => $idValidateur,
        ]);
        return $req->rowCount();
    }

    /** Déplacement d'une réunion : nouvelle salle et/ou nouveau créneau. */
    public function deplacerReservation(int $id, int $nouvelleSalle, string $debut, string $fin): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('UPDATE reservation SET
                                id_salle = :salle, date_debut = :debut, date_fin = :fin
                             WHERE id_reservation = :id');
        $req->execute([
            'id'    => $id,
            'salle' => $nouvelleSalle,
            'debut' => $debut,
            'fin'   => $fin,
        ]);
        return $req->rowCount();
    }

    /** Passe automatiquement les réservations échues au statut « terminée ». */
    public function cloturerReservationsEchues(): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare("UPDATE reservation
                             SET statut = 'terminee'
                             WHERE statut = 'validee' AND date_fin < NOW()");
        $req->execute();
        return $req->rowCount();
    }

    // =================================================================
    //  DELETE
    // =================================================================
    public function deleteReservation(int $id): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('DELETE FROM reservation WHERE id_reservation = :id');
        $req->execute(['id' => $id]);
        return $req->rowCount();
    }

    /**
     * L'utilisateur peut-il encore modifier / annuler sa réservation ?
     * Le délai limite est défini salle par salle (delai_annulation, en heures).
     */
    public function peutEtreModifiee(array $reservation): bool
    {
        if (!in_array($reservation['statut'], ['en_attente', 'validee'], true)) {
            return false;
        }
        $limite = strtotime($reservation['date_debut']) - ((int)$reservation['delai_annulation'] * 3600);
        return time() < $limite;
    }

    // =================================================================
    //  CALENDRIER
    // =================================================================
    /**
     * Réservations d'un mois donné, pour le calendrier interactif.
     * Retourne un tableau indexé par date (Y-m-d).
     */
    public function getCalendrierMois(int $annee, int $mois, ?int $idSalle = null,
                                      ?int $idBatiment = null): array
    {
        $db      = config::getConnexion();
        $debut   = sprintf('%04d-%02d-01 00:00:00', $annee, $mois);
        $finMois = date('Y-m-t 23:59:59', strtotime($debut));

        $sql = self::SELECT_BASE . "
                WHERE r.date_debut BETWEEN :debut AND :fin
                  AND r.statut IN ('en_attente','validee','terminee')";
        $params = ['debut' => $debut, 'fin' => $finMois];

        if ($idSalle !== null) {
            $sql .= ' AND r.id_salle = :salle';
            $params['salle'] = $idSalle;
        }
        if ($idBatiment !== null) {
            $sql .= ' AND b.id_batiment = :bat';
            $params['bat'] = $idBatiment;
        }
        $sql .= ' ORDER BY r.date_debut';

        $req = $db->prepare($sql);
        $req->execute($params);

        $parJour = [];
        foreach ($req->fetchAll() as $r) {
            $parJour[date('Y-m-d', strtotime($r['date_debut']))][] = $r;
        }
        return $parJour;
    }

    /** Réservations d'une salle pour une journée précise. */
    public function getReservationsJour(int $idSalle, string $date): array
    {
        $db  = config::getConnexion();
        $req = $db->prepare(self::SELECT_BASE . "
                WHERE r.id_salle = :salle
                  AND DATE(r.date_debut) = :date
                  AND r.statut IN ('en_attente','validee','terminee')
                ORDER BY r.date_debut");
        $req->execute(['salle' => $idSalle, 'date' => $date]);
        return $req->fetchAll();
    }

    /**
     * Créneaux horaires d'une salle sur une journée, marqués libre/occupé.
     * Pas de 30 minutes entre l'ouverture et la fermeture.
     *
     * @param int|null $exclure Réservation à ignorer. Utile pendant un
     *                          déplacement : la réunion qu'on déplace ne doit
     *                          pas apparaître comme bloquant ses propres créneaux.
     */
    public function getCreneauxJour(array $salle, string $date, ?int $exclure = null): array
    {
        $reservations = $this->getReservationsJour((int)$salle['id_salle'], $date);

        if ($exclure !== null) {
            $reservations = array_values(array_filter(
                $reservations,
                static fn(array $r): bool => (int)$r['id_reservation'] !== $exclure
            ));
        }

        $creneaux  = [];
        $debutJour = strtotime($date . ' ' . $salle['heure_ouverture']);
        $finJour   = strtotime($date . ' ' . $salle['heure_fermeture']);

        for ($t = $debutJour; $t < $finJour; $t += 1800) {
            $fin     = $t + 1800;
            $occupe  = null;
            foreach ($reservations as $r) {
                $rd = strtotime($r['date_debut']);
                $rf = strtotime($r['date_fin']);
                if ($t < $rf && $fin > $rd) {
                    $occupe = $r;
                    break;
                }
            }
            $creneaux[] = [
                'debut'       => date('H:i', $t),
                'fin'         => date('H:i', $fin),
                'timestamp'   => $t,
                'passe'       => $t < time(),
                'occupe'      => $occupe !== null,
                'reservation' => $occupe,
            ];
        }
        return $creneaux;
    }

    // =================================================================
    //  STATISTIQUES ET RAPPORTS
    // =================================================================
    public function compteurs(): array
    {
        $db = config::getConnexion();
        return $db->query("SELECT
                    COUNT(*) AS total,
                    SUM(statut = 'en_attente') AS en_attente,
                    SUM(statut = 'validee')    AS validees,
                    SUM(statut = 'refusee')    AS refusees,
                    SUM(statut = 'annulee')    AS annulees,
                    SUM(statut = 'terminee')   AS terminees,
                    COALESCE(SUM(nb_participants), 0) AS total_participants
                FROM reservation")->fetch();
    }

    /** Rapport détaillé sur une période (export / impression). */
    public function rapportPeriode(string $dateDebut, string $dateFin, ?int $idBatiment = null): array
    {
        $db  = config::getConnexion();
        $sql = self::SELECT_BASE . '
                WHERE r.date_debut >= :debut AND r.date_debut <= :fin';
        $params = [
            'debut' => $dateDebut . ' 00:00:00',
            'fin'   => $dateFin . ' 23:59:59',
        ];
        if ($idBatiment !== null) {
            $sql .= ' AND b.id_batiment = :bat';
            $params['bat'] = $idBatiment;
        }
        $sql .= ' ORDER BY r.date_debut';

        $req = $db->prepare($sql);
        $req->execute($params);
        return $req->fetchAll();
    }

    /** Volume de réservations par jour sur une période (graphique). */
    public function reservationsParJour(string $dateDebut, string $dateFin): array
    {
        $db  = config::getConnexion();
        $req = $db->prepare("SELECT DATE(date_debut) AS jour,
                                    COUNT(*) AS nb,
                                    SUM(statut = 'validee') AS validees
                             FROM reservation
                             WHERE date_debut BETWEEN :debut AND :fin
                             GROUP BY DATE(date_debut)
                             ORDER BY jour");
        $req->execute([
            'debut' => $dateDebut . ' 00:00:00',
            'fin'   => $dateFin . ' 23:59:59',
        ]);
        return $req->fetchAll();
    }

    /** Top des salles les plus réservées. */
    public function topSalles(int $limite = 5): array
    {
        $db  = config::getConnexion();
        $req = $db->prepare("SELECT s.nom, s.code_salle, b.nom AS nom_batiment,
                                    COUNT(r.id_reservation) AS nb
                             FROM salle s
                             JOIN etage e    ON s.id_etage = e.id_etage
                             JOIN batiment b ON e.id_batiment = b.id_batiment
                             LEFT JOIN reservation r ON s.id_salle = r.id_salle
                             GROUP BY s.id_salle
                             ORDER BY nb DESC, s.nom
                             LIMIT :lim");
        $req->bindValue(':lim', $limite, PDO::PARAM_INT);
        $req->execute();
        return $req->fetchAll();
    }

    /** Répartition des réservations par bâtiment. */
    public function repartitionParBatiment(): array
    {
        $db = config::getConnexion();
        return $db->query('SELECT b.nom AS nom_batiment,
                                  COUNT(r.id_reservation) AS nb
                           FROM batiment b
                           LEFT JOIN etage e ON b.id_batiment = e.id_batiment
                           LEFT JOIN salle s ON e.id_etage = s.id_etage
                           LEFT JOIN reservation r ON s.id_salle = r.id_salle
                           GROUP BY b.id_batiment
                           ORDER BY nb DESC')->fetchAll();
    }

    /** Les N prochaines réservations à venir (tableau de bord). */
    public function prochaines(int $limite = 5): array
    {
        $db  = config::getConnexion();
        $req = $db->prepare(self::SELECT_BASE . "
                WHERE r.date_debut >= NOW() AND r.statut IN ('en_attente','validee')
                ORDER BY r.date_debut ASC LIMIT :lim");
        $req->bindValue(':lim', $limite, PDO::PARAM_INT);
        $req->execute();
        return $req->fetchAll();
    }
}
