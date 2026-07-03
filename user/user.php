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

if (isset($_GET['msg']) && $_GET['msg'] === 'uploaded') {
    $message = "<div class='alert success' style='margin-bottom: 20px;'>✅ Document envoyé avec succès ! En attente de validation.</div>";
}

try {
    $stmt = $pdo->prepare("
    SELECT u.*, 
           f.titre as formation_titre, 
           u.note_qcm, 
           u.chemin_diplome,
           i.date_debut_formation,
           i.date_fin_formation,
           s.date_passage_examen
    FROM Users u 
    LEFT JOIN Formation f ON u.id_formation = f.id_formation 
    LEFT JOIN inscription i ON u.id = i.id_utilisateur
    LEFT JOIN session s ON i.id_session = s.id_session
    WHERE u.id = :id
    ");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();

    //Détecteur de profil incomplet
    $profil_incomplet = false;
    if (empty($user['telephone']) || empty($user['adresse']) || empty($user['ville']) || empty($user['code_postal']) || empty($user['date_de_naissance'])) {
        $profil_incomplet = true;
    }

    $requirements = [];
    if (!empty($user['id_formation'])) {
        $stmtReq = $pdo->prepare("SELECT * FROM Formation_Requirements WHERE id_formation = :id_form");
        $stmtReq->execute([':id_form' => $user['id_formation']]);
        $requirements = $stmtReq->fetchAll();
    }

    // TRAITEMENT D'ENVOI MULTIPLE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['documents'])) {
        
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $upload_dir = '../uploads/documents/'; 
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

        $fichiers_envoyes = 0;
        $erreurs_envoi = false;

        foreach ($_FILES['documents']['tmp_name'] as $req_id => $tmp_name) {
            
            if (empty($tmp_name)) {
                continue;
            }

            // Sécurité : l'identifiant du document (clé du champ) doit être un entier.
            // Sans ce contrôle, une valeur comme "../../dossier" permettrait d'écrire
            // le fichier en dehors du dossier d'upload prévu.
            if (!ctype_digit((string) $req_id)) {
                $erreurs_envoi = true;
                continue;
            }
            $req_id = (int) $req_id;

            $file_name = $_FILES['documents']['name'][$req_id];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $erreurs_envoi = true;
                continue;
            }

            $new_name = "user_" . $user_id . "_req_" . $req_id . "_" . time() . "." . $ext;

            if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                
                $stmtCheck = $pdo->prepare("SELECT id_doc, chemin_fichier FROM User_Documents WHERE id_user = :user AND id_requirement = :req");
                $stmtCheck->execute([':user' => $user_id, ':req' => $req_id]);
                $old = $stmtCheck->fetch();

                if ($old) {
                    if (file_exists($upload_dir . $old['chemin_fichier'])) {
                        unlink($upload_dir . $old['chemin_fichier']);
                    }
                    $stmtUpdate = $pdo->prepare("UPDATE User_Documents SET chemin_fichier = :chemin, statut = 'en_attente', date_upload = NOW(), notifie_refus = 0 WHERE id_doc = :id_doc");
                    $stmtUpdate->execute([':chemin' => $new_name, ':id_doc' => $old['id_doc']]);
                } else {
                    $stmtInsert = $pdo->prepare("INSERT INTO User_Documents (id_user, id_requirement, chemin_fichier, statut) VALUES (:id_user, :id_req, :chemin, 'en_attente')");
                    $stmtInsert->execute([':id_user' => $user_id, ':id_req' => $req_id, ':chemin' => $new_name]);
                }
                $fichiers_envoyes++;
            } else {
                $erreurs_envoi = true;
            }
        }

        if ($fichiers_envoyes > 0 && !$erreurs_envoi) {
            header("Location: user.php?msg=uploaded");
            exit();
        } elseif ($fichiers_envoyes > 0 && $erreurs_envoi) {
            $message = "<div class='alert warning' style='margin-bottom: 20px;'>⚠️ Certains documents ont été envoyés, mais d'autres ont échoué (format invalide ?).</div>";
        } elseif ($erreurs_envoi) {
             $message = "<div class='alert error' style='margin-bottom: 20px;'>❌ Erreur : Format de fichier non autorisé.</div>";
        }
    }

    $stmtDocs = $pdo->prepare("SELECT * FROM User_Documents WHERE id_user = :id_user");
    $stmtDocs->execute([':id_user' => $user_id]);
    $user_docs = [];
    foreach ($stmtDocs->fetchAll() as $doc) {
        $user_docs[$doc['id_requirement']] = $doc;
    }

    $total_reqs = count($requirements);
    $valides = 0;
    $docs_to_upload = [];

    foreach ($requirements as $req) {
        $doc = $user_docs[$req['id_requirement']] ?? null;
        if ($doc && $doc['statut'] === 'valide') {
            $valides++;
        }
        if (!$doc || in_array($doc['statut'], ['refuse', 'refusé'])) {
            $docs_to_upload[] = $req;
        }
    }
    
    $pourcentage = ($total_reqs > 0) ? round(($valides / $total_reqs) * 100) : 0;

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
    $initiale = strtoupper($initiale);
    $initiale .= ".";
    return $initiale;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace Formation - Lobody</title>
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
                    <a href="profil.php " class="dropdown-item">👤 Mon Profil</a>
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
        
        <div class="admin-style-panel">
            
            <?= $message ?>

            <?php if ($profil_incomplet): ?>
                <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <div>
                        <strong style="color: #b45309; font-size: 18px;">⚠️ Votre profil est incomplet</strong>
                        <p style="color: #d97706; margin-top: 4px; font-size: 13px;">Pour faciliter le traitement de votre dossier, veuillez renseigner vos coordonnées.</p>
                    </div>
                    <a href="profil.php" class="btn btn-action" style="background-color: #f59e0b; color: white; white-space: nowrap;">Compléter mon profil</a>
                </div>
            <?php endif; ?>

            <div class="welcome-section">
                <div class="welcome-header">
                    <h1>Bonjour <?= htmlspecialchars($user['prenom'] ?? 'Client') ?>,</h1>
                    <p>Bienvenue sur votre espace formation : <strong><?= htmlspecialchars($user['formation_titre'] ?? 'Aucune formation assignée') ?></strong></p>
                </div>
                
                <div class="card card-calendrier">
                    <div class="card-title" style="margin-bottom: 12px; font-size: 12px;">MON CALENDRIER</div>
                    
                    <div class="cal-item">
                        <span class="cal-label">🏁 Début de la formation </span>
                        <span class="cal-valeur"><?= !empty($user['date_debut_formation']) ? date('d/m/Y', strtotime($user['date_debut_formation'])) : '-' ?></span>
                    </div>
                    
                    <div class="cal-item">
                        <span class="cal-label">🎯 Fin de la formation</span>
                        <span class="cal-valeur"><?= !empty($user['date_fin_formation']) ? date('d/m/Y', strtotime($user['date_fin_formation'])) : '-' ?></span>
                    </div>

                    <div class="cal-item" style="border-bottom: none; padding-bottom: <?= empty($user['chemin_diplome']) ? '0' : '10px' ?>;">
                        <span class="cal-label" style="color: var(--brand-blue-vibrant);">🎓 Examen</span>
                        <span class="cal-valeur">
                            <?= !empty($user['date_passage_examen']) ? date('d/m/Y', strtotime($user['date_passage_examen'])) : '<span style="color:var(--warning-orange); font-size:12px; font-weight:500;">Non planifié</span>' ?>
                        </span>
                    </div>

                        <?php if (!empty($user['chemin_diplome'])): ?>
                            <div style="margin-top: 8px;">
                                <a href="../uploads/documents/<?= htmlspecialchars($user['chemin_diplome']) ?>" target="_blank" class="btn btn-success" style="width: 100%; padding: 10px; font-size: 12px;">
                                    📜 Télécharger le diplôme
                                </a>
                            </div>
                        <?php endif; ?>
                </div>

                <div class="card" style="min-width: 320px; padding: 16px 24px;">
                    <div class="card-title" style="margin-bottom: 12px; font-size: 12px;">AVANCEMENT DU DOSSIER</div>
                    <div class="progress-header" style="margin-bottom: 8px;">
                        <h2 style="font-size: 32px; margin: 0; line-height: 1;"><?= $pourcentage ?>%</h2>
                        <span style="color: var(--text-muted); font-weight: bold; font-size: 13px;">COMPLET</span>
                    </div>
                    <div class="progress-bar-bg" style="margin-bottom: 0;">
                        <div class="progress-bar-fill" style="width: <?= $pourcentage ?>%;"></div>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                
                <div class="card card-documents">
                    <div class="card-title">MES DOCUMENTS</div>
                    <?php if(empty($requirements)):?>
                        <p style="color: var(--text-muted); font-size: 14px;">Aucun document requis pour le moment.</p>
                    <?php else: ?>
                        <table class="doc-table">
                            <thead>
                                <tr>
                                    <th>DOCUMENT</th>
                                    <th>STATUT</th>
                                    <th style="text-align: right;">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requirements as $req): 
                                    $mon_doc = $user_docs[$req['id_requirement']] ?? null;
                                    $is_refused = ($mon_doc && in_array($mon_doc['statut'], ['refuse', 'refusé']));
                                ?>
                                <tr class="<?= $is_refused ? 'row-refused-top' : '' ?>">
                                    <td style="vertical-align: top; padding-top: 15px;">
                                        <strong><?= htmlspecialchars($req['nom_document']) ?></strong>
                                    </td>
                                    
                                    <td style="vertical-align: top; padding-top: 15px;">
                                        <?php if (!$mon_doc): ?>
                                            <span style="color: var(--text-muted); font-weight: 500;">À fournir</span>
                                        <?php elseif ($mon_doc['statut'] === 'valide'): ?>
                                            <span class="status valide"><span class="status-dot"></span> Validé</span>
                                        <?php elseif ($mon_doc['statut'] === 'en_attente'): ?>
                                            <span class="status attente"><span class="status-dot"></span> En Attente</span>
                                        <?php elseif ($is_refused): ?>
                                            <span class="status refuse"><span class="status-dot"></span> Refusé</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);"><?= htmlspecialchars($mon_doc['statut']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td style="vertical-align: top; padding-top: 15px; text-align: right;">
                                        <?php if ($mon_doc && in_array($mon_doc['statut'], ['valide', 'en_attente'])): ?>
                                            <a href="../uploads/documents/<?= htmlspecialchars($mon_doc['chemin_fichier']) ?>" target="_blank" class="btn-action" style="background-color: rgba(0, 60, 200, 0.08); padding: 6px 12px; border-radius: 6px; font-size: 12px;">👁️ Voir</a>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <?php if ($is_refused): ?>
                                <tr class="row-refused-bottom">
                                    <td colspan="3">
                                        <div class="alert-refus-full">
                                            <div class="alerte-titre">⚠️ Document refusé. Veuillez en renvoyer un nouveau.</div>
                                            <?php if(!empty($mon_doc['motif_refus'])): ?>
                                                <div class="alerte-motif">(Motif : <?= nl2br(htmlspecialchars($mon_doc['motif_refus'])) ?>)</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="card card-renvoyer">
                    <div class="card-title">ENVOYER UN DOCUMENT</div>
                    
                    <?php if (empty($docs_to_upload)): ?>
                        <div style="text-align: center; padding: 20px 0;">
                            <span style="font-size: 30px; display: block; margin-bottom: 10px;">🎉</span>
                            <p style="color: var(--success-green); font-weight: 600;">Votre dossier est complet ou en cours de vérification !</p>
                        </div>
                    <?php else: ?>
                        <form action="user.php" method="POST" enctype="multipart/form-data">
                            <div style="display: flex; flex-direction: column; gap: 24px; margin-bottom: 24px;">
                                <?php 
                                $chunks = array_chunk($docs_to_upload, 2); 
                                
                                foreach ($chunks as $ligne): 
                                    $classe_grille = (count($ligne) == 2) ? 'upload-row-2' : 'upload-row-1';
                                ?>
                                    <div class="<?= $classe_grille ?>">
                                        <?php 
                                        $premier_element = true;
                                        foreach ($ligne as $req): 
                                            $doc = $user_docs[$req['id_requirement']] ?? null;
                                            $is_refused = ($doc && in_array($doc['statut'], ['refuse', 'refusé']));
                                            
                                            if (!$premier_element && count($ligne) == 2) {
                                                echo '<hr class="vertical-separator">';
                                            }
                                            $premier_element = false;
                                        ?>
                                            <div class="form-group" style="margin: 0;">
                                                <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: bold; color: var(--brand-blue-dark); text-align: center;">
                                                    <?= htmlspecialchars($req['nom_document']) ?>
                                                    <?php if($is_refused): ?><br><span style="color: var(--danger); font-weight: normal; font-size: 12px;">(À renvoyer)</span><?php endif; ?>
                                                </label>
                                                
                                                <input type="file" name="documents[<?= $req['id_requirement'] ?>]" class="css-only-file-input" accept=".pdf,.jpg,.jpeg,.png" style="font-size: 12px; padding: 6px;">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="submit" class="btn btn-success" style="padding: 14px; font-size: 14px; font-weight: bold;">ENVOYER LES DOCUMENTS</button>
                        </form>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

</body>
</html>