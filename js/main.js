document.addEventListener('DOMContentLoaded', async () => {
    // --- 3D Camera Roll / Dynamic Portfolio ---
    let portfolioImages = [];
    const cameraRollStage = document.getElementById('cameraRollStage');

    if (cameraRollStage) {
        try {
            const response = await fetch('api/get-portfolio.php?t=' + Date.now());
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.images && data.images.length > 0) {
                    portfolioImages = data.images;
                }
            }
        } catch(e) {
            console.error("Error loading dynamic portfolio:", e);
        }
    }

    // --- Load Dynamic Pricing ---
    const pricingListContainer = document.getElementById('pricing-list-container');
    const pricingGrid = document.getElementById('pricing-grid-container');
    const customReq = document.getElementById('custom-request-container');
    let loadedPricingData = null;

    async function fetchPricing() {
        if (!pricingListContainer && !pricingGrid) return;
        try {
            const res = await fetch('api/get-pricing.php?t=' + Date.now());
            if (res.ok) {
                loadedPricingData = await res.json();
                renderPricing();
            }
        } catch (e) { console.error("Error loading pricing:", e); }
    }

    function renderPricing() {
        if (!loadedPricingData || !loadedPricingData.packages) return;
        const lang = localStorage.getItem('preferredLang') || 'de';

        // Render Editorial List (Variante B on index.html)
        if (pricingListContainer) {
            const letters = ['A', 'B', 'C', 'D', 'E'];
            pricingListContainer.innerHTML = loadedPricingData.packages.map((pkg, idx) => {
                const letter = letters[idx] || String.fromCharCode(65 + idx);
                const title = (pkg.title && pkg.title[lang]) || (pkg.title && pkg.title.de) || pkg.title || '';

                let featuresText = '';
                if (pkg.features && Array.isArray(pkg.features)) {
                    featuresText = pkg.features
                        .map(f => (typeof f === 'object' ? (f[lang] || f.de) : f))
                        .filter(Boolean)
                        .join(', ');
                }

                let displayPrice = pkg.price;
                if (loadedPricingData.globalDiscount && loadedPricingData.globalDiscount.active) {
                    const gd = loadedPricingData.globalDiscount;
                    if (gd.type === 'percent') {
                        displayPrice = Math.round(pkg.price * (1 - (gd.value / 100)));
                    } else {
                        displayPrice = pkg.price - gd.value;
                    }
                }

                const unit = (pkg.priceUnit && pkg.priceUnit[lang]) ? pkg.priceUnit[lang] : ((pkg.priceUnit && pkg.priceUnit.de) ? pkg.priceUnit.de : '');
                const prefix = lang === 'en' ? 'from ' : 'ab ';
                const priceFormatted = `${prefix}${displayPrice} €${unit}`;

                return `
                    <a href="calendar.html" class="pricing-row" data-package="${pkg.id}" onclick="sessionStorage.setItem('selectedPackage', '${pkg.id}')">
                        <span class="pkg-letter">${letter}</span>
                        <span class="pkg-title">${title}</span>
                        <span class="pkg-features">${featuresText}</span>
                        <span class="pkg-price">${priceFormatted}</span>
                    </a>
                `;
            }).join('');
        }

        // Render Legacy Grid (if present)
        if (pricingGrid && loadedPricingData.packages) {
            pricingGrid.innerHTML = '';
            loadedPricingData.packages.forEach(pkg => {
                const card = document.createElement('div');
                card.className = 'pricing-card';

                let featuresHtml = '';
                pkg.features.forEach(feat => {
                    if (feat[lang]) {
                        featuresHtml += `<li>${feat[lang]}</li>`;
                    }
                });

                let displayPrice = pkg.price;
                let originalPriceHtml = '';

                if (loadedPricingData.globalDiscount && loadedPricingData.globalDiscount.active) {
                    const gd = loadedPricingData.globalDiscount;
                    originalPriceHtml = `<span class="original-price" style="text-decoration: line-through; opacity: 0.5; font-size: 0.8em; margin-right: 0.5rem;">${pkg.price}€</span>`;

                    if (gd.type === 'percent') {
                        displayPrice = Math.round(pkg.price * (1 - (gd.value / 100)));
                    } else {
                        displayPrice = pkg.price - gd.value;
                    }
                }

                const unitHtml = pkg.priceUnit && pkg.priceUnit[lang] ? `<span>${pkg.priceUnit[lang]}</span>` : '';
                const customizeText = lang === 'en' ? 'Customize Package (Extras)' : 'Paket anpassen (Extras)';
                const btnText = lang === 'en' ? 'Send Request' : 'Anfrage senden';

                card.innerHTML = `
                    <h3>${pkg.title[lang] || pkg.title.de || pkg.title}</h3>
                    <div class="price">${originalPriceHtml}${displayPrice}€${unitHtml}</div>
                    <ul class="pricing-features">
                        ${featuresHtml}
                    </ul>
                    <a href="javascript:void(0)" class="customize-link" data-package="${pkg.id}">${customizeText}</a>
                    <a href="calendar.html?package=${pkg.id}" class="cta-button" style="background: transparent; border: 1px solid var(--accent-color); color: var(--accent-color);">${btnText}</a>
                `;
                pricingGrid.appendChild(card);
            });
        }

        // Render Custom Request
        if (customReq && loadedPricingData.custom) {
            const cust = loadedPricingData.custom;
            customReq.innerHTML = `
                <h3 style="margin-bottom: 0.5rem;">${cust.title[lang]}</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                    ${cust.desc[lang]}
                </p>
                <a href="calendar.html?package=custom" class="cta-button" style="background: transparent; border: 1px solid var(--text-primary); color: var(--text-primary);">
                    ${cust.btn[lang]}
                </a>
            `;
        }
    }

    fetchPricing();

    // Listen for language changes to re-render texts
    window.addEventListener('languageChanged', renderPricing);

    // --- Load Dynamic Testimonials ---
    const testimonialsGrid = document.getElementById('testimonials-grid');
    const testimonialsSection = document.getElementById('testimonials');

    async function fetchTestimonials() {
        if (!testimonialsGrid) return;
        try {
            const response = await fetch('api/get-testimonials.php?t=' + Date.now());
            if (response.ok) {
                const data = await response.json();
                if (data.success && Array.isArray(data.testimonials) && data.testimonials.length > 0) {
                    renderTestimonials(data.testimonials);
                    if (testimonialsSection) testimonialsSection.style.display = 'block';
                } else if (testimonialsSection) {
                    testimonialsSection.style.display = 'none';
                }
            }
        } catch (e) {
            console.error("Error loading testimonials:", e);
            if (testimonialsSection) testimonialsSection.style.display = 'none';
        }
    }

    function renderTestimonials(testimonials) {
        if (!testimonialsGrid) return;
        testimonialsGrid.innerHTML = testimonials.map(t => {
            const rating = Math.max(1, Math.min(5, parseInt(t.rating) || 5));
            const stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
            let dateStr = '';
            if (t.date) {
                try {
                    const d = new Date(t.date);
                    const lang = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en') ? 'en-US' : 'de-DE';
                    dateStr = d.toLocaleDateString(lang, {
                        year: 'numeric',
                        month: 'short'
                    });
                } catch (e) { }
            }
            return `
                <div class="testimonial-card">
                    <div>
                        <div class="testimonial-stars" aria-label="${rating} von 5 Sternen">${stars}</div>
                        <p class="testimonial-quote">"${escapeHtml(t.text)}"</p>
                    </div>
                    <div class="testimonial-author-box">
                        <span class="testimonial-author-name">${escapeHtml(t.name)}</span>
                        ${dateStr ? `<span class="testimonial-author-date">${dateStr}</span>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    fetchTestimonials();

    // --- Randomize Gallery Items ---
    if (galleryGrid) {
        const items = Array.from(galleryGrid.children);

        // Fisher-Yates shuffle algorithm
        for (let i = items.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [items[i], items[j]] = [items[j], items[i]];
        }

        // Append shuffled items back to the gallery
        items.forEach(item => galleryGrid.appendChild(item));
    }

    // --- Scroll Progress Indicator ---
    const scrollProgressBar = document.querySelector('.scroll-progress-bar');

    if (scrollProgressBar) {
        window.addEventListener('scroll', () => {
            const windowHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (window.scrollY / windowHeight) * 100;
            scrollProgressBar.style.width = scrolled + '%';
        });
    }

    // --- Dark Mode Toggle ---
    const darkModeToggle = document.querySelector('.dark-mode-toggle');
    const sunIcon = document.querySelector('.sun');
    const moonIcon = document.querySelector('.moon');

    if (darkModeToggle) {
        // Check for saved theme preference or default to dark mode
        // Changed key to 'theme_pref' to reset users to default dark mode
        const currentTheme = localStorage.getItem('theme_pref') || 'dark';

        if (currentTheme === 'light') {
            document.body.classList.add('light-mode');
            if (sunIcon) sunIcon.style.display = 'block';
            if (moonIcon) moonIcon.style.display = 'none';
        } else {
            document.body.classList.remove('light-mode');
            if (sunIcon) sunIcon.style.display = 'none';
            if (moonIcon) moonIcon.style.display = 'block';
        }

        darkModeToggle.addEventListener('click', () => {
            document.body.classList.toggle('light-mode');

            if (document.body.classList.contains('light-mode')) {
                localStorage.setItem('theme_pref', 'light');
                if (sunIcon) sunIcon.style.display = 'block';
                if (moonIcon) moonIcon.style.display = 'none';
            } else {
                localStorage.setItem('theme_pref', 'dark');
                if (sunIcon) sunIcon.style.display = 'none';
                if (moonIcon) moonIcon.style.display = 'block';
            }
        });
    }

    // --- Parallax Scrolling ---
    // --- Parallax Scrolling ---
    const heroBg = document.querySelector('.hero-bg');
    const heroContent = document.querySelector('.hero-content');

    // Use requestAnimationFrame for smoother performance
    let lastScrollY = window.pageYOffset;
    let ticking = false;

    window.addEventListener('scroll', () => {
        lastScrollY = window.pageYOffset;
        if (!ticking) {
            window.requestAnimationFrame(() => {
                const scrolled = lastScrollY;
                if (scrolled < window.innerHeight) {
                    if (heroBg) {
                        // Background moves slower for depth
                        heroBg.style.transform = `translate3d(0, ${scrolled * 0.2}px, 0)`;
                    }
                    if (heroContent) {
                        // Content moves faster and fades out
                        heroContent.style.transform = `translate3d(0, ${scrolled * 0.4}px, 0)`;
                        heroContent.style.opacity = Math.max(0, 1 - (scrolled / 600));
                    }
                }
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    // --- Load Dynamic FAQ ---
    const faqContainer = document.getElementById('faq-container');
    let loadedFaqData = null;

    async function fetchFaq() {
        if (!faqContainer) return;
        try {
            const res = await fetch('api/get-faq.php');
            if (res.ok) {
                loadedFaqData = await res.json();
                renderFaq();
            }
        } catch (e) { console.error("Error loading FAQ:", e); }
    }

    function renderFaq() {
        if (!loadedFaqData || !loadedFaqData.items || !faqContainer) return;
        const lang = localStorage.getItem('preferredLang') || 'de';

        faqContainer.innerHTML = '';
        loadedFaqData.items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'faq-item';
            div.innerHTML = `
                <button class="faq-question">
                    <span>${item.q[lang]}</span>
                    <svg class="faq-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div class="faq-answer">
                    <p>${item.a[lang]}</p>
                </div>
            `;
            faqContainer.appendChild(div);
        });
    }

    fetchFaq();
    window.addEventListener('languageChanged', renderFaq);

    // --- FAQ Accordion Event Delegation ---
    if (faqContainer) {
        faqContainer.addEventListener('click', (e) => {
            const questionBtn = e.target.closest('.faq-question');
            if (!questionBtn) return;

            const faqItem = questionBtn.parentElement;
            const isActive = faqItem.classList.contains('active');

            // Close all other FAQ items
            faqContainer.querySelectorAll('.faq-item').forEach(item => {
                item.classList.remove('active');
            });

            // Toggle current item
            if (!isActive) {
                faqItem.classList.add('active');
            }
        });
    }

    // --- Mobile Menu Toggle ---
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');

    if (hamburger) {
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            if (navLinks) navLinks.classList.toggle('active');
        });
    }

    // --- Language Switching ---
    const langToggle = document.querySelector('.lang-toggle');
    let currentLang = localStorage.getItem('preferredLang') || 'de';

    const updateContent = (lang) => {
        document.querySelectorAll('[data-i18n]').forEach(element => {
            const key = element.getAttribute('data-i18n');
            if (translations[lang] && translations[lang][key]) {
                if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                    if (element.hasAttribute('placeholder') && key.endsWith('.placeholder')) {
                        element.placeholder = translations[lang][key];
                    }
                } else {
                    element.innerHTML = translations[lang][key];
                }
            }
        });
        localStorage.setItem('preferredLang', lang);
        currentLang = lang;

        // Dispatch event for other components
        window.dispatchEvent(new CustomEvent('languageChanged', {
            detail: { language: lang }
        }));
    };

    // Expose updateContent to global scope for other scripts
    window.updateContent = () => updateContent(currentLang);

    if (typeof translations !== 'undefined') {
        updateContent(currentLang);
        if (langToggle) {
            // Show current language on the button (DE = currently German, EN = currently English)
            langToggle.textContent = currentLang.toUpperCase();
            langToggle.addEventListener('click', () => {
                const newLang = currentLang === 'de' ? 'en' : 'de';
                updateContent(newLang);
                langToggle.textContent = newLang.toUpperCase();
            });
        }
    }

    // --- Smooth Scrolling & Navigation ---
    const sections = document.querySelectorAll('section');
    const navItems = document.querySelectorAll('.nav-links a');

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                // Close mobile menu (if it exists on this page)
                if (navLinks) navLinks.classList.remove('active');
                if (hamburger) hamburger.classList.remove('active');

                // Scroll to element
                targetElement.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // Active Link Highlighting
    window.addEventListener('scroll', () => {
        let current = '';
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            if (window.pageYOffset >= (sectionTop - 200)) {
                current = section.getAttribute('id');
            }
        });

        navItems.forEach(a => {
            a.classList.remove('active');
            if (a.getAttribute('href') === `#${current}`) {
                a.classList.add('active');
            }
        });
    });

    // ==========================================================================
    // 3D CAMERA ROLL / FILMSTRIP CAROUSEL (35MM CINEMA AESTHETIC)
    // ==========================================================================

    let currentRollFilter = 'all';
    let filteredRollImages = [];
    let activeRollIndex = 0;
    let isRollDragging = false;
    let rollStartX = 0;
    let rollDragDeltaX = 0;
    let rollHasDragged = false;
    let rollWheelCooldown = false;

    // High quality aesthetic fallback cards when portfolio has no uploaded images yet
    const fallbackPortfolio = [
        { id: 1, filename: '', category: 'portrait', label: 'Porträt Noir' },
        { id: 2, filename: '', category: 'event', label: 'Festival of Lights' },
        { id: 3, filename: '', category: 'portrait', label: 'Studio Noir' },
        { id: 4, filename: '', category: 'pet', label: 'Golden Retriever' },
        { id: 5, filename: '', category: 'event', label: 'Corporate Gala' },
        { id: 6, filename: '', category: 'pet', label: 'Feline Grace' },
        { id: 7, filename: '', category: 'portrait', label: 'Natural Light' },
        { id: 8, filename: '', category: 'event', label: 'Summer Wedding' }
    ];

    const categoryLabels = {
        all: 'Portfolio',
        portrait: 'Porträt',
        event: 'Event',
        pet: 'Tier'
    };

    function createCameraCard(item, index) {
        const card = document.createElement('div');
        card.className = 'camera-card';
        card.dataset.index = index;
        card.dataset.category = item.category;

        const expNumber = String(index + 1).padStart(2, '0');
        const catLabel = categoryLabels[item.category] || item.category;

        let frameContent = '';
        if (item.filename) {
            frameContent = `
                <img src="assets/portfolio/${item.filename}" alt="${catLabel}" loading="lazy" draggable="false">
                <div class="film-frame-overlay">
                    <span class="view-pill">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                        Vollbild
                    </span>
                </div>
            `;
        } else {
            frameContent = `
                <div class="film-placeholder">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    <span>${item.label || 'FOTO · ' + catLabel.toUpperCase()}</span>
                </div>
            `;
        }

        card.innerHTML = `
            <div class="camera-card-inner">
                <div class="film-header">
                    <span class="film-exp">EXP ${expNumber}</span>
                    <span class="film-brand">PHOTON · 400</span>
                    <span class="film-badge">${catLabel}</span>
                </div>
                <div class="film-frame">
                    ${frameContent}
                </div>
                <div class="film-footer">
                    <span class="film-dots">••••</span>
                    <span class="film-code">ISO 400 · 35MM</span>
                    <span class="film-dots">••••</span>
                </div>
            </div>
        `;

        card.addEventListener('click', () => {
            if (rollHasDragged) return;
            const cardIdx = parseInt(card.dataset.index, 10);
            if (cardIdx === activeRollIndex) {
                const img = card.querySelector('img');
                if (img && img.src) {
                    openPortfolioLightbox(img);
                }
            } else {
                goToRollSlide(cardIdx);
            }
        });

        return card;
    }

    function update3DPositions(dragOffsetRatio = 0) {
        const track = document.getElementById('cameraRollTrack');
        if (!track) return;
        const cards = track.querySelectorAll('.camera-card');
        if (!cards.length) return;

        const isMobile = window.innerWidth <= 640;
        const isTablet = window.innerWidth <= 992 && !isMobile;

        const stepX = isMobile ? 165 : (isTablet ? 210 : 255);
        const zStep = isMobile ? 70 : (isTablet ? 95 : 120);
        const baseRot = isMobile ? 18 : (isTablet ? 23 : 28);

        const effectiveCenter = activeRollIndex - dragOffsetRatio;

        cards.forEach((card) => {
            const idx = parseInt(card.dataset.index, 10);
            const dist = idx - effectiveCenter;
            const absDist = Math.abs(dist);

            if (absDist > 3.8) {
                card.style.opacity = '0';
                card.style.visibility = 'hidden';
                card.style.pointerEvents = 'none';
                card.classList.remove('is-center');
                return;
            }

            card.style.visibility = 'visible';

            const sign = dist === 0 ? 0 : Math.sign(dist);
            const translateX = dist * stepX;
            const translateZ = -absDist * zStep;
            const rotateY = -sign * (baseRot + Math.min(8, absDist * 3));
            const scale = Math.max(0.65, 1.05 - absDist * 0.12);
            const opacity = Math.max(0, 1 - absDist * 0.28);
            const zIndex = Math.round(100 - absDist * 15);
            const brightness = Math.max(0.35, 1 - absDist * 0.25);

            card.style.transform = `translate3d(${translateX}px, 0, ${translateZ}px) rotateY(${rotateY}deg) scale(${scale})`;
            card.style.opacity = opacity.toFixed(3);
            card.style.zIndex = zIndex;
            card.style.filter = `brightness(${brightness.toFixed(2)})`;
            card.style.pointerEvents = 'auto';

            if (absDist < 0.45) {
                card.classList.add('is-center');
            } else {
                card.classList.remove('is-center');
            }
        });

        updateRollControls();
    }

    function updateRollControls() {
        const total = filteredRollImages.length;
        const currentEl = document.getElementById('rollCurrent');
        const totalEl = document.getElementById('rollTotal');
        const prevBtn = document.getElementById('rollPrevBtn');
        const nextBtn = document.getElementById('rollNextBtn');
        const scrubberBar = document.getElementById('rollScrubberBar');

        if (currentEl) currentEl.textContent = String(activeRollIndex + 1).padStart(2, '0');
        if (totalEl) totalEl.textContent = String(total).padStart(2, '0');

        if (prevBtn) {
            prevBtn.disabled = (activeRollIndex <= 0);
            prevBtn.classList.toggle('disabled', activeRollIndex <= 0);
        }
        if (nextBtn) {
            nextBtn.disabled = (activeRollIndex >= total - 1);
            nextBtn.classList.toggle('disabled', activeRollIndex >= total - 1);
        }

        if (scrubberBar && total > 0) {
            const percent = total <= 1 ? 100 : (activeRollIndex / (total - 1)) * 100;
            scrubberBar.style.width = `${Math.min(100, Math.max(0, percent))}%`;
        }
    }

    function goToRollSlide(targetIndex) {
        if (!filteredRollImages.length) return;
        activeRollIndex = Math.max(0, Math.min(filteredRollImages.length - 1, targetIndex));
        update3DPositions(0);
    }

    function renderCameraRoll(filterValue = 'all') {
        currentRollFilter = filterValue;
        const track = document.getElementById('cameraRollTrack');
        if (!track) return;

        const source = (portfolioImages && portfolioImages.length > 0) ? portfolioImages : fallbackPortfolio;
        filteredRollImages = (filterValue === 'all')
            ? [...source]
            : source.filter(item => item.category === filterValue);

        track.innerHTML = '';

        if (filteredRollImages.length === 0) {
            track.innerHTML = `
                <div style="position: absolute; transform: translate(-50%, -50%); text-align: center; color: rgba(255,255,255,0.4); font-size: 14px; letter-spacing: 0.05em;">
                    Keine Fotos in dieser Kategorie vorhanden.
                </div>
            `;
            updateRollControls();
            return;
        }

        filteredRollImages.forEach((item, index) => {
            const card = createCameraCard(item, index);
            track.appendChild(card);
        });

        activeRollIndex = 0;
        update3DPositions(0);
    }

    function initCameraRollDrag() {
        const viewport = document.getElementById('cameraRollViewport');
        if (!viewport) return;

        let startTime = 0;

        const onStart = (clientX) => {
            isRollDragging = true;
            rollHasDragged = false;
            rollStartX = clientX;
            rollDragDeltaX = 0;
            startTime = Date.now();
            viewport.classList.add('is-dragging');
        };

        const onMove = (clientX) => {
            if (!isRollDragging) return;
            rollDragDeltaX = clientX - rollStartX;
            if (Math.abs(rollDragDeltaX) > 6) {
                rollHasDragged = true;
            }

            const isMobile = window.innerWidth <= 640;
            const stepX = isMobile ? 165 : 240;
            let offsetRatio = rollDragDeltaX / stepX;

            if ((activeRollIndex === 0 && offsetRatio > 0) || (activeRollIndex === filteredRollImages.length - 1 && offsetRatio < 0)) {
                offsetRatio *= 0.35;
            }

            update3DPositions(offsetRatio);
        };

        const onEnd = () => {
            if (!isRollDragging) return;
            isRollDragging = false;
            viewport.classList.remove('is-dragging');

            const elapsed = Math.max(1, Date.now() - startTime);
            const velocity = Math.abs(rollDragDeltaX) / elapsed;

            if (Math.abs(rollDragDeltaX) > 35 || velocity > 0.3) {
                if (rollDragDeltaX < 0 && activeRollIndex < filteredRollImages.length - 1) {
                    activeRollIndex++;
                } else if (rollDragDeltaX > 0 && activeRollIndex > 0) {
                    activeRollIndex--;
                }
            }

            rollDragDeltaX = 0;
            update3DPositions(0);
            setTimeout(() => { rollHasDragged = false; }, 60);
        };

        viewport.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return;
            onStart(e.clientX);
        });

        window.addEventListener('mousemove', (e) => {
            if (isRollDragging) onMove(e.clientX);
        });

        window.addEventListener('mouseup', () => {
            if (isRollDragging) onEnd();
        });

        viewport.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1) {
                onStart(e.touches[0].clientX);
            }
        }, { passive: true });

        viewport.addEventListener('touchmove', (e) => {
            if (isRollDragging && e.touches.length === 1) {
                onMove(e.touches[0].clientX);
            }
        }, { passive: true });

        viewport.addEventListener('touchend', () => {
            if (isRollDragging) onEnd();
        });

        viewport.addEventListener('touchcancel', () => {
            if (isRollDragging) onEnd();
        });

        viewport.addEventListener('wheel', (e) => {
            if (rollWheelCooldown) return;
            const delta = Math.abs(e.deltaX) > 20 ? e.deltaX : (e.shiftKey && Math.abs(e.deltaY) > 20 ? e.deltaY : 0);
            if (delta > 20 && activeRollIndex < filteredRollImages.length - 1) {
                goToRollSlide(activeRollIndex + 1);
                rollWheelCooldown = true;
                setTimeout(() => { rollWheelCooldown = false; }, 300);
            } else if (delta < -20 && activeRollIndex > 0) {
                goToRollSlide(activeRollIndex - 1);
                rollWheelCooldown = true;
                setTimeout(() => { rollWheelCooldown = false; }, 300);
            }
        }, { passive: true });
    }

    function initCameraRollScrubber() {
        const wrap = document.getElementById('rollScrubberWrap');
        if (!wrap) return;

        let isScrubbing = false;

        const handleScrubber = (clientX) => {
            if (!filteredRollImages.length) return;
            const rect = wrap.getBoundingClientRect();
            const ratio = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
            const target = Math.round(ratio * (filteredRollImages.length - 1));
            goToRollSlide(target);
        };

        wrap.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return;
            isScrubbing = true;
            handleScrubber(e.clientX);
        });

        window.addEventListener('mousemove', (e) => {
            if (isScrubbing) handleScrubber(e.clientX);
        });

        window.addEventListener('mouseup', () => {
            isScrubbing = false;
        });

        wrap.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1) {
                handleScrubber(e.touches[0].clientX);
            }
        }, { passive: true });

        wrap.addEventListener('touchmove', (e) => {
            if (e.touches.length === 1) {
                handleScrubber(e.touches[0].clientX);
            }
        }, { passive: true });
    }

    function initCameraRollControls() {
        const prevBtn = document.getElementById('rollPrevBtn');
        const nextBtn = document.getElementById('rollNextBtn');

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (activeRollIndex > 0) goToRollSlide(activeRollIndex - 1);
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (activeRollIndex < filteredRollImages.length - 1) goToRollSlide(activeRollIndex + 1);
            });
        }

        document.addEventListener('keydown', (e) => {
            const lightbox = document.getElementById('lightbox');
            if (lightbox && lightbox.classList.contains('active')) return;

            const gallerySection = document.getElementById('gallery');
            if (!gallerySection) return;

            const rect = gallerySection.getBoundingClientRect();
            const isInView = (rect.top < window.innerHeight * 0.8 && rect.bottom > window.innerHeight * 0.2);
            if (!isInView) return;

            if (e.key === 'ArrowLeft') {
                if (activeRollIndex > 0) {
                    goToRollSlide(activeRollIndex - 1);
                    e.preventDefault();
                }
            } else if (e.key === 'ArrowRight') {
                if (activeRollIndex < filteredRollImages.length - 1) {
                    goToRollSlide(activeRollIndex + 1);
                    e.preventDefault();
                }
            }
        });

        window.addEventListener('resize', () => {
            update3DPositions(0);
        });
    }

    // --- Filter Buttons & Service Card Click Handlers ---
    const filterBtns = document.querySelectorAll('.filter-btn, .filter-tab');

    function activateFilter(filterValue) {
        filterBtns.forEach(b => b.classList.remove('active'));
        const activeBtns = document.querySelectorAll(`.filter-btn[data-filter="${filterValue}"], .filter-tab[data-filter="${filterValue}"]`);
        activeBtns.forEach(b => b.classList.add('active'));

        renderCameraRoll(filterValue);
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const filterValue = btn.getAttribute('data-filter');
            activateFilter(filterValue);
        });
    });

    const serviceCards = document.querySelectorAll('.service-card');
    serviceCards.forEach(card => {
        card.addEventListener('click', () => {
            const category = card.getAttribute('data-category');
            activateFilter(category);

            const gallerySection = document.getElementById('gallery');
            if (gallerySection) {
                gallerySection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
        card.style.cursor = 'pointer';
    });

    // Compatibility alias
    function initializeCarousel(filterValue = 'all') {
        activateFilter(filterValue);
    }

    // Initialize 3D Camera Roll on page load
    if (cameraRollStage) {
        renderCameraRoll('all');
        initCameraRollDrag();
        initCameraRollScrubber();
        initCameraRollControls();
    }



    // --- Scroll Animations ---
    const observerOptions = {
        threshold: 0.1,
        rootMargin: "0px 0px -50px 0px"
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    const animatedElements = document.querySelectorAll('.fade-in-up, .section-title, .service-card, .gallery-item, .pricing-card, .about-content, .contact-container');

    animatedElements.forEach(el => {
        el.classList.add('fade-in-up');
        observer.observe(el);
    });

    // --- Gallery Lightbox ---
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightbox-img');
    const lightboxCaption = document.querySelector('.lightbox-caption');
    const lightboxClose = document.querySelector('.lightbox-close');
    const lightboxPrev = document.querySelector('.lightbox-prev');
    const lightboxNext = document.querySelector('.lightbox-next');
    const galleryImages = document.querySelectorAll('.gallery-item img');

    let currentImageIndex = 0;
    let visibleImages = [];
    let clickedThumbnail = null; // Track which thumbnail was clicked

    // Update visible images based on current filter
    function updateVisibleImages() {
        const allImgs = document.querySelectorAll('.gallery-item img, .editorial-gallery-grid .gallery-card img, .camera-card img');
        visibleImages = Array.from(allImgs).filter(img => {
            const item = img.closest('.gallery-item, .gallery-card, .camera-card');
            return item && !item.classList.contains('hide') && item.style.visibility !== 'hidden';
        });
    }

    function openPortfolioLightbox(imgEl) {
        if (!lightbox || !lightboxImg) return;
        const track = document.getElementById('cameraRollTrack');
        const cardImgs = track ? Array.from(track.querySelectorAll('.camera-card img')) : [];
        visibleImages = cardImgs.filter(i => i && i.src);
        currentImageIndex = visibleImages.indexOf(imgEl);
        if (currentImageIndex === -1) currentImageIndex = 0;

        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
        lightboxImg.src = imgEl.src;
        if (lightboxCaption) {
            const cat = imgEl.closest('.camera-card')?.dataset.category || '';
            lightboxCaption.textContent = categoryLabels[cat] || imgEl.alt || '';
        }
    }

    // Lightbox delegation for editorial gallery
    const editorialGalEl = document.getElementById('editorialGallery');
    if (editorialGalEl) {
        editorialGalEl.addEventListener('click', (e) => {
            const card = e.target.closest('.gallery-card');
            if (!card) return;
            const img = card.querySelector('img');
            if (!img || !img.src) return;

            updateVisibleImages();
            currentImageIndex = visibleImages.indexOf(img);
            clickedThumbnail = img;

            if (lightbox && lightboxImg) {
                lightbox.classList.add('active');
                document.body.style.overflow = 'hidden';
                lightboxImg.src = img.src;
                if (lightboxCaption) lightboxCaption.textContent = img.alt || '';
            }
        });
    }

    // Open lightbox (Grid Click Handler)
    galleryImages.forEach((img, index) => {
        img.addEventListener('click', (e) => {
            updateVisibleImages();
            currentImageIndex = visibleImages.indexOf(img);
            clickedThumbnail = img; // Store the clicked thumbnail

            // Get the clicked thumbnail's position and size
            const rect = img.getBoundingClientRect();

            // Show lightbox immediately
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Set the image
            lightboxImg.src = img.src;
            lightboxCaption.textContent = img.alt || '';

            // Calculate the translation needed to move from thumbnail to center
            const viewportCenterX = window.innerWidth / 2;
            const viewportCenterY = window.innerHeight / 2;
            const thumbCenterX = rect.left + rect.width / 2;
            const thumbCenterY = rect.top + rect.height / 2;

            const translateX = thumbCenterX - viewportCenterX;
            const translateY = thumbCenterY - viewportCenterY;
            const scaleX = rect.width / (window.innerWidth * 0.9);
            const scaleY = rect.height / (window.innerHeight * 0.85);
            const scale = Math.max(scaleX, scaleY);

            // Set initial state (at thumbnail position)
            lightboxImg.style.transform = `translate(${translateX}px, ${translateY}px) scale(${scale})`;
            lightboxImg.style.objectFit = 'cover';
            lightboxImg.style.borderRadius = '0';
            lightboxImg.style.transition = 'none';

            // Force reflow
            void lightboxImg.offsetWidth;

            // Animate to center
            requestAnimationFrame(() => {
                lightboxImg.style.transition = 'all 0.6s cubic-bezier(0.16, 1, 0.3, 1)';
                lightboxImg.style.transform = 'translate(0, 0) scale(1)';
                lightboxImg.style.objectFit = 'contain';
                lightboxImg.style.borderRadius = '8px';
            });
        });
    });

    // Show specific image
    function showImage(index, direction = 'none') {
        if (visibleImages.length === 0) return;

        currentImageIndex = index;
        if (currentImageIndex < 0) currentImageIndex = visibleImages.length - 1;
        if (currentImageIndex >= visibleImages.length) currentImageIndex = 0;

        const img = visibleImages[currentImageIndex];
        // Note: img is now directly the image element, no querySelector needed

        // Reset any inline styles from hero animation
        lightboxImg.style.transform = '';
        lightboxImg.style.objectFit = '';
        lightboxImg.style.borderRadius = '';
        lightboxImg.style.transition = '';

        // Remove previous animation classes
        lightboxImg.classList.remove('slide-left', 'slide-right', 'zoom-in');

        // Force reflow to ensure animation triggers
        void lightboxImg.offsetWidth;

        // Add animation based on direction
        if (direction === 'next') {
            lightboxImg.classList.add('slide-left');
        } else if (direction === 'prev') {
            lightboxImg.classList.add('slide-right');
        }

        lightboxImg.src = img.src;
        if (lightboxCaption) {
            const cat = img.closest('.camera-card')?.dataset.category || '';
            lightboxCaption.textContent = (typeof categoryLabels !== 'undefined' && categoryLabels[cat]) || img.alt || '';
        }
    }

    // Close lightbox
    function closeLightbox() {
        // Hybrid animation: subtle scale-down + fade-out
        lightbox.style.transition = 'opacity 0.5s ease';
        lightbox.style.opacity = '0';

        lightboxImg.style.transition = 'transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.5s ease';
        lightboxImg.style.transform = 'scale(0.9)';
        lightboxImg.style.opacity = '0';

        // Wait for animation to complete
        setTimeout(() => {
            lightbox.classList.remove('active');
            lightbox.style.opacity = '';
            lightbox.style.transition = '';
            document.body.style.overflow = '';
            clickedThumbnail = null;

            // Reset image styles
            lightboxImg.style.transform = '';
            lightboxImg.style.transition = '';
            lightboxImg.style.opacity = '';
            lightboxImg.style.objectFit = '';
            lightboxImg.style.borderRadius = '';
        }, 500);
    }

    lightboxClose && lightboxClose.addEventListener('click', closeLightbox);

    // Click outside image to close
    lightbox && lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) {
            closeLightbox();
        }
    });

    // Navigation
    lightboxPrev && lightboxPrev.addEventListener('click', (e) => {
        e.stopPropagation();
        showImage(currentImageIndex - 1, 'prev');
    });

    lightboxNext && lightboxNext.addEventListener('click', (e) => {
        e.stopPropagation();
        showImage(currentImageIndex + 1, 'next');
    });

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (lightbox && lightbox.classList.contains('active')) {
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') showImage(currentImageIndex - 1, 'prev');
            if (e.key === 'ArrowRight') showImage(currentImageIndex + 1, 'next');
        }
    });

    // Swipe Support
    let touchStartX = 0;
    let touchEndX = 0;

    lightbox && lightbox.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    lightbox && lightbox.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, { passive: true });

    function handleSwipe() {
        const swipeThreshold = 50; // Minimum distance to be considered a swipe
        if (touchEndX < touchStartX - swipeThreshold) {
            // Swipe Left -> Next Image
            showImage(currentImageIndex + 1, 'next');
        }
        if (touchEndX > touchStartX + swipeThreshold) {
            // Swipe Right -> Prev Image
            showImage(currentImageIndex - 1, 'prev');
        }
    }

    // --- Customization Modal Logic ---
    const modal = document.getElementById('customization-modal');
    const modalContent = document.getElementById('modal-text');
    const closeBtn = document.querySelector('.modal-close');
    let activePackageId = null;

    function calculateModalPrice() {
        if (!loadedPricingData || !activePackageId) return;
        const pkg = loadedPricingData.packages.find(p => p.id === activePackageId);
        if (!pkg) return;

        const lang = localStorage.getItem('preferredLang') || 'de';
        let basePrice = parseFloat(pkg.price) || 0;

        let extrasTotal = 0;
        let selectedCount = 0;
        const checks = modal.querySelectorAll('.extra-check:checked');
        checks.forEach(cb => {
            extrasTotal += parseFloat(cb.dataset.price) || 0;
            selectedCount++;
        });

        let total = basePrice + extrasTotal;
        let discountInfo = '';

        // Global Discount
        if (loadedPricingData.globalDiscount && loadedPricingData.globalDiscount.active) {
            const gd = loadedPricingData.globalDiscount;
            let val = parseFloat(gd.value) || 0;
            if (gd.type === 'percent') {
                total *= (1 - (val / 100));
            } else {
                total -= val;
            }
        }

        // Bulk Discount (Count-based)
        if (pkg.bulkDiscounts && selectedCount > 0) {
            const appliedBulk = pkg.bulkDiscounts.find(d => d.count === selectedCount);
            if (appliedBulk && appliedBulk.discountPercent > 0) {
                total *= (1 - (appliedBulk.discountPercent / 100));
                discountInfo = lang === 'en'
                    ? `Bundle Discount: -${appliedBulk.discountPercent}%`
                    : `Sammelrabatt: -${appliedBulk.discountPercent}%`;
            }
        }

        const priceDisplay = modal.querySelector('.modal-total-price');
        const discountDisplay = modal.querySelector('.modal-discount-info');
        if (priceDisplay) {
            priceDisplay.textContent = total.toFixed(2).replace('.', ',') + '€';
        }
        if (discountDisplay) {
            discountDisplay.textContent = discountInfo;
            discountDisplay.style.display = discountInfo ? 'block' : 'none';
        }
    }

    if (modal && modalContent && closeBtn) {
        document.body.addEventListener('click', (e) => {
            const link = e.target.closest('.customize-link');
            if (link) {
                e.preventDefault();
                activePackageId = link.getAttribute('data-package');
                const pkg = loadedPricingData.packages.find(p => p.id === activePackageId);
                if (!pkg) return;

                const lang = localStorage.getItem('preferredLang') || 'de';
                const title = pkg.title[lang];

                let extrasHtml = '';
                if (pkg.extras && pkg.extras.length > 0) {
                    extrasHtml = `
                        <div class="modal-extras-grid">
                            ${pkg.extras.map(ex => `
                                <label class="modal-extra-item">
                                    <input type="checkbox" class="extra-check" data-id="${ex.id}" data-price="${ex.price}" onchange="this.dispatchEvent(new CustomEvent('recalc'))">
                                    <div class="extra-info">
                                        <span class="extra-name">${ex.label}</span>
                                        <span class="extra-price">+${ex.price}€</span>
                                    </div>
                                </label>
                            `).join('')}
                        </div>
                    `;
                } else {
                    extrasHtml = `<p style="opacity:0.6; font-style:italic;">${lang === 'en' ? 'No extras available for this package.' : 'Keine Extras für dieses Paket verfügbar.'}</p>`;
                }

                modalContent.innerHTML = `
                    <h2 style="margin-bottom:1rem; background:none; -webkit-text-fill-color:white;">${title}</h2>
                    <p style="margin-bottom:2rem; opacity:0.8; font-size:0.9rem;">
                        ${lang === 'en' ? 'Select your desired extras to see the total price including potential bundle discounts.' : 'Wähle deine gewünschten Extras aus, um den Gesamtpreis inklusive möglicher Sammelrabatte zu sehen.'}
                    </p>
                    ${extrasHtml}
                    <div class="modal-pricing-summary" style="margin-top:2.5rem; padding-top:1.5rem; border-top:1px solid rgba(255,255,255,0.1);">
                        <div class="modal-discount-info" style="color:var(--accent); font-size:0.85rem; margin-bottom:0.5rem; display:none;"></div>
                        <div style="display:flex; justify-content:space-between; align-items:baseline;">
                            <span style="opacity:0.6;">${lang === 'en' ? 'Estimated Total' : 'Voraussichtlicher Gesamtpreis'}:</span>
                            <span class="modal-total-price" style="font-size:2rem; font-weight:700; color:var(--accent);">0,00€</span>
                        </div>
                    </div>
                `;

                // Add recalc listeners
                modal.querySelectorAll('.extra-check').forEach(cb => {
                    cb.addEventListener('recalc', calculateModalPrice);
                });

                modal.classList.add('active');
                calculateModalPrice();
            }
        });

        closeBtn.addEventListener('click', () => {
            modal.classList.remove('active');
            activePackageId = null;
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
                activePackageId = null;
            }
        });
    }

    // Prevent scroll jump on refresh
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    // --- Cookie Banner Logic ---
    const cookieBanner = document.getElementById('cookie-banner');
    const acceptCookiesBtn = document.getElementById('accept-cookies');
    const declineCookiesBtn = document.getElementById('decline-cookies');
    const cookieSettingsBtn = document.getElementById('cookie-settings');

    if (cookieBanner) {
        // Check if user has already made a choice
        const cookieConsent = localStorage.getItem('cookieConsent');

        if (!cookieConsent) {
            // Show banner after 2 seconds
            setTimeout(() => {
                cookieBanner.classList.add('show');
            }, 2000);
        }

        if (acceptCookiesBtn) acceptCookiesBtn.addEventListener('click', () => {
            if (typeof CookieManager !== 'undefined') {
                CookieManager.acceptAll();
            } else {
                localStorage.setItem('cookieConsent', 'accepted');
            }
            cookieBanner.classList.remove('show');
        });

        if (declineCookiesBtn) declineCookiesBtn.addEventListener('click', () => {
            if (typeof CookieManager !== 'undefined') {
                CookieManager.declineAll();
            } else {
                localStorage.setItem('cookieConsent', 'declined');
            }
            cookieBanner.classList.remove('show');
        });

        if (cookieSettingsBtn) cookieSettingsBtn.addEventListener('click', () => {
            if (typeof CookieManager !== 'undefined') {
                CookieManager.showSettings();
                cookieBanner.classList.remove('show');
            }
        });
    }

    // --- Image Protection ---
    // Protect gallery images from right-click saving and dragging
    const protectImages = () => {
        // Select all images in gallery (both grid and carousel)
        const allGalleryImages = document.querySelectorAll('.gallery-item img, .gallery-row img, #lightbox-img');

        allGalleryImages.forEach(img => {
            // Disable right-click context menu
            img.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                showProtectionMessage();
                return false;
            });

            // Disable drag-and-drop
            img.addEventListener('dragstart', (e) => {
                e.preventDefault();
                return false;
            });

            // Add CSS protection attributes
            img.style.userSelect = 'none';
            img.style.webkitUserSelect = 'none';
            img.style.mozUserSelect = 'none';
            img.style.msUserSelect = 'none';
            img.setAttribute('draggable', 'false');
        });
    };

    // Show protection message when user tries to save
    let protectionMessageTimeout;
    const showProtectionMessage = () => {
        // Create message if it doesn't exist
        let message = document.getElementById('image-protection-message');
        if (!message) {
            message = document.createElement('div');
            message.id = 'image-protection-message';
            message.className = 'image-protection-message';
            message.innerHTML = '📷 Image protection active';
            document.body.appendChild(message);
        }

        // Show message
        message.classList.add('show');

        // Hide after 2 seconds
        clearTimeout(protectionMessageTimeout);
        protectionMessageTimeout = setTimeout(() => {
            message.classList.remove('show');
        }, 2000);
    };

    // Initial protection
    protectImages();

    // Re-apply protection after dynamic content loads (carousel)
    const protectionObserver = new MutationObserver(() => {
        protectImages();
    });

    // Observe gallery carousel for new images
    const carouselWrapper = document.querySelector('.gallery-carousel-wrapper');
    if (carouselWrapper) {
        protectionObserver.observe(carouselWrapper, {
            childList: true,
            subtree: true
        });
    }
});

