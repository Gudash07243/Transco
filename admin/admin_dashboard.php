<?php
// ============================================================
//  admin/admin_dashboard.php — Tableau de bord administrateur
// ============================================================

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/auth_check.php';

try {
    requireRole('ADMIN');

    $pdo = getPDO();

    // ── Statistiques globales ────────────────────────────────
    $stats = [];

    $stats['clients']     = $pdo->query('SELECT COUNT(*) FROM CLIENT')->fetchColumn();
    $stats['voyages']     = $pdo->query('SELECT COUNT(*) FROM VOYAGE')->fetchColumn();
    $stats['billets']     = $pdo->query("SELECT COUNT(*) FROM BILLET WHERE statut_billet = 'VALIDE'")->fetchColumn();
    $stats['revenus']     = $pdo->query('SELECT COALESCE(SUM(montant_total),0) FROM COMMANDE')->fetchColumn();
    $stats['controleurs'] = $pdo->query("SELECT COUNT(*) FROM UTILISATEUR WHERE role = 'CONTROLEUR'")->fetchColumn();
    $stats['bus']         = $pdo->query('SELECT COUNT(*) FROM BUS')->fetchColumn();

    // ── Prochains voyages ────────────────────────────────────
    $voyages = $pdo->query("
        SELECT v.id_voyage, l.ville_depart, l.ville_destination,
               v.date_depart, v.heure_date, v.prix_billet, v.places_disponibles,
               b.plaque_bus
        FROM VOYAGE v
        JOIN LIGNE l ON l.id_ligne = v.id_ligne
        JOIN BUS   b ON b.id_bus   = v.id_bus
    WHERE v.date_depart >= CURDATE()
    ORDER BY v.date_depart, v.heure_date
    LIMIT 8
")->fetchAll();

// ── Dernières commandes ──────────────────────────────────
$commandes = $pdo->query("
    SELECT c.id_commande, c.date_commande, c.montant_total,
           cl.nom, cl.postnom
    FROM COMMANDE c
    JOIN CLIENT cl ON cl.id_client = c.id_client
    ORDER BY c.date_commande DESC
    LIMIT 6
")->fetchAll();

} catch (Exception $e) {
    error_log('Admin Dashboard Error: ' . $e->getMessage());
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Erreur — Dashboard Admin</title>
    </head>
    <body style="background:#fee; padding:20px; font-family:Arial">
        <h2 style="color:#c33">❌ ERREUR AU CHARGEMENT DU DASHBOARD ADMIN</h2>
        <pre style="background:#fff; padding:15px; border:1px solid #ccc; overflow:auto">
<?php echo htmlspecialchars($e->getMessage()); ?>
        </pre>
        <p><a href="../public/logout.php">← Revenir à l'accueil</a></p>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — Transco</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="wrapper">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">🚌 Tran<span>co</span></div>
        <div class="sidebar-user">
            <strong><?= htmlspecialchars($_SESSION['email']) ?></strong>
            <span class="badge badge-admin">Admin</span>
        </div>
        <nav>
            <a href="admin_dashboard.php" class="active">
                <span class="nav-icon">📊</span> Dashboard
            </a>
            <a href="gestion_personnel.php">
                <span class="nav-icon">👥</span> Personnel
            </a>
            <a href="gestion_voyages.php">
                <span class="nav-icon">🗺️</span> Voyages
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="../public/logout.php">⬅ Déconnexion</a>
        </div>
    </aside>

    <!-- Main -->
    <main class="main-content">
        <div class="topbar">
            <h1>Vue d'ensemble</h1>
            <span class="badge badge-admin">Administrateur</span>
        </div>

        <!-- Stats -->
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <div class="stat-icon">👤</div>
                <div class="stat-value"><?= $stats['clients'] ?></div>
                <div class="stat-label">Clients inscrits</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚌</div>
                <div class="stat-value"><?= $stats['voyages'] ?></div>
                <div class="stat-label">Voyages planifiés</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎫</div>
                <div class="stat-value"><?= $stats['billets'] ?></div>
                <div class="stat-label">Billets valides</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value"><?= number_format($stats['revenus'], 0, ',', ' ') ?></div>
                <div class="stat-label">Revenus (FC)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🛡️</div>
                <div class="stat-value"><?= $stats['controleurs'] ?></div>
                <div class="stat-label">Contrôleurs</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚍</div>
                <div class="stat-value"><?= $stats['bus'] ?></div>
                <div class="stat-label">Bus en flotte</div>
            </div>
        </div>

        <!-- Prochains voyages -->
        <div class="card fade-in">
            <div class="card-header">
                <h3>🗺️ Prochains voyages</h3>
                <a href="gestion_voyages.php" class="btn btn-secondary btn-sm">Gérer</a>
            </div>
            <?php if (empty($voyages)): ?>
                <div class="card-body"><p style="color:var(--c-muted)">Aucun voyage à venir.</p></div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Trajet</th>
                        <th>Date</th>
                        <th>Heure</th>
                        <th>Bus</th>
                        <th>Places</th>
                        <th>Prix</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($voyages as $v): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($v['ville_depart']) ?></strong> → <?= htmlspecialchars($v['ville_destination']) ?></td>
                        <td><?= date('d/m/Y', strtotime($v['date_depart'])) ?></td>
                        <td><?= substr($v['heure_date'], 0, 5) ?></td>
                        <td><?= htmlspecialchars($v['plaque_bus']) ?></td>
                        <td><?= $v['places_disponibles'] ?></td>
                        <td><?= number_format($v['prix_billet'], 2) ?> FC</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Dernières commandes -->
        <div class="card fade-in">
            <div class="card-header">
                <h3>🧾 Dernières commandes</h3>
            </div>
            <?php if (empty($commandes)): ?>
                <div class="card-body"><p style="color:var(--c-muted)">Aucune commande.</p></div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($commandes as $c): ?>
                    <tr>
                        <td>#<?= $c['id_commande'] ?></td>
                        <td><?= htmlspecialchars($c['nom'] . ' ' . $c['postnom']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($c['date_commande'])) ?></td>
                        <td><?= number_format($c['montant_total'], 2) ?> FC</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
