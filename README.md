# 🚌 Tranco — Plateforme de Gestion de Transport

Application web de gestion de transport développée en **PHP Procédural + MySQL (PDO)**.

---

## 📁 Arborescence du projet

```
tranco/
├── index.php                      ← Point d'entrée (redirige vers login)
├── tranco_db.sql                  ← Script SQL complet
│
├── config/
│   ├── db_connect.php             ← Connexion PDO
│   └── auth_check.php             ← Vérification sessions & rôles
│
├── public/
│   ├── login.php                  ← Page de connexion
│   ├── register.php               ← Inscription client
│   └── logout.php                 ← Déconnexion
│
├── admin/
│   ├── admin_dashboard.php        ← Vue d'ensemble (stats, voyages, commandes)
│   ├── gestion_personnel.php      ← Création des comptes Contrôleurs
│   └── gestion_voyages.php        ← Gestion bus, lignes & voyages (onglets)
│
├── client/
│   ├── dashboard.php              ← Recherche de voyages disponibles
│   └── reservation.php           ← Achat de billets (famille, max 10)
│
├── controlleur/
│   └── scan.php                   ← Interface de scan & validation QR
│
└── assets/
    └── css/
        └── style.css              ← Feuille de style globale (dark theme)
```

---

## ⚙️ Installation

### 1. Base de données

```sql
-- Importez le fichier SQL :
mysql -u root -p < tranco_db.sql
```

Ou copiez le contenu de `tranco_db.sql` dans phpMyAdmin.

### 2. Configuration

Ouvrez `config/db_connect.php` et ajustez :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tranco_db');
define('DB_USER', 'root');    // ← votre utilisateur MySQL
define('DB_PASS', '');        // ← votre mot de passe MySQL
```

### 3. Créer le compte Admin

Exécutez ce script PHP **une seule fois** pour créer le premier administrateur :

```php
<?php
require 'config/db_connect.php';
$pdo = getPDO();
$hash = password_hash('Admin@2024', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO UTILISATEUR (email, mot_de_passe, role) VALUES (?, ?, 'ADMIN')")
    ->execute(['admin@tranco.com', $hash]);
echo 'Admin créé !';
```

### 4. Démarrer

Placez le dossier `tranco/` dans votre dossier web (`htdocs`, `www`, ou `public_html`).

Accédez à : `http://localhost/tranco/`

---

## 🔐 Comptes de démonstration

| Rôle        | Email               | Mot de passe  |
|-------------|---------------------|---------------|
| ADMIN       | admin@tranco.com    | Admin@2024    |
| CONTROLEUR  | *(créé par admin)*  | *(défini par admin)* |
| CLIENT      | *(auto-inscription)*| *(défini à l'inscription)* |

---

## 🎯 Fonctionnalités par rôle

### 👑 ADMIN
- Dashboard avec statistiques globales
- Gestion du personnel (création / suppression de contrôleurs)
- Planification des voyages (bus, lignes, voyages par onglets)

### 👤 CLIENT
- Inscription en ligne
- Recherche de voyages par ville et date
- Réservation familiale (1 à 10 billets par commande)
- Consultation de ses billets avec codes QR

### 🛡️ CONTRÔLEUR
- Interface mobile-first de scan QR
- Validation des billets (marquage UTILISE)
- Détection des billets déjà utilisés ou annulés
- Statistiques de validation du jour

---

## 🔒 Sécurité

- Mots de passe hashés avec `password_hash()` / `password_verify()`
- Sessions sécurisées avec `session_regenerate_id()` à la connexion
- Protection par rôle sur chaque page (`requireRole()`)
- Requêtes préparées PDO (protection contre les injections SQL)
- Échappement HTML avec `htmlspecialchars()` sur toutes les sorties

---

## 🛠️ Technologies

- **Backend** : PHP 8+ Procédural
- **Base de données** : MySQL 8+ via PDO
- **Frontend** : HTML5, CSS3 (design sombre personnalisé)
- **Polices** : Syne + DM Sans (Google Fonts)
