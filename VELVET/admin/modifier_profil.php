<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ../login.php'); exit; }
require_once __DIR__ . '/../db.php';

$currentPage = basename($_SERVER['PHP_SELF']);

$admin_id = $_SESSION['admin_id'];

$stmt = $pdo->prepare("SELECT * FROM administrateur WHERE ID_ADMIN = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$errors  = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nom       = trim($_POST['nom']             ?? '');
    $prenom    = trim($_POST['prenom']          ?? '');
    $email     = trim($_POST['email']           ?? '');
    $password  = $_POST['password']             ?? '';
    $password2 = $_POST['password_confirm']     ?? '';

    // Validations
    if (empty($nom)) $errors['nom'] = "Le nom est obligatoire.";
    elseif (strlen($nom) < 2) $errors['nom'] = "Le nom doit contenir au moins 2 caractères.";

    if (empty($prenom)) $errors['prenom'] = "Le prénom est obligatoire.";
    elseif (strlen($prenom) < 2) $errors['prenom'] = "Le prénom doit contenir au moins 2 caractères.";

    if (empty($email)) $errors['email'] = "L'adresse e-mail est obligatoire.";
    elseif (strlen($email) > 254) $errors['email'] = "L'adresse e-mail est trop longue.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = "L'adresse e-mail n'est pas valide.";
    else {
        $check = $pdo->prepare("SELECT ID_ADMIN FROM administrateur WHERE EMAIL_ADMIN = ? AND ID_ADMIN != ?");
        $check->execute([$email, $admin_id]);
        if ($check->fetch()) $errors['email'] = "Cette adresse e-mail est déjà utilisée.";
    }

    if (!empty($password)) {
        if (strlen($password) < 8) $errors['password'] = "Le mot de passe doit contenir au moins 8 caractères.";
        elseif (strlen($password) > 30) $errors['password'] = "Le mot de passe ne peut pas dépasser 30 caractères.";
        elseif (preg_match('/<[^>]*>|<\/[^>]*>/i', $password)) $errors['password'] = "Le mot de passe contient des balises HTML non autorisées.";
        elseif (preg_match('/script|alert|eval|document|window|onload|onerror/i', $password)) $errors['password'] = "Le mot de passe contient des mots-clés non autorisés.";
        elseif (preg_match('/SELECT|INSERT|UPDATE|DELETE|DROP|UNION|ALTER|CREATE|EXEC|--|;|\/\*|\*\//i', $password)) $errors['password'] = "Le mot de passe contient des caractères non autorisés.";
        elseif (!preg_match('/[0-9]/', $password)) $errors['password'] = "Le mot de passe doit contenir au moins un chiffre.";
        elseif ($password !== $password2) $errors['password_confirm'] = "Les mots de passe ne correspondent pas.";
    }

    if (empty($errors)) {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE administrateur SET NOM_ADMIN=?, PRENOM_ADMIN=?, EMAIL_ADMIN=?, MOT_DE_PASSE_ADMIN=? WHERE ID_ADMIN=?")
                ->execute([$nom, $prenom, $email, $hash, $admin_id]);
        } else {
            $pdo->prepare("UPDATE administrateur SET NOM_ADMIN=?, PRENOM_ADMIN=?, EMAIL_ADMIN=? WHERE ID_ADMIN=?")
                ->execute([$nom, $prenom, $email, $admin_id]);
        }

        $stmt = $pdo->prepare("SELECT * FROM administrateur WHERE ID_ADMIN = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        $success = "Profil mis à jour avec succès.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier mon profil | Velvet</title>
    <link rel="icon" type="image/png" href="../images/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/style_admin.css">
    <style>
        .global-toast { position: fixed; top: 20px; left: 50%; transform: translateX(-50%) translateY(-100%); background: #2e7d32; color: #fff; padding: 12px 24px; border-radius: 40px; font-size: 14px; font-weight: 500; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: transform 0.3s ease, opacity 0.3s ease; opacity: 0; pointer-events: none; font-family: 'Inter', sans-serif; }
        .global-toast.error { background: #c62828; }
        .global-toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .main-content-profile { align-items: center; justify-content: center; min-height: 100vh; padding: 40px 32px; }
        .profile-wrap { max-width: 520px; width: 100%; }
        .profile-card { background: #fff; border-radius: 16px; padding: 40px; box-shadow: 0 4px 24px rgba(0,0,0,0.07); }
        .profile-card h2 { font-family: 'Anton', sans-serif; font-size: 28px; margin-bottom: 6px; }
        .profile-card .subtitle { font-size: 13px; color: #888; margin-bottom: 32px; font-style: italic; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }
        .field-group { display: flex; flex-direction: column; margin-bottom: 18px; }
        .field-group label { font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #333; margin-bottom: 7px; }
        .input-wrap { position: relative; }
        .input-wrap input { width: 100%; padding: 12px 14px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 14px; outline: none; background: #fafafa; }
        .input-wrap input:focus { border-color: #000; background: #fff; }
        .input-wrap input.error { border-color: #e53935; }
        .input-wrap input.valid { border-color: #43a047; }
        .eye-btn { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #aaa; }
        .eye-btn:hover { color: #000; }
        .field-error { display: flex; align-items: center; gap: 5px; font-size: 11.5px; color: #e53935; margin-top: 5px; }
        .divider { display: flex; align-items: center; gap: 12px; margin: 24px 0 20px; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #eee; }
        .divider span { font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: 1px; }
        .btn-submit { width: 100%; padding: 14px; background: #000; color: #fff; border: none; border-radius: 10px; font-size: 13.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 8px; }
        .btn-submit:hover { background: #222; }
        .hint { font-size: 11.5px; color: #aaa; margin-bottom: 15px; }
        .pwd-checklist { list-style: none; margin-top: 10px; display: flex; flex-direction: column; gap: 5px; }
        .pwd-checklist li { font-size: 12px; color: #e53935; display: flex; align-items: center; gap: 7px; transition: color 0.2s; }
        .pwd-checklist li.valid { color: #43a047; }
        .pwd-checklist li.valid i::before { content: "\f00c"; }
    </style>
</head>
<body>
<div class="container">

    <div class="sidebar">
        <div class="logo-section"><a href="index.php"><img src="../images/logo2.png" alt="Logo"></a></div>
        <ul class="menu">
            <li><a href="index.php"          class="<?= $currentPage=='index.php'          ? 'active':'' ?>"><i class="fas fa-chart-line"></i><span> Tableau de bord</span></a></li>
            <li><a href="produits.php"        class="<?= $currentPage=='produits.php'        ? 'active':'' ?>"><i class="fas fa-box"></i><span> Produits</span></a></li>
            <li><a href="categories.php"      class="<?= $currentPage=='categories.php'      ? 'active':'' ?>"><i class="fas fa-tags"></i><span> Catégories</span></a></li>
            <li><a href="comptes.php"         class="<?= $currentPage=='comptes.php'         ? 'active':'' ?>"><i class="fas fa-users"></i><span> Comptes</span></a></li>
            <li><a href="commandes.php"       class="<?= $currentPage=='commandes.php'       ? 'active':'' ?>"><i class="fas fa-shopping-cart"></i><span> Commandes</span></a></li>
            <li><a href="avis.php"            class="<?= $currentPage=='avis.php'            ? 'active':'' ?>"><i class="fas fa-star"></i><span> Avis</span></a></li>
            <li><a href="messages.php"        class="<?= $currentPage=='messages.php'        ? 'active':'' ?>"><i class="fas fa-envelope"></i><span> Messages</span></a></li>
            <li><a href="modifier_profil.php" class="<?= $currentPage=='modifier_profil.php' ? 'active':'' ?>"><i class="fas fa-user-cog"></i><span> Mon profil</span></a></li>
            <li class="logout"><a href="deconnexion.php"><i class="fas fa-sign-out-alt"></i><span> Déconnexion</span></a></li>
        </ul>
    </div>

    <div class="main-content main-content-profile">
        <div class="profile-wrap">
            <div class="profile-card">
                <h2>Modifier mon profil</h2>
                <p class="subtitle">Mettez à jour vos informations administrateur.</p>

                <?php if ($success): ?>
                <div id="phpToast" data-msg="<?= htmlspecialchars($success) ?>" data-type="success"></div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                <div id="phpToast" data-msg="<?= htmlspecialchars('Veuillez corriger les erreurs ci-dessous.') ?>" data-type="error"></div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="field-row">
                        <div class="field-group">
                            <label>Prénom</label>
                            <div class="input-wrap">
                                <input type="text" name="prenom" value="<?= htmlspecialchars($_POST['prenom'] ?? $admin['PRENOM_ADMIN'] ?? '') ?>" class="<?= isset($errors['prenom']) ? 'error' : '' ?>">
                            </div>
                            <?php if (isset($errors['prenom'])): ?><div class="field-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($errors['prenom']) ?></div><?php endif; ?>
                        </div>
                        <div class="field-group">
                            <label>Nom</label>
                            <div class="input-wrap">
                                <input type="text" name="nom" value="<?= htmlspecialchars($_POST['nom'] ?? $admin['NOM_ADMIN'] ?? '') ?>" class="<?= isset($errors['nom']) ? 'error' : '' ?>">
                            </div>
                            <?php if (isset($errors['nom'])): ?><div class="field-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($errors['nom']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="field-group">
                        <label>E-mail</label>
                        <div class="input-wrap">
                            <input type="email" name="email" maxlength="254" value="<?= htmlspecialchars($_POST['email'] ?? $admin['EMAIL_ADMIN'] ?? '') ?>" class="<?= isset($errors['email']) ? 'error' : '' ?>">
                        </div>
                        <?php if (isset($errors['email'])): ?><div class="field-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="divider"><span>Changer le mot de passe</span></div>
                    <p class="hint">Laissez vide pour conserver le mot de passe actuel.</p>
                    <div class="field-group">
                        <label>Nouveau mot de passe</label>
                        <div class="input-wrap">
                            <input type="password" name="password" id="pwd" maxlength="30" class="has-eye <?= isset($errors['password']) ? 'error' : '' ?>" oninput="checkPassword(this.value)">
                            <button type="button" class="eye-btn" onclick="togglePwd('pwd','eye1')"><i class="fas fa-eye" id="eye1"></i></button>
                        </div>
                        <?php if (isset($errors['password'])): ?><div class="field-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
                        <ul class="pwd-checklist" id="pwdChecklist">
                            <li id="chk-len"><i class="fas fa-circle"></i> Entre 8 et 30 caractères</li>
                            <li id="chk-num"><i class="fas fa-circle"></i> Au moins un chiffre</li>
                            <li id="chk-safe"><i class="fas fa-circle"></i> Aucun caractère dangereux</li>
                        </ul>
                    </div>
                    <div class="field-group">
                        <label>Confirmer le mot de passe</label>
                        <div class="input-wrap">
                            <input type="password" name="password_confirm" id="pwd2" maxlength="30" class="has-eye <?= isset($errors['password_confirm']) ? 'error' : '' ?>">
                            <button type="button" class="eye-btn" onclick="togglePwd('pwd2','eye2')"><i class="fas fa-eye" id="eye2"></i></button>
                        </div>
                        <?php if (isset($errors['password_confirm'])): ?><div class="field-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($errors['password_confirm']) ?></div><?php endif; ?>
                    </div>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Enregistrer les modifications</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePwd(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye', 'fa-eye-slash'); }
    else { input.type = 'password'; icon.classList.replace('fa-eye-slash', 'fa-eye'); }
}
function checkPassword(val) {
    const list = document.getElementById('pwdChecklist');
    list.style.display = val.length > 0 ? 'flex' : 'none';
    const checks = {
        'chk-len':  val.length >= 8 && val.length <= 30,
        'chk-num':  /[0-9]/.test(val),
        'chk-safe': !/<|>|script|eval|alert|SELECT|INSERT|UPDATE|DELETE|DROP|--|;/i.test(val)
    };
    for (const [id, valid] of Object.entries(checks)) {
        document.getElementById(id).classList.toggle('valid', valid);
    }
}
function showToast(msg, type) {
    let toast = document.getElementById('globalToast');
    if (!toast) { toast = document.createElement('div'); toast.id = 'globalToast'; toast.className = 'global-toast'; document.body.appendChild(toast); }
    toast.textContent = msg;
    toast.className = 'global-toast ' + (type === 'success' ? 'success' : 'error') + ' show';
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.classList.remove('show'), 5000);
}
window.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('phpToast');
    if (el) showToast(el.dataset.msg, el.dataset.type);
    document.getElementById('pwdChecklist').style.display = 'none';
});
</script>
</body>
</html>