// Shared logic for customer portal

// Simple SHA-1 hash function for client-side password verification
// Note: In a real production app with sensitive data, this should be handled by a backend
async function sha1(message) {
    const msgBuffer = new TextEncoder().encode(message);
    const hashBuffer = await crypto.subtle.digest('SHA-1', msgBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
}

// Auth Management
const Auth = {
    async login(username, password) {
        try {
            // Check if database is loaded
            if (typeof window.usersDatabase === 'undefined') {
                console.error('Database not loaded. Make sure users.js is included.');
                return false;
            }

            const data = window.usersDatabase;
            const passwordHash = await sha1(password);

            const user = data.users.find(u => u.username === username && u.passwordHash === passwordHash);

            if (user) {
                // Store session
                const session = {
                    id: user.id,
                    name: user.name,
                    folder: user.folder,
                    imageCount: user.imageCount,
                    token: Math.random().toString(36).substring(2) + Date.now().toString(36)
                };
                sessionStorage.setItem('photon_customer_session', JSON.stringify(session));
                return true;
            }
            return false;
        } catch (error) {
            console.error('Login error:', error);
            return false;
        }
    },

    logout() {
        sessionStorage.removeItem('photon_customer_session');
        window.location.href = 'index.html';
    },

    getSession() {
        const session = sessionStorage.getItem('photon_customer_session');
        return session ? JSON.stringify(JSON.parse(session)) : null; // Validate JSON
    },

    requireAuth() {
        if (!this.getSession()) {
            window.location.href = 'index.html';
        }
    },

    getUser() {
        const session = sessionStorage.getItem('photon_customer_session');
        return session ? JSON.parse(session) : null;
    }
};

// Admin Auth (Separate from customer auth)
const AdminAuth = {
    login(password) {
        // Hardcoded admin password for simplicity in this static setup
        // In production, this should be more secure
        if (password === 'photon2024') {
            sessionStorage.setItem('photon_admin_portal_auth', 'true');
            return true;
        }
        return false;
    },

    requireAuth() {
        if (sessionStorage.getItem('photon_admin_portal_auth') !== 'true') {
            window.location.href = 'index.html'; // Or show login modal
            return false;
        }
        return true;
    }
};
