<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Durcissement du cookie de session : bloque son envoi depuis un autre site
// (protection CSRF de base) et empêche sa lecture en JavaScript.
session_set_cookie_params([
    'samesite' => 'Strict',
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']), // Secure seulement en HTTPS (marche aussi en local http)
]);
session_start();

require_once '../config/config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($site_settings)) {
    $site_settings = ['url_site' => '', 'nom_societe' => 'Admin', 'email_contact' => ''];
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../includes/PHPMailer/Exception.php';
require __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
require __DIR__ . '/../includes/PHPMailer/SMTP.php';

$message_doc = "";
$message_inscription = "";
$message_template = "";
$message_lieu = "";
$message_session = "";

$active_tab = 'clients';
if (isset($_GET['tab'])) {
    $active_tab = $_GET['tab'];
}

// Jeton anti-CSRF, généré une fois par session.
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

// Garde CSRF : les actions qui modifient la base doivent présenter le bon jeton.
// Un lien piégé venant d'un autre site ne connaît pas ce jeton et sera rejeté.
$actions_sensibles = ['valider_doc', 'del_template', 'del_doc'];
if (isset($_GET['action']) && in_array($_GET['action'], $actions_sensibles, true)) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf'], $_GET['csrf'])) {
        http_response_code(403);
        exit('Requête refusée (jeton de sécurité invalide).');
    }
}

if (isset($_GET['action']) && isset($_GET['id_doc'])) {

    $id_doc = (int) $_GET['id_doc'];

    if ($_GET['action'] === 'valider_doc') {
        $pdo->prepare("UPDATE User_Documents SET statut = 'valide' WHERE id_doc = :id")->execute([':id' => $id_doc]);
        $stmtUser = $pdo->prepare("
            SELECT ud.id_user, u.id_formation, u.nom, u.prenom, u.mail, u.etape_en_cours
            FROM User_Documents ud 
            JOIN Users u ON ud.id_user = u.id 
            WHERE ud.id_doc = :id_doc
        ");
        $stmtUser->execute([':id_doc' => $id_doc]);
        $info = $stmtUser->fetch();

        if ($info) {
            $id_user = $info['id_user'];
            $id_formation = $info['id_formation'];

            $stmtReq = $pdo->prepare("SELECT COUNT(*) FROM Formation_Requirements WHERE id_formation = :id_formation");
            $stmtReq->execute([':id_formation' => $id_formation]);
            $total_requis = $stmtReq->fetchColumn();

            $stmtValid = $pdo->prepare("SELECT COUNT(*) FROM User_Documents WHERE id_user = :id_user AND statut = 'valide'");
            $stmtValid->execute([':id_user' => $id_user]);
            $total_valides = $stmtValid->fetchColumn();

            if ($total_requis > 0 && $total_valides >= $total_requis) {
                if ($info['etape_en_cours'] !== 'dossier_complet') {
                    $pdo->prepare("UPDATE Users SET etape_en_cours = 'dossier_complet' WHERE id = :id_user")->execute([':id_user' => $id_user]);
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = getenv('SMTP_HOST');
                        $mail->SMTPAuth = true;
                        $mail->Username = getenv('SMTP_USERNAME');
                        $mail->Password = getenv('SMTP_PASSWORD');
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
                        $mail->Port = 587; 
                        $mail->CharSet = 'UTF-8';

                        $email_from = !empty($site_settings['email_contact']) ? $site_settings['email_contact'] : getenv('MAIL_FROM');
                        $mail->setFrom($email_from, 'Lobody Formation');
                        $mail->addAddress($info['mail'], $info['prenom'] . ' ' . $info['nom']);
                        
                        $mail->isHTML(true);
                        $stmtTpl = $pdo->prepare("SELECT sujet, contenu_html FROM Email_Templates WHERE code_contexte = 'dossier_complet'");
                        $stmtTpl->execute();
                        $gabarit = $stmtTpl->fetch();

                        if ($gabarit) {
                            $sujet_final = $gabarit['sujet'];
                            $corps_final = $gabarit['contenu_html'];

                            $sujet_final = str_replace('{{prenom}}', htmlspecialchars($info['prenom']), $sujet_final);
                            $corps_final = str_replace('{{prenom}}', htmlspecialchars($info['prenom']), $corps_final);

                            $mail->Subject = $sujet_final;
                            $mail->Body    = $corps_final;
                            $mail->AltBody = strip_tags($corps_final);
                            
                            $mail->send();
                        }

                    } catch (Exception $e) {
                        error_log("Erreur d'envoi email dossier complet : " . $e->getMessage());
                    }
                }
            }
        }
        header("Location: admin.php?msg=doc_valide");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'valider_refus_doc') {
    $id_doc = (int) $_POST['id_doc'];
    $motif = trim($_POST['motif_refus']);
        
    $stmt = $pdo->prepare("UPDATE User_Documents SET statut = 'refuse', motif_refus = :motif WHERE id_doc = :id");
    $stmt->execute([
        ':motif' => $motif,
        ':id' => $id_doc
    ]);
        
    header("Location: admin.php?msg=doc_refuse&tab=clients");
    exit();
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'doc_valide') $message_doc = "<div class='alert success'>✅ Document validé.</div>";
    if ($_GET['msg'] === 'doc_refuse') $message_doc = "<div class='alert error'>❌ Document refusé.</div>";

    if ($_GET['msg'] === 'client_deleted' && isset($_GET['name'])) {
        $nom_supprime = htmlspecialchars($_GET['name']);
        $message_doc = "<div class='alert success'>🗑️ Le client <strong>$nom_supprime</strong> a bien été supprimé.</div>";
    }
}

$etape_actuelle = 1;

if (isset($_SESSION['ajout_client']['etape'])) {
    $etape_actuelle = $_SESSION['ajout_client']['etape'];
}

