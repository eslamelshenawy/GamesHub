// Sidebar controls (mobile)
    // ...existing code...
    
    // Logic to determine if the user is the profile owner
    // This is a placeholder. In a real app, this would be a check from your backend.
    function isProfileOwner(profileId) {
        // Assume '123' is the current logged-in user ID.
        // The profileId would be passed dynamically to the page.
        const currentUserId = '123';
        const pageProfileId = '123'; // This should be a dynamic value
        return currentUserId === pageProfileId;
    }
    
    // Function to load user data and show/hide buttons
    function loadUserProfile(profileData) {
        // تحديث صورة الملف الشخصي مع معالجة الأخطاء
        const profileImage = document.getElementById('user-profile-image');
        const sidebarAvatar = document.getElementById('user-avatar-sidebar');
        
        const imageUrl = profileData.image && profileData.image.trim() ? profileData.image : 'uploads/default-avatar.svg';
        
        if (profileImage) {
            profileImage.src = imageUrl;
            profileImage.onerror = function() {
                this.src = 'uploads/default-avatar.svg';
            };
        }
        
        if (sidebarAvatar) {
            sidebarAvatar.src = imageUrl;
            sidebarAvatar.onerror = function() {
                this.src = 'uploads/default-avatar.svg';
            };
        }
        
        document.getElementById('user-name').textContent = profileData.name;
        document.getElementById('user-bio').textContent = profileData.bio;
        document.getElementById('user-phone').textContent = profileData.phone;
        document.getElementById('user-age').textContent = profileData.age;
        document.getElementById('user-gender').textContent = profileData.gender;
        
        // Update sidebar
        document.getElementById('username-sidebar').textContent = profileData.name;

        // Show/hide buttons based on who is viewing the profile
        if (isProfileOwner(profileData.id)) {
            document.getElementById('owner-buttons').classList.remove('hidden');
            document.getElementById('visitor-buttons').classList.add('hidden');
        } else {
            document.getElementById('owner-buttons').classList.add('hidden');
            document.getElementById('visitor-buttons').classList.remove('hidden');
        }
    }

    // Load profile dynamically from server
    document.addEventListener('DOMContentLoaded', () => {
        // If the page includes a query id ?id=.. the server will return that profile, otherwise the current user
        fetch('/api/api/get_user.php', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (data && !data.error) {
                    loadUserProfile(data);
                }
            })
            .catch(() => { /* ignore, page may handle empty state */ });
    });




    // دي وظيفة عشان تضبط الألوان اللي بتنور (Glow)
    document.documentElement.style.setProperty('--neon-purple', '#9c27b0');
    document.documentElement.style.setProperty('--neon-blue', '#00f7ff');