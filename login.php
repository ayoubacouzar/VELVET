<?php
session_start();

// Already logged in → redirect to right page
if (isset($_SESSION['client_id']))  { header('Location: client/index.php');   exit; }
if (isset($_SESSION['admin_id']))   { header('Location: admin/index.php');    exit; }
if (isset($_SESSION['livreur_id'])) { header('Location: livreur/index.php'); exit; }

require_once __DIR__ . '/db.php';

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $erreur = "Veuillez remplir tous les champs.";
    } else {
        // 1. Check CLIENT
        $stmt = $pdo->prepare("SELECT * FROM client WHERE EMAIL_CLIENT = ?");
        $stmt->execute([$email]);
        $client = $stmt->fetch();
        if ($client && password_verify($password, $client['MOT_DE_PASSE_CLIENT'])) {
            $_SESSION['client_id']     = $client['ID_CLIENT'];
            $_SESSION['client_prenom'] = $client['PRENOM_CLIENT'];
            $_SESSION['client_nom']    = $client['NOM_CLIENT'];
            $_SESSION['client_email']  = $client['EMAIL_CLIENT'];
            header('Location: client/index.php');
            exit;
        }
        // 2. Check ADMIN
        $stmt = $pdo->prepare("SELECT * FROM administrateur WHERE EMAIL_ADMIN = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['MOT_DE_PASSE_ADMIN'])) {
            $_SESSION['admin_id']     = $admin['ID_ADMIN'];
            $_SESSION['admin_prenom'] = $admin['PRENOM_ADMIN'];
            $_SESSION['admin_nom']    = $admin['NOM_ADMIN'];
            $_SESSION['admin_email']  = $admin['EMAIL_ADMIN'];
            header('Location: admin/index.php');
            exit;
        }
        // 3. Check LIVREUR
        $stmt = $pdo->prepare("SELECT * FROM livreur WHERE EMAIL_LIVREUR = ?");
        $stmt->execute([$email]);
        $livreur = $stmt->fetch();
        if ($livreur && password_verify($password, $livreur['MOT_DE_PASSE_LIVREUR'])) {
            $_SESSION['livreur_id']     = $livreur['ID_LIVREUR'];
            $_SESSION['livreur_nom']    = $livreur['NOM_LIVREUR'];
            $_SESSION['livreur_prenom'] = $livreur['PRENOM_LIVREUR'];
            $_SESSION['livreur_email']  = $livreur['EMAIL_LIVREUR'];
            $_SESSION['livreur_zone']   = $livreur['ZONE_LIVRAISON'];
            header('Location: livreur/index.php');
            exit;
        }

        $erreur = "Email ou mot de passe incorrect.";
    }
}

$base = ''; // root level
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Velvet</title>
    <link rel="icon" type="image/png" href="images/VELVET_LOGO_blanc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=DM+Sans:wght@300;400;500;700&family=Cormorant+Garamond:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/style.css">
</head>
<body class="auth-page-body">

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="auth-main">
    <div class="auth-card">

        <h1 class="auth-title">Connexion</h1>
        <p class="auth-subtitle">Bienvenue dans votre espace Velvet.</p>

        <?php if ($erreur): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($erreur) ?>
        </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="form-group">
                <label class="form-label">E-mail</label>
                <input class="form-input" type="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="votre@email.com" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Mot de passe</label>
                <div class="input-wrap">
                    <input class="form-input" type="password" name="password"
                           id="passwordField" placeholder="••••••••" required>
                    <button type="button" class="toggle-pwd"
                            onclick="togglePwd('passwordField','eyeIcon')" title="Afficher / Masquer">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-auth-submit">
                <i class="fas fa-right-to-bracket"></i> Se connecter
            </button>
        </form>

        <div class="auth-divider">ou</div>
        <div class="auth-link">
            Pas encore de compte ?
            <a href="client/inscription.php">Créer un compte</a>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="JS/script.js"></script>
</body>
</html>