if (isset($_GET['action']) && $_GET['action'] === 'annuler_ajout_client') {
    unset($_SESSION['ajout_client']);
    header("Location: admin.php?tab=ajouter");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'etape_ajout_client') {
    $active_tab = 'ajouter';
    $etape_soumise = (int)$_POST['etape_soumise'];

    if($etape_soumise === 1){
        $_SESSION['ajout_client'] = [
            'etape' => 2,
            'nom' => trim($_POST['nom']),
            'prenom' => trim($_POST['prenom']),
            'email' => trim($_POST['email']),
            'id_formation' => (int)$_POST['id_formation'],
            'date_debut' => $_POST['date_debut'],
            'date_fin' => $_POST['date_fin']            
        ];
        $etape_actuelle = 2;
    }

    if($etape_soumise === 2){
        $_SESSION['ajout_client']['id_lieu'] = (int)$_POST['id_lieu'];
        $_SESSION['ajout_client']['etape'] = 3;
        $etape_actuelle = 3;
    }

    if($etape_soumise === 3){
        $id_session_choisie = (int)$_POST['id_session'];
        $email_client = $_SESSION['ajout_client']['email'];

        $check = $pdo->prepare("SELECT id FROM Users WHERE mail = :mail");
        $check->execute([':mail' => $email_client]);
        
        if ($check->fetch()) {
            $message_inscription = "<div class='alert error'>❌ Cet email existe déjà.</div>";
            $etape_actuelle = 3;
        } else {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));

            try {
                $pdo->beginTransaction();

                $insert = $pdo->prepare("INSERT INTO Users (nom, prenom, mail, id_formation, reset_token, reset_expires, etape_en_cours) VALUES (:nom, :prenom, :mail, :id_formation, :token, :expires, 'en_attente_docs')");
                $insert->execute([
                    ':nom' => $_SESSION['ajout_client']['nom'],
                    ':prenom' => $_SESSION['ajout_client']['prenom'],
                    ':mail' => $email_client,
                    ':id_formation' => $_SESSION['ajout_client']['id_formation'],
                    ':token' => $token,
                    ':expires' => $expires
                ]);

                $id_nouvel_utilisateur = $pdo->lastInsertId();

                $insertInscription = $pdo->prepare("INSERT INTO inscription (id_utilisateur, id_session, statut_etape, date_debut_formation, date_fin_formation) VALUES (:id_user, :id_session, 'en_cours', :date_deb, :date_fin) ");
                $insertInscription->execute([
                    ':id_user' => $id_nouvel_utilisateur,
                    ':id_session' => $id_session_choisie,
                    ':date_deb' => $_SESSION['ajout_client']['date_debut'],
                    ':date_fin' => $_SESSION['ajout_client']['date_fin']
                ]);

                $pdo->commit();

                $lien_creation = $site_settings['url_site'] . "/auth/creation_mdp.php?token=" . $token;
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = getenv('SMTP_HOST');
                    $mail->SMTPAuth = true;
                    $mail->Username = getenv('SMTP_USERNAME');
                    $mail->Password = getenv('SMTP_PASSWORD');
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
                    $mail->Port = 587; 
                    $mail->CharSet = 'UTF-8';

                    $mail->setFrom($site_settings['email_contact'], $site_settings['nom_societe']);
                    $mail->addAddress($email_client, $_SESSION['ajout_client']['prenom'] . ' ' . $_SESSION['ajout_client']['nom']);
                    $mail->isHTML(true);
                    
                    $stmtTpl = $pdo->prepare("SELECT sujet, contenu_html FROM Email_Templates WHERE code_contexte = 'invitation_client'");
                    $stmtTpl->execute();
                    $gabarit = $stmtTpl->fetch();

                    if ($gabarit) {
                        $sujet_final = $gabarit['sujet'];
                        $corps_final = $gabarit['contenu_html'];

                        $balises = ['{{prenom}}', '{{lien_creation}}'];
                        $valeurs = [htmlspecialchars($_SESSION['ajout_client']['prenom']), $lien_creation];
                        
                        $sujet_final = str_replace($balises, $valeurs, $sujet_final);
                        $corps_final = str_replace($balises, $valeurs, $corps_final);

                        $mail->Subject = $sujet_final;
                        $mail->Body    = $corps_final;
                        $mail->AltBody = strip_tags($corps_final);
                        $mail->send();
                    }

                    $message_inscription = "<div class='alert success'>✅ Client invité avec succès !</div>";
                } catch (Exception $e) {
                    $message_inscription = "<div class='alert warning'>⚠️ Client créé en base de données, mais l'e-mail d'invitation n'a pas pu être envoyé.</div>";
                }

                unset($_SESSION['ajout_client']);
                $etape_actuelle = 1;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message_inscription = "<div class='alert error'>❌ Erreur système lors de l'enregistrement SQL : " . $e->getMessage() . "</div>";
                $etape_actuelle = 3;
            }
        }
    }
}

$sessions_disponibles = [];

