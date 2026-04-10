<?php

$base = $base ?? '';


$_footer_cats = $pdo->query("
    SELECT ID_CATEGORIE, NOM_CATEGORIE FROM categorie ORDER BY ID_CATEGORIE
")->fetchAll(PDO::FETCH_ASSOC);
?>
<footer id="footer">
    <div class="container-xl px-4">
        <div class="row g-5">

            
            <div class="col-12 col-lg-4">
                <div class="footer-logo">
                    <img src="<?= $base ?>images/VELVET_LOGO_blanc.png" alt="VELVET">
                </div>
                <p class="footer-tagline">
                    "L'art de s'habiller avec élégance,<br>au cœur du Maroc."
                </p>
                <p style="font-size:11px;color:rgba(249,247,242,0.65);margin-bottom:20px;letter-spacing:0.5px;text-align:left;">
                    VELVET — votre destination mode premium au Maroc depuis 2018.
                </p>
                <div class="footer-social">
                    <a href="https://www.instagram.com/"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.facebook.com/"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.tiktok.com/explore"><i class="fab fa-tiktok"></i></a>
                    <a href="https://www.youtube.com/"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            
            <div class="col-6 col-sm-3 col-lg-2">
                <p class="footer-heading">Collections</p>
                <ul class="footer-links">
                    <?php foreach ($_footer_cats as $fc):
                        $catName = strtolower($fc['NOM_CATEGORIE']);
                        $catLink = '#';
                        if (strpos($catName, 'femme') !== false) $catLink = $base . 'client/collection-femme.php';
                        elseif (strpos($catName, 'homme') !== false) $catLink = $base . 'client/collection-homme.php';
                        else $catLink = $base . 'client/nouvelles-arrivees.php';
                    ?>
                    <li>
                        <a href="<?= $catLink ?>">
                            Mode <?= htmlspecialchars(ucfirst(strtolower($fc['NOM_CATEGORIE']))) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <li>
                        <a href="<?= $base ?>client/nouvelles-arrivees.php">Nouvelles Arrivées</a>
                    </li>
                </ul>
            </div>

            
            <div class="col-6 col-sm-3 col-lg-2">
                <p class="footer-heading">Service Client</p>
                <ul class="footer-links">
                    <li><a href="<?= $base ?>client/index.php">Mon Compte</a></li>
                    <li><a href="<?= $base ?>client/index.php">Mes Commandes</a></li>
                    <li><a href="<?= $base ?>client/panier.php">Mon Panier</a></li>
                    <li><a href="<?= $base ?>client/favoris.php">Mes Favoris</a></li>
                </ul>
            </div>

            
            <div class="col-12 col-lg-4">
                <p class="footer-heading">Nous Contacter</p>
                <div id="footer-form-msg" style="display:none;margin-bottom:10px;"></div>
                <form class="footer-form" id="footerContactForm" onsubmit="submitContact(event)" novalidate>
                    <input type="text"     name="nom"     placeholder="Nom"      required>
                    <input type="text"     name="prenom"  placeholder="Prénom"   required>
                    <input type="email"    name="email"   placeholder="Email (optionnel)">
                    <textarea name="problem" placeholder="Décrivez votre problème..." required></textarea>
                    <div class="footer-form-btns">
                        <button type="submit" class="btn-footer-send">
                            <i class="fas fa-paper-plane me-1"></i> Envoyer
                        </button>
                        <button type="reset" class="btn-footer-cancel">Annuler</button>
                    </div>
                </form>
            </div>

        </div>

        <div class="footer-bottom">
            <p style="text-align:center;width:100%;">
                © <?= date('Y') ?> VELVET Morocco. Tous droits réservés.
            </p>
        </div>
    </div>
</footer>

<script>
async function submitContact(e) {
    e.preventDefault();
    var form = e.target;
    var nom = form.querySelector('[name="nom"]').value.trim();
    var prenom = form.querySelector('[name="prenom"]').value.trim();
    var problem = form.querySelector('[name="problem"]').value.trim();
    var msg = document.getElementById('footer-form-msg');

    if (!nom || !prenom || !problem) {
        var missing = [];
        if (!nom) missing.push('Nom');
        if (!prenom) missing.push('Prénom');
        if (!problem) missing.push('Message');
        msg.style.cssText = 'display:block;color:#ff6b6b;font-size:13px;margin-bottom:10px;background:rgba(255,107,107,0.1);padding:10px 16px;border-radius:8px;border:1px solid rgba(255,107,107,0.25);';
        msg.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Champ(s) requis : ' + missing.join(', ');
        setTimeout(function(){ msg.style.display = 'none'; }, 4000);
        return;
    }

    var data = new FormData(form);
    data.append('action', 'contact');

    try {
        var res  = await fetch('<?= $base ?>contact.php', { method: 'POST', body: data });
        var json = await res.json().catch(function(){ return null; });
        msg.style.display = 'block';
        if (json && json.success) {
            msg.style.cssText = 'display:block;color:#4CAF50;font-size:13px;margin-bottom:10px;background:rgba(76,175,80,0.1);padding:10px 16px;border-radius:8px;border:1px solid rgba(76,175,80,0.25);';
            msg.innerHTML = '<i class="fas fa-check-circle me-1"></i> Message envoyé avec succès !';
            form.reset();
        } else {
            msg.style.cssText = 'display:block;color:#ff6b6b;font-size:13px;margin-bottom:10px;background:rgba(255,107,107,0.1);padding:10px 16px;border-radius:8px;border:1px solid rgba(255,107,107,0.25);';
            msg.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> ' + (json && json.message ? json.message : 'Erreur, veuillez réessayer.');
        }
    } catch(err) {
        msg.style.cssText = 'display:block;color:#ff6b6b;font-size:13px;margin-bottom:10px;background:rgba(255,107,107,0.1);padding:10px 16px;border-radius:8px;border:1px solid rgba(255,107,107,0.25);';
        msg.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Erreur de connexion.';
    }
    setTimeout(function(){ msg.style.display = 'none'; }, 4000);
}
</script>