// --- Premium Scroll Animations ---
const fadeInSections = document.querySelectorAll('.about-section, .services-section, .gallery-section, .pricing-section, .testimonials-section, .faq-section, .contact-section');

const sectionObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('fade-in-section', 'is-visible');
        }
    });
}, {
    threshold: 0.1,
    rootMargin: '0px 0px -100px 0px'
});

fadeInSections.forEach(section => {
    section.classList.add('fade-in-section');
    sectionObserver.observe(section);
});

// Stagger animations for grid items
const staggerElements = document.querySelectorAll('.service-card, .pricing-card');
const staggerObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
            setTimeout(() => {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }, index * 100);
            staggerObserver.unobserve(entry.target);
        }
    });
}, {
    threshold: 0.2
});

staggerElements.forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s cubic-bezier(0.16, 1, 0.3, 1)';
    staggerObserver.observe(el);
});

//Hide scroll indicator on scroll
const scrollIndicator = document.querySelector('.scroll-indicator');
if (scrollIndicator) {
    window.addEventListener('scroll', () => {
        if (window.scrollY > 100) {
            scrollIndicator.style.opacity = '0';
            scrollIndicator.style.pointerEvents = 'none';
        } else {
            scrollIndicator.style.opacity = '0.6';
            scrollIndicator.style.pointerEvents = 'auto';
        }
    });
}
