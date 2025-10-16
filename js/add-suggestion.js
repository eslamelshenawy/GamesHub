    // Sidebar helpers are centralized in js/sidebar.js (openSidebar/closeSidebar/showPlaceholder)
    // Use the global helpers provided by that file instead of redefining here.

    // Function to handle form submission
    function submitSuggestion(event) {
        event.preventDefault(); // Prevents the form from reloading the page
        
        const suggestionText = document.getElementById('suggestion-textarea').value;
        const suggestionForm = document.getElementById('suggestion-form');
        const thankYouMessage = document.getElementById('thank-you-message');
        
        if (suggestionText.trim() === '') {
            showPlaceholder('لا يمكنك إرسال اقتراح فارغ.');
            return;
        }

        // إرسال الاقتراح فعليًا إلى السيرفر
        fetch('/api/api/submit-suggestion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ suggestion: suggestionText }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showThankYouMessage();
            } else {
                showPlaceholder(data.error || 'حدث خطأ أثناء إرسال الاقتراح. حاول مرة أخرى.');
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            showPlaceholder('حدث خطأ في الاتصال. حاول مرة أخرى.');
        });
    }

    // Function to show the thank you message with animation
    function showThankYouMessage() {
        const suggestionForm = document.getElementById('suggestion-form');
        const thankYouMessage = document.getElementById('thank-you-message');

        // Fade out the form
        suggestionForm.style.opacity = '0';
        suggestionForm.style.transform = 'scale(0.9)';

        // After a delay, hide the form and show the thank you message
        setTimeout(() => {
            suggestionForm.style.display = 'none';
            thankYouMessage.style.display = 'block';
            
            // Animate the thank you message
            setTimeout(() => {
                thankYouMessage.style.opacity = '1';
                thankYouMessage.style.transform = 'scale(1)';
            }, 50);
        }, 500); // Wait for the fade-out transition to finish
    }