<?php
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

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {

    $turnstile_response = $_POST['cf-turnstile-response'] ?? '';
    $secret_key = getenv('TURNSTILE_SECRET');

    // Vérification anti-robot uniquement si une clé Turnstile est configurée.
    if (!empty($secret_key)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://challenges.cloudflare.com/turnstile/v0/siteverify");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'secret' => $secret_key,
            'response' => $turnstile_response
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $verify = curl_exec($ch);

        $verify_data = $verify ? json_decode($verify) : null;

        if (!$verify_data || empty($verify_data->success)) {
            die("<div class='error-message'>Erreur : Validation anti-robot échouée. Veuillez rafraîchir la page.</div>");
        }
    }
    
    $email = trim($_POST['email']);
    
    $stmt = $pdo->prepare("SELECT id, prenom, nom FROM Users WHERE mail = :mail");
    $stmt->execute([':mail' => $email]);
    $user = $stmt->fetch();
    
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+3 hour'));

        $update = $pdo->prepare("
            UPDATE Users 
            SET reset_token = :token, reset_expires = :expires 
            WHERE id = :id
        ");
        $update->execute([
            ':token' => $token,
            ':expires' => $expires,
            ':id' => $user['id']
        ]);

        $lien_reinitialisation = rtrim(getenv('APP_URL'), '/') . "/auth/reinitialisation.php?token=" . $token;
        
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
            $mail->addAddress($email, $user['prenom'] . ' ' . $user['nom']);

            $mail->isHTML(true);

            $stmtTpl = $pdo->prepare("SELECT sujet, contenu_html FROM Email_Templates WHERE code_contexte = 'mdp_oublie'");
            $stmtTpl->execute();
            $gabarit = $stmtTpl->fetch();

            if ($gabarit) {
                $sujet_final = $gabarit['sujet'];
                $corps_final = $gabarit['contenu_html'];

                $balises = ['{{prenom}}', '{{lien_reinitialisation}}'];
                $valeurs = [htmlspecialchars($user['prenom']), $lien_reinitialisation];
                
                $sujet_final = str_replace($balises, $valeurs, $sujet_final);
                $corps_final = str_replace($balises, $valeurs, $corps_final);

                $mail->Subject = $sujet_final;
                $mail->Body    = $corps_final;
                $mail->AltBody = strip_tags($corps_final);

                $mail->send();
            }

            $mail->send();
            
            $message = "<div class='success-message'>Si cette adresse email existe dans notre système, un lien de réinitialisation vient de vous être envoyé.</div>";
            
        } catch (Exception $e) {
            $message = "<div class='error-message'><strong>Erreur technique :</strong> L'email n'a pas pu être envoyé. Erreur : {$mail->ErrorInfo}</div>";
        }
    } else {
        $message = "<div class='success-message'>Si cette adresse email existe dans notre système, un lien de réinitialisation vient de vous être envoyé.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../assets/css/style.css">
        <title>Page de réinitialisation de mot de passe</title>
        <link rel="icon" type="image/png" href="../assets/img/icone.png">
    </head>
    <body class="body_ok">
        <main class="login-container">
        <section class="glass-card">
            
            <header class="brand-header">
                <img src="../assets/img/logo-formation-bleu-Copie-e1739893739311.png" alt="Logo Lobody Formation" class="logo">
                <div class="decorative-bar" aria-hidden="true"></div>
            </header>

            <div class="form-section">
                <h1>Réinitialiser votre mot de passe</h1>
                
                <form action="mdp_oublie.php" method="POST">
                    
                    <div class="input-group">
                        <label for="email">Adresse email</label>
                        <div class="input-wrapper">
                            <svg class="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            <input type="email" id="email" name="email" placeholder="Ex: jean.dupont@email.com" required>
                        </div>
                    </div>

                    <div class="input-group" style="display: flex; justify-content: center;">
                        <div class="cf-turnstile" data-sitekey="0x4AAAAAADI3oUryPnZZodpe" data-theme="light"></div>
                    </div>

                    <button type="submit" class="submit-btn">Recevoir le lien</button>
                </form>

                <?php if (!empty($message)): ?>
                    <?php echo $message; ?>
                <?php endif; ?>

                <a href="../index.html" class="forgot-password">⬅ Retour à la connexion</a>
            </div>
        </section>
        </main>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    </body>
</html>