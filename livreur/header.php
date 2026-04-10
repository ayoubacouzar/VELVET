<?php

if (session_status() == PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['livreur_id'])) {
    header("Location: ../login.php"); exit();
}

require_once __DIR__ . '/../db.php';
$conn = $pdo;

$stmt = $conn->prepare("SELECT * FROM livreur WHERE ID_LIVREUR = :id");
$stmt->execute(['id' => $_SESSION['livreur_id']]);
$livreur_bdd = $stmt->fetch(PDO::FETCH_ASSOC);

if ($livreur_bdd) {
    $_SESSION['livreur_nom']           = $livreur_bdd['NOM_LIVREUR'];
    $_SESSION['livreur_prenom']        = $livreur_bdd['PRENOM_LIVREUR'];
    $_SESSION['livreur_statut']        = $livreur_bdd['STATUT_LIVREUR'] ?? 'disponible';
    $_SESSION['livreur_telephone']     = $livreur_bdd['TEL_LIVREUR'];
    $_SESSION['livreur_zone']          = $livreur_bdd['ZONE_LIVRAISON'];
    $_SESSION['livreur_email']         = $livreur_bdd['EMAIL_LIVREUR'];
    $_SESSION['livreur_date_embauche'] = $livreur_bdd['DATE_EMBAUCHE'];
    $livreur = [
        'id'            => $livreur_bdd['ID_LIVREUR'],
        'nom'           => $livreur_bdd['NOM_LIVREUR'],
        'prenom'        => $livreur_bdd['PRENOM_LIVREUR'],
        'statut'        => $livreur_bdd['STATUT_LIVREUR'] ?? 'disponible',
        'telephone'     => $livreur_bdd['TEL_LIVREUR'],
        'zone'          => $livreur_bdd['ZONE_LIVRAISON'],
        'email'         => $livreur_bdd['EMAIL_LIVREUR'],
        'date_embauche' => $livreur_bdd['DATE_EMBAUCHE'],
    ];
} else {
    session_unset(); session_destroy();
    header("Location: ../login.php"); exit();
}

$initiales = strtoupper(substr($livreur['prenom'],0,1) . substr($livreur['nom'],0,1));


