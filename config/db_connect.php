<?php
// ============================================================
//  config/db_connect.php — Connexion PDO à la base de données
// ============================================================

// DEBUG MODE - À désactiver en production
define('DEBUG_MODE', true);

define('DB_HOST', 'localhost');
define('DB_NAME', 'transco_db');
define('DB_USER', 'root');       // À modifier selon votre environnement
define('DB_PASS', '');           // À modifier selon votre environnement
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Erreur de connexion DB : ' . $e->getMessage());
            
            if (DEBUG_MODE) {
                echo '<pre style="background:#fee; padding:20px; color:#c33; font-family:monospace; margin:20px;">';
                echo '<strong>❌ ERREUR DE CONNEXION À LA BASE DE DONNÉES</strong><br>';
                echo 'Host: ' . DB_HOST . '<br>';
                echo 'Database: ' . DB_NAME . '<br>';
                echo 'User: ' . DB_USER . '<br>';
                echo '<br>Erreur: ' . htmlspecialchars($e->getMessage());
                echo '</pre>';
            } else {
                die(json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données.']));
            }
            exit;
        }
    }

    return $pdo;
}