if($etape_actuelle === 3){
    $requetesSessions = $pdo->prepare("
        SELECT id_session, date_passage_examen
        FROM session
        WHERE id_formation = :id_form
        AND id_lieu = :id_lieu
        AND date_passage_examen > :date_debut
        ORDER BY date_passage_examen ASC
    ");

    $requetesSessions->execute([
        ':id_form' => $_SESSION['ajout_client']['id_formation'],
        ':id_lieu' => $_SESSION['ajout_client']['id_lieu'],
        ':date_debut' => $_SESSION['ajout_client']['date_debut']
    ]);

    $sessions_disponibles = $requetesSessions->fetchAll();
}

//Notifier le client des refus
if(isset($_GET['action']) && $_GET['action'] === 'notifier_refus' && isset($_GET['id_user'])){
    $id_user = (int)$_GET['id_user'];
    $stmtUser = $pdo->prepare("SELECT prenom, nom, mail FROM Users WHERE id = :id");
    $stmtUser->execute([':id' => $id_user]);
    $client_info = $stmtUser->fetch();

    if($client_info){
        $stmtRefus = $pdo->prepare("
            SELECT motif_refus, Formation_Requirements.nom_document 
            FROM User_Documents
            INNER JOIN Formation_Requirements ON User_Documents.id_requirement = Formation_Requirements.id_requirement
            WHERE id_user = :id_user
            AND (User_Documents.statut = 'refuse' OR User_Documents.statut = 'refusé')
        ");
        $stmtRefus->execute([':id_user' => $id_user]);
        $documents_refuses = $stmtRefus->fetchAll();

        $liste_html = "<ul>";
        foreach ($documents_refuses as $doc) {
            $liste_html .= "<li><strong>" . $doc['nom_document'] . " :</strong> " . $doc['motif_refus'] . "</li>"; 
        }
        $liste_html .= "</ul>";

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = getenv('SMTP_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = getenv('SMTP_USERNAME');
            $mail->Password = getenv('SMTP_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port = 587; 
            $mail->CharSet = 'UTF-8';

            $email_from = !empty($site_settings['email_contact']) ? $site_settings['email_contact'] : getenv('MAIL_FROM');
            $mail->setFrom($email_from, 'Lobody Formation');
            $mail->addAddress($client_info['mail'], $client_info['prenom'] . ' ' . $client_info['nom']);
            
            $mail->isHTML(true);

            $stmtTpl = $pdo->prepare("SELECT sujet, contenu_html FROM Email_Templates WHERE code_contexte = 'dossier_incomplet'");
            $stmtTpl->execute();
            $gabarit = $stmtTpl->fetch();

            if ($gabarit) {
                $sujet_final = $gabarit['sujet'];
                $corps_final = $gabarit['contenu_html'];

                $sujet_final = str_replace('{{prenom}}', htmlspecialchars($client_info['prenom']), $sujet_final);
                $corps_final = str_replace('{{prenom}}', htmlspecialchars($client_info['prenom']), $corps_final);
                $corps_final = str_replace('{{liste_refus}}', $liste_html, $corps_final);

                $mail->Subject = $sujet_final;
                $mail->Body    = $corps_final;
                $mail->AltBody = strip_tags($corps_final);
                
                $mail->send();
            }

            $stmtUpdate = $pdo->prepare("
                UPDATE User_Documents SET notifie_refus = 1 WHERE id_user = :id_user AND (statut = 'refusé' OR statut = 'refuse')
            ");
            $stmtUpdate->execute([':id_user' => $id_user]);

            header("Location: admin.php?msg=refus_notifie&tab=clients");
            exit();

        } catch (Exception $e) {
            error_log("Erreur d'envoi email refus : " . $e->getMessage());
        }
    }
}

// --- Actions : Templates & Formations ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_template') {
        $active_tab = 'templates';
        $titre = trim($_POST['titre']);
        if (!empty($titre)) {
            $stmt = $pdo->prepare("INSERT INTO Formation (titre) VALUES (:titre)");
            $stmt->execute([':titre' => $titre]);
            $message_template = "<div class='alert success'>✅ Nouveau template '$titre' créé avec succès !</div>";
        }
    }
    
    if ($_POST['action'] === 'add_doc') {
        $active_tab = 'templates';
        $stmt = $pdo->prepare("INSERT INTO Formation_Requirements (id_formation, nom_document, description) VALUES (:id, :nom, :desc)");
        $stmt->execute([
            ':id' => $_POST['id_formation'],
            ':nom' => trim($_POST['nom_document']),
            ':desc' => trim($_POST['description'])
        ]);

        // CORRIGÉ : Utilisation de $_POST['id_formation'] au lieu de la variable indéfinie $id_formation
        $pdo->prepare("UPDATE Users SET etape_en_cours = 'en_attente_docs' WHERE id_formation = :id_form AND etape_en_cours = 'dossier_complet'")->execute([':id_form' => $_POST['id_formation']]);
        $message_template = "<div class='alert success'>✅ Document ajouté. Les dossiers complets ont été repassés en attente.</div>";
    }

    if ($_POST['action'] === 'edit_doc_save') {
        $active_tab = 'templates';
        $stmt = $pdo->prepare("UPDATE Formation_Requirements SET nom_document = :nom, description = :desc WHERE id_requirement = :id");
        $stmt->execute([
            ':nom' => trim($_POST['nom_document']),
            ':desc' => trim($_POST['description']),
            ':id' => $_POST['id_req']
        ]);
        $message_template = "<div class='alert success'>✏️ Document mis à jour.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_template_email') {
    $active_tab = 'emails';
    $id_tpl = (int)$_POST['id_template'];
    $sujet = trim($_POST['sujet']);
    $contenu = trim($_POST['contenu_html']);

    $stmt = $pdo->prepare("UPDATE Email_Templates SET sujet = :sujet, contenu_html = :contenu WHERE id_template = :id");
    $stmt->execute([
        ':sujet' => $sujet,
        ':contenu' => $contenu,
        ':id' => $id_tpl
    ]);
    $message_template = "<div class='alert success'>✉️ Le modèle d'e-mail a bien été mis à jour !</div>";
}

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'del_template' && isset($_GET['id_form'])) {
        $active_tab = 'templates';
        $pdo->prepare("DELETE FROM Formation_Requirements WHERE id_formation = :id")->execute([':id' => $_GET['id_form']]);
        $pdo->prepare("DELETE FROM Formation WHERE id_formation = :id")->execute([':id' => $_GET['id_form']]);
        $message_template = "<div class='alert success'>🗑️ Template et ses documents supprimés.</div>";
    }

    if ($_GET['action'] === 'del_doc' && isset($_GET['id_req'])) {
        $active_tab = 'templates';
        $pdo->prepare("DELETE FROM Formation_Requirements WHERE id_requirement = :id")->execute([':id' => $_GET['id_req']]);
        $message_template = "<div class='alert success'>🗑️ Document retiré.</div>";
    }
}

if (isset($_GET['edit_id']) || isset($_GET['modifier_lieu_id'])) {
    $active_tab = isset($_GET['modifier_lieu_id']) ? 'lieux' : 'templates';
}

// --- Actions : Gestion des Lieux ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_lieu') {
    $active_tab = 'lieux';
    $ville = trim($_POST['ville']);
    
    if (!empty($ville)) {
        $stmt = $pdo->prepare("INSERT INTO lieux (ville) VALUES (:ville)");
        $stmt->execute([':ville' => $ville]);
        $message_lieu = "<div class='alert success'>✅ Nouvelle ville ajoutée avec succès !</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier_lieu_sauvegarder') {
    $active_tab = 'lieux';
    $id_lieu = (int)$_POST['id_lieu'];
    $ville = trim($_POST['ville']);
    
    if (!empty($ville)) {
        $stmt = $pdo->prepare("UPDATE lieux SET ville = :ville WHERE id_lieu = :id");
        $stmt->execute([':ville' => $ville, ':id' => $id_lieu]);
        $message_lieu = "<div class='alert success'>✏️ Ville mise à jour avec succès.</div>";
    }
}

$lieux_existants = $pdo->query("SELECT * FROM lieux ORDER BY ville ASC")->fetchAll();

// --- Actions : Gestion des Sessions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_session') {
    $active_tab = 'sessions';
    $id_formation = (int)$_POST['id_formation'];
    $id_lieu = (int)$_POST['id_lieu'];
    $date_examen = $_POST['date_passage_examen'];

    if (!empty($id_formation) && !empty($id_lieu) && !empty($date_examen)) {
        $stmt = $pdo->prepare("INSERT INTO session (id_formation, id_lieu, date_passage_examen) VALUES (:id_form, :id_lieu, :date_ex)");
        $stmt->execute([
            ':id_form' => $id_formation,
            ':id_lieu' => $id_lieu,
            ':date_ex' => $date_examen
        ]);
        $message_session = "<div class='alert success'>✅ Nouvelle session d'évaluation planifiée avec succès !</div>";
    } else {
        $message_session = "<div class='alert error'>❌ Tous les champs sont obligatoires.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier_session_sauvegarder') {
    $active_tab = 'sessions';
    $id_session = (int)$_POST['id_session'];
    $id_formation = (int)$_POST['id_formation'];
    $id_lieu = (int)$_POST['id_lieu'];
    $date_examen = $_POST['date_passage_examen'];

    if (!empty($id_session) && !empty($id_formation) && !empty($id_lieu) && !empty($date_examen)) {
        $stmt = $pdo->prepare("UPDATE session SET id_formation = :id_form, id_lieu = :id_lieu, date_passage_examen = :date_ex WHERE id_session = :id");
        $stmt->execute([
            ':id_form' => $id_formation,
            ':id_lieu' => $id_lieu,
            ':date_ex' => $date_examen,
            ':id' => $id_session
        ]);
        $message_session = "<div class='alert success'>✏️ Session d'évaluation mise à jour avec succès.</div>";
    }
}

if (isset($_GET['modifier_session_id'])) {
    $active_tab = 'sessions';
}

$sessions_existantes = $pdo->query("
    SELECT s.*, f.titre AS formation_titre, l.ville AS lieu_ville, (SELECT COUNT(*) FROM inscription i WHERE i.id_session = s.id_session) as nb_inscrits
    FROM session s
    JOIN Formation f ON s.id_formation = f.id_formation
    JOIN lieux l ON s.id_lieu = l.id_lieu
    ORDER BY s.date_passage_examen ASC
")->fetchAll();

$gabarits_emails = $pdo->query("SELECT * FROM Email_Templates ORDER BY nom_affichage ASC")->fetchAll();

try {
    $formations = $pdo->query("SELECT * FROM Formation")->fetchAll();
    $all_requirements = $pdo->query("SELECT * FROM Formation_Requirements")->fetchAll();
} catch (PDOException $e) {
    $formations = [];
    $all_requirements = [];
}

$page_a_sauter = 5;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_attente = (!empty($_GET['filter_attente']) && $_GET['filter_attente'] == '1') ? 1 : 0;
$page_actuelle = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page_actuelle - 1) * $page_a_sauter;

$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(nom LIKE :s OR prenom LIKE :s)";
    $params[':s'] = "%$search%";
}

