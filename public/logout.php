<?php
// ============================================================
//  public/logout.php — Déconnexion
// ============================================================

require_once __DIR__ . '/../config/auth_check.php';

logout(); // Détruit la session et redirige vers login
