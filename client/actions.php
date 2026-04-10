<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$action   = $_POST['action'] ?? $_GET['action'] ?? '';
$clientId = $_SESSION['client_id'] ?? null;
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
        $taille    = trim($_POST['taille'] ?? '');
        $qte       = max(1, intval($_POST['qte'] ?? 1));
        if (!$produitId) { echo json_encode(['success' => false, 'message' => 'Produit introuvable.']); exit; }
        if (empty($taille)) { echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner une taille.']); exit; }
        if (!isset($_SESSION['panier_id'])) {
            $pdo->prepare("INSERT INTO panier (DATE_CREATION, MONTANT_PANIER) VALUES (CURDATE(), 0)")->execute();
            $_SESSION['panier_id'] = $pdo->lastInsertId();
        }
        $panierId = $_SESSION['panier_id'];
        $stockStmt = $pdo->prepare("SELECT COALESCE(SUM(QUANTITE),0) FROM modele_produit WHERE ID_PRODUIT=? AND TAILLE=?");
        $stockStmt->execute([$produitId, $taille]);
        $stockTaille = (int)$stockStmt->fetchColumn();
        if ($stockTaille <= 0) {
            echo json_encode(['success' => false, 'message' => 'Cette taille est en rupture de stock.']);
            exit;
        }
        $exist = $pdo->prepare("SELECT QUANTITE FROM inclure WHERE ID_PANIER=? AND ID_PRODUIT=? AND TAILLE=?");
        $exist->execute([$panierId, $produitId, $taille]);
        $existRow = $exist->fetch();
        $currentQty = $existRow ? (int)$existRow['QUANTITE'] : 0;
        $newQty = $currentQty + $qte;
        if ($newQty > $stockTaille) {
            $remaining = $stockTaille - $currentQty;
            if ($remaining <= 0) {
                echo json_encode(['success' => false, 'message' => 'Vous avez déjà le maximum en stock ('.$stockTaille.') pour la taille '.$taille.' dans votre panier.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Stock insuffisant pour la taille '.$taille.'. Il ne reste que '.$remaining.' article(s).']);
            }
            exit;
        }
        if ($existRow) {
            $pdo->prepare("UPDATE inclure SET QUANTITE=? WHERE ID_PANIER=? AND ID_PRODUIT=? AND TAILLE=?")->execute([$newQty, $panierId, $produitId, $taille]);
        } else {
            $pdo->prepare("INSERT INTO inclure (ID_PANIER,ID_PRODUIT,TAILLE,QUANTITE) VALUES(?,?,?,?)")->execute([$panierId, $produitId, $taille, $qte]);
        }
        $total = $pdo->prepare("SELECT COALESCE(SUM(QUANTITE),0) FROM inclure WHERE ID_PANIER=?");
        $total->execute([$panierId]);
        $panierCount = (int)$total->fetchColumn();
        echo json_encode(['success' => true, 'message' => 'Ajouté au panier ! (Taille '.$taille.')', 'panier_count' => $panierCount]);
        break;

    case 'remove_from_cart':
        $produitId = intval($_POST['id_produit'] ?? 0);
        $taille    = trim($_POST['taille'] ?? '');
        $panierId  = $_SESSION['panier_id'] ?? null;
        if (!$produitId || !$panierId) { echo json_encode(['success' => false]); exit; }
        $pdo->prepare("DELETE FROM inclure WHERE ID_PANIER=? AND ID_PRODUIT=? AND TAILLE=?")->execute([$panierId, $produitId, $taille]);
        $total = $pdo->prepare("SELECT COALESCE(SUM(QUANTITE),0) FROM inclure WHERE ID_PANIER=?");
        $total->execute([$panierId]);
        $panierCount = (int)$total->fetchColumn();
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
        $taille    = trim($_POST['taille'] ?? '');
        $qte       = intval($_POST['qte'] ?? 1);
        $panierId  = $_SESSION['panier_id'] ?? null;
        if (!$produitId || !$panierId) { echo json_encode(['success' => false]); exit; }
        if ($qte <= 0) {
            $pdo->prepare("DELETE FROM inclure WHERE ID_PANIER=? AND ID_PRODUIT=? AND TAILLE=?")->execute([$panierId, $produitId, $taille]);
        } else {
            $stockStmt = $pdo->prepare("SELECT COALESCE(SUM(QUANTITE),0) FROM modele_produit WHERE ID_PRODUIT=? AND TAILLE=?");
            $stockStmt->execute([$produitId, $taille]);
            $stockTaille = (int)$stockStmt->fetchColumn();
            if ($qte > $stockTaille) {
                echo json_encode(['success' => false, 'message' => 'Stock insuffisant pour la taille '.$taille.'. Il ne reste que '.$stockTaille.' article(s).', 'stock_max' => $stockTaille]);
                exit;
            }
            $pdo->prepare("UPDATE inclure SET QUANTITE=? WHERE ID_PANIER=? AND ID_PRODUIT=? AND TAILLE=?")->execute([$qte, $panierId, $produitId, $taille]);
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

    case 'add_avis':
        if (!$clientId) { echo json_encode(['success' => false, 'requireLogin' => true]); exit; }
        $produitId = intval($_POST['id_produit'] ?? 0);
        $note = max(1, min(5, intval($_POST['note'] ?? 0)));
        $commentaire = trim($_POST['commentaire'] ?? '');
        if (!$produitId || !$note || empty($commentaire)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs et choisir une note.']);
            exit;
        }
        try {
            $stmtA = $pdo->prepare("INSERT INTO avis (ID_CLIENT, ID_PRODUIT, NOTE, COMMENTAIRE, DATE_AVIS) VALUES (?, ?, ?, ?, CURDATE())");
            $stmtA->execute([$clientId, $produitId, $note, $commentaire]);
            $nom = $pdo->prepare("SELECT CONCAT(PRENOM_CLIENT, ' ', LEFT(NOM_CLIENT, 1), '.') AS NOM FROM client WHERE ID_CLIENT = ?");
            $nom->execute([$clientId]);
            $auteur = $nom->fetchColumn() ?: 'Client';
            echo json_encode(['success' => true, 'message' => 'Merci ! Votre avis a été publié.', 'avis' => [
                'note' => $note, 'commentaire' => htmlspecialchars($commentaire), 'auteur' => htmlspecialchars($auteur), 'date' => date('d M Y')
            ]]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la soumission.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
}