$profil_success = '';
$profil_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profil_livreur') {
    $telephone = trim($_POST['telephone'] ?? '');
    $zone      = trim($_POST['zone_livraison'] ?? '');
    $statut    = $_POST['statut'] ?? 'disponible';
    $mdp       = $_POST['mot_de_passe'] ?? '';
    $confirm   = $_POST['confirm_mot_de_passe'] ?? '';

    if (empty($telephone) || empty($zone)) {
        $profil_error = "Le téléphone et la zone de livraison sont obligatoires.";
    } elseif (!preg_match('/^0[5-7][0-9]{8}$/', $telephone) && !preg_match('/^\+212[5-7][0-9]{8}$/', $telephone)) {
        $profil_error = "Numéro de téléphone invalide (ex : 06XXXXXXXX).";
    } elseif (!empty($mdp) && strlen($mdp) < 8) {
        $profil_error = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif (!empty($mdp) && $mdp !== $confirm) {
        $profil_error = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            $sql    = "UPDATE livreur SET TEL_LIVREUR=:tel, ZONE_LIVRAISON=:zone, STATUT_LIVREUR=:statut";
            $params = ['tel'=>$telephone,'zone'=>$zone,'statut'=>$statut,'id'=>$livreur['id']];
            if (!empty($mdp)) {
                $sql .= ", MOT_DE_PASSE_LIVREUR=:mdp";
                $params['mdp'] = password_hash($mdp, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE ID_LIVREUR=:id";
            $conn->prepare($sql)->execute($params);

            
            $upd = $conn->prepare("SELECT * FROM livreur WHERE ID_LIVREUR=?");
            $upd->execute([$livreur['id']]);
            $upd = $upd->fetch(PDO::FETCH_ASSOC);
            $_SESSION['livreur_statut']    = $upd['STATUT_LIVREUR'];
            $_SESSION['livreur_telephone'] = $upd['TEL_LIVREUR'];
            $_SESSION['livreur_zone']      = $upd['ZONE_LIVRAISON'];
            $livreur['telephone'] = $upd['TEL_LIVREUR'];
            $livreur['zone']      = $upd['ZONE_LIVRAISON'];
            $livreur['statut']    = $upd['STATUT_LIVREUR'];

            $profil_success = "Profil mis à jour avec succès !";
        } catch (Exception $e) {
            $profil_error = "Erreur lors de la mise à jour. Veuillez réessayer.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Espace Livreur') ?> — Velvet</title>
    <link rel="icon" type="image/png" href="../images/VELVET_LOGO_blanc.png">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style_livreur.css">
</head>
<body>


<div class="lv-topbar">

    
    <div class="lv-topbar-side lv-topbar-left">
        <div class="lv-user-wrap" id="lvUserWrap">
            <button class="lv-user-btn" onclick="toggleLvDropdown(event)">
                <span class="lv-initials"><?= $initiales ?></span>
                <span class="lv-user-name"><?= htmlspecialchars($livreur['prenom'].' '.$livreur['nom']) ?></span>
                <i class="fas fa-chevron-down lv-chevron"></i>
            </button>
            <div class="lv-dropdown" id="lvDropdown">
                <div class="lv-dropdown-header">
                    <div class="lv-dropdown-avatar"><?= $initiales ?></div>
                    <div>
                        <div class="lv-dropdown-name"><?= htmlspecialchars($livreur['prenom'].' '.$livreur['nom']) ?></div>
                        <div class="lv-dropdown-email"><?= htmlspecialchars($livreur['email']) ?></div>
                    </div>
                </div>
                <div class="lv-dropdown-divider"></div>
                <a href="#" class="lv-dropdown-item"
                   onclick="openLvModal(); closeLvDropdown(); return false;">
                    <i class="fas fa-pen-to-square"></i> Modifier mon profil
                </a>
                <div class="lv-dropdown-divider"></div>
                <a href="deconnexion.php" class="lv-dropdown-item lv-dropdown-logout">
                    <i class="fas fa-right-from-bracket"></i> Déconnexion
                </a>
            </div>
        </div>
    </div>

    
    <div class="lv-topbar-center">
        <a href="index.php" class="lv-logo-link">
            <img src="../images/velvet.png" alt="VELVET" class="lv-logo-img">
        </a>
    </div>

    
    <div class="lv-topbar-side lv-topbar-right">
        <span class="lv-status-pill <?= $livreur['statut'] ?>">
            <span class="lv-status-dot"></span>
            <?= $livreur['statut'] === 'disponible' ? 'Disponible' : 'Non disponible' ?>
        </span>
    </div>

</div>


<div class="lv-modal-overlay" id="lvModalOverlay" onclick="closeLvModal()"></div>
<div class="lv-modal-box" id="lvModalBox">

    
    <div class="lv-profile-tabs">
        <button class="lv-profile-tab active" onclick="switchLvTab('info', this)">
            <i class="fas fa-user"></i> Mes informations
        </button>
        <button class="lv-profile-tab" onclick="switchLvTab('password', this)">
            <i class="fas fa-lock"></i> Mot de passe
        </button>
        <button class="lv-modal-close-tab" onclick="closeLvModal()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    
    <div id="lv-tab-info" class="lv-tab-content active">
        <form id="lvInfoForm" onsubmit="submitLvInfo(event)" novalidate>
            <input type="hidden" name="action" value="update_profil_livreur">

            
            <div id="lvInfoAlert" class="lv-form-alert" style="display:none;margin:20px 32px 0;"></div>

            <div class="lv-modal-body">
                
                <div class="lv-modal-row">
                    <div class="lv-modal-field">
                        <label>Prénom</label>
                        <input type="text" value="<?= htmlspecialchars($livreur['prenom']) ?>" readonly
                               class="lv-input-readonly">
                    </div>
                    <div class="lv-modal-field">
                        <label>Nom</label>
                        <input type="text" value="<?= htmlspecialchars($livreur['nom']) ?>" readonly
                               class="lv-input-readonly">
                    </div>
                </div>
                
                <div class="lv-modal-field">
                    <label>E-mail</label>
                    <input type="email" value="<?= htmlspecialchars($livreur['email']) ?>" readonly
                           class="lv-input-readonly">
                </div>
                
                <div class="lv-modal-field">
                    <label>Téléphone <span class="lv-req">*</span></label>
                    <input type="tel" name="telephone" id="lvTelInput"
                           value="<?= htmlspecialchars($livreur['telephone']) ?>"
                           placeholder="05/06/07XXXXXXXX" required>
                    <small style="color:#999;font-size:11px;margin-top:4px;display:block;">
                        Format marocain (05 / 06 / 07 + 8 chiffres)
                    </small>
                </div>
                
                <div class="lv-modal-field">
                    <label>Zone de livraison <span class="lv-req">*</span></label>
                    <input type="text" name="zone_livraison" id="lvZoneInput"
                           value="<?= htmlspecialchars($livreur['zone']) ?>"
                           placeholder="Ex : Hay Salam, Centre-ville..." required>
                </div>
                
                <div class="lv-modal-field">
                    <label>Statut</label>
                    <select name="statut" id="lvStatutSelect" class="lv-modal-select">
                        <option value="disponible"     <?= $livreur['statut']==='disponible'    ?'selected':'' ?>>Disponible</option>
                        <option value="non_disponible" <?= $livreur['statut']==='non_disponible'?'selected':'' ?>>Non disponible</option>
                    </select>
                </div>
                <p class="lv-required-note"><span class="lv-req">*</span> Champs obligatoires</p>
            </div>

            
            <div class="lv-save-bar">
                <div class="lv-save-bar-info">
                    <i class="fas fa-info-circle"></i>
                    Vos modifications seront enregistrées immédiatement.
                </div>
                <div class="lv-save-bar-actions">
                    <button type="button" class="lv-btn-cancel" onclick="closeLvModal()">Annuler</button>
                    <button type="submit" class="lv-btn-save" id="lvInfoSaveBtn">
                        <i class="fas fa-floppy-disk"></i>
                        <span>Sauvegarder</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    
    <div id="lv-tab-password" class="lv-tab-content">
        <form id="lvPwdForm" onsubmit="submitLvPassword(event)" novalidate>
            <input type="hidden" name="action" value="update_profil_livreur">
            
            <input type="hidden" name="telephone" id="lvPwdTelHidden"
                   value="<?= htmlspecialchars($livreur['telephone']) ?>">
            <input type="hidden" name="zone_livraison" id="lvPwdZoneHidden"
                   value="<?= htmlspecialchars($livreur['zone']) ?>">
            <input type="hidden" name="statut" id="lvPwdStatutHidden"
                   value="<?= htmlspecialchars($livreur['statut']) ?>">

            
            <div id="lvPwdAlert" class="lv-form-alert" style="display:none;margin:20px 32px 0;"></div>

            <div class="lv-modal-body">
                
                <div class="lv-modal-field">
                    <label>Nouveau mot de passe <span class="lv-req">*</span></label>
                    <div class="lv-input-icon-wrap">
                        <input type="password" name="mot_de_passe" id="lvNewPwd"
                               placeholder="Min. 8 caractères"
                               oninput="checkLvPwdStrength(this.value)" required>
                        <button type="button" class="lv-eye-btn"
                                onclick="lvToggleEye('lvNewPwd','lvEye1')">
                            <i class="fas fa-eye" id="lvEye1"></i>
                        </button>
                    </div>
                    <div class="lv-strength-bar">
                        <div class="lv-strength-fill" id="lvStrengthFill"></div>
                    </div>
                    <div class="lv-strength-label" id="lvStrengthLabel"></div>
                </div>
                
                <div class="lv-modal-field">
                    <label>Confirmer le nouveau mot de passe <span class="lv-req">*</span></label>
                    <div class="lv-input-icon-wrap">
                        <input type="password" name="confirm_mot_de_passe" id="lvConfirmPwd"
                               placeholder="Répétez le mot de passe"
                               oninput="checkLvPwdMatch()" required>
                        <button type="button" class="lv-eye-btn"
                                onclick="lvToggleEye('lvConfirmPwd','lvEye2')">
                            <i class="fas fa-eye" id="lvEye2"></i>
                        </button>
                    </div>
                    <div id="lvPwdMatchMsg" class="lv-pwd-match"></div>
                </div>
            </div>

            <div class="lv-modal-footer">
                <button type="button" class="lv-btn-cancel" onclick="closeLvModal()">Annuler</button>
                <button type="submit" class="lv-btn-save" id="lvPwdSaveBtn">
                    <span>Modifier</span> <i class="fas fa-lock"></i>
                </button>
            </div>
        </form>
    </div>

</div>


<div id="lv-toast" class="lv-toast"></div>


<div class="lv-page-wrapper">
