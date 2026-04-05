<?php
// livreur/update_profil.php — JSON endpoint for profile updates
if (session_status() == PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['livreur_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté.']);
    exit;
}

require_once __DIR__ . '/../db.php';

$action = $_POST['action'] ?? '';

// ── Update info ────────────────────────────────────────
if ($action === 'update_info') {
    $telephone = trim($_POST['telephone'] ?? '');
    $zone      = trim($_POST['zone_livraison'] ?? '');
    $statut    = in_array($_POST['statut'] ?? '', ['disponible','non_disponible'])
                 ? $_POST['statut']
                 : 'disponible';

    if (empty($telephone) || empty($zone)) {
        echo json_encode(['success' => false, 'message' => 'Le téléphone et la zone sont obligatoires.']);
        exit;
    }
    if (!preg_match('/^0[5-7][0-9]{8}$/', $telephone) && !preg_match('/^\+212[5-7][0-9]{8}$/', $telephone)) {
        echo json_encode(['success' => false, 'message' => 'Numéro de téléphone invalide (ex : 05/06/07XXXXXXXX).']);
        exit;
    }

    try {
        $pdo->prepare("UPDATE livreur SET TEL_LIVREUR=?, ZONE_LIVRAISON=?, STATUT_LIVREUR=? WHERE ID_LIVREUR=?")
            ->execute([$telephone, $zone, $statut, $_SESSION['livreur_id']]);

        $_SESSION['livreur_telephone'] = $telephone;
        $_SESSION['livreur_zone']      = $zone;
        $_SESSION['livreur_statut']    = $statut;

        echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès !']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur base de données. Réessayez.']);
    }
    exit;
}

// ── Update password ────────────────────────────────────
if ($action === 'update_password') {
    $mdp     = $_POST['mot_de_passe'] ?? '';
    $confirm = $_POST['confirm_mot_de_passe'] ?? '';

    if (empty($mdp)) {
        echo json_encode(['success' => false, 'message' => 'Veuillez saisir un nouveau mot de passe.']);
        exit;
    }
    if (strlen($mdp) < 8) {
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.']);
        exit;
    }
    if ($mdp !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Les mots de passe ne correspondent pas.']);
        exit;
    }

    try {
        $pdo->prepare("UPDATE livreur SET MOT_DE_PASSE_LIVREUR=? WHERE ID_LIVREUR=?")
            ->execute([password_hash($mdp, PASSWORD_DEFAULT), $_SESSION['livreur_id']]);

        echo json_encode(['success' => true, 'message' => 'Mot de passe modifié avec succès !']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur base de données. Réessayez.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action invalide.']);
