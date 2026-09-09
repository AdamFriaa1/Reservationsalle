<?php
/**
 * Entité Salle : caractéristiques, équipements, disponibilité, maintenance.
 */
class Salle
{
    private ?int $id_salle = null;
    private int $id_etage;
    private string $code_salle;
    private string $nom;
    private int $capacite;
    private string $type_salle;
    private ?string $equipements;
    private ?string $localisation;
    private string $etat;
    private string $heure_ouverture;
    private string $heure_fermeture;
    private int $delai_annulation;
    private ?string $image;
    private ?string $date_creation = null;

    public const TYPES = ['reunion', 'conference', 'formation', 'visio', 'coworking'];
    public const ETATS = ['disponible', 'maintenance', 'indisponible'];

    public function __construct(
        int $id_etage = 0,
        string $code_salle = '',
        string $nom = '',
        int $capacite = 1,
        string $type_salle = 'reunion',
        ?string $equipements = null,
        ?string $localisation = null,
        string $etat = 'disponible',
        string $heure_ouverture = '08:00:00',
        string $heure_fermeture = '19:00:00',
        int $delai_annulation = 24,
        ?string $image = null
    ) {
        $this->id_etage         = $id_etage;
        $this->code_salle       = $code_salle;
        $this->nom              = $nom;
        $this->capacite         = $capacite;
        $this->type_salle       = $type_salle;
        $this->equipements      = $equipements;
        $this->localisation     = $localisation;
        $this->etat             = $etat;
        $this->heure_ouverture  = $heure_ouverture;
        $this->heure_fermeture  = $heure_fermeture;
        $this->delai_annulation = $delai_annulation;
        $this->image            = $image;
    }

    // ---------------------- Getters ----------------------
    public function getIdSalle(): ?int          { return $this->id_salle; }
    public function getIdEtage(): int           { return $this->id_etage; }
    public function getCodeSalle(): string      { return $this->code_salle; }
    public function getNom(): string            { return $this->nom; }
    public function getCapacite(): int          { return $this->capacite; }
    public function getTypeSalle(): string      { return $this->type_salle; }
    public function getEquipements(): ?string   { return $this->equipements; }
    public function getLocalisation(): ?string  { return $this->localisation; }
    public function getEtat(): string           { return $this->etat; }
    public function getHeureOuverture(): string { return $this->heure_ouverture; }
    public function getHeureFermeture(): string { return $this->heure_fermeture; }
    public function getDelaiAnnulation(): int   { return $this->delai_annulation; }
    public function getImage(): ?string         { return $this->image; }
    public function getDateCreation(): ?string  { return $this->date_creation; }

    // ---------------------- Setters ----------------------
    public function setIdSalle($v): void            { $this->id_salle = (int)$v; }
    public function setIdEtage($v): void            { $this->id_etage = (int)$v; }
    public function setCodeSalle(string $v): void   { $this->code_salle = strtoupper(trim($v)); }
    public function setNom(string $v): void         { $this->nom = trim($v); }
    public function setCapacite($v): void           { $this->capacite = (int)$v; }
    public function setTypeSalle(string $v): void   { $this->type_salle = $v; }
    public function setEquipements(?string $v): void{ $this->equipements = $v !== null ? trim($v) : null; }
    public function setLocalisation(?string $v): void { $this->localisation = $v !== null ? trim($v) : null; }
    public function setEtat(string $v): void        { $this->etat = $v; }
    public function setHeureOuverture(string $v): void { $this->heure_ouverture = $v; }
    public function setHeureFermeture(string $v): void { $this->heure_fermeture = $v; }
    public function setDelaiAnnulation($v): void    { $this->delai_annulation = (int)$v; }
    public function setImage(?string $v): void      { $this->image = $v; }
    public function setDateCreation(?string $v): void { $this->date_creation = $v; }
}
