<?php
require_once dirname(__DIR__) . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Système de notifications par email.
 *
 * Deux niveaux :
 *   1. PHPMailer en SMTP (si MAIL_ACTIF = true et la librairie est présente)
 *   2. journalisation en base dans la table notification
 *
 * Le journal est TOUJOURS écrit : la soutenance peut donc montrer les
 * notifications même si le serveur SMTP n'est pas disponible sous XAMPP.
 *
 * ---------------------------------------------------------------------
 * CONFIGURATION GMAIL (à faire avant la démonstration) :
 *   - mettre MAIL_ACTIF à true ci-dessous
 *   - SMTP_USER  : votre adresse Gmail
 *   - SMTP_PASS  : un « mot de passe d'application » Google (16 caractères),
 *                  pas le mot de passe du compte
 * ---------------------------------------------------------------------
 */
class MailC
{
    public  const MAIL_ACTIF = true;
    private const SMTP_HOST  = 'smtp.gmail.com';
    private const SMTP_PORT  = 587;
    private const FROM_NAME  = 'ReservaSalles';

    /**
     * Identifiants SMTP chargés depuis un fichier NON versionné : config.mail.php
     * (racine du projet, voir .gitignore). Aucun mot de passe en dur dans le code.
     * Fichier absent ou vide => l'envoi réel est ignoré, les emails restent journalisés.
     */
    private static ?array $identifiants = null;
    private static function identifiants(): array
    {
        if (self::$identifiants === null) {
            $fichier = dirname(__DIR__) . '/config.mail.php';
            $conf = is_file($fichier) ? require $fichier : [];
            self::$identifiants = [
                'user' => is_array($conf) ? trim((string)($conf['user'] ?? '')) : '',
                'pass' => is_array($conf) ? (string)($conf['pass'] ?? '') : '',
            ];
        }
        return self::$identifiants;
    }

    // =================================================================
    //  ENVOI GÉNÉRIQUE
    // =================================================================
    public function envoyer(string $destinataire, string $sujet, string $corpsHtml,
                            string $type = 'demande', ?int $idUtilisateur = null,
                            ?int $idReservation = null): bool
    {
        $envoye = false;

        if (self::MAIL_ACTIF) {
            $envoye = $this->envoyerViaPHPMailer($destinataire, $sujet, $corpsHtml);
        }

        $this->journaliser($destinataire, $sujet, $corpsHtml, $type, $envoye, $idUtilisateur, $idReservation);
        return $envoye;
    }

    private function envoyerViaPHPMailer(string $destinataire, string $sujet, string $corpsHtml): bool
    {
        if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
            error_log('Email destinataire invalide : ' . $destinataire);
            return false;
        }

        $base = dirname(__DIR__) . '/PHPMailer/src/';
        if (!file_exists($base . 'PHPMailer.php')) {
            return false;
        }
        require_once $base . 'Exception.php';
        require_once $base . 'PHPMailer.php';
        require_once $base . 'SMTP.php';

        $ids = self::identifiants();
        if ($ids['user'] === '' || $ids['pass'] === '') {
            error_log('MailC : identifiants SMTP absents ou vides (config.mail.php) — envoi réel ignoré.');
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = self::SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = $ids['user'];
            $mail->Password   = $ids['pass'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = self::SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => true,
                    'verify_peer_name'  => true,
                    'allow_self_signed' => false,
                ],
            ];

            $mail->setFrom($ids['user'], self::FROM_NAME);
            $mail->addAddress($destinataire);
            $mail->isHTML(true);
            $mail->Subject = $sujet;
            $mail->Body    = $corpsHtml;
            $mail->AltBody = strip_tags($corpsHtml);

