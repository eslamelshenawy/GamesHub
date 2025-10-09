// Logic for toggling a favorite card without immediate removal
// Note: sidebar is loaded centrally by js/sidebar.js — do not re-fetch it here.
function toggleFavorite(event, card) {
        event.stopPropagation(); // Prevents the card from doing anything else when the heart is clicked
        
        const icon = card.querySelector('.fa-heart');
        
        if (card.classList.contains('removed-from-favorites')) {
            // Re-add to favorites (or cancel the removal)
            card.classList.remove('removed-from-favorites');
            icon.classList.remove('fa-heart-crack');
            icon.classList.add('fa-heart');
            showPlaceholder('تمت إعادة الحساب للمفضلة. قم بتحديث الصفحة للحفظ.');
        } else {
            // Mark for removal
            card.classList.add('removed-from-favorites');
            icon.classList.remove('fa-heart');
            icon.classList.add('fa-heart-crack');
            showPlaceholder('سيتم إزالة الحساب عند تحديث الصفحة.');
        }
}

// Function to update the favorite count and show "no favorites" message if needed
// This function will now only count the cards that are NOT marked for removal
function updateFavoriteCount() {
        const grid = document.getElementById('favorites-grid');
        const countElement = document.getElementById('favorite-count');
        const noFavoritesMessage = document.getElementById('no-favorites');
        
        // Count only the cards not marked with 'removed-from-favorites'
        const visibleCards = grid.querySelectorAll('article:not(.removed-from-favorites)');
        const count = visibleCards.length;
        
        countElement.textContent = count;
        
        if (count === 0 && grid.children.length === 0) {
            noFavoritesMessage.classList.remove('hidden');
        } else {
            noFavoritesMessage.classList.add('hidden');
        }
}

// Placeholder for "Report" function
function reportProblem() {
    // --- ضع كود نافذة "التبليغ عن مشكلة" هنا لاحقًا ---
    showPlaceholder('سوف تظهر نافذة الإبلاغ عن مشكلة هنا.');
}