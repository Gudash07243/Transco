<?php
// ============================================================
//  client/dashboard.php — Espace client : recherche de voyages
// ============================================================

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/auth_check.php';

try {
    requireRole('CLIENT');

    $pdo = getPDO();

    // Récupérer les infos du client
    $stmt = $pdo->prepare('SELECT * FROM CLIENT WHERE id_user = ?');
    $stmt->execute([$_SESSION['id_user']]);
    $client = $stmt->fetch();

    // ── Recherche de voyages ─────────────────────────────────
    $voyages = [];
    $recherche = false;

    // Default: show upcoming voyages so user sees options immediately
    $recherche = true;
    $sql = "SELECT v.id_voyage, l.ville_depart, l.ville_destination,
                   v.date_depart, v.heure_date, v.prix_billet, v.places_disponibles,
                   b.plaque_bus, b.capacite
            FROM VOYAGE v
            JOIN LIGNE l ON l.id_ligne = v.id_ligne
            JOIN BUS   b ON b.id_bus   = v.id_bus
            WHERE v.places_disponibles > 0
              AND v.date_depart >= CURDATE()
            ORDER BY v.date_depart, v.heure_date
            LIMIT 12";
    $voyages = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {
    error_log('Dashboard Error: ' . $e->getMessage());
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Erreur — Dashboard</title>
    </head>
    <body style="background:#fee; padding:20px; font-family:Arial">
        <h2 style="color:#c33">❌ ERREUR AU CHARGEMENT DU DASHBOARD</h2>
        <pre style="background:#fff; padding:15px; border:1px solid #ccc; overflow:auto">
<?php echo htmlspecialchars($e->getMessage()); ?>
        </pre>
        <p><a href="../public/logout.php">← Revenir à l'accueil</a></p>
    </body>
    </html>
    <?php
    exit;
}

// ── Mes commandes récentes ───────────────────────────────
$commandes = [];
if ($client) {
    $stmt = $pdo->prepare("
        SELECT c.id_commande, c.date_commande, c.montant_total,
               COUNT(b.id_billet) as nb_billets
        FROM COMMANDE c
        LEFT JOIN BILLET b ON b.id_commande = c.id_commande
        WHERE c.id_client = ?
        GROUP BY c.id_commande
        ORDER BY c.date_commande DESC
        LIMIT 5
    ");
    $stmt->execute([$client['id_client']]);
    $commandes = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace — Transco</title>
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
            <strong><?= htmlspecialchars(($client['nom'] ?? '') . ' ' . ($client['postnom'] ?? '')) ?></strong>
            <span class="badge badge-client">Client</span>
        </div>
        <nav>
            <a href="dashboard.php" class="active"><span class="nav-icon">🔍</span> Rechercher</a>
            <a href="reservation.php"><span class="nav-icon">🎫</span> Mes billets</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../public/logout.php">⬅ Déconnexion</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1>Bonjour, <?= htmlspecialchars($client['nom'] ?? 'Client') ?> 👋</h1>
            <span class="badge badge-client">Client</span>
        </div>

        <!-- Voyages disponibles affichés par défaut -->

        <!-- Résultats de recherche -->
        <?php if ($recherche): ?>
        <div class="card fade-in" style="margin-bottom:2rem">
            <div class="card-header">
                <h3>🗺️ Voyages disponibles (<?= count($voyages) ?>)</h3>
            </div>
            <?php if (empty($voyages)): ?>
                <div class="card-body">
                    <div class="alert alert-info">ℹ Aucun voyage disponible pour ces critères.</div>
                </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Trajet</th>
                        <th>Date</th>
                        <th>Heure</th>
                        <th>Places</th>
                        <th>Prix</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($voyages as $v): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($v['ville_depart']) ?></strong>
                            → <?= htmlspecialchars($v['ville_destination']) ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($v['date_depart'])) ?></td>
                        <td><?= substr($v['heure_date'], 0, 5) ?></td>
                        <td><?= $v['places_disponibles'] ?> places</td>
                        <td><strong><?= number_format($v['prix_billet'], 2) ?> FC</strong></td>
                        <td>
                            <a href="reservation.php?id_voyage=<?= $v['id_voyage'] ?>"
                               class="btn btn-primary btn-sm">
                                Réserver 🎫
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Commandes récentes -->
        <?php if (!empty($commandes)): ?>
        <div class="card fade-in">
            <div class="card-header">
                <h3>🧾 Mes commandes récentes</h3>
                <a href="reservation.php" class="btn btn-secondary btn-sm">Voir tout</a>
            </div>
            <table>
                <thead>
                    <tr><th>#</th><th>Date</th><th>Billets</th><th>Montant</th></tr>
                </thead>
                <tbody>
                <?php foreach ($commandes as $c): ?>
                    <tr>
                        <td>#<?= $c['id_commande'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($c['date_commande'])) ?></td>
                        <td><?= $c['nb_billets'] ?> billet(s)</td>
                        <td><?= number_format($c['montant_total'], 2) ?> FC</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </main>
</div>
<script src="../assets/js/sidebar.js"></script>
</body>
</html>
