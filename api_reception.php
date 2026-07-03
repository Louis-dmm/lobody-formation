<?php
/*
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(["statut" => "erreur", "message" => "Méthode non autorisée."]));
}

// Filtrage par adresse IP (IP Whitelisting).
// Défini dans le .env via API_ALLOWED_IP. Laissé vide (ex. en local),
// le filtrage par IP est ignoré : seule la clé API protège alors l'accès.
$ip_serveur_qcm = getenv('API_ALLOWED_IP');
if ($ip_serveur_qcm && $_SERVER['REMOTE_ADDR'] !== $ip_serveur_qcm) {
    http_response_code(403);
    error_log("Tentative d'accès API refusée depuis l'IP : " . $_SERVER['REMOTE_ADDR']);
    die(json_encode(["statut" => "erreur", "message" => "IP non autorisée."]));
}

$cle_secrete = getenv('API_KEY');
$cle_recue = $_POST['api_key'] ?? '';
if ($cle_recue !== $cle_secrete) {
    http_response_code(401);
    die(json_encode(["statut" => "erreur", "message" => "Clé API invalide."]));
}

$nom_client    = trim($_POST['nom'] ?? '');
$prenom_client = trim($_POST['prenom'] ?? '');
$id_formation  = $_POST['id_formation'] ?? '';
$score         = $_POST['score'] ?? '';
$pdf_base64    = $_POST['pdf_base64'] ?? ''; 

if (empty($nom_client) || empty($prenom_client) || empty($id_formation) || empty($pdf_base64)) {
    http_response_code(400);
    die(json_encode(["statut" => "erreur", "message" => "Données incomplètes (nom, prenom, id_formation ou pdf_base64 manquants)."]));
}

try {
    $stmtRecherche = $pdo->prepare("
        SELECT id FROM Users 
        WHERE nom = :nom AND prenom = :prenom AND id_formation = :id_form
    ");
    $stmtRecherche->execute([
        ':nom' => $nom_client,
        ':prenom' => $prenom_client,
        ':id_form' => $id_formation
    ]);
    $clients_trouves = $stmtRecherche->fetchAll();

    if (count($clients_trouves) === 0) {
        http_response_code(404);
        die(json_encode(["statut" => "erreur", "message" => "Client introuvable dans la base Lobody."]));
    } 
    if (count($clients_trouves) > 1) {
        http_response_code(409);
        error_log("Conflit API : Plusieurs clients trouvés pour $prenom_client $nom_client.");
        die(json_encode(["statut" => "erreur", "message" => "Homonymes détectés. Mise à jour manuelle requise."]));
    }

    $user_id = $clients_trouves[0]['id'];

    $nom_fichier_pdf = "diplome_user_" . $user_id . "_" . time() . ".pdf";

    $chemin_sauvegarde = "uploads/documents/" . $nom_fichier_pdf; 
    
    // On décode le texte reçu et on le transforme en vrai fichier PDF
    file_put_contents($chemin_sauvegarde, base64_decode($pdf_base64));
    
    $stmtUpdate = $pdo->prepare("
        UPDATE Users 
        SET note_qcm = :score, 
            chemin_diplome = :pdf 
        WHERE id = :id
    ");
    $stmtUpdate->execute([
        ':score' => $score,
        ':pdf' => $nom_fichier_pdf,
        ':id' => $user_id
    ]);

    http_response_code(200);
    echo json_encode(["statut" => "succes", "message" => "Diplôme enregistré avec succès pour l'utilisateur ID $user_id."]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Erreur SQL API Reception : " . $e->getMessage());
    echo json_encode(["statut" => "erreur", "message" => "Erreur interne du serveur lors de la mise à jour."]);
}
*/
?>