/* ===========================
   VELVET FASHION — Main.js
   =========================== */

// ── Mini-bar rotation ──
(function () {
    const slides = document.querySelectorAll('.mini-bar-slide');
    if (!slides.length) return;
    let current = 0;
    setInterval(() => {
        slides[current].classList.remove('active');
        current = (current + 1) % slides.length;
        slides[current].classList.add('active');
    }, 5000);
})();

// ── Scroll smooth avec easing easeInOutCubic ──
function smoothScrollTo(targetId) {
    const el = document.getElementById(targetId);
    if (!el) return;
    const navHeight = document.getElementById('main-nav')?.offsetHeight || 70;
    const targetY   = el.getBoundingClientRect().top + window.pageYOffset - navHeight;
    const startY    = window.pageYOffset;
    const distance  = targetY - startY;
    const duration  = 1000;
    let startTime   = null;

    function ease(t) {
        return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
    }
    function step(ts) {
        if (!startTime) startTime = ts;
        const progress = Math.min((ts - startTime) / duration, 1);
        window.scrollTo(0, startY + distance * ease(progress));
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

// ── Alias générique ──
function scrollToSection(id) { smoothScrollTo(id); }

// ── Sélection de taille ──
document.querySelectorAll('.sizes').forEach(group => {
    group.querySelectorAll('.size:not(.out)').forEach(size => {
        size.addEventListener('click', () => {
            group.querySelectorAll('.size').forEach(s => s.classList.remove('active-size'));
            size.classList.add('active-size');
        });
    });
});

// ── Recherche — Ouvrir ──
function openSearch(e) {
    e.preventDefault();
    document.getElementById('searchOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('searchInput').focus(), 350);
}

// ── Recherche — Fermer ──
function closeSearch() {
    document.getElementById('searchOverlay').classList.remove('open');
    document.body.style.overflow = '';
    document.getElementById('searchInput').value = '';
}

// ── Recherche — Fermer en cliquant en dehors ──
function closeSearchOnBg(e) {
    if (e.target === document.getElementById('searchOverlay')) closeSearch();
}

// ── Recherche — Remplir le champ avec un tag ──
function fillSearch(t) {
    document.getElementById('searchInput').value = t;
    document.getElementById('searchInput').focus();
}

// ── Recherche — Lancer ──
function doSearch() {
    const q = document.getElementById('searchInput').value.trim();
    if (!q) return;
    const base = window.VELVET_BASE || '';
    window.location.href = base + 'recherche.php?q=' + encodeURIComponent(q);
}

// ── Navigation helpers (homepage) ──
function goToProducts() {
    const base = window.VELVET_BASE || '';
    window.location.href = base + 'client/nouvelles-arrivees.php';
}
function goToCollection() {
    const el = document.getElementById('collection-section');
    if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    else { window.location.href = (window.VELVET_BASE || '') + 'client/collection-femme.php'; }
}
function goToCategories() {
    window.location.href = (window.VELVET_BASE || '') + 'client/nouvelles-arrivees.php';
}

// ── Recherche — Raccourcis clavier ──
function handleSearchKey(e) {
    if (e.key === 'Enter')  doSearch();
    if (e.key === 'Escape') closeSearch();
}

// ── Dropdown user (navbar) ──
function toggleUserDropdown(e) {
    e.preventDefault();
    const dd = document.getElementById('userNavDropdown');
    if (dd) dd.classList.toggle('open');
}
function closeUserDropdown() {
    const dd = document.getElementById('userNavDropdown');
    if (dd) dd.classList.remove('open');
}
document.addEventListener('click', function(e) {
    const userItem = document.querySelector('.nav-user-item');
    if (userItem && !userItem.contains(e.target)) closeUserDropdown();
});

// ── Toast global ──
function showToast(msg, type = 'success') {
    let t = document.getElementById('toast-global');
    if (!t) {
        t = document.createElement('div');
        t.id = 'toast-global';
        t.style.cssText = [
            'position:fixed', 'bottom:28px', 'left:50%',
            'transform:translateX(-50%) translateY(20px)',
            'padding:13px 28px', 'border-radius:50px',
            'font-size:14px', 'font-weight:500',
            'box-shadow:0 4px 18px rgba(0,0,0,0.18)',
            'z-index:9999', 'opacity:0',
            'transition:opacity .3s,transform .3s',
            'font-family:Inter,sans-serif', 'color:#fff',
            'white-space:nowrap'
        ].join(';');
        document.body.appendChild(t);
    }
    t.style.background = type === 'success' ? '#2e7d32' : '#c62828';
    t.textContent = msg;
    requestAnimationFrame(() => {
        t.style.opacity = '1';
        t.style.transform = 'translateX(-50%) translateY(0)';
    });
    clearTimeout(t._timer);
    t._timer = setTimeout(() => {
        t.style.opacity = '0';
        t.style.transform = 'translateX(-50%) translateY(20px)';
    }, 2800);
}
// ── Update panier badge ──
function updatePanierBadge(count) {
    let badge = document.querySelector('.panier-nav-badge');
    const wrap  = document.querySelector('.nav-panier-wrap');
    if (!wrap) return;
    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'panier-nav-badge';
            wrap.appendChild(badge);
        }
        badge.textContent = count;
    } else if (badge) {
        badge.remove();
    }
}

// ── Quick add to cart (from homepage — works for guests too) ──
function quickAddToCart(id) {
    const inClient = window.location.pathname.includes('/client/');
    const actionsUrl = inClient ? 'actions.php' : 'client/actions.php';
    const prodUrl    = inClient ? 'produit.php?id=' + id : 'client/produit.php?id=' + id;

    fetch(actionsUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add_to_cart&id_produit=' + id + '&qte=1'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            updatePanierBadge(d.panier_count || 0);
            const icon = document.querySelector('.nav-panier-wrap .nav-icon i');
            if (icon) { icon.style.transform = 'scale(1.4)'; setTimeout(() => { icon.style.transform = ''; }, 200); }
        } else {
            window.location.href = prodUrl;
        }
    })
    .catch(() => { window.location.href = prodUrl; });
}

// ── Quick add to favourites (from homepage — silent) ──
function quickAddToFav(id) {
    const inClient = window.location.pathname.includes('/client/');
    const actionsUrl = inClient ? 'actions.php' : 'client/actions.php';
    const loginUrl   = inClient ? '../login.php' : 'login.php';

    const loggedIn = !!document.getElementById('userNavDropdown');
    if (!loggedIn) { window.location.href = loginUrl; return; }

    fetch(actionsUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=toggle_fav&id_produit=' + id
    })
    .then(r => r.json())
    .then(() => {})
    .catch(() => {});
}
