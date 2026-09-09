<?php
/**
 * Entité Étage : rattachée à un bâtiment (jointure batiment <-> etage <-> salle).
 */
class Etage
{
    private ?int $id_etage = null;
    private int $id_batiment;
    private int $numero_etage;
    private string $nom_etage;
    private int $accessible_pmr;

    public function __construct(
        int $id_batiment = 0,
        int $numero_etage = 0,
        string $nom_etage = '',
        int $accessible_pmr = 0
    ) {
        $this->id_batiment    = $id_batiment;
        $this->numero_etage   = $numero_etage;
        $this->nom_etage      = $nom_etage;
        $this->accessible_pmr = $accessible_pmr;
    }

    // ---------------------- Getters ----------------------
    public function getIdEtage(): ?int       { return $this->id_etage; }
    public function getIdBatiment(): int     { return $this->id_batiment; }
    public function getNumeroEtage(): int    { return $this->numero_etage; }
    public function getNomEtage(): string    { return $this->nom_etage; }
    public function getAccessiblePmr(): int  { return $this->accessible_pmr; }

    // ---------------------- Setters ----------------------
    public function setIdEtage($v): void       { $this->id_etage = (int)$v; }
    public function setIdBatiment($v): void    { $this->id_batiment = (int)$v; }
    public function setNumeroEtage($v): void   { $this->numero_etage = (int)$v; }
    public function setNomEtage(string $v): void { $this->nom_etage = trim($v); }
    public function setAccessiblePmr($v): void { $this->accessible_pmr = (int)((bool)$v); }
}
