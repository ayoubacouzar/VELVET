<?php
session_start();
require_once __DIR__ . '/../db.php';

if (isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$erreur = '';
$succes = false;
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom      = trim($_POST['nom']              ?? '');
    $prenom   = trim($_POST['prenom']           ?? '');
    $email    = trim($_POST['email']            ?? '');
    $tel      = trim($_POST['telephone']        ?? '');
    $adresse  = trim($_POST['adresse']          ?? '');
    $ville    = 'Oujda';
    $password =      $_POST['password']         ?? '';
    $confirm  =      $_POST['confirm_password'] ?? '';


    $old = ['nom'=>$nom,'prenom'=>$prenom,'email'=>$email,'tel'=>$tel,'adresse'=>$adresse,'ville'=>$ville];

    if (empty($nom)||empty($prenom)||empty($email)||empty($tel)||empty($adresse)||empty($ville)||empty($password)) {
        $erreur = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Adresse e-mail invalide.";
    } elseif (!preg_match('/^0[5-7][0-9]{8}$/', $tel) && !preg_match('/^\+212[5-7][0-9]{8}$/', $tel)) {
        $erreur = "Numéro de téléphone marocain invalide (ex : 05/06/07XXXXXXXX).";
    } elseif (strlen($password) < 8) {
        $erreur = "Le mot de passe doit comporter au moins 8 caractères.";
    } elseif ($password !== $confirm) {
        $erreur = "Les mots de passe ne correspondent pas.";
    } else {
        $stmt = $pdo->prepare("SELECT ID_CLIENT FROM client WHERE EMAIL_CLIENT = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erreur = "Cette adresse e-mail est déjà associée à un compte.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO client (NOM_CLIENT,PRENOM_CLIENT,EMAIL_CLIENT,MOT_DE_PASSE_CLIENT,TEL_CLIENT,ADRESSE_CLIENT,VILLE_LIVRAISON) VALUES (?,?,?,?,?,?,?)")
                ->execute([$nom,$prenom,$email,$hash,$tel,$adresse,$ville]);
            $succes = true;
            $old    = [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — Velvet</title>
    <link rel="icon" type="image/png" href="../images/VELVET_LOGO_blanc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=DM+Sans:wght@300;400;500;700&family=Cormorant+Garamond:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body class="auth-page-body">

<?php $base = '../'; include __DIR__ . '/../includes/navbar.php'; ?>

<main class="auth-main">
    <div class="auth-card auth-card-wide">

        <h1 class="auth-title">Créer un compte</h1>
        <p class="auth-subtitle">Rejoignez l'univers Velvet.</p>

        <?php if ($erreur): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($erreur) ?>
        </div>
        <?php endif; ?>

        <?php if ($succes): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i>
            <div>
                Compte créé avec succès !<br>
                <a href="../login.php">Se connecter maintenant →</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$succes): ?>
        <form method="post" novalidate>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Prénom</label>
                    <input class="form-input" type="text" name="prenom"
                           value="<?= htmlspecialchars($old['prenom'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nom</label>
                    <input class="form-input" type="text" name="nom"
                           value="<?= htmlspecialchars($old['nom'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">E-mail</label>
                <input class="form-input" type="email" name="email"
                       value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                       placeholder="votre@email.com" required>
            </div>

            <div class="form-group">
                <label class="form-label">Téléphone</label>
                <input class="form-input" type="tel" name="telephone"
                       value="<?= htmlspecialchars($old['tel'] ?? '') ?>"
                       placeholder="05/06/07XXXXXXXX" required>
                <div class="form-hint">Format marocain uniquement (06 / 07 / +2126 / +2127)</div>
            </div>

            <div class="form-group">
                <label class="form-label">Adresse de livraison</label>
                <textarea class="form-textarea" name="adresse" required><?= htmlspecialchars($old['adresse'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Ville</label>
                <input class="form-input" type="text" name="ville" value="Oujda" readonly
                       style="background:rgba(0,0,0,0.04);cursor:not-allowed;color:#555;">
                <div class="form-hint">Livraison disponible uniquement à Oujda.</div>
                <input type="hidden" name="ville" value="Oujda">
            </div>

            <div class="form-group">
                <label class="form-label">Mot de passe</label>
                <div class="input-wrap">
                    <input class="form-input" type="password" name="password"
                           id="passwordField" placeholder="Min. 8 caractères" required>
                    <button type="button" class="toggle-pwd" onclick="togglePwd('passwordField','eyeIcon1')">
                        <i class="fas fa-eye" id="eyeIcon1"></i>
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-label" id="strengthLabel"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Confirmer le mot de passe</label>
                <div class="input-wrap">
                    <input class="form-input" type="password" name="confirm_password"
                           id="confirmField" placeholder="Répétez le mot de passe" required>
                    <button type="button" class="toggle-pwd" onclick="togglePwd('confirmField','eyeIcon2')">
                        <i class="fas fa-eye" id="eyeIcon2"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-auth-submit">
                <i class="fas fa-user-plus"></i> Créer mon compte
            </button>
        </form>
        <?php endif; ?>

        <div class="auth-divider">ou</div>
        <div class="auth-link">
            Déjà inscrit ? <a href="../login.php">Se connecter</a>
        </div>

    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="../JS/script.js"></script>
</body>
</html>
