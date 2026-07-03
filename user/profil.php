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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profil') {
    $tel = trim($_POST['telephone'] ?? '');
    $adr = trim($_POST['adresse'] ?? '');
    $cp = trim($_POST['code_postal'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $date_naissance = !empty($_POST['date_de_naissance']) ? $_POST['date_de_naissance'] : null;

    $erreurs = [];
    if (!empty($cp)) {
        if (!preg_match('/^\d{5}$/', $cp)) {
            $erreurs[] = "Le code postal est invalide. Il doit comporter exactement 5 chiffres.";
        }
    }

    if (!empty($tel)) {
        $tel_clean = preg_replace('/[\s\-\.]/', '', $tel);
        if (!preg_match('/^(0[1-9]\d{8}|\+[1-9]\d{6,14})$/', $tel_clean)) {
            $erreurs[] = "Le format du numéro de téléphone est invalide.";
        }
    }

    if ($date_naissance) {
        $date_jour = date('Y-m-d');
        if ($date_naissance > $date_jour) {
            $erreurs[] = "La date de naissance ne peut pas être dans le futur.";
        }
    }
    
    if (empty($erreurs)) {
        $stmtUpdate = $pdo->prepare("
            UPDATE Users 
            SET telephone = :tel, 
                adresse = :adr, 
                code_postal = :cp, 
                ville = :ville, 
                date_de_naissance = :ddn 
            WHERE id = :id
        ");
        
        $success = $stmtUpdate->execute([
            ':tel' => $tel,
            ':adr' => $adr,
            ':cp' => $cp,
            ':ville' => $ville,
            ':ddn' => $date_naissance,
            ':id' => $user_id
        ]);

        if ($success) {
            $message = "<div class='alert success' style='margin-bottom: 24px;'>Les modifications ont été enregistrées avec succès.</div>";
        } else {
            $message = "<div class='alert error' style='margin-bottom: 24px;'>Une erreur est survenue lors de l'enregistrement de vos informations.</div>";
        }

    } else {
        $message = "<div class='alert error' style='margin-bottom: 24px;'>" . implode('<br>', $erreurs) . "</div>";
    }
}

try {
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}

$profil_complet = (!empty($user['telephone']) && !empty($user['adresse']) && !empty($user['ville']) && !empty($user['code_postal']) && !empty($user['date_de_naissance']));

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
    <title>Mon Profil - Lobody</title>
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
                    <a href="parametre.php" class="dropdown-item">⚙️ Paramètres</a>
                </div>
            </details>
            
            <a href="../auth/logout.php" class="logout-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Déconnexion
            </a>
        </div>
    </header>

    <main class="main-container">
        <div class="admin-style-panel" style="max-width: 800px; margin: 0 auto;">
            
            <div class="welcome-header" style="margin-bottom: 30px;">
                <h1>Mon compte</h1>
                <p>Consultez et modifiez vos coordonnées personnelles nécessaires à la gestion de votre dossier de formation.</p>
            </div>

            <?= $message ?>
            
            <?php if ($profil_complet): ?>
                <div style="background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <strong style="color: #166534; font-size: 16px;">Profil complet</strong>
                    <p style="color: #15803d; margin-top: 4px; font-size: 14px;">Toutes vos informations obligatoires sont renseignées. Vous pouvez retourner à votre tableau de bord.</p>
                </div>
            <?php else: ?>
                <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px 20px; border-radius: 8px; margin-bottom: 24px;">
                    <strong style="color: #b45309; font-size: 15px;">Informations manquantes</strong>
                    <p style="color: #d97706; margin-top: 4px; font-size: 13px;">Certains champs obligatoires ne sont pas complétés. Veuillez les renseigner pour finaliser votre dossier.</p>
                </div>
            <?php endif; ?>

            <form action="profil.php" method="POST">
                <input type="hidden" name="action" value="update_profil">
                
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label>Nom</label>
                        <input type="text" value="<?= htmlspecialchars($user['nom'] ?? '') ?>" disabled style="background-color: #f3f4f6; color: #6b7280; cursor: not-allowed";>
                    </div>
                    
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" value="<?= htmlspecialchars($user['prenom'] ?? '') ?>" disabled style="background-color: #f3f4f6; color: #6b7280; cursor: not-allowed";>
                    </div>
                    
                    <div class="form-group">
                        <label>Adresse e-mail</label>
                        <input type="email" value="<?= htmlspecialchars($user['mail'] ?? '') ?>" disabled style="background-color: #f3f4f6; color: #6b7280; cursor: not-allowed;">
                    </div>
                    
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="telephone" value="<?= htmlspecialchars($user['telephone'] ?? '') ?>" placeholder="Ex: +33 6 12 34 56 78">
                    </div>
                    
                    <div class="form-group full-width" style="align-items: center; margin-top: 10px; margin-bottom: 10px;">
                        <label>Date de naissance</label>
                        <input type="date" name="date_de_naissance" value="<?= htmlspecialchars($user['date_de_naissance'] ?? '') ?>">
                    </div>

                    <div class="form-group full-width">
                        <label>Adresse postale</label>
                        <input type="text" name="adresse" value="<?= htmlspecialchars($user['adresse'] ?? '') ?>" placeholder="Ex: 12 rue des Fleurs">
                    </div>
                    
                    <div class="form-group">
                        <label>Code postal</label>
                        <input type="text" name="code_postal" value="<?= htmlspecialchars($user['code_postal'] ?? '') ?>" placeholder="Ex: 75001">
                    </div>
                    
                    <div class="form-group">
                        <label>Ville</label>
                        <input type="text" name="ville" value="<?= htmlspecialchars($user['ville'] ?? '') ?>" placeholder="Ex: Paris">
                    </div>

                </div>

                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 16px; margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
                    <a href="user.php" style="color: #4b5563; text-decoration: none; font-weight: 500; font-size: 14px;">Retour</a>
                    <button type="submit" class="btn-submit" style="margin: 0;">Enregistrer les modifications</button>
                </div>

            </form>

        </div>
    </main>

</body>
</html>