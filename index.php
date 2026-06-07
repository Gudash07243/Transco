<?php
// ============================================================
//  index.php — Point d'entrée principal → redirige vers login
// ============================================================

require_once __DIR__ . '/config/auth_check.php';

// Si déjà connecté, envoyer vers le bon dashboard
if (isLoggedIn()) {
    redirectToDashboard();
}

// Sinon, vers la page de connexion
header('Location: public/login.php');
exit;
