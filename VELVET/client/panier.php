<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['client_id'])) { header('Location: ../login.php'); exit; }

$clientId           = $_SESSION['client_id'];
$navReturnToAccount = true;
$panierId           = $_SESSION['panier_id'] ?? null;

$items     = [];
$sousTotal = 0;

if ($panierId) {
    $stmt = $pdo->prepare("
        SELECT i.ID_PRODUIT, i.QUANTITE,
               p.NOM_PRODUIT, p.IMAGE1, p.PRIX, p.EN_PROMO, p.PRIX_PROMO,
               sc.NOM_SOUS_CATEGORIE,
               (SELECT COALESCE(SUM(QUANTITE),0) FROM modele_produit WHERE ID_PRODUIT = p.ID_PRODUIT) AS stock_total
        FROM inclure i
        JOIN produit p ON i.ID_PRODUIT = p.ID_PRODUIT
        LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
        WHERE i.ID_PANIER = ?
        ORDER BY i.ID_PRODUIT
    ");
    $stmt->execute([$panierId]);
    $items = $stmt->fetchAll();
    foreach ($items as $it) {
        $p = ($it['EN_PROMO'] && $it['PRIX_PROMO']) ? $it['PRIX_PROMO'] : $it['PRIX'];
        $sousTotal += $p * $it['QUANTITE'];
    }
}

$livraison  = $sousTotal >= 500 ? 0 : 30;
$total      = $sousTotal + $livraison;
$nbArticles = array_sum(array_column($items, 'QUANTITE'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Panier — Velvet</title>
    <link rel="icon" type="image/png" href="../images/VELVET_LOGO_blanc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=DM+Sans:wght@300;400;500;700&family=Cormorant+Garamond:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        /* ── Page header ── */
        .cart-page-header {
            background: #0a0a0a;
            padding: 48px 0 36px;
            text-align: center;
        }
        .cart-page-header h1 {
            font-family: 'Anton', sans-serif;
            font-size: clamp(2rem, 5vw, 3rem);
            color: #fff;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin: 0;
        }
        .cart-page-header .cart-count-label {
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.35);
            margin-top: 8px;
        }
        .cart-breadcrumb {
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            margin-bottom: 14px;
        }
        .cart-breadcrumb a { color: rgba(255,255,255,0.4); text-decoration: none; }
        .cart-breadcrumb a:hover { color: #fff; }

        /* ── Layout ── */
        .cart-section { padding: 48px 0 80px; background: #f7f5f2; min-height: 60vh; }

        /* ── Delivery banner ── */
        .delivery-banner {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 13px;
            color: #555;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .delivery-banner strong { color: #000; }
        .delivery-banner.free { color: #2e7d32; }
        .delivery-banner i { margin-right: 8px; }
        .delivery-progress {
            height: 4px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }
        .delivery-fill {
            height: 100%;
            background: linear-gradient(90deg,#000,#555);
            border-radius: 4px;
            transition: width .6s ease;
        }

        /* ── Cart list ── */
        .cart-list { display: flex; flex-direction: column; gap: 16px; }

        /* ── Cart item ── */
        .cart-item {
            background: #fff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 18px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            transition: box-shadow .2s;
        }
        .cart-item:hover { box-shadow: 0 4px 24px rgba(0,0,0,0.1); }
        .cart-item-img {
            width: 90px;
            height: 110px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            background: #f0eeec;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cart-item-img img {
            width: 100%; height: 100%;
            object-fit: cover;
        }
        .cart-item-img .no-img { font-size: 2rem; color: #ccc; }
        .cart-item-body { flex: 1; min-width: 0; }
        .cart-item-cat {
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #999;
            margin-bottom: 4px;
        }
        .cart-item-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.1rem;
            font-weight: 600;
            color: #111;
            margin-bottom: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        /* Qty controls */
        .qty-controls {
            display: inline-flex;
            align-items: center;
            border: 1.5px solid #e0e0e0;
            border-radius: 40px;
            overflow: hidden;
        }
        .qty-btn {
            width: 34px; height: 34px;
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            color: #333;
            display: flex; align-items: center; justify-content: center;
            transition: background .15s;
        }
        .qty-btn:hover { background: #f0eeec; }
        .qty-value {
            min-width: 36px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            color: #000;
        }
        /* Price */
        .cart-item-price { text-align: right; min-width: 90px; flex-shrink: 0; }
        .cart-unit-price { font-size: 11px; color: #999; margin-bottom: 4px; }
        .cart-line-total {
            font-family: 'Anton', sans-serif;
            font-size: 1.2rem;
            color: #000;
            letter-spacing: 0.5px;
        }
        .cart-promo-old { font-size: 11px; color: #bbb; text-decoration: line-through; }
        /* Remove */
        .cart-item-remove {
            background: none;
            border: none;
            color: #ccc;
            font-size: 16px;
            cursor: pointer;
            padding: 8px;
            transition: color .2s;
            flex-shrink: 0;
        }
        .cart-item-remove:hover { color: #e74c3c; }

        /* ── Summary card ── */
        .summary-card {
            background: #fff;
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            position: sticky;
            top: 20px;
        }
        .summary-title {
            font-family: 'Anton', sans-serif;
            font-size: 1.3rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #000;
            margin-bottom: 24px;
        }
        .summary-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #555;
            padding: 10px 0;
            border-bottom: 1px solid #f0eeec;
        }
        .summary-line:last-of-type { border-bottom: none; }
        .summary-line .free-tag { color: #27ae60; font-weight: 600; }
        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Anton', sans-serif;
            font-size: 1.6rem;
            color: #000;
            letter-spacing: 1px;
            margin: 20px 0;
            padding: 16px 0;
            border-top: 2px solid #000;
        }
        .checkout-btn {
            width: 100%;
            background: #000;
            color: #fff;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-family: 'Anton', sans-serif;
            font-size: 1rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background .2s, transform .15s;
        }
        .checkout-btn:hover { background: #222; transform: translateY(-1px); }
        .checkout-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

        .continue-link {
            display: block;
            text-align: center;
            font-size: 12px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #999;
            text-decoration: none;
            margin-top: 16px;
        }
        .continue-link:hover { color: #000; }

        /* ── Empty state ── */
        .cart-empty {
            text-align: center;
            padding: 80px 20px;
        }
        .cart-empty i { font-size: 4rem; color: #ddd; display: block; margin-bottom: 20px; }
        .cart-empty h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            color: #333;
            margin-bottom: 10px;
        }
        .cart-empty p { color: #999; font-size: 14px; margin-bottom: 28px; }
        .btn-shop {
            display: inline-block;
            background: #000;
            color: #fff;
            padding: 14px 36px;
            border-radius: 40px;
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-decoration: none;
            transition: background .2s;
        }
        .btn-shop:hover { background: #333; color: #fff; }

        /* ── Removing animation ── */
        .cart-item.removing {
            animation: slideOut .35s ease forwards;
        }
        @keyframes slideOut {
            to { opacity: 0; transform: translateX(30px); max-height: 0; padding: 0; margin: 0; }
        }
    </style>
</head>
<body>

<?php $base = '../'; include __DIR__ . '/../includes/navbar.php'; ?>

<!-- Page header -->
<div class="cart-page-header">
    <div class="container">
        <div class="cart-breadcrumb">
            <a href="index.php">Mon compte</a>
            <i class="fas fa-chevron-right" style="font-size:9px;margin:0 8px;"></i>
            <span>Mon panier</span>
        </div>
        <h1>Mon Panier</h1>
        <div class="cart-count-label" id="cartCountLabel">
            <?= $nbArticles
                ? $nbArticles . ' article' . ($nbArticles > 1 ? 's' : '')
                : 'Votre panier est vide' ?>
        </div>
    </div>
</div>

<div class="cart-section">
    <div class="container">

        <?php if (empty($items)): ?>
        <div class="cart-empty">
            <i class="fas fa-shopping-bag"></i>
            <h2>Votre panier est vide</h2>
            <p>Ajoutez des articles pour commencer votre commande.</p>
            <a href="collection-femme.php" class="btn-shop me-2">Collection Femmes</a>
            <a href="collection-homme.php" class="btn-shop" style="background:#555;">Collection Hommes</a>
        </div>

        <?php else: ?>
        <div class="row g-5">

            <!-- ── Left: articles ── -->
            <div class="col-lg-8">

                <!-- Delivery bar -->
                <?php if ($sousTotal < 500): ?>
                <div class="delivery-banner" id="deliveryBanner">
                    <div>
                        <i class="fas fa-truck"></i>
                        Plus que <strong id="deliveryRemain"><?= number_format(500 - $sousTotal,0) ?> DH</strong>
                        pour la livraison gratuite !
                    </div>
                    <div class="delivery-progress">
                        <div class="delivery-fill" id="deliveryFill"
                             style="width:<?= min(100, round($sousTotal/500*100)) ?>%"></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="delivery-banner free" id="deliveryBanner">
                    <i class="fas fa-check-circle"></i>
                    <strong>Livraison gratuite</strong> — merci pour votre commande !
                </div>
                <?php endif; ?>

                <!-- Items -->
                <div class="cart-list" id="cartList">
                    <?php foreach ($items as $it):
                        $promo    = $it['EN_PROMO'] && $it['PRIX_PROMO'];
                        $prixUnit = $promo ? $it['PRIX_PROMO'] : $it['PRIX'];
                        $subtotal = $prixUnit * $it['QUANTITE'];
                        $stockMax = min(10, max(1, (int)$it['stock_total']));
                    ?>
                    <div class="cart-item" id="ci-<?= $it['ID_PRODUIT'] ?>">
                        <!-- Image -->
                        <div class="cart-item-img">
                            <?php if ($it['IMAGE1']): ?>
                                <img src="../<?= htmlspecialchars($it['IMAGE1']) ?>"
                                     alt="<?= htmlspecialchars($it['NOM_PRODUIT']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="no-img"><i class="fas fa-shirt"></i></div>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
                        <div class="cart-item-body">
                            <p class="cart-item-cat"><?= htmlspecialchars($it['NOM_SOUS_CATEGORIE'] ?? '') ?></p>
                            <p class="cart-item-name"><?= htmlspecialchars($it['NOM_PRODUIT']) ?></p>
                            <!-- Qty controls -->
                            <div class="qty-controls">
                                <button class="qty-btn"
                                        onclick="changeQty(<?= $it['ID_PRODUIT'] ?>, -1, <?= $stockMax ?>)"
                                        title="Diminuer">−</button>
                                <span class="qty-value" id="qty-<?= $it['ID_PRODUIT'] ?>"><?= $it['QUANTITE'] ?></span>
                                <button class="qty-btn"
                                        onclick="changeQty(<?= $it['ID_PRODUIT'] ?>, +1, <?= $stockMax ?>)"
                                        title="Augmenter">+</button>
                            </div>
                        </div>

                        <!-- Price -->
                        <div class="cart-item-price">
                            <?php if ($promo): ?>
                                <div class="cart-promo-old"><?= number_format($it['PRIX'],0) ?> DH</div>
                            <?php endif; ?>
                            <div class="cart-unit-price"><?= number_format($prixUnit,0) ?> DH / pièce</div>
                            <div class="cart-line-total" id="total-<?= $it['ID_PRODUIT'] ?>">
                                <?= number_format($subtotal,0) ?> DH
                            </div>
                        </div>

                        <!-- Remove -->
                        <button class="cart-item-remove"
                                onclick="removeItem(<?= $it['ID_PRODUIT'] ?>, <?= (float)$prixUnit ?>)"
                                title="Supprimer">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Right: summary ── -->
            <div class="col-lg-4">
                <div class="summary-card">
                    <h3 class="summary-title">Récapitulatif</h3>
                    <div class="summary-line">
                        <span>Sous-total</span>
                        <span id="summSousTotal"><?= number_format($sousTotal,0) ?> DH</span>
                    </div>
                    <div class="summary-line">
                        <span>Livraison</span>
                        <span id="summLivraison" class="<?= $livraison === 0 ? 'free-tag' : '' ?>">
                            <?= $livraison === 0 ? 'Gratuite' : $livraison . ' DH' ?>
                        </span>
                    </div>
                    <div class="summary-line">
                        <span>Destination</span>
                        <span>Oujda, MA</span>
                    </div>
                    <div class="summary-total">
                        <span>Total</span>
                        <span id="summTotal"><?= number_format($total,0) ?> DH</span>
                    </div>

                    <button class="checkout-btn" id="checkoutBtn" onclick="passerCommande()">
                        <i class="fas fa-lock me-2"></i>Passer la commande
                    </button>
                    <a href="index.php" class="continue-link">
                        <i class="fas fa-arrow-left me-1"></i>Retour à mon compte
                    </a>
                </div>
            </div>

        </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php include __DIR__ . '/../includes/profile_modal.php'; ?>
<div id="toast" class="toast-msg"></div>

<script>
/* ── Data state ── */
const PRIX = {
    <?php foreach ($items as $it):
        $promo = $it['EN_PROMO'] && $it['PRIX_PROMO'];
        $pu    = $promo ? $it['PRIX_PROMO'] : $it['PRIX'];
    ?>
    <?= $it['ID_PRODUIT'] ?>: <?= (float)$pu ?>,
    <?php endforeach; ?>
};
const STOCK = {
    <?php foreach ($items as $it): ?>
    <?= $it['ID_PRODUIT'] ?>: <?= min(10, max(1,(int)$it['stock_total'])) ?>,
    <?php endforeach; ?>
};

/* ── Changer quantité ── */
function changeQty(produitId, delta, stockMax) {
    const qtyEl = document.getElementById('qty-' + produitId);
    if (!qtyEl) return;
    let current = parseInt(qtyEl.textContent, 10);
    let newQty  = current + delta;
    if (newQty < 1) { removeItem(produitId, PRIX[produitId]); return; }
    if (newQty > (STOCK[produitId] || 10)) {
        showToast('Stock maximum atteint.', 'warning'); return;
    }
    qtyEl.textContent = newQty;

    // Mise à jour ligne
    const lineTotal = document.getElementById('total-' + produitId);
    if (lineTotal) lineTotal.textContent = Math.round(PRIX[produitId] * newQty).toLocaleString('fr-MA') + ' DH';

    // AJAX
    const data = new FormData();
    data.append('action', 'update_cart_qty');
    data.append('id_produit', produitId);
    data.append('qte', newQty);

    fetch('actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) updateSummary(res);
        })
        .catch(() => showToast('✗ Erreur réseau', 'error'));
}

/* ── Supprimer article ── */
function removeItem(produitId, prixUnit) {
    const item = document.getElementById('ci-' + produitId);
    if (item) {
        item.style.transition = 'opacity .3s, transform .3s';
        item.style.opacity    = '0';
        item.style.transform  = 'translateX(30px)';
        setTimeout(() => item.remove(), 320);
    }

    const data = new FormData();
    data.append('action', 'remove_from_cart');
    data.append('id_produit', produitId);

    fetch('actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                updateSummary(res);
                showToast('Article retiré du panier.', 'info');
                // Si panier vide, recharger
                if (res.panier_count === 0) {
                    setTimeout(() => location.reload(), 800);
                }
                // Mise à jour badge nav
                updateNavBadge(res.panier_count);
            }
        })
        .catch(() => showToast('✗ Erreur réseau', 'error'));
}

/* ── Mettre à jour le récap ── */
function updateSummary(res) {
    const sST = document.getElementById('summSousTotal');
    const sLV = document.getElementById('summLivraison');
    const sTT = document.getElementById('summTotal');
    const dFill = document.getElementById('deliveryFill');
    const dRemain = document.getElementById('deliveryRemain');
    const dBanner = document.getElementById('deliveryBanner');
    const cLabel  = document.getElementById('cartCountLabel');

    if (sST) sST.textContent = Math.round(res.sous_total).toLocaleString('fr-MA') + ' DH';
    if (sTT) sTT.textContent = Math.round(res.total).toLocaleString('fr-MA') + ' DH';
    if (sLV) {
        if (res.livraison === 0) {
            sLV.textContent = 'Gratuite';
            sLV.classList.add('free-tag');
        } else {
            sLV.textContent = res.livraison + ' DH';
            sLV.classList.remove('free-tag');
        }
    }
    // Delivery bar
    if (dFill) {
        const pct = Math.min(100, Math.round(res.sous_total / 500 * 100));
        dFill.style.width = pct + '%';
    }
    if (res.sous_total >= 500 && dBanner) {
        dBanner.classList.add('free');
        dBanner.innerHTML = '<i class="fas fa-check-circle"></i> <strong>Livraison gratuite</strong> — merci pour votre commande !';
    } else if (dRemain) {
        dRemain.textContent = Math.round(500 - res.sous_total).toLocaleString('fr-MA') + ' DH';
    }
    // Article count
    if (cLabel && res.panier_count !== undefined) {
        cLabel.textContent = res.panier_count
            ? res.panier_count + ' article' + (res.panier_count > 1 ? 's' : '')
            : 'Votre panier est vide';
    }
    updateNavBadge(res.panier_count);
}

/* ── Mettre à jour badge navbar ── */
function updateNavBadge(count) {
    const badges = document.querySelectorAll('.panier-nav-badge');
    badges.forEach(b => {
        if (count > 0) { b.textContent = count; b.style.display = 'flex'; }
        else { b.style.display = 'none'; }
    });
}

/* ── Passer la commande ── */
async function passerCommande() {
    const btn = document.getElementById('checkoutBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Traitement…';
    try {
        const res  = await fetch('checkout.php', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            btn.innerHTML = '<i class="fas fa-check me-2"></i>Commande confirmée !';
            btn.style.background = '#2e7d32';
            setTimeout(() => { window.location.href = 'index.php?commande=ok'; }, 1500);
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock me-2"></i>Passer la commande';
            showToast('✗ ' + (data.message || 'Une erreur est survenue.'), 'error');
        }
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock me-2"></i>Passer la commande';
        showToast('✗ Erreur réseau. Veuillez réessayer.', 'error');
    }
}
</script>
<script src="../JS/script.js"></script>
</body>
</html>
