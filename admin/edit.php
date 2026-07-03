<?php
// 1. CONFIGURATION ET SÉCURITÉ
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

require_once '../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../includes/PHPMailer/Exception.php';
require __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
require __DIR__ . '/../includes/PHPMailer/SMTP.php';

// Redirection si non connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    
    if (isset($_POST['action_bouton']) && $_POST['action_bouton'] === 'actualiser') {

        $lieu_temporaire = (int)$_POST['id_lieu'];
        $date_debut_temporaire = $_POST['date_debut'];
        $date_fin_temporaire = $_POST['date_fin'];

    } else {
        // Nettoyage strict des entrées
        $id_client = (int) $_POST['id'];
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $date_debut = $_POST['date_debut'];
        $date_fin = $_POST['date_fin'];
        $nouvel_email = trim($_POST['email']);
        $nouvelle_session = !empty($_POST['id_session']) ? (int)$_POST['id_session'] : null;

        // Est-ce que l'examen choisi est bien APRÈS la date de début ?
        if (!empty($nouvelle_session)) {
            $stmtCheck = $pdo->prepare("SELECT date_passage_examen FROM session WHERE id_session = :id_sess");
            $stmtCheck->execute([':id_sess' => $nouvelle_session]);
            $date_examen = $stmtCheck->fetchColumn();
            
            // Si l'examen a lieu avant ou le jour même du début de formation -> Annulation (NULL)
            if ($date_examen && $date_examen <= $date_debut) {
                $nouvelle_session = null; 
            }
        }

        // Vérification de l'ancien email pour savoir si on doit renvoyer un token
        $stmt = $pdo->prepare("SELECT mail FROM Users WHERE id = :id");
        $stmt->execute([':id' => $id_client]);
        $ancien_client = $stmt->fetch();

        if ($ancien_client) {
            if ($ancien_client['mail'] !== $nouvel_email) {
                
                // Génération de la sécurité
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));

                // Mise à jour complète avec suppression de l'ancien mot de passe
                $update = $pdo->prepare("
                    UPDATE Users 
                    SET nom = :nom, prenom = :prenom, mail = :mail, 
                        reset_token = :token, reset_expires = :expires, mot_de_passe = NULL 
                    WHERE id = :id
                ");

                $update->execute([
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':mail' => $nouvel_email,
                    ':token' => $token,
                    ':expires' => $expires,
                    ':id' => $id_client
                ]);

                // Envoi de l'email
                $lien_creation = rtrim(getenv('APP_URL'), '/') . "/auth/creation_mdp.php?token=" . $token;
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = getenv('SMTP_HOST');
                    $mail->SMTPAuth   = true;
                    $mail->Username   = getenv('SMTP_USERNAME');
                    $mail->Password   = getenv('SMTP_PASSWORD');
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
                    $mail->Port       = 587; 
                    $mail->CharSet    = 'UTF-8';

                    $mail->setFrom(getenv('MAIL_FROM'), 'Formation Lobody Perfect');
                    $mail->addAddress($nouvel_email, $prenom . ' ' . $nom);

                    $mail->isHTML(true);

                    $stmtTpl = $pdo->prepare("SELECT sujet, contenu_html FROM Email_Templates WHERE code_contexte = 'mise_a_jour_email'");
                    $stmtTpl->execute();
                    $gabarit = $stmtTpl->fetch();

                    if ($gabarit) {
                        $sujet_final = $gabarit['sujet'];
                        $corps_final = $gabarit['contenu_html'];

                        $balises = ['{{prenom}}', '{{lien_creation}}'];
                        $valeurs = [htmlspecialchars($prenom), $lien_creation];
                        
                        $sujet_final = str_replace($balises, $valeurs, $sujet_final);
                        $corps_final = str_replace($balises, $valeurs, $corps_final);

                        $mail->Subject = $sujet_final;
                        $mail->Body    = $corps_final;
                        $mail->AltBody = strip_tags($corps_final);

                        $mail->send();
                    }

                    $message = "<div class='ep-alert success'>✅ Profil mis à jour. Une invitation a été envoyée à {$nouvel_email}.</div>";
                    
                } catch (Exception $e) {
                    $message = "<div class='ep-alert error'>⚠️ Profil mis à jour, mais l'email automatique n'a pas pu être envoyé.</div>";
                }

            } else {

                $update = $pdo->prepare("UPDATE Users SET nom = :nom, prenom = :prenom WHERE id = :id");
                $update->execute([
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':id' => $id_client
                ]);
            }
            try {
                $update_inscription = $pdo->prepare("UPDATE inscription SET id_session = :id_sess, date_debut_formation = :date_deb, date_fin_formation = :date_fin WHERE id_utilisateur = :id");
                $update_inscription->execute([
                    ':id_sess' => $nouvelle_session,
                    ':date_deb' => $date_debut,
                    ':date_fin' => $date_fin,
                    ':id' => $id_client
                ]);
            } catch (PDOException $e) {
                die("<div>
                        <strong>🛑 Erreur SQL bloquante :</strong><br>" . $e->getMessage() . "
                    </div>");
            }

            if (empty($message)) {
                header("Location: admin.php?msg=edit_success&tab=clients");
                exit();
            }
        }
    }
}

// ========================================================
// PHASE : RÉCUPÉRATION COMPLÈTE DU CLIENT (GET)
// ========================================================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admin.php");
    exit();
}

$id_get = (int) $_GET['id'];

