<?php
/**
 * Entité Réservation.
 * Cycle de vie : en_attente -> validee / refusee ; annulee par l'utilisateur ;
 * terminee une fois la date de fin dépassée.
 */
class Reservation
{
    private ?int $id_reservation = null;
    private int $id_salle;
    private int $id_utilisateur;
    private string $titre;
    private ?string $description;
    private string $date_debut;
    private string $date_fin;
    private int $nb_participants;
    private string $statut;
    private ?string $motif_refus;
    private ?int $id_validateur;
    private ?string $date_validation;
    private ?string $date_creation = null;

    public const STATUTS = ['en_attente', 'validee', 'refusee', 'annulee', 'terminee'];

    public function __construct(
        int $id_salle = 0,
        int $id_utilisateur = 0,
        string $titre = '',
        ?string $description = null,
        string $date_debut = '',
        string $date_fin = '',
        int $nb_participants = 1,
        string $statut = 'en_attente',
        ?string $motif_refus = null,
        ?int $id_validateur = null,
        ?string $date_validation = null
    ) {
        $this->id_salle        = $id_salle;
        $this->id_utilisateur  = $id_utilisateur;
        $this->titre           = $titre;
        $this->description     = $description;
        $this->date_debut      = $date_debut;
        $this->date_fin        = $date_fin;
        $this->nb_participants = $nb_participants;
        $this->statut          = $statut;
        $this->motif_refus     = $motif_refus;
        $this->id_validateur   = $id_validateur;
        $this->date_validation = $date_validation;
    }

    // ---------------------- Getters ----------------------
    public function getIdReservation(): ?int   { return $this->id_reservation; }
    public function getIdSalle(): int          { return $this->id_salle; }
    public function getIdUtilisateur(): int    { return $this->id_utilisateur; }
    public function getTitre(): string         { return $this->titre; }
    public function getDescription(): ?string  { return $this->description; }
    public function getDateDebut(): string     { return $this->date_debut; }
    public function getDateFin(): string       { return $this->date_fin; }
    public function getNbParticipants(): int   { return $this->nb_participants; }
    public function getStatut(): string        { return $this->statut; }
    public function getMotifRefus(): ?string   { return $this->motif_refus; }
    public function getIdValidateur(): ?int    { return $this->id_validateur; }
    public function getDateValidation(): ?string { return $this->date_validation; }
    public function getDateCreation(): ?string { return $this->date_creation; }

    // ---------------------- Setters ----------------------
    public function setIdReservation($v): void      { $this->id_reservation = (int)$v; }
    public function setIdSalle($v): void            { $this->id_salle = (int)$v; }
    public function setIdUtilisateur($v): void      { $this->id_utilisateur = (int)$v; }
    public function setTitre(string $v): void       { $this->titre = trim($v); }
    public function setDescription(?string $v): void{ $this->description = $v !== null ? trim($v) : null; }
    public function setDateDebut(string $v): void   { $this->date_debut = $v; }
    public function setDateFin(string $v): void     { $this->date_fin = $v; }
    public function setNbParticipants($v): void     { $this->nb_participants = (int)$v; }
    public function setStatut(string $v): void      { $this->statut = $v; }
    public function setMotifRefus(?string $v): void { $this->motif_refus = $v; }
    public function setIdValidateur($v): void       { $this->id_validateur = $v !== null ? (int)$v : null; }
    public function setDateValidation(?string $v): void { $this->date_validation = $v; }
    public function setDateCreation(?string $v): void   { $this->date_creation = $v; }

    /** Durée de la réservation en minutes. */
    public function getDureeMinutes(): int
    {
        return (int)round((strtotime($this->date_fin) - strtotime($this->date_debut)) / 60);
    }
}
