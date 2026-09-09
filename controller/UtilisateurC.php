<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/model/Utilisateur.php';

/**
 * Contrôleur Utilisateur : CRUD + authentification + recherche multicritère.
 */
class UtilisateurC
{
    // =================================================================
    //  CREATE
    // =================================================================
    public function addUtilisateur(Utilisateur $u): int
    {
        $db = config::getConnexion();
        $sql = 'INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, departement, role, statut)
                VALUES (:nom, :prenom, :email, :mdp, :tel, :dep, :role, :statut)';
        $req = $db->prepare($sql);
        $req->execute([
            'nom'    => $u->getNom(),
            'prenom' => $u->getPrenom(),
            'email'  => $u->getEmail(),
            'mdp'    => $u->getMotDePasse(),
            'tel'    => $u->getTelephone(),
            'dep'    => $u->getDepartement(),
            'role'   => $u->getRole(),
            'statut' => $u->getStatut(),
        ]);
        return (int)$db->lastInsertId();
    }

    // =================================================================
    //  READ
    // =================================================================
    public function showUtilisateurs(): array
    {
        $db = config::getConnexion();
        return $db->query('SELECT * FROM utilisateur ORDER BY nom, prenom')->fetchAll();
    }

    public function getUtilisateur(int $id): ?array
    {
        $db  = config::getConnexion();
        $req = $db->prepare('SELECT * FROM utilisateur WHERE id_utilisateur = :id');
        $req->execute(['id' => $id]);
        $res = $req->fetch();
        return $res ?: null;
    }

    public function getUtilisateurParEmail(string $email): ?array
    {
        $db  = config::getConnexion();
        $req = $db->prepare('SELECT * FROM utilisateur WHERE email = :email');
        $req->execute(['email' => strtolower(trim($email))]);
        $res = $req->fetch();
        return $res ?: null;
    }

    /** Utilisateurs actifs, pour les listes déroulantes du back-office. */
    public function getUtilisateursActifs(): array
    {
        $db = config::getConnexion();
        return $db->query("SELECT id_utilisateur, nom, prenom, email, departement
                           FROM utilisateur WHERE statut = 'actif'
                           ORDER BY nom, prenom")->fetchAll();
    }

    /**
     * Liste avec JOINTURE : nombre de réservations par utilisateur.
     * Recherche et filtres multicritères.
     */
    public function filterUtilisateurs(string $recherche = '', string $role = 'tous',
                                       string $statut = 'tous', string $orderBy = 'nom',
                                       string $orderDir = 'ASC'): array
    {
        $db = config::getConnexion();

        $sql = 'SELECT u.*,
                       COUNT(r.id_reservation) AS nb_reservations,
                       SUM(CASE WHEN r.statut = "validee" THEN 1 ELSE 0 END) AS nb_validees
                FROM utilisateur u
                LEFT JOIN reservation r ON u.id_utilisateur = r.id_utilisateur';

        $conditions = [];
        $params     = [];

        if ($recherche !== '') {
            // Placeholders distincts (émulation PDO désactivée : pas de réutilisation).
            $conditions[] = '(u.nom LIKE :q1 OR u.prenom LIKE :q2 OR u.email LIKE :q3 OR u.departement LIKE :q4)';
            $motif = '%' . $recherche . '%';
            foreach (['q1','q2','q3','q4'] as $ph) {
                $params[$ph] = $motif;
            }
        }
        if ($role !== 'tous') {
            $conditions[]   = 'u.role = :role';
            $params['role'] = $role;
        }
        if ($statut !== 'tous') {
            $conditions[]     = 'u.statut = :statut';
            $params['statut'] = $statut;
        }
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' GROUP BY u.id_utilisateur';

        // Liste blanche : jamais de colonne issue directement de l'URL.
        $colonnes = [
            'nom'          => 'u.nom',
            'email'        => 'u.email',
            'role'         => 'u.role',
            'reservations' => 'nb_reservations',
            'date'         => 'u.date_creation',
        ];
        $col = $colonnes[$orderBy] ?? 'u.nom';
        $dir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY $col $dir";

        $req = $db->prepare($sql);
        $req->execute($params);
        return $req->fetchAll();
    }

    // =================================================================
    //  UPDATE
    // =================================================================
    public function updateUtilisateur(Utilisateur $u, int $id): int
    {
        $db = config::getConnexion();
        $sql = 'UPDATE utilisateur SET
                    nom = :nom, prenom = :prenom, email = :email,
                    telephone = :tel, departement = :dep,
                    role = :role, statut = :statut
                WHERE id_utilisateur = :id';
        $req = $db->prepare($sql);
        $req->execute([
            'id'     => $id,
            'nom'    => $u->getNom(),
            'prenom' => $u->getPrenom(),
            'email'  => $u->getEmail(),
            'tel'    => $u->getTelephone(),
            'dep'    => $u->getDepartement(),
            'role'   => $u->getRole(),
            'statut' => $u->getStatut(),
        ]);
        return $req->rowCount();
    }

    public function updateMotDePasse(int $id, string $motDePasseEnClair): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('UPDATE utilisateur SET mot_de_passe = :mdp WHERE id_utilisateur = :id');
        $req->execute([
            'id'  => $id,
            'mdp' => password_hash($motDePasseEnClair, PASSWORD_DEFAULT),
        ]);
        return $req->rowCount();
    }

    /** Active ou désactive un compte (un compte inactif ne peut plus se connecter). */
    public function changerStatut(int $id, string $statut): int
    {
        if (!in_array($statut, ['actif', 'inactif'], true)) {
            throw new InvalidArgumentException('Statut de compte invalide.');
        }
        $db  = config::getConnexion();
        $req = $db->prepare('UPDATE utilisateur SET statut = :statut WHERE id_utilisateur = :id');
        $req->execute(['id' => $id, 'statut' => $statut]);
        return $req->rowCount();
    }

    // =================================================================
    //  DELETE
    // =================================================================
    public function deleteUtilisateur(int $id): int
    {
        $db  = config::getConnexion();
        $req = $db->prepare('DELETE FROM utilisateur WHERE id_utilisateur = :id');
        $req->execute(['id' => $id]);
        return $req->rowCount();
    }

    // =================================================================
    //  AUTHENTIFICATION
    // =================================================================
    /** Retourne l'utilisateur si les identifiants sont bons, sinon null. */
    public function authentifier(string $email, string $motDePasse): ?array
    {
        $u = $this->getUtilisateurParEmail($email);
        if ($u === null || $u['statut'] !== 'actif') {
            return null;
        }
        if (!password_verify($motDePasse, $u['mot_de_passe'])) {
            return null;
        }
        unset($u['mot_de_passe']);
        return $u;
    }

    /** Vérifie l'unicité de l'email (en excluant éventuellement un id). */
    public function emailExiste(string $email, ?int $exclureId = null): bool
    {
        $db  = config::getConnexion();
        $sql = 'SELECT COUNT(*) FROM utilisateur WHERE email = :email';
        $params = ['email' => strtolower(trim($email))];
        if ($exclureId !== null) {
            $sql .= ' AND id_utilisateur <> :id';
            $params['id'] = $exclureId;
        }
        $req = $db->prepare($sql);
        $req->execute($params);
        return (int)$req->fetchColumn() > 0;
    }

    /** Emails des gestionnaires actifs, pour les notifications. */
    public function getEmailsGestionnaires(): array
    {
        $db = config::getConnexion();
        return $db->query("SELECT email FROM utilisateur
                           WHERE role IN ('gestionnaire','admin') AND statut = 'actif'")
                  ->fetchAll(PDO::FETCH_COLUMN);
    }
}
