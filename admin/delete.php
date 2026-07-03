<?php
session_start();
require_once '../config/config.php';

// 1. Vérification des accès
if (!isset($_SESSION['admin_id']) || empty($_GET['id'])) {
    header("Location: admin.php?tab=clients");
    exit();
}

// 1bis. Protection CSRF : le jeton doit correspondre à celui de la session.
if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_GET['csrf'])) {
    http_response_code(403);
    exit('Requête refusée (jeton de sécurité invalide).');
}

$id_client = (int) $_GET['id'];

try {
    // 2. Mémoriser les infos du client AVANT la suppression (pour le message de succès)
    $stmtUser = $pdo->prepare("SELECT nom, prenom FROM Users WHERE id = :id");
    $stmtUser->execute([':id' => $id_client]);
    $client = $stmtUser->fetch();

    if (!$client) {
        header("Location: admin.php?tab=clients");
        exit();
    }
    $nom_complet = $client['prenom'] . ' ' . $client['nom'];

    // 3. NETTOYAGE DU DISQUE DUR (PHP efface les vrais fichiers PDF/JPG)
    $stmtDocs = $pdo->prepare("SELECT chemin_fichier FROM User_Documents WHERE id_user = :id");
    $stmtDocs->execute([':id' => $id_client]);
    $docs = $stmtDocs->fetchAll();

    foreach ($docs as $doc) {
        if (!empty($doc['chemin_fichier'])) {
            $chemin_physique = '../uploads/documents/' . $doc['chemin_fichier'];
            if (file_exists($chemin_physique) && is_file($chemin_physique)) {
                unlink($chemin_physique); 
            }
        }
    }

    // 4. SUPPRESSION EN BASE DE DONNÉES
    $pdo->prepare("DELETE FROM Users WHERE id = :id")->execute([':id' => $id_client]);

    // 5. Redirection avec le Flash Message
    header("Location: admin.php?tab=clients&msg=client_deleted&name=" . urlencode($nom_complet));
    exit();

} catch (PDOException $e) {
    error_log('Erreur suppression client : ' . $e->getMessage());
    header("Location: admin.php?tab=clients&msg=erreur");
    exit();
}
?>