            return $mail->send();
        } catch (PHPMailerException $e) {
            error_log('Erreur PHPMailer : ' . $e->getMessage() . ' | ' . $mail->ErrorInfo);
            return false;
        } catch (Throwable $e) {
            error_log('Erreur mail : ' . $e->getMessage());
            return false;
        }
    }

    private function journaliser(string $destinataire, string $sujet, string $message,
                                 string $type, bool $envoye, ?int $idUtilisateur,
                                 ?int $idReservation): void
    {
        try {
            $db  = config::getConnexion();
            $req = $db->prepare('INSERT INTO notification
                (id_utilisateur, id_reservation, destinataire, sujet, message, type, envoye)
                VALUES (:user, :res, :dest, :sujet, :msg, :type, :envoye)');
            $req->execute([
                'user'   => $idUtilisateur,
                'res'    => $idReservation,
                'dest'   => $destinataire,
                'sujet'  => $sujet,
                'msg'    => $message,
                'type'   => $type,
                'envoye' => $envoye ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            error_log('Erreur journal notification : ' . $e->getMessage());
        }
    }

    // =================================================================
    //  GABARIT HTML COMMUN
    // =================================================================
    private function gabarit(string $titre, string $contenu, string $couleur = '#2563eb'): string
    {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:24px;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;">
          <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:auto;background:#ffffff;border-radius:12px;overflow:hidden;">
            <tr><td style="background:' . $couleur . ';padding:24px;color:#ffffff;">
                <h1 style="margin:0;font-size:20px;">ReservaSalles</h1>
                <p style="margin:6px 0 0;font-size:14px;opacity:.9;">' . htmlspecialchars($titre) . '</p>
            </td></tr>
            <tr><td style="padding:28px;color:#1e293b;font-size:15px;line-height:1.6;">' . $contenu . '</td></tr>
            <tr><td style="padding:18px 28px;background:#f8fafc;color:#64748b;font-size:12px;">
                Message automatique — merci de ne pas y répondre.
            </td></tr>
          </table>
        </body></html>';
    }

    private function blocReservation(array $r): string
    {
        return '<table cellpadding="8" cellspacing="0" style="width:100%;background:#f8fafc;border-radius:8px;margin:16px 0;font-size:14px;">
            <tr><td style="color:#64748b;">Objet</td><td><strong>' . htmlspecialchars($r['titre']) . '</strong></td></tr>
            <tr><td style="color:#64748b;">Salle</td><td>' . htmlspecialchars($r['nom_salle'] . ' (' . $r['code_salle'] . ')') . '</td></tr>
            <tr><td style="color:#64748b;">Bâtiment</td><td>' . htmlspecialchars($r['nom_batiment']) . '</td></tr>
            <tr><td style="color:#64748b;">Début</td><td>' . date('d/m/Y à H:i', strtotime($r['date_debut'])) . '</td></tr>
            <tr><td style="color:#64748b;">Fin</td><td>' . date('d/m/Y à H:i', strtotime($r['date_fin'])) . '</td></tr>
            <tr><td style="color:#64748b;">Participants</td><td>' . (int)$r['nb_participants'] . '</td></tr>
        </table>';
    }

    // =================================================================
    //  NOTIFICATIONS MÉTIER
    // =================================================================
    /** Accusé de réception d'une demande. */
    public function notifierDemande(array $r): bool
    {
        $contenu = '<p>Bonjour ' . htmlspecialchars($r['prenom_utilisateur']) . ',</p>
            <p>Votre demande de réservation a bien été enregistrée. Elle sera examinée
            par un gestionnaire, qui vous répondra prochainement.</p>'
            . $this->blocReservation($r)
            . '<p>Statut actuel : <strong>en attente de validation</strong>.</p>';

        return $this->envoyer(
            $r['email_utilisateur'],
            'Demande de réservation enregistrée — ' . $r['nom_salle'],
            $this->gabarit('Demande enregistrée', $contenu, '#f59e0b'),
            'demande',
            (int)$r['id_utilisateur'],
            (int)$r['id_reservation']
        );
    }

    /** Confirmation après validation par le gestionnaire. */
    public function notifierValidation(array $r): bool
    {
        $contenu = '<p>Bonjour ' . htmlspecialchars($r['prenom_utilisateur']) . ',</p>
            <p>Bonne nouvelle : votre réservation a été <strong>confirmée</strong>.</p>'
            . $this->blocReservation($r)
            . '<p>Merci de prévenir le gestionnaire si vous ne pouvez plus occuper la salle.</p>';

        return $this->envoyer(
            $r['email_utilisateur'],
            'Réservation confirmée — ' . $r['nom_salle'],
            $this->gabarit('Réservation confirmée', $contenu, '#16a34a'),
            'validation',
            (int)$r['id_utilisateur'],
            (int)$r['id_reservation']
        );
    }

    /** Notification de refus avec motif. */
    public function notifierRefus(array $r, string $motif): bool
    {
        $contenu = '<p>Bonjour ' . htmlspecialchars($r['prenom_utilisateur']) . ',</p>
            <p>Votre demande de réservation n\'a pas pu être acceptée.</p>'
            . $this->blocReservation($r)
            . '<p><strong>Motif :</strong> ' . htmlspecialchars($motif) . '</p>
            <p>Vous pouvez soumettre une nouvelle demande sur un autre créneau.</p>';

        return $this->envoyer(
            $r['email_utilisateur'],
            'Réservation refusée — ' . $r['nom_salle'],
            $this->gabarit('Demande refusée', $contenu, '#dc2626'),
            'refus',
            (int)$r['id_utilisateur'],
            (int)$r['id_reservation']
        );
    }

    /** Notification d'annulation. */
    public function notifierAnnulation(array $r): bool
    {
        $contenu = '<p>Bonjour ' . htmlspecialchars($r['prenom_utilisateur']) . ',</p>
            <p>La réservation suivante a été annulée.</p>'
            . $this->blocReservation($r);

        return $this->envoyer(
            $r['email_utilisateur'],
            'Réservation annulée — ' . $r['nom_salle'],
            $this->gabarit('Réservation annulée', $contenu, '#64748b'),
            'annulation',
            (int)$r['id_utilisateur'],
            (int)$r['id_reservation']
        );
    }

    /** Notification de déplacement de réunion (changement de salle ou d'horaire). */
    public function notifierDeplacement(array $r, string $ancienCreneau, string $ancienneSalle): bool
    {
        $contenu = '<p>Bonjour ' . htmlspecialchars($r['prenom_utilisateur']) . ',</p>
            <p>Votre réunion a été déplacée par le gestionnaire des réservations.</p>
            <p style="color:#64748b;">Auparavant : ' . htmlspecialchars($ancienneSalle) . ' — ' . htmlspecialchars($ancienCreneau) . '</p>
            <p><strong>Nouveau créneau :</strong></p>'
            . $this->blocReservation($r);

        return $this->envoyer(
            $r['email_utilisateur'],
            'Réunion déplacée — ' . $r['titre'],
            $this->gabarit('Réunion déplacée', $contenu, '#7c3aed'),
            'deplacement',
            (int)$r['id_utilisateur'],
            (int)$r['id_reservation']
        );
    }

    /** Alerte envoyée aux gestionnaires à chaque nouvelle demande. */
    public function alerterGestionnaires(array $emails, array $r): void
    {
        $contenu = '<p>Une nouvelle demande de réservation attend votre validation.</p>'
            . $this->blocReservation($r)
            . '<p>Demandeur : <strong>' . htmlspecialchars($r['prenom_utilisateur'] . ' ' . $r['nom_utilisateur']) . '</strong></p>';

        foreach ($emails as $email) {
            $this->envoyer(
                $email,
                'Nouvelle demande à traiter — ' . $r['nom_salle'],
                $this->gabarit('Demande à traiter', $contenu, '#2563eb'),
                'demande',
                null,
                (int)$r['id_reservation']
            );
        }
    }

    // =================================================================
    //  JOURNAL
    // =================================================================
    public function getNotifications(string $type = 'tous', int $limite = 50): array
    {
        $db  = config::getConnexion();
        $sql = 'SELECT n.*, u.nom, u.prenom
                FROM notification n
                LEFT JOIN utilisateur u ON n.id_utilisateur = u.id_utilisateur';

        // Liste blanche : le type vient de l'URL, il n'est jamais concaténé tel quel.
        $types = ['demande', 'validation', 'refus', 'annulation', 'deplacement', 'rappel'];
        $filtre = in_array($type, $types, true);
        if ($filtre) {
            $sql .= ' WHERE n.type = :type';
        }
        $sql .= ' ORDER BY n.date_envoi DESC LIMIT :lim';

        $req = $db->prepare($sql);
        if ($filtre) {
            $req->bindValue(':type', $type, PDO::PARAM_STR);
        }
        $req->bindValue(':lim', $limite, PDO::PARAM_INT);
        $req->execute();
        return $req->fetchAll();
    }

    public function getNotificationsUtilisateur(int $idUtilisateur, int $limite = 20): array
    {
        $db  = config::getConnexion();
        $req = $db->prepare('SELECT * FROM notification
                             WHERE id_utilisateur = :user
                             ORDER BY date_envoi DESC LIMIT :lim');
        $req->bindValue(':user', $idUtilisateur, PDO::PARAM_INT);
        $req->bindValue(':lim', $limite, PDO::PARAM_INT);
        $req->execute();
        return $req->fetchAll();
    }
}