if ($filter_attente) {
    $where_clauses[] = "etape_en_cours = 'en_attente_docs'";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM Users" . $where_sql);
$stmtCount->execute($params);
$totalPages = ceil($stmtCount->fetchColumn() / $page_a_sauter);
$query = "SELECT Users.*, inscription.date_debut_formation, inscription.date_fin_formation, session.date_passage_examen 
          FROM Users 
          LEFT JOIN inscription ON Users.id = inscription.id_utilisateur 
          LEFT JOIN session ON inscription.id_session = session.id_session" . $where_sql . " 
          ORDER BY Users.date_d_inscription DESC LIMIT " . (int)$page_a_sauter . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$tous_les_docs = $pdo->query("SELECT * FROM User_Documents")->fetchAll();
$docs_par_user_req = [];
foreach ($tous_les_docs as $doc) {
    $docs_par_user_req[$doc['id_user']][$doc['id_requirement']] = $doc;
}

$reqs_par_formation = [];
foreach ($all_requirements as $req) {
    $reqs_par_formation[$req['id_formation']][] = $req;
}

$url_params = "&tab=clients";
if (!empty($search)) {
    $url_params .= "&search=" . urlencode($search);
}
if ($filter_attente === 1) {
    $url_params .= "&filter_attente=1";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin - Lobody Formation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/img/icone.png">
</head>
<body class="body_admin">
    <div class="dashboard">
        <input type="radio" id="tab-clients" name="menu" <?= $active_tab === 'clients' ? 'checked' : '' ?>>
        <input type="radio" id="tab-ajouter" name="menu" <?= $active_tab === 'ajouter' ? 'checked' : '' ?>>
        <input type="radio" id="tab-templates" name="menu" <?= $active_tab === 'templates' ? 'checked' : '' ?>>
        <input type="radio" id="tab-emails" name="menu" <?= $active_tab === 'emails' ? 'checked' : '' ?>>
        <input type="radio" id="tab-sessions" name="menu" <?= $active_tab === 'sessions' ? 'checked' : '' ?>>
        <input type="radio" id="tab-lieux" name="menu" <?= $active_tab === 'lieux' ? 'checked' : '' ?>>

        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../assets/img/icone.png" alt="Logo Lobody" class="sidebar-logo">
                <h2>Panel Admin</h2>
            </div>
            <nav class="sidebar-nav">
                <label for="tab-clients" class="nav-item">👥 Liste des clients</label>
                <label for="tab-ajouter" class="nav-item">➕ Ajouter un client</label>
                <label for="tab-templates" class="nav-item">📄 Templates</label>
                <label for="tab-emails" class="nav-item">✉️ Modèles d'E-mails</label>
                <label for="tab-sessions" class="nav-item">📅 Sessions d'évaluation</label>
                <label for="tab-lieux" class="nav-item">📍 Lieux</label>
            </nav>
            <div class="sidebar-footer">
                <h3>Connecté : <?= htmlspecialchars($_SESSION['user_nom'] ?? 'Admin') ?></h3>
                <a href="../auth/logout.php" class="logout-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Déconnexion
                </a>
            </div>
        </aside>

        <main class="content">
            <section class="panel" id="panel-clients">
                <?php if (isset($_GET['action']) && $_GET['action'] === 'demander_motif_refus' && isset($_GET['id_doc'])): ?>
                    <div style="background-color: #fef2f2; border: 2px solid #ef4444; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                        <h2 style="color: #991b1b; margin-bottom: 12px;">❌ Refus de document</h2>
                        <form action="admin.php" method="POST">
                            <input type="hidden" name="action" value="valider_refus_doc">
                            <input type="hidden" name="id_doc" value="<?= (int)$_GET['id_doc'] ?>">
                            <input type="hidden" name="tab" value="clients">
                            
                            <label style="display: block; font-weight: 600; color: #111827; margin-bottom: 8px;">Motif du refus (ce message sera affiché au client) :</label>
                            <textarea name="motif_refus" required rows="3" placeholder="Ex: Le document est illisible..." style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; margin-bottom: 16px; outline: none; font-family: inherit; resize: vertical;"></textarea>
                            
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <button type="submit" class="btn-submit" style="background-color: #dc2626; margin: 0; box-shadow: none;">Confirmer le refus</button>
                                <a href="admin.php?tab=clients" style="color: #4b5563; text-decoration: none; font-weight: 500; padding: 10px;">Annuler</a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <h1>Liste de vos clients</h1>
                <?php if (!empty($message_doc)): ?>
                    <?= $message_doc; ?>
                <?php endif; ?>
                <form class="tool-bar" action="admin.php" method="GET">
                    <input type="hidden" name="tab" value="clients">
                    <input type="text" name="search" placeholder="Rechercher un client (Nom, Prenom)..." value="<?= htmlspecialchars($search) ?>">
                    
                    <label class="filtre-checkbox" title="Cocher pour voir uniquement les clients en attente de documents">
                        <input type="checkbox" name="filter_attente" value="1" <?= $filter_attente ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span>Clients en attente</span>
                    </label>

                    <input type="submit" value="Rechercher">
                    <?php if ($search || $filter_attente): ?><a href="admin.php?tab=clients">Réinitialiser le filtre</a><?php endif; ?>
                </form>
                
                <table border="1" cellpadding="5">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Adresse</th>
                            <th>Inscription</th>
                            <th>Date de formation</th>
                            <th>Statut compte</th>
                            <th>Étape</th>
                            <th>Documents</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($client['nom']) ?> <?= htmlspecialchars($client['prenom']) ?></strong><br>
                                    <span class="text-table">
                                        <?= !empty($client['date_de_naissance']) ? 'Né(e) le ' . date('d/m/Y', strtotime($client['date_de_naissance'])) : 'Date inconnue' ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($client['mail']) ?><br>
                                    <span class="text-table"><?= htmlspecialchars($client['telephone'] ?? 'Non renseigné') ?></span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($client['adresse'] ?? 'Adresse inconnue') ?><br>
                                    <span class="text-table"><?= htmlspecialchars($client['code_postal'] ?? 'Code postal inconnu') ?></span> 
                                    <span class="text-table"><?= htmlspecialchars($client['ville'] ?? ' | Ville inconnue') ?></span>
                                </td>
                                <td><?= !empty($client['date_d_inscription']) ? date('d/m/Y H:i', strtotime($client['date_d_inscription'])) : 'Contactez l admin' ?></td>
                                <td>
                                    <?php if (!empty($client['date_debut_formation']) && !empty($client['date_fin_formation'])): ?>
                                        <span class="text-table" style="display: block; white-space: nowrap;">📅 <strong>Début :</strong> <?= date('d/m/Y', strtotime($client['date_debut_formation'])) ?></span>
                                        <span class="text-table" style="display: block; white-space: nowrap; margin-bottom: 6px;">📅 <strong>Fin :</strong> <?= date('d/m/Y', strtotime($client['date_fin_formation'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-table" style="display: block; font-style: italic; color: #9ca3af; margin-bottom: 6px;">Dates non définies</span>
                                    <?php endif; ?>

                                    <?php if (!empty($client['date_passage_examen'])): ?>
                                        <span class="badge success" style="white-space: nowrap; font-size: 11px; background-color: #e0f2fe; color: #0369a1;">
                                            🎓 Épreuve : <?= date('d/m/Y', strtotime($client['date_passage_examen'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge warning" style="white-space: nowrap; font-size: 11px;">
                                            ⚠️ Pas d'épreuve liée
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= empty($client['mot_de_passe']) ? '<span class="badge warning">En attente</span>' : '<span class="badge success">Actif</span>' ?></td>
                                <td>
                                    <?php 
                                        if ($client['etape_en_cours'] === 'en_attente_docs') {
                                            echo "En attente des documents";
                                        } elseif ($client['etape_en_cours'] === 'dossier_complet') {
                                            echo "Dossier complet";
                                        } else {
                                            echo htmlspecialchars($client['etape_en_cours'] ?? 'Contactez l admin');
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                        $id_form = $client['id_formation'];
                                        if (empty($id_form)) {
                                            echo "<span class='text-muted' style='font-style:italic; font-size:12px;'>⚠️ Aucune formation assignée</span>";
                                        } 
                                        elseif (isset($reqs_par_formation[$id_form])) {
                                            foreach ($reqs_par_formation[$id_form] as $req) {
                                                $id_req = $req['id_requirement'];
                                                echo "<div style='margin-bottom: 10px;'>";
                                                echo "<strong>" . htmlspecialchars($req['nom_document']) . "</strong><br>";

                                                if (isset($docs_par_user_req[$client['id']][$id_req])) {
                                                    $d = $docs_par_user_req[$client['id']][$id_req];
                                                    echo "<a href='../uploads/documents/" . htmlspecialchars($d['chemin_fichier']) . "' target='_blank'>[Voir fichier]</a><br>";
                                                    
                                                    if ($d['statut'] === 'en_attente') {
                                                        echo "<a class='btn-action valide' href='admin.php?action=valider_doc&csrf=$csrf&id_doc=" . $d['id_doc'] . "&tab=clients'>[Valider]</a> | ";
                                                        echo "<a class='btn-action delete' href='admin.php?action=demander_motif_refus&id_doc=" . $d['id_doc'] . "&tab=clients'>[Refuser]</a>";
                                                    } else {
                                                        echo "Statut : " . ($d['statut'] === 'valide' ? '✅ Validé' : '❌ Refusé');
                                                    }
                                                } else {
                                                    echo "<span class='text-muted' style='font-style:italic; font-size:12px;'> Non fourni</span>";
                                                }
                                                echo "</div>";
                                            }
                                        } else {
                                            echo "<span class='text-muted'>Aucun document requis</span>";
                                        }
                                    ?>
                                </td>
                                <td> 
                                    <?php
                                        $nb_attente = 0;
                                        $nb_refus = 0;

                                        if(isset($docs_par_user_req[$client['id']])){
                                            foreach($docs_par_user_req[$client['id']] as $doc_analyse){
                                                if($doc_analyse['statut']=== 'en_attente'){
                                                    $nb_attente++;
                                                } elseif ($doc_analyse['statut']=== 'refusé' || $doc_analyse['statut']=== 'refuse' && $doc_analyse['notifie_refus'] == 0){
                                                    $nb_refus++;
                                                }
                                            }
                                        }      
                                        if($nb_attente === 0 && $nb_refus > 0):
                                    ?>
                                        <a class="btn-action" style="color: #f59e0b;" href="admin.php?action=notifier_refus&id_user=<?= $client['id'] ?>&tab=clients" onclick="return confirm('Êtes-vous sûr de vouloir envoyer le mail à ce client ? Il recevra le(s) nom(s) des documents ainsi que le(s) motif(s) !')">
                                            ✉️ Envoyer le bilan
                                        </a><br><br>
                                    <?php endif; ?>
                                    <a class="btn-action" href="edit.php?id=<?= $client['id'] ?>">Éditer</a><br><br>
                                    <a class="btn-action delete" href="delete.php?id=<?= $client['id'] ?>" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce client ? Cette action est irréversible.');">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="pagination-container">
                    <div class="pagination">
                        <?php if ($page_actuelle > 1): ?>
                            <a href="admin.php?page=<?= $page_actuelle - 1 ?><?= $url_params ?>">&larr; Précédent</a>
                        <?php endif; ?>
                    </div>
                    <div class="pagination-suivant">
                        <?php if ($page_actuelle < $totalPages): ?>
                            <a href="admin.php?page=<?= $page_actuelle + 1 ?><?= $url_params ?>">Suivant &rarr;</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="panel" id="panel-ajouter">
                <h1>Ajouter un nouveau client</h1>
                <?php if (!empty($message_inscription)): ?>
                    <?= $message_inscription; ?>
                <?php endif; ?>
                <form action="admin.php" method="POST">
                    <input type="hidden" name="action" value="etape_ajout_client">
                    <input type="hidden" name="etape_soumise" value="<?= $etape_actuelle ?>">
                    
                    <div class="form-grid">
                        <?php 
                            $bloquer_etape1 = ($etape_actuelle > 1) ? 'readonly style="background-color: #c2c2c2; pointer-events: none;"' : '';
                        ?>
                        <div class="form-group">
                            <label for="nom">Nom :</label>
                            <input type="text" name="nom" placeholder="Ex: Durand" required value="<?= htmlspecialchars($_SESSION['ajout_client']['nom'] ?? '')?>" <?= $bloquer_etape1 ?>>
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom :</label>
                            <input type="text" name="prenom" placeholder="Mathis" required value="<?= htmlspecialchars($_SESSION['ajout_client']['prenom'] ?? '') ?>" <?= $bloquer_etape1 ?>>
                        </div>
                        <div class="form-group">
                            <label for="email">Email :</label>
                            <input type="email" name="email" placeholder="mathis.durand@email.com" required value="<?= htmlspecialchars($_SESSION['ajout_client']['email'] ?? '')?>" <?= $bloquer_etape1 ?>>
                        </div>
                        <div class="form-group">
                            <label for="date_debut">Date de début de formation :</label>
                            <input type="date" name="date_debut" required value="<?= htmlspecialchars($_SESSION['ajout_client']['date_debut'] ?? '')?>" <?= $bloquer_etape1 ?>>
                        </div>
                        <div class="form-group">
                            <label for="date_fin">Date de fin de formation :</label>
                            <input type="date" name="date_fin" required value="<?= htmlspecialchars($_SESSION['ajout_client']['date_fin'] ?? '')?>" <?= $bloquer_etape1 ?>>
                        </div>

                        <div class="form-group">
                            <label for="formation">Assigner un type de formation :</label>
                            <?php if ($etape_actuelle > 1) : ?>
                                <?php 
                                    $nom_formation_choisie = "Inconnue";
                                    foreach($formations as $f){
                                        if($f['id_formation'] == $_SESSION['ajout_client']['id_formation']){
                                            $nom_formation_choisie = $f['titre'];
                                        }
                                    }
                                ?>
                                <input type="hidden" name="id_formation" value="<?= $_SESSION['ajout_client']['id_formation'] ?>">
                                <input type="text" value="<?= htmlspecialchars($nom_formation_choisie) ?>" readonly style="background-color: #c2c2c2;">
                            <?php else : ?>
                            <select name="id_formation" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($formations as $formation): ?>
                                    <option value="<?= $formation['id_formation'] ?>"
                                        <?= (isset($_SESSION['ajout_client']['id_formation']) && $_SESSION['ajout_client']['id_formation'] == $formation['id_formation']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($formation['titre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>

                        <?php if ($etape_actuelle === 1): ?>
                            <div class="form-group full-width">
                                <button type="submit" class="btn-submit">Suivant : Choisir le lieu </button>
                            </div>
                        <?php endif; ?>

                        <?php if ($etape_actuelle >= 2): ?>
                            <div class="form-group">
                                <label for="id_lieu">Lieu de formation :</label>
                                <?php if ($etape_actuelle > 2) : ?>
                                    <?php 
                                        $nom_ville_choisie = "Inconnue";
                                        foreach($lieux_existants as $l){
                                            if($l['id_lieu'] == $_SESSION['ajout_client']['id_lieu']){
                                                $nom_ville_choisie = $l['ville'];
                                            }
                                        }
                                    ?>
                                    <input type="hidden" name="id_lieu" value="<?= $_SESSION['ajout_client']['id_lieu'] ?>">
                                    <input type="text" value="📍 <?= htmlspecialchars($nom_ville_choisie) ?>" readonly style="background-color: #c2c2c2;">
                                <?php else : ?>
                                    <select name="id_lieu" required>
                                        <option value="">-- Sélectionner la ville --</option>
                                        <?php foreach ($lieux_existants as $lieu): ?>
                                            <option value="<?= $lieu['id_lieu'] ?>" <?= (isset($_SESSION['ajout_client']['id_lieu']) && $_SESSION['ajout_client']['id_lieu'] == $lieu['id_lieu']) ? 'selected' : '' ?>>📍 <?= htmlspecialchars($lieu['ville']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <?php if ($etape_actuelle === 2): ?>
                                <div class="form-group full-width" style="display: flex; gap: 15px;">
                                    <a href="admin.php?action=annuler_ajout_client" class="btn-action delete" style="padding: 12px; border: 1px solid #991b1b; border-radius: 8px; text-align: center; text-decoration: none;">Recommencer</a>
                                    <button type="submit" class="btn-submit" style="flex: 1; margin: 0;">Suivant : Choisir la date d'examen</button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($etape_actuelle >= 3): ?>
                            <div class="form-group">
                                <label for="id_session">Choisir une date d'épreuve disponible :</label>
                                <select name="id_session" id="id_session" required>
                                    <option value="">-- Selectionner une date planifiée --</option>
                                    <?php if (count($sessions_disponibles) > 0) : ?>
                                        <?php foreach($sessions_disponibles as $session): ?>
                                            <option value="<?= $session['id_session'] ?>">
                                                Épreuve du : <?= date('d/m/Y', strtotime($session['date_passage_examen'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>Aucune session d'évaluation n'est programmée pour ce lieu et cette formation !</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="form-group full-width" style="display: flex; gap: 15px;">
                                <a href="admin.php?action=annuler_ajout_client" class="btn-action delete" style="padding: 12px; border: 1px solid #991b1b; border-radius: 8px; text-align: center; text-decoration: none;">Recommencer</a>
                                <?php if (count($sessions_disponibles) > 0): ?>
                                    <button type="submit" class="btn-submit" style="flex: 1; margin: 0;">Inviter le client</button>
                                <?php endif; ?>
                            </div>

                            <div class="form-group msg-helper">
                                <p class="text-table">ℹ️ Le client recevra un lien sécurisé valable 48h pour créer son mot de passe !</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="panel" id="panel-templates">
                <h1>Gestion Globale des Modèles (Templates)</h1>
                
                <div class="bloc-creation-modele">
                    <h3 class="titre-creation-modele">➕ Créer un nouveau Modèle</h3>
                    <form method="POST" action="admin.php" class="formulaire-creation-modele">
                        <input type="hidden" name="action" value="add_template">
                        <div class="form-group conteneur-champ-modele">
                            <label>Titre de la formation</label>
                            <input type="text" name="titre" placeholder="Ex: Formation Diététique" required>
                        </div>
                        <button type="submit" class="btn-submit bouton-creation-modele">Créer le modèle</button>
                    </form>
                    <br>
                    <?php if (!empty($message_template)): ?>
                        <?= $message_template; ?>
                    <?php endif; ?>
                </div>

                <?php foreach ($formations as $formation): ?>
                    <div class="element-liste-modele">
                        
                        <div class="en-tete-modele">
                            <h2 class="titre-modele">📚 <?= htmlspecialchars($formation['titre'] ?? '') ?></h2>
                            <a href="admin.php?action=del_template&csrf=<?= $csrf ?>&id_form=<?= $formation['id_formation'] ?>&tab=templates" class="btn-action delete" onclick="return confirm('⚠️ ATTENTION : Cela supprimera cette formation et tous ses documents. Continuer ?');">
                                🗑️ Supprimer tout le modèle
                            </a>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Nom du document</th>
                                    <th>Description</th>
                                    <th class="colonne-actions-modele">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $doc_count = 0;
                                foreach ($all_requirements as $req): 
                                    if ($req['id_formation'] == $formation['id_formation']): 
                                        $doc_count++;
                                        $is_editing = (isset($_GET['edit_id']) && $_GET['edit_id'] == $req['id_requirement']);
                                ?>
                                    <tr>
                                        <?php if ($is_editing): ?>
                                            <td colspan="3">
                                                <form method="POST" action="admin.php" class="formulaire-edition-document">
                                                    <input type="hidden" name="action" value="edit_doc_save">
                                                    <input type="hidden" name="id_req" value="<?= $req['id_requirement'] ?>">
                                                    <input type="text" name="nom_document" value="<?= htmlspecialchars($req['nom_document'] ?? '') ?>" required class="champ-saisie-modele champ-etroit">
                                                    <input type="text" name="description" value="<?= htmlspecialchars($req['description'] ?? '') ?>" class="champ-saisie-modele champ-large">
                                                    <button type="submit" class="btn-action valide bouton-action-integre">💾 Sauver</button>
                                                    <a href="admin.php?tab=templates" class="btn-action delete bouton-annulation-integre">Annuler</a>
                                                </form>
                                            </td>
                                        <?php else: ?>
                                            <td><strong><?= htmlspecialchars($req['nom_document'] ?? '') ?></strong></td>
                                            <td class="text-muted"><?= htmlspecialchars($req['description'] ?? 'Aucune description') ?></td>
                                            <td>
                                                <a href="admin.php?edit_id=<?= $req['id_requirement'] ?>&tab=templates" class="btn-action mr-2">✏️ Modifier</a>
                                                <a href="admin.php?action=del_doc&csrf=<?= $csrf ?>&id_req=<?= $req['id_requirement'] ?>&tab=templates" class="btn-action delete" onclick="return confirm('Retirer ce document ?');">❌</a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php 
                                    endif;
                                endforeach; 
                                
                                if ($doc_count == 0): ?>
                                    <tr><td colspan="3" class="text-muted ligne-vide-modele">Aucun document requis pour cette formation.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <form method="POST" action="admin.php" class="formulaire-ajout-rapide">
                            <strong class="titre-ajout-rapide">➕ Ajouter un document :</strong>
                            <input type="hidden" name="action" value="add_doc">
                            <input type="hidden" name="id_formation" value="<?= $formation['id_formation'] ?>">
                            
                            <input type="text" name="nom_document" placeholder="Nom (ex: RIB)" required class="champ-saisie-modele champ-etroit">
                            <input type="text" name="description" placeholder="Description courte" class="champ-saisie-modele champ-large">
                            
                            <button type="submit" class="btn-action valide bouton-action-integre">Ajouter</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="panel" id="panel-emails">
                <div class="panel-header">
                    <h1 style="justify-content: flex-start; padding-bottom: 8px;">✉️ Configuration des e-mails sortants</h1>
                    <p class="panel-subtitle">Personnalisez le sujet et le contenu des e-mails envoyés automatiquement par la plateforme.</p>
                </div>

                <?php if (!empty($message_template) && $active_tab === 'emails'): ?>
                    <?= $message_template; ?>
                <?php endif; ?>

                <div class="zone-configuration-emails">
                    <?php foreach ($gabarits_emails as $gabarit): ?>
                        <div class="carte-edition-email">
                            <h3 class="titre-carte-email">⚙️ <?= htmlspecialchars($gabarit['nom_affichage']) ?></h3>
                            
                            <form method="POST" action="admin.php">
                                <input type="hidden" name="action" value="edit_template_email">
                                <input type="hidden" name="id_template" value="<?= $gabarit['id_template'] ?>">
                                
                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label>Sujet du message :</label>
                                    <input type="text" name="sujet" value="<?= htmlspecialchars($gabarit['sujet']) ?>" required class="champ-saisie-modele" style="width:100%;">
                                </div>

                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label>Contenu HTML du message :</label>
                                    <textarea name="contenu_html" rows="6" required class="champ-saisie-modele" style="width:100%; font-family:'Courier New', monospace; font-size:14px; resize:vertical; padding:12px; border:1px solid #d1d5db; border-radius:8px; outline:none;"><?= htmlspecialchars($gabarit['contenu_html']) ?></textarea>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="text-table" style="font-style: italic;">⚠️ Ne modifiez pas les balises de type <code style="background:#e5e7eb; padding:2px 4px; border-radius:4px; color:#003cc8;">{{...}}</code></span>
                                    <button type="submit" class="btn-submit" style="margin: 0; padding: 10px 20px; font-size: 14px;">Enregistrer le modèle</button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="panel" id="panel-sessions">
                <div class="panel-header">
                    <h1 style="justify-content: flex-start; padding-bottom: 8px;">📅 Gestion des Sessions d'Évaluation</h1>
                    <p class="panel-subtitle">Associe un jour d'évaluation à un lieu pré-créé pour ouvrir des créneaux d'inscription.</p>
                </div>

                <?php if (!empty($message_session)): ?>
                    <?= $message_session; ?>
                <?php endif; ?>

                <div class="bloc-creation-modele">
                    <h3 class="titre-creation-modele">➕ Planifier une nouvelle session</h3>
                    <form method="POST" action="admin.php" class="formulaire-creation-modele" style="align-items: flex-end; flex-wrap: wrap; gap: 16px;">
                        <input type="hidden" name="action" value="ajouter_session">
                        
                        <div class="form-group" style="flex: 1; min-width: 200px; margin: 0;">
                            <label style="font-size: 14px; margin-bottom: 4px; font-weight: bold;">Formation cible :</label>
                            <select name="id_formation" required class="champ-saisie-modele">
                                <option value="">-- Choisir la formation --</option>
                                <?php foreach ($formations as $f): ?>
                                    <option value="<?= $f['id_formation'] ?>"><?= htmlspecialchars($f['titre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="flex: 1; min-width: 200px; margin: 0;">
                            <label style="font-size: 14px; margin-bottom: 4px; font-weight: bold;">Ville d'accueil :</label>
                            <select name="id_lieu" required class="champ-saisie-modele">
                                <option value="">-- Choisir la ville --</option>
                                <?php foreach ($lieux_existants as $l): ?>
                                    <option value="<?= $l['id_lieu'] ?>">📍 <?= htmlspecialchars($l['ville']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="flex: 1; min-width: 180px; margin: 0;">
                            <label style="font-size: 14px; margin-bottom: 4px; font-weight: bold;">Date de l'épreuve :</label>
                            <input type="date" name="date_passage_examen" required class="champ-saisie-modele">
                        </div>

                        <button type="submit" class="btn-submit bouton-creation-modele" style="height: 38px; padding: 0 20px; margin: 0;">Planifier</button>
                    </form>
                </div>

                <table border="1" cellpadding="5">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Formation</th>
                            <th>Lieu (Ville)</th>
                            <th>Date de l'évaluation</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($sessions_existantes) > 0): ?>
                            <?php foreach ($sessions_existantes as $s): 
                                $en_cours_edition = (isset($_GET['modifier_session_id']) && $_GET['modifier_session_id'] == $s['id_session']);
                            ?>
                                <tr>
                                    <td class="text-muted">#<?= $s['id_session'] ?></td>
                                    
                                    <?php if ($en_cours_edition): ?>
                                        <td colspan="4">
                                            <form method="POST" action="admin.php" class="formulaire-edition-document" style="gap: 12px; width: 100%;">
                                                <input type="hidden" name="action" value="modifier_session_sauvegarder">
                                                <input type="hidden" name="id_session" value="<?= $s['id_session'] ?>">
                                                
                                                <select name="id_formation" required class="champ-saisie-modele" style="flex: 1;">
                                                    <?php foreach ($formations as $f): ?>
                                                        <option value="<?= $f['id_formation'] ?>" <?= $f['id_formation'] == $s['id_formation'] ? 'selected' : '' ?>><?= htmlspecialchars($f['titre']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <select name="id_lieu" required class="champ-saisie-modele" style="flex: 1;">
                                                    <?php foreach ($lieux_existants as $l): ?>
                                                        <option value="<?= $l['id_lieu'] ?>" <?= $l['id_lieu'] == $s['id_lieu'] ? 'selected' : '' ?>><?= htmlspecialchars($l['ville']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <input type="date" name="date_passage_examen" value="<?= $s['date_passage_examen'] ?>" required class="champ-saisie-modele" style="width: 160px;">
                                                
                                                <button type="submit" class="btn-action valide bouton-action-integre">💾 Sauvegarder</button>
                                                <a href="admin.php?tab=sessions" class="btn-action delete bouton-annulation-integre">Annuler</a>
                                            </form>
                                        </td>
                                    <?php else: ?>
                                        <td><strong><?= htmlspecialchars($s['formation_titre']) ?></strong></td>
                                        <td>📍 <?= htmlspecialchars($s['lieu_ville']) ?></td>
                                        <td>📅 <?= date('d/m/Y', strtotime($s['date_passage_examen'])) ?></td>
                                        <td>
                                            <?php if ($s['nb_inscrits'] == 0): ?>
                                                <a href="admin.php?modifier_session_id=<?= $s['id_session'] ?>&tab=sessions" class="btn-action">
                                                    ✏️ Modifier
                                                </a>
                                            <?php else: ?>
                                                <span class="badge warning" style="font-size: 11px; cursor: not-allowed;" title="Modification bloquée : des clients sont inscrits">
                                                    🔒 <?= $s['nb_inscrits'] ?> Inscrit(s)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-muted" style="text-align: center;">Aucune session d'évaluation enregistrée pour le moment.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="panel" id="panel-lieux">
                <div class="panel-header">
                    <h1 style="justify-content: flex-start; padding-bottom: 8px;">📍 Gestion des Lieux</h1>
                    <p class="panel-subtitle">Gère la liste des villes disponibles pour les sessions de formation.</p>
                </div>

                <?php if (!empty($message_lieu)): ?>
                    <?= $message_lieu; ?>
                <?php endif; ?>

                <div class="bloc-creation-modele">
                    <h3 class="titre-creation-modele">➕ Ajouter une ville</h3>
                    <form method="POST" action="admin.php" class="formulaire-creation-modele">
                        <input type="hidden" name="action" value="ajouter_lieu">
                        <div class="form-group conteneur-champ-modele">
                            <input type="text" name="ville" placeholder="Ex: Bordeaux, Lyon, Paris..." required class="champ-saisie-modele">
                        </div>
                        <button type="submit" class="btn-submit bouton-creation-modele">Ajouter</button>
                    </form>
                </div>

                <table border="1" cellpadding="5">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ville</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($lieux_existants) > 0): ?>
                            <?php foreach ($lieux_existants as $lieu): 
                                $en_cours_edition = (isset($_GET['modifier_lieu_id']) && $_GET['modifier_lieu_id'] == $lieu['id_lieu']);
                            ?>
                                <tr>
                                    <td class="text-muted">#<?= $lieu['id_lieu'] ?></td>
                                    
                                    <?php if ($en_cours_edition): ?>
                                        <td colspan="2">
                                            <form method="POST" action="admin.php" class="formulaire-edition-document">
                                                <input type="hidden" name="action" value="modifier_lieu_sauvegarder">
                                                <input type="hidden" name="id_lieu" value="<?= $lieu['id_lieu'] ?>">
                                                <input type="text" name="ville" value="<?= htmlspecialchars($lieu['ville']) ?>" required class="champ-saisie-modele champ-large" style="flex: 1;">
                                                <button type="submit" class="btn-action valide bouton-action-integre">💾 Sauvegarder</button>
                                                <a href="admin.php?tab=lieux" class="btn-action delete bouton-annulation-integre">Annuler</a>
                                            </form>
                                        </td>
                                    <?php else: ?>
                                        <td><strong><?= htmlspecialchars($lieu['ville']) ?></strong></td>
                                        <td>
                                            <a href="admin.php?modifier_lieu_id=<?= $lieu['id_lieu'] ?>&tab=lieux" class="btn-action">
                                                ✏️ Modifier
                                            </a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-muted" style="text-align: center;">Aucun lieu enregistré pour le moment.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
</body>
</html> 