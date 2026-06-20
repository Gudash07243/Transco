<?php
// ============================================================
//  admin/gestion_personnel.php — Gestion des contrôleurs
// ============================================================

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/auth_check.php';

requireRole('ADMIN');

$pdo     = getPDO();
$error   = '';
$success = '';

// ── Action : Créer un contrôleur ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'creer_controleur') {
    $email = trim($_POST['email'] ?? '');
    $mdp   = $_POST['mot_de_passe'] ?? '';
    $mdp2  = $_POST['confirmation']  ?? '';

    if (empty($email) || empty($mdp)) {
        $error = 'Email et mot de passe sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide.';
    } elseif (strlen($mdp) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($mdp !== $mdp2) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $stmt = $pdo->prepare('SELECT id_user FROM UTILISATEUR WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Cet email est déjà utilisé.';
        } else {
            $hash = password_hash($mdp, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO UTILISATEUR (email, mot_de_passe, role) VALUES (?, ?, ?)');
            $stmt->execute([$email, $hash, 'CONTROLEUR']);
            $success = 'Compte contrôleur créé avec succès.';
        }
    }
}

// ── Action : Supprimer un contrôleur ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer') {
    $id = (int)($_POST['id_user'] ?? 0);
    if ($id > 0) {
        // Protection : ne pas supprimer un ADMIN
        $stmt = $pdo->prepare("DELETE FROM UTILISATEUR WHERE id_user = ? AND role = 'CONTROLEUR'");
        $stmt->execute([$id]);
        $success = 'Contrôleur supprimé.';
    }
}

// ── Liste des contrôleurs ────────────────────────────────
$controleurs = $pdo->query("
    SELECT id_user, email
    FROM UTILISATEUR
    WHERE role = 'CONTROLEUR'
    ORDER BY id_user DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Personnel — Transco</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="wrapper">

    <aside class="sidebar">
        <?php
            $logo_new = __DIR__ . '/../assets/css/img/Capture d’écran 2026-06-07 175009.png';
            $logo_old = __DIR__ . '/../assets/img/transco_logo.png';
            if (file_exists($logo_new)):
        ?>
            <div class="sidebar-logo"><img src="../assets/css/img/Capture d’écran 2026-06-07 175009.png" alt="Transco" class="logo-img"></div>
        <?php elseif (file_exists($logo_old)): ?>
            <div class="sidebar-logo"><img src="../assets/img/transco_logo.png" alt="Transco" class="logo-img"></div>
        <?php else: ?>
            <div class="sidebar-logo">🚌 Tran<span class="brand-sco">SCO</span></div>
        <?php endif; ?>
        <div class="sidebar-user">
            <strong><?= htmlspecialchars($_SESSION['email']) ?></strong>
            <span class="badge badge-admin">Admin</span>
        </div>
        <nav>
            <a href="admin_dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
            <a href="gestion_personnel.php" class="active"><span class="nav-icon">👥</span> Personnel</a>
            <a href="gestion_voyages.php"><span class="nav-icon">🗺️</span> Voyages</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../public/logout.php">⬅ Déconnexion</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1>Gestion du Personnel</h1>
            <span class="badge badge-admin">Administrateur</span>
        </div>

        <!-- Formulaire de création -->
        <div class="card fade-in" style="max-width:520px">
            <div class="card-header">
                <h3>➕ Créer un compte Contrôleur</h3>
            </div>
            <div class="card-body">
                <?php if ($error):   ?><div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="creer_controleur">

                    <div class="form-group">
                        <label for="email">Adresse email du contrôleur</label>
                        <input type="email" id="email" name="email"
                               placeholder="controleur@tranco.com" required>
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

                    <button type="submit" class="btn btn-primary btn-full">
                        Créer le compte contrôleur
                    </button>
                </form>
            </div>
        </div>

        <!-- Liste des contrôleurs -->
        <div class="card fade-in">
            <div class="card-header">
                <h3>🛡️ Contrôleurs actifs (<?= count($controleurs) ?>)</h3>
            </div>
            <?php if (empty($controleurs)): ?>
                <div class="card-body"><p style="color:var(--c-muted)">Aucun contrôleur enregistré.</p></div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($controleurs as $c): ?>
                    <tr>
                        <td><?= $c['id_user'] ?></td>
                        <td><?= htmlspecialchars($c['email']) ?></td>
                        <td><span class="badge badge-controleur">Contrôleur</span></td>
                        <td>
                            <form method="POST" action=""
                                  onsubmit="return confirm('Supprimer ce contrôleur ?')">
                                <input type="hidden" name="action"  value="supprimer">
                                <input type="hidden" name="id_user" value="<?= $c['id_user'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="../assets/js/sidebar.js"></script>
</body>
</html>
