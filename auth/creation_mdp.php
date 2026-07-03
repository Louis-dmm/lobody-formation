<?php
require_once '../config/config.php';

$erreur = false;
$succes = false;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $pdo->prepare("SELECT * FROM Users WHERE reset_token = :token AND reset_expires > NOW()");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        die("<h2>Ce lien est invalide ou a expiré. Veuillez contacter l'administrateur.</h2>");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nouveau_mdp = $_POST['nouveau_mdp'];
        $confirmation_mdp = $_POST['confirmation_mdp'];
        
        if ($nouveau_mdp === $confirmation_mdp) {
            $mdp_hashe = password_hash($nouveau_mdp, PASSWORD_DEFAULT);

            $update = $pdo->prepare("UPDATE Users SET mot_de_passe = :mdp, reset_token = NULL, reset_expires = NULL WHERE id = :id");
            $update->execute([
                ':mdp' => $mdp_hashe,
                ':id' => $user['id']
            ]);
            $succes = true;
        }else{
            $erreur = true;
        }   
    }
} else {
    die("Accès refusé.");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Créer votre mot de passe - Lobody Formation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
                <h1>Création de votre mot de passe</h1>

                <form method="POST" action="creation_mdp.php?token=<?php echo htmlspecialchars($token); ?>">
                    <div class="input-group">
                        <label for="confirmation_mdp">Votre mot de passe :</label>
                        <div class="input-wrapper">
                            <svg class="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            <input type="password" name="nouveau_mdp" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div class="input-group">
                            <label for="password">Confirmer le mot de passe :</label>
                            <div class="input-wrapper">
                                <svg class="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                <input type="password" id="confirmation_mdp" name="confirmation_mdp" placeholder="••••••••" required>
                            </div>
                    </div>

                    <button type="submit" class="submit-btn">Activer mon compte</button>
                </form>

                <?php if ($succes): ?>
                    <div class='success-message'>
                        Votre mot de passe a été crée !
                    </div>
                    <a href="../index.html" class="forgot-password">Cliquez ici pour vous connecter</a>
                <?php endif; ?>

                <?php if ($erreur): ?>
                    <div class='error-message'>Les mots de passe ne correspondent pas.</div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>