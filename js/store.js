  // Sidebar controls (mobile)
    // ...existing code...
    
    // Client-side filtering logic
    function filterCards() {
        const searchText = document.getElementById('search-input').value.toLowerCase();
        const gameFilter = document.getElementById('game-filter').value.toLowerCase();
        const cards = document.querySelectorAll('#accounts-grid article');

        cards.forEach(card => {
            // منطق الفلترة موجود هنا ويمكن إضافة أي منطق إضافي مستقبلاً
            const gameName = card.getAttribute('data-game').toLowerCase();
            const cardText = card.innerText.toLowerCase();

            const isGameMatch = gameFilter === '' || gameName.includes(gameFilter);
            const isSearchMatch = cardText.includes(searchText);

            if (isGameMatch && isSearchMatch) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
