<?php

$base = $base ?? '';
$_nav_return = isset($navReturnToAccount) && $navReturnToAccount === true;

$_nav_cats_raw = $pdo->query("
    SELECT c.ID_CATEGORIE, c.NOM_CATEGORIE,
           sc.ID_SOUS_CATEGORIE, sc.NOM_SOUS_CATEGORIE
    FROM categorie c
    LEFT JOIN sous_categorie sc ON sc.ID_CATEGORIE = c.ID_CATEGORIE
    ORDER BY c.ID_CATEGORIE, sc.NOM_SOUS_CATEGORIE
")->fetchAll(PDO::FETCH_ASSOC);

$_nav_cats = [];
foreach ($_nav_cats_raw as $row) {
    $_cat_id = $row['ID_CATEGORIE'];
    if (!isset($_nav_cats[$_cat_id]))
        $_nav_cats[$_cat_id] = ['nom' => $row['NOM_CATEGORIE'], 'sous' => []];
    if ($row['ID_SOUS_CATEGORIE'])
        $_nav_cats[$_cat_id]['sous'][] = ['id' => $row['ID_SOUS_CATEGORIE'], 'nom' => $row['NOM_SOUS_CATEGORIE']];
}

$_nav_tags = array_column(
    $pdo->query("SELECT NOM_SOUS_CATEGORIE FROM sous_categorie LIMIT 5")->fetchAll(PDO::FETCH_ASSOC),
    'NOM_SOUS_CATEGORIE'
);


$_panier_count = 0;
if (isset($_SESSION['panier_id'])) {
    $stmtP = $pdo->prepare("SELECT COALESCE(SUM(QUANTITE),0) FROM inclure WHERE ID_PANIER = ?");
    $stmtP->execute([$_SESSION['panier_id']]);
    $_panier_count = (int)$stmtP->fetchColumn();
}
?>

<script>window.VELVET_BASE = '<?= addslashes($base) ?>';</script>

<div class="search-overlay" id="searchOverlay" onclick="closeSearchOnBg(event)">
    <div class="search-box">
        <div class="search-input-wrap">
            <input type="text" id="searchInput"
                   placeholder="Rechercher un produit, une marque..."
                   autocomplete="off"
                   onkeydown="handleSearchKey(event)">
            <button class="search-submit" onclick="doSearch()"><i class="fas fa-search"></i></button>
        </div>
        <div class="search-suggestions">
            <?php foreach ($_nav_tags as $tag): ?>
                <span class="search-tag" onclick="fillSearch('<?= htmlspecialchars($tag, ENT_QUOTES) ?>')">
                    <?= htmlspecialchars(ucfirst(strtolower($tag))) ?>
                </span>
            <?php endforeach; ?>
        </div>
        <div class="search-close">
            <button onclick="closeSearch()"><i class="fas fa-times"></i> Fermer</button>
        </div>
    </div>
</div>


<div class="mini-bar">
    <div class="mini-bar-slide active">Livraison gratuite à partir de 500 MAD</div>
    <div class="mini-bar-slide">Retours gratuits sous 30 jours</div>
</div>


<nav class="nav-bar" id="main-nav">
    <div class="nav-container">
        <div class="nav-left">
            <div class="nav-item">
                <a href="<?= $base ?>index.php" class="nav-link">Accueil</a>
            </div>
            <?php foreach ($_nav_cats as $catId => $cat):
                $catNom = ucfirst(strtolower($cat['nom']));
                $nomLower = strtolower($cat['nom']);
                if ($nomLower === 'femmes') $catLink = $base . 'client/collection-femme.php';
                elseif ($nomLower === 'hommes') $catLink = $base . 'client/collection-homme.php';
                else $catLink = $base . 'client/produits.php?categorie=' . $catId;
            ?>
            <div class="nav-item">
                <a href="<?= $catLink ?>" class="nav-link">
                    Mode <?= htmlspecialchars($catNom) ?>
                    <?php if (!empty($cat['sous'])): ?><i class="fa fa-chevron-down"></i><?php endif; ?>
                </a>
                <?php if (!empty($cat['sous'])): ?>
                <div class="nav-dropdown">
                    <?php foreach ($cat['sous'] as $souscat): ?>
                    <a href="<?= $base ?>client/sous-categorie.php?id=<?= $souscat['id'] ?>">
                        <?= htmlspecialchars(ucfirst(strtolower($souscat['nom']))) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="nav-logo text-center">
            <a href="<?= $base ?>index.php">
                <img src="<?= $base ?>images/velvet.png" alt="VELVET">
            </a>
        </div>

        <div class="nav-right">
            <div class="nav-icon-wrap">
                <a href="#" class="nav-icon" onclick="openSearch(event)"><i class="fas fa-search"></i></a>
                <span class="nav-tooltip">Recherche</span>
            </div>

            <?php if (isset($_SESSION['client_id'])): ?>
                <?php $isClientPage = (basename($_SERVER['SCRIPT_FILENAME']) === 'index.php' && strpos($_SERVER['SCRIPT_FILENAME'], 'client') !== false); ?>
                <?php if ($isClientPage): ?>
                <div class="nav-item nav-user-item">
                    <a href="#" class="nav-icon nav-icon--active" onclick="toggleUserDropdown(event)"><i class="fas fa-user"></i></a>
                    <div class="nav-user-dropdown" id="userNavDropdown">
                        <div class="nav-user-greeting">
                            <i class="fas fa-user-circle"></i>
                            <?= htmlspecialchars(($_SESSION['client_prenom'] ?? '').' '.($_SESSION['client_nom'] ?? '')) ?>
                        </div>
                        <a href="#" onclick="openProfileModal(); closeUserDropdown(); return false;">
                            <i class="fas fa-pen-to-square"></i> Modifier mon profil
                        </a>
                        <a href="<?= $base ?>client/index.php?logout=1" class="nav-user-logout">
                            <i class="fas fa-right-from-bracket"></i> Déconnexion
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="nav-icon-wrap">
                    <a href="<?= $base ?>client/index.php" class="nav-icon nav-icon--active"><i class="fas fa-user"></i></a>
                    <span class="nav-tooltip">Mon Compte</span>
                </div>
                <?php endif; ?>
            <?php else: ?>
            <div class="nav-icon-wrap">
                <a href="<?= $base ?>login.php" class="nav-icon"><i class="far fa-user"></i></a>
                <span class="nav-tooltip">Connexion</span>
            </div>
            <?php endif; ?>

            <div class="nav-icon-wrap">
                <a href="<?= $base ?>client/favoris.php" class="nav-icon"><i class="far fa-heart"></i></a>
                <span class="nav-tooltip">Favoris</span>
            </div>
            <div class="nav-icon-wrap nav-panier-wrap">
                <a href="<?= $base ?>client/panier.php" class="nav-icon"><i class="fas fa-shopping-bag"></i></a>
                <?php if ($_panier_count > 0): ?>
                <span class="panier-nav-badge"><?= $_panier_count ?></span>
                <?php endif; ?>
                <span class="nav-tooltip">Panier</span>
            </div>
        </div>
    </div>
</nav>
