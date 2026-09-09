<?php
/**
 * Entité Utilisateur : rôles admin / gestionnaire / utilisateur.
 */
class Utilisateur
{
    private ?int $id_utilisateur = null;
    private string $nom;
    private string $prenom;
    private string $email;
    private string $mot_de_passe;
    private ?string $telephone;
    private ?string $departement;
    private string $role;
    private string $statut;
    private ?string $date_creation = null;

    public function __construct(
        string $nom = '',
        string $prenom = '',
        string $email = '',
        string $mot_de_passe = '',
        ?string $telephone = null,
        ?string $departement = null,
        string $role = 'utilisateur',
        string $statut = 'actif'
    ) {
        $this->nom          = $nom;
        $this->prenom       = $prenom;
        $this->email        = $email;
        $this->mot_de_passe = $mot_de_passe;
        $this->telephone    = $telephone;
        $this->departement  = $departement;
        $this->role         = $role;
        $this->statut       = $statut;
    }

    // ---------------------- Getters ----------------------
    public function getIdUtilisateur(): ?int    { return $this->id_utilisateur; }
    public function getNom(): string            { return $this->nom; }
    public function getPrenom(): string         { return $this->prenom; }
    public function getEmail(): string          { return $this->email; }
    public function getMotDePasse(): string     { return $this->mot_de_passe; }
    public function getTelephone(): ?string     { return $this->telephone; }
    public function getDepartement(): ?string   { return $this->departement; }
    public function getRole(): string           { return $this->role; }
    public function getStatut(): string         { return $this->statut; }
    public function getDateCreation(): ?string  { return $this->date_creation; }
    public function getNomComplet(): string     { return $this->prenom . ' ' . $this->nom; }

    // ---------------------- Setters ----------------------
    public function setIdUtilisateur($v): void       { $this->id_utilisateur = (int)$v; }
    public function setNom(string $v): void          { $this->nom = trim($v); }
    public function setPrenom(string $v): void       { $this->prenom = trim($v); }
    public function setEmail(string $v): void        { $this->email = strtolower(trim($v)); }
    public function setMotDePasse(string $v): void   { $this->mot_de_passe = $v; }
    public function setTelephone(?string $v): void   { $this->telephone = $v !== null ? trim($v) : null; }
    public function setDepartement(?string $v): void { $this->departement = $v !== null ? trim($v) : null; }
    public function setRole(string $v): void         { $this->role = $v; }
    public function setStatut(string $v): void       { $this->statut = $v; }
    public function setDateCreation(?string $v): void{ $this->date_creation = $v; }

    /** Hache le mot de passe en clair avant enregistrement. */
    public function hasherMotDePasse(string $enClair): void
    {
        $this->mot_de_passe = password_hash($enClair, PASSWORD_DEFAULT);
    }
}