$stmt = $pdo->prepare("
    SELECT Users.*, 
           inscription.id_session, 
           inscription.date_debut_formation,
           inscription.date_fin_formation,
           session.id_lieu 
    FROM Users 
    LEFT JOIN inscription ON Users.id = inscription.id_utilisateur 
    LEFT JOIN session ON inscription.id_session = session.id_session 
    WHERE Users.id = :id
");
$stmt->execute([':id' => $id_get]);
$client = $stmt->fetch();

if (!$client) {
    die("Client introuvable dans la base de données.");
}

// ========================================================
// PRÉPARATION DES LISTES DÉROULANTES
// ========================================================
$lieux_existants = $pdo->query("SELECT * FROM lieux ORDER BY ville ASC")->fetchAll();
$sessions_disponibles = [];

$lieu_recherche = isset($lieu_temporaire) ? $lieu_temporaire : $client['id_lieu'];
$date_recherche = isset($date_debut_temporaire) ? $date_debut_temporaire : $client['date_debut_formation'];

if(!empty($lieu_recherche)){
    $stmtDates = $pdo->prepare("
        SELECT id_session, date_passage_examen
        FROM session
        WHERE id_lieu = :id_lieu
        AND id_formation = :id_form
        AND date_passage_examen > :date_debut
        ORDER BY date_passage_examen ASC
    ");

    $stmtDates->execute([
        ':id_lieu' => $lieu_recherche,
        ':id_form' => $client['id_formation'],
        ':date_debut' => $date_recherche
    ]);
    
    $sessions_disponibles = $stmtDates->fetchAll();
}

// Astuce visuelle pour garder la ville sélectionnée à l'écran
if (isset($lieu_temporaire)) {
    $client['id_lieu'] = $lieu_temporaire;
    $client['date_debut_formation'] = $date_debut_temporaire;
    $client['date_fin_formation'] = $date_fin_temporaire;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le profil - Lobody</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/img/icone.png">
</head>
<body class="ep-page-wrapper">

    <main class="ep-main-container">
        <section class="ep-card">
            
            <a href="admin.php?tab=clients" class="ep-link-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 5px;"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Retour au Panel
            </a>

            <header class="ep-card-header">
                <div class="ep-icon-wrapper">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
                
                <h2 class="ep-title">Modifier le profil</h2>
                <p class="ep-subtitle">Édition de : <strong><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></strong></p>
            </header>

            <form action="edit.php?id=<?= $client['id'] ?>" method="POST" class="ep-form">
                <input type="hidden" name="id" value="<?= $client['id'] ?>">
                
                <div class="ep-form-group">
                    <label class="ep-label" for="nom">Nom de famille</label>
                    <input type="text" id="nom" name="nom" class="ep-input" value="<?= htmlspecialchars($client['nom']) ?>" required>
                </div>
                
                <div class="ep-form-group">
                    <label class="ep-label" for="prenom">Prénom</label>
                    <input type="text" id="prenom" name="prenom" class="ep-input" value="<?= htmlspecialchars($client['prenom']) ?>" required>
                </div>
                
                <div class="ep-form-group">
                    <label class="ep-label" for="email">Adresse email</label>
                    <input type="email" id="email" name="email" class="ep-input" value="<?= htmlspecialchars($client['mail']) ?>" required>
                    <small class="ep-input-helper">⚠️ Changer l'email réinitialisera l'accès du client.</small>
                </div>

                <div class="ep-form-group" style="margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
                    <label class="ep-label" for="date_debut">Date de début de formation</label>
                    <input type="date" id="date_debut" name="date_debut" class="ep-input" value="<?= htmlspecialchars($client['date_debut_formation'] ?? '') ?>" required>
                </div>

                <div class="ep-form-group">
                    <label class="ep-label" for="date_fin">Date de fin de formation</label>
                    <input type="date" id="date_fin" name="date_fin" class="ep-input" value="<?= htmlspecialchars($client['date_fin_formation'] ?? '') ?>" required>
                </div>

                <div class="ep-form-group" style="margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
                    <label class="ep-label" for="id_lieu">Lieu de la formation</label>
                    <select name="id_lieu" id="id_lieu" class="ep-input">
                        <option value="">-- Sélectionner la ville --</option>
                        <?php foreach($lieux_existants as $lieu): ?>
                            <option value="<?= $lieu['id_lieu'] ?>" <?= ($lieu['id_lieu'] == $client['id_lieu']) ? 'selected' : '' ?>>
                                📍 <?= htmlspecialchars($lieu['ville']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="ep-form-group">
                    <button type="submit" name="action_bouton" value="actualiser" class="ep-btn-submit" style="background-color: #4b5563; padding: 10px; font-size: 14px; box-shadow: none;">
                        Actualiser les dates pour cette ville
                    </button>
                </div>

                <div class="ep-form-group">
                    <label class="ep-label" for="id_session">Date d'épreuve</label>
                    <select name="id_session" id="id_session" class="ep-input">
                        <?php if(count($sessions_disponibles) > 0): ?>
                            <option value="">-- Choisir une date --</option>
                            <?php foreach($sessions_disponibles as $session): ?>
                                <option value="<?= $session['id_session'] ?>" <?= ($session['id_session'] == $client['id_session']) ? 'selected' : '' ?>>
                                    🎓 Épreuve du : <?= date('d/m/Y', strtotime($session['date_passage_examen'])) ?>
                                </option>
                            <?php endforeach ?>
                        <?php else: ?>
                            <option value="" disabled>⚠️ Aucune session d'évaluation n'est programmée pour ce lieu !</option>
                        <?php endif ?>
                    </select>
                </div>

                <button type="submit" class="ep-btn-submit" style="margin-top: 30px;">Enregistrer les modifications</button>
            </form>

            <?php if (!empty($message)): ?>
                <?= $message; ?>
            <?php endif; ?>

        </section>
    </main>

</body>
</html>