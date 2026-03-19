// Cookie Management System
// Manages user cookie preferences and consent

const CookieManager = {
    // Cookie categories
    categories: {
        essential: {
            name: 'Essential',
            description: 'Required for website functionality',
            required: true,
            cookies: ['theme_pref', 'preferredLang', 'cookieConsent']
        },
        analytics: {
            name: 'Analytics',
            description: 'Help us understand how visitors use our site',
            required: false,
            cookies: ['_ga', '_gid', '_gat']
        },
        preferences: {
            name: 'Preferences',
            description: 'Remember your settings and preferences',
            required: false,
            cookies: ['theme_pref', 'preferredLang']
        }
    },

    // Get current preferences
    getPreferences() {
        const saved = localStorage.getItem('cookiePreferences');
        if (saved) {
            return JSON.parse(saved);
        }
        return {
            essential: true,
            analytics: false,
            preferences: false
        };
    },

    // Save preferences
    savePreferences(preferences) {
        localStorage.setItem('cookiePreferences', JSON.stringify(preferences));

        // If analytics disabled, remove GA cookies
        if (!preferences.analytics) {
            this.deleteCookies(this.categories.analytics.cookies);
        }

        return preferences;
    },

    // Delete specific cookies
    deleteCookies(cookieNames) {
        cookieNames.forEach(name => {
            document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`;
        });
    },

    // Accept all cookies
    acceptAll() {
        const prefs = {
            essential: true,
            analytics: true,
            preferences: true
        };
        this.savePreferences(prefs);
        localStorage.setItem('cookieConsent', 'accepted');

        // Initialize analytics
        if (typeof window.initializeAnalytics === 'function') {
            window.initializeAnalytics();
        }

        return prefs;
    },

    // Decline non-essential cookies
    declineAll() {
        const prefs = {
            essential: true,
            analytics: false,
            preferences: false
        };
        this.savePreferences(prefs);
        localStorage.setItem('cookieConsent', 'declined');

        // Delete analytics cookies
        this.deleteCookies(this.categories.analytics.cookies);

        return prefs;
    },

    // Show cookie settings modal
    showSettings() {
        // Create modal if doesn't exist
        let modal = document.getElementById('cookie-settings-modal');
        if (!modal) {
            modal = this.createSettingsModal();
            document.body.appendChild(modal);
        }

        // Load current preferences
        const prefs = this.getPreferences();
        document.getElementById('analytics-toggle').checked = prefs.analytics;
        document.getElementById('preferences-toggle').checked = prefs.preferences;

        modal.classList.add('active');
    },

    // Create settings modal
    createSettingsModal() {
        const modal = document.createElement('div');
        modal.id = 'cookie-settings-modal';
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content cookie-settings">
                <button class="modal-close" onclick="CookieManager.hideSettings()">&times;</button>
                <h3>Cookie Settings</h3>
                <p style="color: var(--text-secondary); margin-bottom: 2rem;">
                    We use cookies to improve your experience. Essential cookies are required for the website to function.
                </p>
                
                <div class="cookie-category">
                    <div class="cookie-category-header">
                        <h4>Essential Cookies</h4>
                        <span class="cookie-required">Required</span>
                    </div>
                    <p>${this.categories.essential.description}</p>
                </div>
                
                <div class="cookie-category">
                    <div class="cookie-category-header">
                        <h4>Analytics Cookies</h4>
                        <label class="toggle-switch">
                            <input type="checkbox" id="analytics-toggle">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <p>${this.categories.analytics.description}</p>
                </div>
                
                <div class="cookie-category">
                    <div class="cookie-category-header">
                        <h4>Preference Cookies</h4>
                        <label class="toggle-switch">
                            <input type="checkbox" id="preferences-toggle">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <p>${this.categories.preferences.description}</p>
                </div>
                
                <div class="cookie-actions">
                    <button class="cta-button" onclick="CookieManager.saveFromModal()">Save Preferences</button>
                    <button class="cta-button" style="background: transparent; border: 1px solid var(--accent-color); color: var(--accent-color);" 
                            onclick="CookieManager.acceptAllFromModal()">Accept All</button>
                </div>
            </div>
        `;
        return modal;
    },

    // Hide settings modal
    hideSettings() {
        const modal = document.getElementById('cookie-settings-modal');
        if (modal) {
            modal.classList.remove('active');
        }
    },

    // Save from modal
    saveFromModal() {
        const prefs = {
            essential: true,
            analytics: document.getElementById('analytics-toggle').checked,
            preferences: document.getElementById('preferences-toggle').checked
        };

        this.savePreferences(prefs);

        if (prefs.analytics) {
            localStorage.setItem('cookieConsent', 'accepted');
            if (typeof window.initializeAnalytics === 'function') {
                window.initializeAnalytics();
            }
        } else {
            localStorage.setItem('cookieConsent', 'declined');
        }

        this.hideSettings();

        // Hide banner if visible
        const banner = document.getElementById('cookie-banner');
        if (banner) {
            banner.classList.remove('show');
        }
    },

    // Accept all from modal
    acceptAllFromModal() {
        document.getElementById('analytics-toggle').checked = true;
        document.getElementById('preferences-toggle').checked = true;
        this.saveFromModal();
    }
};

// Make available globally
window.CookieManager = CookieManager;
