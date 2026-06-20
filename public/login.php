<?php
// ============================================================
//  public/login.php — Page de connexion
// ============================================================

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/auth_check.php';

// Si déjà connecté → rediriger
if (isLoggedIn()) {
    redirectToDashboard();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mdp   = $_POST['mot_de_passe'] ?? '';

    if (empty($email) || empty($mdp)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $pdo  = getPDO();
        $stmt = $pdo->prepare('SELECT id_user, mot_de_passe, role FROM UTILISATEUR WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($mdp, $user['mot_de_passe'])) {
            // Régénérer l'ID de session pour éviter la fixation de session
            session_regenerate_id(true);

            $_SESSION['id_user'] = $user['id_user'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['email']   = $email;

            // Charger les infos complémentaires selon le rôle
            if ($user['role'] === 'CLIENT') {
                $s = $pdo->prepare('SELECT id_client, nom, postnom FROM CLIENT WHERE id_user = ?');
                $s->execute([$user['id_user']]);
                $client = $s->fetch();
                if ($client) {
                    $_SESSION['id_client'] = $client['id_client'];
                    $_SESSION['nom']       = $client['nom'] . ' ' . $client['postnom'];
                }
            }

            // Redirection selon le rôle (chemins absolus depuis la racine du serveur)
            switch ($user['role']) {
                case 'ADMIN':
                    $target = '/tranco/admin/admin_dashboard.php';
                    break;
                case 'CONTROLEUR':
                    $target = '/tranco/controlleur/scan.php';
                    break;
                case 'CLIENT':
                    $target = '/tranco/client/dashboard.php';
                    break;
                default:
                    $target = '/tranco/public/login.php';
            }

            // Si les en-têtes n'ont pas encore été envoyées, utiliser header(), sinon fallback JS
            if (!headers_sent()) {
                header('Location: ' . $target);
            } else {
                echo '<script>window.location.href="' . $target . '";</script>';
            }
            exit;
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Transco</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-card">
    <div class="auth-logo">
        <?php
            $logo_new = __DIR__ . '/../assets/css/img/Capture d’écran 2026-06-07 175009.png';
            $logo_old = __DIR__ . '/../assets/img/transco_logo.png';
            if (file_exists($logo_new)):
        ?>
            <div class="logo-mark"><img src="../assets/css/img/Capture d’écran 2026-06-07 175009.png" alt="Transco" class="logo-img"></div>
        <?php elseif (file_exists($logo_old)): ?>
            <div class="logo-mark"><img src="../assets/img/transco_logo.png" alt="Transco" class="logo-img"></div>
        <?php else: ?>
            <div class="logo-mark">🚌 Tran<span class="brand-sco">SCO</span></div>
        <?php endif; ?>
        <p>Plateforme de gestion de transport</p>
    </div>

    <h2>Connexion</h2>

    <?php if ($error): ?>
        <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email"
                   placeholder="votre@email.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required autofocus>
        </div>

        <div class="form-group">
            <label for="mot_de_passe">Mot de passe</label>
            <input type="password" id="mot_de_passe" name="mot_de_passe"
                   placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem">
            Se connecter →
        </button>
    </form>

    <p style="text-align:center; margin-top:1.5rem; font-size:.875rem; color:var(--c-muted)">
        Pas encore de compte ?
        <a href="register.php">S'inscrire</a>
    </p>
</div>

</body>
</html>
