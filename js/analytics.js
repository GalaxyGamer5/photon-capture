// Google Analytics 4 Integration
// Replace 'G-XXXXXXXXXX' with your actual Google Analytics measurement ID

// Initialize Google Analytics
window.dataLayer = window.dataLayer || [];
function gtag() {
    dataLayer.push(arguments);
}

// Check if user has accepted cookies
function initializeAnalytics() {
    const cookieConsent = localStorage.getItem('cookieConsent');

    if (cookieConsent === 'accepted') {
        // Load Google Analytics
        gtag('js', new Date());
        gtag('config', 'G-XXXXXXXXXX', {
            'anonymize_ip': true,
            'cookie_flags': 'SameSite=None;Secure'
        });

        console.log('Google Analytics initialized');
        return true;
    }
    return false;
}

// Track custom events
function trackEvent(eventName, eventParams = {}) {
    const cookieConsent = localStorage.getItem('cookieConsent');
    if (cookieConsent === 'accepted' && typeof gtag !== 'undefined') {
        gtag('event', eventName, eventParams);
    }
}

// Track service clicks
document.addEventListener('DOMContentLoaded', () => {
    // Initialize analytics if consent given
    initializeAnalytics();

    // Track service card clicks
    document.querySelectorAll('.service-card').forEach(card => {
        card.addEventListener('click', () => {
            const category = card.getAttribute('data-category');
            trackEvent('service_click', {
                'event_category': 'engagement',
                'event_label': category,
                'value': 1
            });
        });
    });

    // Track pricing button clicks
    document.querySelectorAll('.pricing-card .cta-button').forEach(button => {
        button.addEventListener('click', () => {
            const packageType = button.closest('.pricing-card').querySelector('h3').textContent;
            trackEvent('pricing_click', {
                'event_category': 'conversion',
                'event_label': packageType,
                'value': 1
            });
        });
    });

    // Track contact form submission
    const contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', () => {
            trackEvent('form_submit', {
                'event_category': 'conversion',
                'event_label': 'contact_form',
                'value': 1
            });
        });
    }

    // Track gallery filter usage
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.getAttribute('data-filter');
            trackEvent('gallery_filter', {
                'event_category': 'engagement',
                'event_label': filter,
                'value': 1
            });
        });
    });
});

// Export for use in other scripts
window.trackEvent = trackEvent;
window.initializeAnalytics = initializeAnalytics;
