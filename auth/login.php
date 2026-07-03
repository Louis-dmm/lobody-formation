<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Le cookie de session est créé ici, à la connexion : on le durcit dès le départ
// (SameSite=Strict pour bloquer les requêtes cross-site, HttpOnly, Secure).
session_set_cookie_params([
    'samesite' => 'Strict',
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']), // Secure seulement en HTTPS (marche aussi en local http)
]);
session_start();

require_once '../config/config.php';

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $turnstile_response = $_POST['cf-turnstile-response'] ?? '';
    $secret_key = getenv('TURNSTILE_SECRET');

    // Vérification anti-robot uniquement si une clé Turnstile est configurée.
    // En local (clé vide), on saute cette étape pour permettre la connexion.
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
        curl_close($ch);

        $verify_data = json_decode($verify);

        if (!$verify_data->success) {
            die("<div class='error-message'>Erreur : Validation anti-robot échouée. Veuillez rafraîchir la page.</div>");
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        try {
            $stmtAdmin = $pdo->prepare("SELECT * FROM Admins WHERE mail = :mail");
            $stmtAdmin->execute([':mail' => $email]);
            $admin = $stmtAdmin->fetch();

            if ($admin && password_verify($password, $admin['mot_de_passe'])) {
                // Anti-fixation de session : nouvel ID après connexion
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id_admin'];
                $_SESSION['user_nom'] = $admin['prenom'] . ' ' . $admin['nom'];
                header("Location: ../admin/admin.php");
                exit();
            }

            $stmtUser = $pdo->prepare("SELECT * FROM Users WHERE mail = :mail");
            $stmtUser->execute([':mail' => $email]);
            $user = $stmtUser->fetch();

            if ($user && password_verify($password, $user['mot_de_passe'])) {
                // Anti-fixation de session : nouvel ID après connexion
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nom'] = $user['prenom'] . ' ' . $user['nom'];
                header("Location: ../user/user.php");
                exit();
            }

            $_SESSION['erreur'] = "Email ou mot de passe incorrect.";
            header("Location: ../index.html?erreur=1");
            exit();

        } catch (PDOException $e) {
            error_log("Erreur SQL : " . $e->getMessage()); http_response_code(500); die("Service momentanément indisponible. Merci de réessayer plus tard.");
        }
    }
}
?>