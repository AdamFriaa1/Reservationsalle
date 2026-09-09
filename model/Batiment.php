<?php
/**
 * Entité Bâtiment.
 */
class Batiment
{
    private ?int $id_batiment = null;
    private string $nom;
    private string $code_batiment;
    private string $adresse;
    private string $ville;
    private ?string $description;
    private ?string $image;
    private ?string $date_creation = null;

    public function __construct(
        string $nom = '',
        string $code_batiment = '',
        string $adresse = '',
        string $ville = '',
        ?string $description = null,
        ?string $image = null
    ) {
        $this->nom           = $nom;
        $this->code_batiment = $code_batiment;
        $this->adresse       = $adresse;
        $this->ville         = $ville;
        $this->description   = $description;
        $this->image         = $image;
    }

    // ---------------------- Getters ----------------------
    public function getIdBatiment(): ?int      { return $this->id_batiment; }
    public function getNom(): string           { return $this->nom; }
    public function getCodeBatiment(): string  { return $this->code_batiment; }
    public function getAdresse(): string       { return $this->adresse; }
    public function getVille(): string         { return $this->ville; }
    public function getDescription(): ?string  { return $this->description; }
    public function getImage(): ?string        { return $this->image; }
    public function getDateCreation(): ?string { return $this->date_creation; }

    // ---------------------- Setters ----------------------
    public function setIdBatiment($v): void          { $this->id_batiment = (int)$v; }
    public function setNom(string $v): void          { $this->nom = trim($v); }
    public function setCodeBatiment(string $v): void { $this->code_batiment = strtoupper(trim($v)); }
    public function setAdresse(string $v): void      { $this->adresse = trim($v); }
    public function setVille(string $v): void        { $this->ville = trim($v); }
    public function setDescription(?string $v): void { $this->description = $v !== null ? trim($v) : null; }
    public function setImage(?string $v): void       { $this->image = $v; }
    public function setDateCreation(?string $v): void{ $this->date_creation = $v; }
}
