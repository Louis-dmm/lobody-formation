<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once '../config/config.php'; 
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Traitement du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    
    $ancien_mdp = $_POST['ancien_password'] ?? '';
    $nouveau_mdp = $_POST['nouveau_password'] ?? '';
    $confirm_mdp = $_POST['confirm_password'] ?? '';

    $erreurs = [];

    if (empty($ancien_mdp) || empty($nouveau_mdp) || empty($confirm_mdp)) {
        $erreurs[] = "Tous les champs sont obligatoires.";
    } else {
        if ($nouveau_mdp !== $confirm_mdp) {
            $erreurs[] = "Les nouveaux mots de passe ne correspondent pas.";
        }
    }

    if (empty($erreurs)) {
        $stmtCheck = $pdo->prepare("SELECT mot_de_passe FROM Users WHERE id = :id");
        $stmtCheck->execute([':id' => $user_id]);
        $user_db = $stmtCheck->fetch();

        if (password_verify($ancien_mdp, $user_db['mot_de_passe'])) {
            
            $nouveau_hash = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
            
            $stmtUpdate = $pdo->prepare("UPDATE Users SET mot_de_passe = :mdp WHERE id = :id");
            $success = $stmtUpdate->execute([
                ':mdp' => $nouveau_hash,
                ':id' => $user_id
            ]);

            if ($success) {
                $message = "<div class='alert success' style='margin-bottom: 24px;'>Votre mot de passe a été mis à jour avec succès.</div>";
            } else {
                $message = "<div class='alert error' style='margin-bottom: 24px;'>Une erreur est survenue lors de la mise à jour.</div>";
            }
        } else {
            $erreurs[] = "L'ancien mot de passe est incorrect.";
        }
    }

    if (!empty($erreurs)) {
        $message = "<div class='alert error' style='margin-bottom: 24px;'>" . implode('<br>', $erreurs) . "</div>";
    }
}

try {
    $stmt = $pdo->prepare("SELECT nom, prenom FROM Users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}

function genererInitiale($nom_de_famille){
    $nom_propre = trim($nom_de_famille);
    $mots = explode(" ", $nom_propre);
    $initiale = "";
    foreach($mots as $mot){
        if (!empty($mot)) {
            $initiale .= $mot[0];
        }
    }
    return strtoupper($initiale) . ".";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Lobody</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/img/icone.png">
</head>
<body class="page-body">

    <header class="top-navbar">
        <a href="user.php" class="logo-area" style="text-decoration: none;">
            <img src="../assets/img/icone.png" alt="Logo Lobody" class="sidebar-logo" style="width: 32px; height: 32px; object-fit: contain; filter: brightness(0) invert(1);">
            Espace Client
        </a>
        <div class="user-controls">
            <details class="profile-dropdown">
                <summary class="profile-summary">
                    <span class="user-prenom"><?= htmlspecialchars($user['prenom'] ?? 'Client') . " " . htmlspecialchars(genererInitiale($user['nom'] ?? ''))?></span>
                    <svg class="dropdown-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </summary>
                
                <div class="profile-menu-inline">
                    <a href="user.php" class="dropdown-item">Tableau de bord</a>
                    <a href="profil.php" class="dropdown-item">Mon compte</a>
                </div>
            </details>
            
            <a href="../auth/logout.php" class="logout-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Déconnexion
            </a>
        </div>
    </header>

    <main class="main-container">
        <div class="admin-style-panel" style="max-width: 600px; margin: 0 auto;">
            
            <div class="welcome-header" style="margin-bottom: 30px;">
                <h1>Paramètres de sécurité</h1>
                <p>Modifiez votre mot de passe pour sécuriser l'accès à votre espace de formation.</p>
            </div>

            <?= $message ?>
            
            <form action="parametre.php" method="POST">
                <input type="hidden" name="action" value="update_password">
                
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    
                    <div class="form-group" style="margin: 0;">
                        <label>Ancien mot de passe</label>
                        <input type="password" name="ancien_password" required placeholder="Saisissez votre mot de passe actuel">
                    </div>
                    
                    <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 10px 0;">

                    <div class="form-group" style="margin: 0;">
                        <label>Nouveau mot de passe</label>
                        <input type="password" name="nouveau_password" required placeholder="Votre nouveau mot de passe">
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label>Confirmer le nouveau mot de passe</label>
                        <input type="password" name="confirm_password" required placeholder="Retapez le nouveau mot de passe">
                    </div>

                </div>

                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 16px; margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
                    <a href="user.php" style="color: #4b5563; text-decoration: none; font-weight: 500; font-size: 14px;">Retour</a>
                    <button type="submit" class="btn-submit" style="margin: 0;">Mettre à jour le mot de passe</button>
                </div>

            </form>

        </div>
    </main>

</body>
</html>