(() => {
    const { base, csrf } = window.VEILLE || {};

    async function post(path, id) {
        const body = new URLSearchParams({ id, csrf });
        const res = await fetch(base + path, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf },
            body,
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('.js-read, .js-fav');
        if (!button) return;
        const card = button.closest('.card');
        const isFav = button.classList.contains('js-fav');
        button.classList.add('is-loading');
        try {
            const { value } = await post(isFav ? '/articles/favorite' : '/articles/read', card.dataset.id);
            button.setAttribute('aria-pressed', String(value));
            if (!isFav) card.classList.toggle('is-read', value);
        } catch (e) {
            alert("L'action n'a pas abouti. Recharge la page et réessaie.");
        } finally {
            button.classList.remove('is-loading');
        }
    });

    // Ouvrir un article le marque comme lu
    document.addEventListener('click', (event) => {
        const link = event.target.closest('.js-open');
        if (!link) return;
        const card = link.closest('.card');
        if (card.classList.contains('is-read')) return;
        card.classList.add('is-read');
        card.querySelector('.js-read')?.setAttribute('aria-pressed', 'true');
        post('/articles/read', card.dataset.id).catch(() => {});
    });

    // Bouton « Récupérer maintenant » : état de chargement
    document.querySelector('.js-refresh')?.addEventListener('submit', (event) => {
        const btn = event.target.querySelector('button');
        btn.textContent = 'Récupération en cours…';
        btn.classList.add('is-loading');
    });
})();
