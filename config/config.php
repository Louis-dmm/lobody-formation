<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Chargement des identifiants secrets depuis le fichier .env (non versionné)
require_once __DIR__ . '/env.php';

$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5 
    ];

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $options);

    $query_config = $pdo->query("SELECT * FROM Configuration_Site WHERE id = 1");
    $site_settings = $query_config->fetch();

    if (!$site_settings) {
        die("Erreur : La configuration du site est manquante dans la base de données.");
    }

    // --- FEATURE : VALIDATION AUTOMATIQUE ---
    // Cette requête valide tous les documents 'en_attente' depuis plus de X temps
    try {        
        $interval = "7 DAY"; 
        $sqlAutoValide = "UPDATE User_Documents 
                        SET statut = 'valide' 
                        WHERE statut = 'en_attente' 
                        AND date_upload <= (NOW() - INTERVAL $interval)";
        
        $pdo->exec($sqlAutoValide);

    } catch (PDOException $e) {
        // On ne bloque pas le site si l'auto-validation échoue, on reste discret
    }

} catch (PDOException $e) {
    // Le détail technique est journalisé côté serveur, jamais affiché à l'utilisateur.
    error_log('Erreur de connexion BDD : ' . $e->getMessage());
    http_response_code(500);
    die("Service momentanément indisponible. Merci de réessayer plus tard.");
}
?>