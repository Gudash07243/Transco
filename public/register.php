<?php
// ============================================================
//  public/register.php — Inscription d'un nouveau client
// ============================================================

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/auth_check.php';

if (isLoggedIn()) {
    redirectToDashboard();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom       = trim($_POST['nom']       ?? '');
    $postnom   = trim($_POST['postnom']   ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $mdp       = $_POST['mot_de_passe']   ?? '';
    $mdp2      = $_POST['confirmation']   ?? '';

    // Validations
    if (empty($nom) || empty($postnom) || empty($telephone) || empty($email) || empty($mdp)) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($mdp) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($mdp !== $mdp2) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $pdo = getPDO();

        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare('SELECT id_user FROM UTILISATEUR WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Cette adresse email est déjà utilisée.';
        } else {
            try {
                $pdo->beginTransaction();

                // Créer le compte utilisateur
                $hash = password_hash($mdp, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO UTILISATEUR (email, mot_de_passe, role) VALUES (?, ?, ?)');
                $stmt->execute([$email, $hash, 'CLIENT']);
                $id_user = $pdo->lastInsertId();

                // Créer le profil client
                $stmt = $pdo->prepare('INSERT INTO CLIENT (nom, postnom, telephone, id_user) VALUES (?, ?, ?, ?)');
                $stmt->execute([$nom, $postnom, $telephone, $id_user]);

                $pdo->commit();
                $success = 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Une erreur est survenue lors de la création du compte.';
                error_log($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — Transco</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-card" style="max-width:500px">
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

    <h2>Créer un compte</h2>

    <?php if ($error):   ?><div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="nom">Nom</label>
                <input type="text" id="nom" name="nom"
                       value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                       placeholder="Dupont" required>
            </div>
            <div class="form-group">
                <label for="postnom">Post-nom</label>
                <input type="text" id="postnom" name="postnom"
                       value="<?= htmlspecialchars($_POST['postnom'] ?? '') ?>"
                       placeholder="Jean" required>
            </div>
        </div>

        <div class="form-group">
            <label for="telephone">Téléphone</label>
            <input type="text" id="telephone" name="telephone"
                   value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>"
                   placeholder="+243 812 345 678" required>
        </div>

        <div class="form-group">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="votre@email.com" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="mot_de_passe">Mot de passe</label>
                <input type="password" id="mot_de_passe" name="mot_de_passe"
                       placeholder="Min. 6 caractères" required>
            </div>
            <div class="form-group">
                <label for="confirmation">Confirmation</label>
                <input type="password" id="confirmation" name="confirmation"
                       placeholder="Répétez" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem">
            Créer mon compte →
        </button>
    </form>
    <?php endif; ?>

    <p style="text-align:center; margin-top:1.5rem; font-size:.875rem; color:var(--c-muted)">
        Déjà un compte ?
        <a href="login.php">Se connecter</a>
    </p>
</div>

</body>
</html>
