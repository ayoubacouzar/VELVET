<?php
// ─── actions.php — Gestion AJAX ──────────────────────────────────────────
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$action   = $_POST['action'] ?? $_GET['action'] ?? '';
$clientId = $_SESSION['client_id'] ?? null;

// Actions nécessitant connexion (sauf panier qui marche pour invités)
$requiresLogin = ['toggle_fav', 'remove_favorite', 'update_profile', 'change_password', 'get_order_details'];

if (in_array($action, $requiresLogin) && !$clientId) {
    echo json_encode(['success' => false, 'requireLogin' => true, 'message' => 'Connexion requise pour cette action.']);
    exit;
}

switch ($action) {

    case 'toggle_fav':
        $produitId = intval($_POST['id_produit'] ?? $_POST['produit_id'] ?? 0);
        if (!$produitId) { echo json_encode(['success' => false, 'message' => 'Produit introuvable.']); exit; }
        $check = $pdo->prepare("SELECT COUNT(*) FROM aime WHERE ID_CLIENT=? AND ID_PRODUIT=?");
        $check->execute([$clientId, $produitId]);
        if ($check->fetchColumn() > 0) {
            $pdo->prepare("DELETE FROM aime WHERE ID_CLIENT=? AND ID_PRODUIT=?")->execute([$clientId, $produitId]);
            echo json_encode(['success' => true, 'added' => false, 'message' => 'Retiré des favoris.']);
        } else {
            $pdo->prepare("INSERT INTO aime (ID_CLIENT,ID_PRODUIT,DATE_AIME) VALUES(?,?,CURDATE())")->execute([$clientId, $produitId]);
            echo json_encode(['success' => true, 'added' => true, 'message' => 'Ajouté aux favoris !']);
        }
        break;

    case 'add_to_cart':
        $produitId = intval($_POST['id_produit'] ?? 0);
        $qte       = max(1, intval($_POST['qte'] ?? 1));
        if (!$produitId) { echo json_encode(['success' => false, 'message' => 'Produit introuvable.']); exit; }
        if (!isset($_SESSION['panier_id'])) {
            $pdo->prepare("INSERT INTO panier (DATE_CREATION, MONTANT_PANIER) VALUES (CURDATE(), 0)")->execute();
            $_SESSION['panier_id'] = $pdo->lastInsertId();
        }
        $panierId = $_SESSION['panier_id'];
        $modele = $pdo->prepare("SELECT ID_MODELE, QUANTITE FROM modele_produit WHERE ID_PRODUIT=? AND QUANTITE>0 ORDER BY ID_MODELE LIMIT 1");
        $modele->execute([$produitId]);
        $modele = $modele->fetch(PDO::FETCH_ASSOC);
        if (!$modele) {
            echo json_encode(['success' => false, 'message' => 'Ce produit est en rupture de stock.']);
            exit;
        }
        $exist = $pdo->prepare("SELECT QUANTITE FROM inclure WHERE ID_PANIER=? AND ID_PRODUIT=?");
        $exist->execute([$panierId, $produitId]);
        $existRow = $exist->fetch();
        if ($existRow) {
            $pdo->prepare("UPDATE inclure SET QUANTITE=QUANTITE+? WHERE ID_PANIER=? AND ID_PRODUIT=?")->execute([$qte, $panierId, $produitId]);
        } else {
            $pdo->prepare("INSERT INTO inclure (ID_PANIER,ID_PRODUIT,QUANTITE) VALUES(?,?,?)")->execute([$panierId, $produitId, $qte]);
        }
        $total = $pdo->prepare("SELECT COALESCE(SUM(QUANTITE),0) FROM inclure WHERE ID_PANIER=?");
        $total->execute([$panierId]);
        $panierCount = (int)$total->fetchColumn();
        echo json_encode(['success' => true, 'message' => 'Ajouté au panier !', 'panier_count' => $panierCount]);
        break;

    case 'remove_from_cart':
        $produitId = intval($_POST['id_produit'] ?? 0);
        $panierId  = $_SESSION['panier_id'] ?? null;
        if (!$produitId || !$panierId) { echo json_encode(['success' => false]); exit; }
        $pdo->prepare("DELETE FROM inclure WHERE ID_PANIER=? AND ID_PRODUIT=?")->execute([$panierId, $produitId]);
        $total = $pdo->prepare("SELECT COALESCE(SUM(QUANTITE),0) FROM inclure WHERE ID_PANIER=?");
        $total->execute([$panierId]);
        $panierCount = (int)$total->fetchColumn();
        // Recalcul montant
        $stmt = $pdo->prepare("SELECT i.QUANTITE, p.PRIX, p.EN_PROMO, p.PRIX_PROMO FROM inclure i JOIN produit p ON i.ID_PRODUIT=p.ID_PRODUIT WHERE i.ID_PANIER=?");
        $stmt->execute([$panierId]);
        $rows = $stmt->fetchAll();
        $sousTotal = 0;
        foreach ($rows as $r) { $prix = ($r['EN_PROMO']&&$r['PRIX_PROMO']) ? $r['PRIX_PROMO'] : $r['PRIX']; $sousTotal += $prix * $r['QUANTITE']; }
        $livraison = $sousTotal >= 500 ? 0 : 30;
        echo json_encode(['success' => true, 'panier_count' => $panierCount, 'sous_total' => $sousTotal, 'livraison' => $livraison, 'total' => $sousTotal + $livraison]);
        break;

    case 'update_cart_qty':
        $produitId = intval($_POST['id_produit'] ?? 0);
        $qte       = intval($_POST['qte'] ?? 1);
        $panierId  = $_SESSION['panier_id'] ?? null;
        if (!$produitId || !$panierId) { echo json_encode(['success' => false]); exit; }
        if ($qte <= 0) {
            $pdo->prepare("DELETE FROM inclure WHERE ID_PANIER=? AND ID_PRODUIT=?")->execute([$panierId, $produitId]);
        } else {
            $pdo->prepare("UPDATE inclure SET QUANTITE=? WHERE ID_PANIER=? AND ID_PRODUIT=?")->execute([$qte, $panierId, $produitId]);
        }
        $stmt = $pdo->prepare("SELECT i.QUANTITE, p.PRIX, p.EN_PROMO, p.PRIX_PROMO FROM inclure i JOIN produit p ON i.ID_PRODUIT=p.ID_PRODUIT WHERE i.ID_PANIER=?");
        $stmt->execute([$panierId]);
        $rows = $stmt->fetchAll();
        $sousTotal = 0;
        foreach ($rows as $r) { $prix = ($r['EN_PROMO']&&$r['PRIX_PROMO']) ? $r['PRIX_PROMO'] : $r['PRIX']; $sousTotal += $prix * $r['QUANTITE']; }
        $panierCount = array_sum(array_column($rows, 'QUANTITE'));
        $livraison   = $sousTotal >= 500 ? 0 : 30;
        echo json_encode(['success' => true, 'sous_total' => $sousTotal, 'livraison' => $livraison, 'total' => $sousTotal + $livraison, 'panier_count' => $panierCount]);
        break;

    case 'remove_favorite':
        $produitId = intval($_POST['produit_id'] ?? 0);
        if (!$produitId) { echo json_encode(['success' => false]); exit; }
        $pdo->prepare("DELETE FROM aime WHERE ID_CLIENT=? AND ID_PRODUIT=?")->execute([$clientId, $produitId]);
        echo json_encode(['success' => true]);
        break;

    case 'update_profile':
        $nom=$prenom=$email=$tel=$adresse='';
        extract(array_map('trim', ['nom'=>$_POST['nom']??'','prenom'=>$_POST['prenom']??'','email'=>$_POST['email']??'','tel'=>$_POST['tel']??'','adresse'=>$_POST['adresse']??'']));
        if (empty($nom)||empty($prenom)||empty($email)||empty($tel)||empty($adresse)) { echo json_encode(['success'=>false,'message'=>'Tous les champs sont obligatoires.']); exit; }
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'Adresse e-mail invalide.']); exit; }
        if (!preg_match('/^0[5-7][0-9]{8}$/',$tel)&&!preg_match('/^\+212[5-7][0-9]{8}$/',$tel)) { echo json_encode(['success'=>false,'message'=>'Numéro invalide. Format : 05/06/07 + 8 chiffres.']); exit; }
        $s=$pdo->prepare("SELECT ID_CLIENT FROM client WHERE EMAIL_CLIENT=? AND ID_CLIENT!=?"); $s->execute([$email,$clientId]);
        if ($s->fetch()) { echo json_encode(['success'=>false,'message'=>'Cet e-mail est déjà utilisé.']); exit; }
        $pdo->prepare("UPDATE client SET NOM_CLIENT=?,PRENOM_CLIENT=?,EMAIL_CLIENT=?,TEL_CLIENT=?,ADRESSE_CLIENT=?,VILLE_LIVRAISON='Oujda' WHERE ID_CLIENT=?")->execute([$nom,$prenom,$email,$tel,$adresse,$clientId]);
        $_SESSION['client_nom']=$nom; $_SESSION['client_prenom']=$prenom; $_SESSION['client_email']=$email;
        echo json_encode(['success'=>true,'message'=>'Profil mis à jour avec succès !']);
        break;

    case 'change_password':
        $current=$_POST['current_password']??''; $new=$_POST['new_password']??''; $confirm=$_POST['confirm_password']??'';
        if (!$current||!$new||!$confirm) { echo json_encode(['success'=>false,'message'=>'Tous les champs sont obligatoires.']); exit; }
        if (strlen($new)<8) { echo json_encode(['success'=>false,'message'=>'Le mot de passe doit comporter au moins 8 caractères.']); exit; }
        if ($new!==$confirm) { echo json_encode(['success'=>false,'message'=>'Les mots de passe ne correspondent pas.']); exit; }
        $s=$pdo->prepare("SELECT MOT_DE_PASSE_CLIENT FROM client WHERE ID_CLIENT=?"); $s->execute([$clientId]); $row=$s->fetch();
        if (!$row||!password_verify($current,$row['MOT_DE_PASSE_CLIENT'])) { echo json_encode(['success'=>false,'message'=>'Mot de passe actuel incorrect.']); exit; }
        $pdo->prepare("UPDATE client SET MOT_DE_PASSE_CLIENT=? WHERE ID_CLIENT=?")->execute([password_hash($new,PASSWORD_DEFAULT),$clientId]);
        echo json_encode(['success'=>true,'message'=>'Mot de passe modifié avec succès !']);
        break;

    case 'get_order_details':
        $commandeId=intval($_GET['commande_id']??0);
        if (!$commandeId) { echo json_encode(['success'=>false]); exit; }
        $s=$pdo->prepare("SELECT * FROM commande WHERE ID_COMMANDE=? AND ID_CLIENT=?"); $s->execute([$commandeId,$clientId]); $commande=$s->fetch();
        if (!$commande) { echo json_encode(['success'=>false,'message'=>'Commande introuvable']); exit; }
        $s=$pdo->prepare("SELECT ct.QUANTITE,ct.PRIX,m.TAILLE,m.COULEUR,p.NOM_PRODUIT,p.IMAGE1,p.IMAGE2 FROM contient ct JOIN modele_produit m ON ct.ID_MODELE=m.ID_MODELE JOIN produit p ON m.ID_PRODUIT=p.ID_PRODUIT WHERE ct.ID_COMMANDE=?");
        $s->execute([$commandeId]);
        echo json_encode(['success'=>true,'commande'=>$commande,'articles'=>$s->fetchAll()]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
}
