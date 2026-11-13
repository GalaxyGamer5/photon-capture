document.addEventListener('DOMContentLoaded', async () => {
    // --- Load Dynamic Portfolio ---
    function renderEditorialGallery(images) {
        const gallery = document.getElementById('editorialGallery');
        if (!gallery || !images || images.length === 0) return;

        const numCols = 4;
        const cols = Array.from({ length: numCols }, (_, i) => {
            const colDiv = document.createElement('div');
            colDiv.className = 'gallery-col';
            colDiv.dataset.col = i + 1;
            return colDiv;
        });

        const colWeights = [0, 0, 0, 0];

        images.forEach((img) => {
            let targetCol = 0;
            let minWeight = colWeights[0];
            for (let c = 1; c < numCols; c++) {
                if (colWeights[c] < minWeight) {
                    minWeight = colWeights[c];
                    targetCol = c;
                }
            }

            let isTall = false;
            if (colWeights[targetCol] === 0) {
                isTall = (targetCol === 0 || targetCol === 3);
            } else if (colWeights[targetCol] === 2) {
                isTall = (targetCol === 1 || targetCol === 2);
            } else {
                isTall = false;
            }

            const card = document.createElement('div');
            card.className = `gallery-card ${isTall ? 'tall' : 'square'}`;
            card.setAttribute('data-category', img.category);
                card.innerHTML = `
                <div class="gallery-card-inner">
                    <img src="assets/portfolio/${img.filename}" alt="${img.category}" loading="lazy">
                </div>
            `;

            cols[targetCol].appendChild(card);
            colWeights[targetCol] += (isTall ? 2 : 1);
        });

        gallery.innerHTML = '';
        cols.forEach(col => gallery.appendChild(col));
    }

    const galleryGrid = document.querySelector('.gallery-grid');
    const editorialGrid = document.getElementById('editorialGallery');

    if (galleryGrid || editorialGrid) {
        try {
            const response = await fetch('api/get-portfolio.php?t=' + Date.now());
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.images && data.images.length > 0) {
                    if (editorialGrid) {
                        renderEditorialGallery(data.images);
                    }
                    if (galleryGrid) {
                        galleryGrid.innerHTML = '';
                        data.images.forEach(img => {
                            const div = document.createElement('div');
                            div.className = 'gallery-item show';
                            div.setAttribute('data-category', img.category);
                            const image = document.createElement('img');
                            image.src = `assets/portfolio/${img.filename}`;
                            image.alt = img.category;
                            image.loading = 'lazy';
                            div.appendChild(image);
                            galleryGrid.appendChild(div);
                        });
                    }
                }
            }
        } catch (e) { console.error("Error loading dynamic portfolio:", e); }
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
    // 3D CAMERA ROLL / FILMSTRIP CAROUSEL (FREE-SPINNING KINETIC MOMENTUM)
    // ==========================================================================

    const cameraRollStage = document.getElementById('cameraRollStage');
    let portfolioImages = [];

    let currentRollFilter = "all";
    let filteredRollImages = [];
    let rollPos = 0;              // Continuous floating-point position
    let isRollDragging = false;
    let rollDragStartX = 0;
    let rollDragStartPos = 0;
    let rollHasDragged = false;
    let velocitySamples = [];
    let rollAnimFrameId = null;
    let wheelSnapTimer = null;

    // High quality aesthetic fallback cards when portfolio has no uploaded images yet
    const fallbackPortfolio = [
        { id: 1, filename: "", category: "portrait", label: "Porträt Noir" },
        { id: 2, filename: "", category: "event", label: "Festival of Lights" },
        { id: 3, filename: "", category: "portrait", label: "Studio Noir" },
        { id: 4, filename: "", category: "pet", label: "Golden Retriever" },
        { id: 5, filename: "", category: "event", label: "Corporate Gala" },
        { id: 6, filename: "", category: "pet", label: "Feline Grace" },
        { id: 7, filename: "", category: "portrait", label: "Natural Light" },
        { id: 8, filename: "", category: "event", label: "Summer Wedding" }
    ];

    const categoryLabels = {
        all: "Portfolio",
        portrait: "Porträt",
        event: "Event",
        pet: "Tier"
    };

    function createCameraCard(item, index) {
        const card = document.createElement("div");
        card.className = "camera-card";
        card.dataset.index = index;
        card.dataset.category = item.category;

        const originalIdx = item.originalIndex !== undefined ? item.originalIndex : index;
        const expNumber = String(originalIdx + 1).padStart(2, "0");
        const catLabel = categoryLabels[item.category] || item.category;

        let frameContent = "";
        if (item.filename) {
            frameContent = `
                <img src="assets/portfolio/${item.filename}" alt="${catLabel}" loading="lazy" draggable="false">
            `;
        } else {
            frameContent = `
                <div class="film-placeholder">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    <span>${item.label || "FOTO · " + catLabel.toUpperCase()}</span>
                </div>
            `;
        }

        // Build film-footer meta line from real EXIF or fall back to aesthetic placeholder
        const focalStr   = item.focal_length  || '35mm';
        const isoStr     = item.iso           || 'ISO 400';
        const apertureStr = item.aperture     || null;
        const shutterStr  = item.shutter_speed || null;
        // Compose a compact line: ISO · focal · aperture · shutter (omit nulls)
        const metaParts = [isoStr, focalStr, apertureStr, shutterStr].filter(Boolean);
        const filmMeta  = metaParts.join(' · ');

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
                    <span class="film-code">${filmMeta}</span>
                    <span class="film-dots">••••</span>
                </div>
            </div>
        `;

        card.addEventListener("click", () => {
            if (rollHasDragged) return;
            const cardIdx = parseInt(card.dataset.index, 10);
            const N = filteredRollImages.length;
            const currentNorm = ((Math.round(rollPos) % N) + N) % N;

            if (cardIdx === currentNorm) {
                const img = card.querySelector("img");
                if (img && img.src) {
                    openPortfolioLightbox(img);
                }
            } else {
                goToRollSlide(cardIdx);
            }
        });

        return card;
    }

    function update3DPositions() {
        const track = document.getElementById("cameraRollTrack");
        if (!track) return;
        const cards = track.querySelectorAll(".camera-card");
        const N = filteredRollImages.length;
        if (!cards.length || !N) return;

        const isMobile = window.innerWidth <= 640;
        const isTablet = window.innerWidth <= 992 && !isMobile;

        const stepX   = isMobile ? 165 : (isTablet ? 210 : 255);
        const zStep   = isMobile ? 70  : (isTablet ? 95  : 120);
        const baseRot = isMobile ? 18  : (isTablet ? 23  : 28);

        cards.forEach((card) => {
            const idx = parseInt(card.dataset.index, 10);

            // Shortest circular distance (continuous float)
            let dist = (idx - rollPos) % N;
            if (dist >  N / 2) dist -= N;
            if (dist < -N / 2) dist += N;

            const absDist = Math.abs(dist);

            // Fade-dissolve band near edge, hard-hide beyond
            if (absDist > 4.2) {
                card.style.opacity = "0";
                card.style.visibility = "hidden";
                card.style.pointerEvents = "none";
                card.classList.remove("is-center");
                return;
            }

            card.style.visibility = "visible";
            card.style.pointerEvents = absDist < 3.5 ? "auto" : "none";

            // Original aesthetic values
            const translateX = dist * stepX;
            const translateZ = -absDist * zStep;

            // Continuous rotation without flip at center (0deg at center, smooth ramp to flank angle)
            const maxRot = isMobile ? 30 : (isTablet ? 35 : 40);
            const rotAngle = Math.min(maxRot, absDist * baseRot);
            const rotateY = dist === 0 ? 0 : -Math.sign(dist) * rotAngle;

            const scale     = Math.max(0.65, 1.05 - absDist * 0.12);
            const opacity   = Math.max(0, (absDist > 3.8 ? (4.2 - absDist) / 0.4 : 1) * (1 - absDist * 0.28));
            const zIndex    = Math.round(100 - absDist * 15);
            const brightness = Math.max(0.35, 1 - absDist * 0.25);

            card.style.transform  = `translate3d(${translateX}px, 0, ${translateZ}px) rotateY(${rotateY}deg) scale(${scale})`;
            card.style.opacity    = opacity.toFixed(3);
            card.style.zIndex     = zIndex;
            card.style.filter     = `brightness(${brightness.toFixed(3)})`;

            // Glow driven per-frame as inline styles so it fades smoothly with dist
            // rather than jumping when the CSS class is toggled
            const inner = card.querySelector(".camera-card-inner");
            if (inner) {
                const t = Math.max(0, 1 - absDist);  // 1 at center, 0 at dist≥1
                const borderAlpha = (0.08 + t * 0.40).toFixed(3);
                const glowAlpha   = (t * 0.28).toFixed(3);
                inner.style.borderColor = `rgba(224, 90, 43, ${borderAlpha})`;
                inner.style.boxShadow   = `0 20px 45px -10px rgba(0,0,0,0.85), 0 0 40px -5px rgba(224,90,43,${glowAlpha})`;
            }

            // is-center class only used for hover styles — suppress its CSS transitions
            const shouldBeCenter = absDist < 0.5;
            if (shouldBeCenter !== card.classList.contains("is-center")) {
                if (inner) inner.style.transition = "none";
                card.classList.toggle("is-center", shouldBeCenter);
                if (inner) requestAnimationFrame(() => { inner.style.transition = ""; });
            }
        });

        updateRollControls();
    }

    function updateRollControls() {
        const N = filteredRollImages.length;
        if (!N) return;
        const activeIdx = ((Math.round(rollPos) % N) + N) % N;

        const currentEl = document.getElementById("rollCurrent");
        const totalEl = document.getElementById("rollTotal");
        const scrubberBar = document.getElementById("rollScrubberBar");

        if (currentEl) currentEl.textContent = String(activeIdx + 1).padStart(2, "0");
        if (totalEl) totalEl.textContent = String(N).padStart(2, "0");

        if (scrubberBar && N > 0) {
            const percent = N <= 1 ? 100 : (activeIdx / (N - 1)) * 100;
            scrubberBar.style.width = `${Math.min(100, Math.max(0, percent))}%`;
        }

        // Live update Viewfinder HUD exposure values with current center image
        if (typeof updateViewfinderExif === 'function') {
            updateViewfinderExif(filteredRollImages[activeIdx]);
        }
    }

    // Smooth physics snap to nearest card
    function snapToNearest() {
        cancelAnimationFrame(rollAnimFrameId);
        const target = Math.round(rollPos);

        function snapStep() {
            const diff = target - rollPos;
            if (Math.abs(diff) < 0.002) {
                rollPos = target;
                update3DPositions();
                updateRollControls();
                return;
            }

            rollPos += diff * 0.16;
            update3DPositions();
            rollAnimFrameId = requestAnimationFrame(snapStep);
        }

        rollAnimFrameId = requestAnimationFrame(snapStep);
    }

    // Free momentum spinning
    function startSpinMomentum(initialVel) {
        cancelAnimationFrame(rollAnimFrameId);
        let vel = Math.max(-1.5, Math.min(1.5, initialVel));
        const friction = 0.955; // Silky momentum gliding
        let lastTime = performance.now();

        if (Math.abs(vel) < 0.015) {
            snapToNearest();
            return;
        }

        function momentumStep() {
            const now = performance.now();
            const deltaRatio = Math.min(2.5, Math.max(0.4, (now - lastTime) / 16.67));
            lastTime = now;

            if (Math.abs(vel) > 0.012) {
                rollPos += vel * deltaRatio;
                vel *= Math.pow(friction, deltaRatio);
                update3DPositions();
                rollAnimFrameId = requestAnimationFrame(momentumStep);
            } else {
                snapToNearest();
            }
        }

        rollAnimFrameId = requestAnimationFrame(momentumStep);
    }

    // Smooth navigation to a specific slide
    function goToRollSlide(targetIndex) {
        cancelAnimationFrame(rollAnimFrameId);
        const N = filteredRollImages.length;
        if (!N) return;

        const currentNorm = ((rollPos % N) + N) % N;
        let diff = (targetIndex - currentNorm) % N;
        if (diff > N / 2) diff -= N;
        if (diff < -N / 2) diff += N;

        const target = rollPos + diff;

        function slideStep() {
            const d = target - rollPos;
            if (Math.abs(d) < 0.003) {
                rollPos = target;
                update3DPositions();
                updateRollControls();
                return;
            }
            rollPos += d * 0.16;
            update3DPositions();
            rollAnimFrameId = requestAnimationFrame(slideStep);
        }

        rollAnimFrameId = requestAnimationFrame(slideStep);
    }

    function renderCameraRoll(filterValue = "all") {
        currentRollFilter = filterValue;
        const track = document.getElementById("cameraRollTrack");
        if (!track) return;

        cancelAnimationFrame(rollAnimFrameId);

        const source = (portfolioImages && portfolioImages.length > 0) ? portfolioImages : fallbackPortfolio;
        let matched = (filterValue === "all")
            ? [...source]
            : source.filter(item => item.category === filterValue);

        track.innerHTML = "";

        if (matched.length === 0) {
            track.innerHTML = `
                <div style="position: absolute; transform: translate(-50%, -50%); text-align: center; color: rgba(255,255,255,0.4); font-size: 14px; letter-spacing: 0.05em;">
                    Keine Fotos in dieser Kategorie vorhanden.
                </div>
            `;
            filteredRollImages = [];
            updateRollControls();
            return;
        }

        // Tag with original indices
        matched = matched.map((item, i) => ({ ...item, originalIndex: i }));

        // Ensure at least 6 cards for smooth 360-degree cylindrical spinning
        filteredRollImages = [...matched];
        while (filteredRollImages.length < 6) {
            filteredRollImages = [...filteredRollImages, ...matched];
        }

        filteredRollImages.forEach((item, index) => {
            const card = createCameraCard(item, index);
            track.appendChild(card);
        });

        rollPos = 0;
        update3DPositions();
    }

    function initCameraRollDrag() {
        const viewport = document.getElementById("cameraRollViewport");
        if (!viewport) return;

        const isMobile = () => window.innerWidth <= 640;
        const getStepX = () => isMobile() ? 165 : 240;

        const onStart = (clientX) => {
            cancelAnimationFrame(rollAnimFrameId);
            isRollDragging = true;
            rollHasDragged = false;
            rollDragStartX = clientX;
            rollDragStartPos = rollPos;
            velocitySamples = [{ dx: 0, dt: 1, time: performance.now(), x: clientX }];
            viewport.classList.add("is-dragging");
        };

        const onMove = (clientX) => {
            if (!isRollDragging) return;
            const now = performance.now();
            const lastSample = velocitySamples[velocitySamples.length - 1];
            const dx = clientX - lastSample.x;
            const dt = Math.max(1, now - lastSample.time);

            if (Math.abs(clientX - rollDragStartX) > 6) {
                rollHasDragged = true;
            }

            velocitySamples.push({ dx, dt, time: now, x: clientX });
            velocitySamples = velocitySamples.filter(s => now - s.time < 120);

            const totalDelta = clientX - rollDragStartX;
            rollPos = rollDragStartPos - (totalDelta / getStepX());
            update3DPositions();
        };

        const onEnd = () => {
            if (!isRollDragging) return;
            isRollDragging = false;
            viewport.classList.remove("is-dragging");

            const now = performance.now();
            const recentSamples = velocitySamples.filter(s => now - s.time < 90);

            let slideVel = 0;
            if (recentSamples.length > 0) {
                const totalDx = recentSamples.reduce((sum, s) => sum + s.dx, 0);
                const totalDt = Math.max(1, recentSamples.reduce((sum, s) => sum + s.dt, 0));
                const pxPerMs = totalDx / totalDt;
                slideVel = -(pxPerMs * 16.67) / getStepX();
            }

            // Launch free momentum spinning
            startSpinMomentum(slideVel);

            setTimeout(() => { rollHasDragged = false; }, 60);
        };

        viewport.addEventListener("mousedown", (e) => {
            if (e.button !== 0) return;
            onStart(e.clientX);
        });

        window.addEventListener("mousemove", (e) => {
            if (isRollDragging) onMove(e.clientX);
        });

        window.addEventListener("mouseup", () => {
            if (isRollDragging) onEnd();
        });

        viewport.addEventListener("touchstart", (e) => {
            if (e.touches.length === 1) {
                onStart(e.touches[0].clientX);
            }
        }, { passive: true });

        viewport.addEventListener("touchmove", (e) => {
            if (isRollDragging && e.touches.length === 1) {
                onMove(e.touches[0].clientX);
            }
        }, { passive: true });

        viewport.addEventListener("touchend", () => {
            if (isRollDragging) onEnd();
        });

        viewport.addEventListener("touchcancel", () => {
            if (isRollDragging) onEnd();
        });

        // Trackpad 2-finger swipe / mouse wheel
        viewport.addEventListener("wheel", (e) => {
            const delta = Math.abs(e.deltaX) > 4 ? e.deltaX : (e.shiftKey && Math.abs(e.deltaY) > 4 ? e.deltaY : 0);
            if (Math.abs(delta) > 4) {
                e.preventDefault();
                cancelAnimationFrame(rollAnimFrameId);
                rollPos += (delta / getStepX()) * 0.65;
                update3DPositions();

                clearTimeout(wheelSnapTimer);
                wheelSnapTimer = setTimeout(() => {
                    snapToNearest();
                }, 110);
            }
        }, { passive: false });
    }

    function initCameraRollScrubber() {
        const wrap = document.getElementById("rollScrubberWrap");
        if (!wrap) return;

        let isScrubbing = false;

        const handleScrubber = (clientX) => {
            if (!filteredRollImages.length) return;
            const rect = wrap.getBoundingClientRect();
            const ratio = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
            const target = Math.round(ratio * (filteredRollImages.length - 1));
            goToRollSlide(target);
        };

        wrap.addEventListener("mousedown", (e) => {
            if (e.button !== 0) return;
            isScrubbing = true;
            handleScrubber(e.clientX);
        });

        window.addEventListener("mousemove", (e) => {
            if (isScrubbing) handleScrubber(e.clientX);
        });

        window.addEventListener("mouseup", () => {
            isScrubbing = false;
        });

        wrap.addEventListener("touchstart", (e) => {
            if (e.touches.length === 1) {
                handleScrubber(e.touches[0].clientX);
            }
        }, { passive: true });

        wrap.addEventListener("touchmove", (e) => {
            if (e.touches.length === 1) {
                handleScrubber(e.touches[0].clientX);
            }
        }, { passive: true });
    }

    function initCameraRollControls() {
        const prevBtn = document.getElementById("rollPrevBtn");
        const nextBtn = document.getElementById("rollNextBtn");

        if (prevBtn) {
            prevBtn.addEventListener("click", () => {
                goToRollSlide(Math.round(rollPos) - 1);
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener("click", () => {
                goToRollSlide(Math.round(rollPos) + 1);
            });
        }

        document.addEventListener("keydown", (e) => {
            const lightbox = document.getElementById("lightbox");
            if (lightbox && lightbox.classList.contains("active")) return;

            const gallerySection = document.getElementById("gallery");
            if (!gallerySection) return;

            const rect = gallerySection.getBoundingClientRect();
            const isInView = (rect.top < window.innerHeight * 0.8 && rect.bottom > window.innerHeight * 0.2);
            if (!isInView) return;

            if (e.key === "ArrowLeft") {
                goToRollSlide(Math.round(rollPos) - 1);
                e.preventDefault();
            } else if (e.key === "ArrowRight") {
                goToRollSlide(Math.round(rollPos) + 1);
                e.preventDefault();
            }
        });

        window.addEventListener("resize", () => {
            update3DPositions();
        });
    }

    // --- Filter Buttons & Service Card Click Handlers ---
    const filterBtns = document.querySelectorAll(".filter-btn, .filter-tab");

    function activateFilter(filterValue) {
        filterBtns.forEach(b => b.classList.remove("active"));
        const activeBtns = document.querySelectorAll(`.filter-btn[data-filter="${filterValue}"], .filter-tab[data-filter="${filterValue}"]`);
        activeBtns.forEach(b => b.classList.add("active"));

        renderCameraRoll(filterValue);
    }

    filterBtns.forEach(btn => {
        btn.addEventListener("click", () => {
            const filterValue = btn.getAttribute("data-filter");
            activateFilter(filterValue);
        });
    });

    const serviceCards = document.querySelectorAll(".service-card");
    serviceCards.forEach(card => {
        card.addEventListener("click", () => {
            const category = card.getAttribute("data-category");
            activateFilter(category);

            const gallerySection = document.getElementById("gallery");
            if (gallerySection) {
                gallerySection.scrollIntoView({ behavior: "smooth", block: "start" });
            }
        });
        card.style.cursor = "pointer";
    });

    // Compatibility alias
    function initializeCarousel(filterValue = "all") {
        activateFilter(filterValue);
    }

    async function loadPortfolio() {
        if (!cameraRollStage) return;
        try {
            const response = await fetch("api/get-portfolio.php?t=" + Date.now());
            if (response.ok) {
                const data = await response.json();
                if (data.success && Array.isArray(data.images) && data.images.length > 0) {
                    portfolioImages = data.images;
                    renderCameraRoll(currentRollFilter);
                }
            }
        } catch(e) {
            console.error("Error loading dynamic portfolio:", e);
        }
    }

    // Initialize 3D Camera Roll on page load
    if (cameraRollStage) {
        renderCameraRoll("all");
        initCameraRollDrag();
        initCameraRollScrubber();
        initCameraRollControls();
        loadPortfolio();
    }

    // ==========================================================================
    // LEICA / RANGEFINDER VIEWFINDER HUD MODE
    // ==========================================================================

    let isViewfinderActive = false;

    function playMechanicalShutterSound() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            if (!window._vfAudioCtx) {
                window._vfAudioCtx = new AudioCtx();
            }
            const ctx = window._vfAudioCtx;
            if (ctx.state === 'suspended') ctx.resume();

            const now = ctx.currentTime;

            // Mirror flip click
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'triangle';
            osc1.frequency.setValueAtTime(140, now);
            osc1.frequency.exponentialRampToValueAtTime(30, now + 0.035);
            gain1.gain.setValueAtTime(0.25, now);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.035);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(now);
            osc1.stop(now + 0.04);

            // Curtain snap
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(750, now + 0.04);
            osc2.frequency.exponentialRampToValueAtTime(120, now + 0.075);
            gain2.gain.setValueAtTime(0.20, now + 0.04);
            gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.075);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(now + 0.04);
            osc2.stop(now + 0.085);
        } catch(e) {}
    }

    function playCameraFocusBeep() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            if (!window._vfAudioCtx) window._vfAudioCtx = new AudioCtx();
            const ctx = window._vfAudioCtx;
            if (ctx.state === 'suspended') ctx.resume();

            const now = ctx.currentTime;
            // Authentic camera AF confirmation dual-tone (880Hz -> 1760Hz)
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, now);
            osc.frequency.setValueAtTime(1760, now + 0.035);
            gain.gain.setValueAtTime(0.07, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.08);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(now);
            osc.stop(now + 0.085);
        } catch(e) {}
    }

    function triggerFocusLock() {
        playCameraFocusBeep();
        const brackets = document.getElementById('vfAfBrackets');
        if (brackets) {
            brackets.classList.add('is-locked');
            clearTimeout(window._vfAfLockTimer);
            window._vfAfLockTimer = setTimeout(() => {
                brackets.classList.remove('is-locked');
            }, 350);
        }
    }

    function updateViewfinderExif(item) {
        if (!item) return;
        const shutterEl = document.getElementById('vfShutterVal');
        const apertureEl = document.getElementById('vfApertureVal');
        const isoEl = document.getElementById('vfIsoVal');
        const focalEl = document.getElementById('vfFocalVal');

        if (shutterEl) shutterEl.textContent = item.shutter_speed || '1/250s';
        if (apertureEl) apertureEl.textContent = item.aperture || 'f/1.8';
        if (isoEl) isoEl.textContent = item.iso ? (String(item.iso).startsWith('ISO') ? item.iso : `ISO ${item.iso}`) : 'ISO 400';
        if (focalEl) focalEl.textContent = item.focal_length || '35mm';
    }

    function toggleViewfinder(force) {
        isViewfinderActive = (typeof force === 'boolean') ? force : !isViewfinderActive;
        document.body.classList.toggle('viewfinder-active', isViewfinderActive);

        const hud = document.getElementById('viewfinderHud');
        if (hud) hud.setAttribute('aria-hidden', String(!isViewfinderActive));

        if (isViewfinderActive) {
            playMechanicalShutterSound();
            if (filteredRollImages && filteredRollImages.length > 0) {
                const N = filteredRollImages.length;
                const activeIdx = ((Math.round(rollPos) % N) + N) % N;
                updateViewfinderExif(filteredRollImages[activeIdx]);
            }
        }
    }

    function initViewfinderHud() {
        const toggleBtn = document.getElementById('viewfinderToggle');
        const rollHudBtn = document.getElementById('rollHudBtn');
        const closeBtn = document.getElementById('vfCloseBtn');
        const horizonLine = document.getElementById('vfHorizonLine');
        const meterNeedle = document.getElementById('vfMeterNeedle');
        const centerReticle = document.getElementById('vfCenterReticle');

        if (toggleBtn) toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleViewfinder();
        });
        if (rollHudBtn) rollHudBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleViewfinder(true);
        });
        if (closeBtn) closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleViewfinder(false);
        });

        // ESC key to close, Space to trigger autofocus confirmation lock
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isViewfinderActive) {
                toggleViewfinder(false);
            } else if (e.code === 'Space' && isViewfinderActive && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                triggerFocusLock();
            }
        });

        // Click while HUD is active triggers autofocus confirmation lock (no blinding screen flash)
        window.addEventListener('click', (e) => {
            if (!isViewfinderActive) return;
            if (e.target.closest('#vfCloseBtn') || e.target.closest('#viewfinderToggle') || e.target.closest('#rollHudBtn')) return;
            triggerFocusLock();
        }, true);

        // Gyroscope / Electronic Level Horizon tracking mouse
        let rafId = null;
        window.addEventListener('mousemove', (e) => {
            if (!isViewfinderActive) return;
            if (rafId) return;

            rafId = requestAnimationFrame(() => {
                rafId = null;
                const cx = window.innerWidth / 2;
                const cy = window.innerHeight / 2;

                const normX = (e.clientX - cx) / cx;
                const normY = (e.clientY - cy) / cy;

                // Dynamic Horizon Tilt (-7.5 deg to +7.5 deg)
                const tilt = normX * 7.5;
                if (horizonLine) {
                    horizonLine.style.transform = `rotate(${tilt.toFixed(2)}deg)`;
                    if (Math.abs(tilt) < 0.65) {
                        horizonLine.classList.add('is-level');
                    } else {
                        horizonLine.classList.remove('is-level');
                    }
                }

                // Meter needle position
                if (meterNeedle) {
                    const needlePct = Math.min(88, Math.max(12, 50 + (normX * 36)));
                    meterNeedle.style.left = `${needlePct.toFixed(1)}%`;
                }

                // Parallax shift on center reticle
                if (centerReticle) {
                    const px = (normX * 14).toFixed(1);
                    const py = (normY * 14).toFixed(1);
                    centerReticle.style.transform = `translate(calc(-50% + ${px}px), calc(-50% + ${py}px))`;
                }
            });
        });
    }

    initViewfinderHud();

    // ==========================================================================
    // 3D PHOTON PARTICLE & OPTICAL LENS CAPTURE ENGINE
    // ==========================================================================

    function initPhotonCaptureEngine() {
        const canvas = document.getElementById('photonCanvas');
        const lensStage = document.getElementById('heroLensStage');
        const triggerBtn = document.getElementById('photonTriggerBtn');
        const capturedImg = document.getElementById('sensorCapturedImg');
        const placeholderLogo = document.getElementById('sensorPlaceholderLogo');
        const exposureFlash = document.getElementById('sensorExposureFlash');
        const statusText = document.getElementById('sensorStatusText');
        const expText = document.getElementById('sensorExpText');

        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        let width = 0, height = 0;
        let dpr = Math.min(2, window.devicePixelRatio || 1);

        function resize() {
            const rect = canvas.getBoundingClientRect();
            width = rect.width;
            height = rect.height;
            canvas.width = Math.round(width * dpr);
            canvas.height = Math.round(height * dpr);
            ctx.scale(dpr, dpr);
        }
        resize();
        window.addEventListener('resize', resize);

        // Particle System Setup
        const PARTICLE_COUNT = window.innerWidth <= 768 ? 160 : 340;
        const particles = [];
        const colors = [
            '#ffffff',   // White-hot photon
            '#fff2e0',   // Warm starlight
            '#ffc466',   // Gold
            '#ff8a3d',   // Amber orange
            '#e05a2b'    // Signature Photon orange
        ];

        class Photon {
            constructor() {
                this.reset(true);
            }

            reset(initial = false) {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.z = 0.4 + Math.random() * 1.8;
                const speed = (0.3 + Math.random() * 0.7) * (1 / this.z);
                const angle = Math.random() * Math.PI * 2;
                this.vx = Math.cos(angle) * speed;
                this.vy = Math.sin(angle) * speed;
                this.radius = (1.2 + Math.random() * 1.8) * this.z;
                this.color = colors[Math.floor(Math.random() * colors.length)];
                this.alpha = 0.25 + Math.random() * 0.75;
                this.baseAlpha = this.alpha;
                this.phase = Math.random() * Math.PI * 2;
                this.trail = [];
                this.maxTrail = Math.round(3 + Math.random() * 3);
            }

            update(mouseX, mouseY, isVortex, targetX, targetY) {
                // Save trail
                this.trail.push({ x: this.x, y: this.y, alpha: this.alpha });
                if (this.trail.length > this.maxTrail) this.trail.shift();

                if (isVortex && targetX !== null && targetY !== null) {
                    // --- VORTEX GRAVITATIONAL PULL INTO LENS ---
                    const dx = targetX - this.x;
                    const dy = targetY - this.y;
                    const distSq = dx * dx + dy * dy;
                    const dist = Math.sqrt(distSq);

                    if (dist < 18) {
                        this.x = targetX + (Math.random() - 0.5) * 10;
                        this.y = targetY + (Math.random() - 0.5) * 10;
                        this.alpha = 0;
                        return;
                    }

                    const pull = Math.min(12, (width * 0.9) / Math.max(30, dist));
                    const angleIn = Math.atan2(dy, dx);
                    const spiralAngle = angleIn + (Math.PI / 2.3);

                    this.vx += (Math.cos(angleIn) * pull * 0.08) + (Math.cos(spiralAngle) * pull * 0.12);
                    this.vy += (Math.sin(angleIn) * pull * 0.08) + (Math.sin(spiralAngle) * pull * 0.12);

                    this.vx *= 0.92;
                    this.vy *= 0.92;

                    this.x += this.vx;
                    this.y += this.vy;

                    this.alpha = Math.min(1, this.baseAlpha + (1 - Math.min(1, dist / 400)) * 0.7);
                } else {
                    // --- IDLE ORGANIC DRIFT ---
                    this.phase += 0.02;
                    this.alpha = this.baseAlpha + Math.sin(this.phase) * 0.25;

                    if (mouseX !== null && mouseY !== null) {
                        const mdx = mouseX - this.x;
                        const mdy = mouseY - this.y;
                        const mdist = Math.sqrt(mdx * mdx + mdy * mdy);
                        if (mdist < 140) {
                            const force = (1 - mdist / 140) * 1.5;
                            this.vx -= (mdx / mdist) * force * 0.4;
                            this.vy -= (mdy / mdist) * force * 0.4;
                        }
                    }

                    this.x += this.vx;
                    this.y += this.vy;

                    if (this.x < -20) this.x = width + 20;
                    if (this.x > width + 20) this.x = -20;
                    if (this.y < -20) this.y = height + 20;
                    if (this.y > height + 20) this.y = -20;
                }
            }

            draw(ctx) {
                if (this.alpha <= 0.02) return;

                if (this.trail.length > 1) {
                    ctx.beginPath();
                    ctx.moveTo(this.trail[0].x, this.trail[0].y);
                    for (let i = 1; i < this.trail.length; i++) {
                        ctx.lineTo(this.trail[i].x, this.trail[i].y);
                    }
                    ctx.strokeStyle = this.color;
                    ctx.globalAlpha = Math.max(0, this.alpha * 0.35);
                    ctx.lineWidth = this.radius * 0.6;
                    ctx.stroke();
                }

                ctx.beginPath();
                ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                ctx.fillStyle = this.color;
                ctx.globalAlpha = Math.min(1, this.alpha);
                ctx.shadowColor = this.color;
                ctx.shadowBlur = this.radius * 4;
                ctx.fill();
                ctx.shadowBlur = 0;
            }
        }

        for (let i = 0; i < PARTICLE_COUNT; i++) {
            particles.push(new Photon());
        }

        let mouseX = null, mouseY = null;
        const heroSection = document.getElementById('hero');
        if (heroSection) {
            heroSection.addEventListener('mousemove', (e) => {
                const rect = canvas.getBoundingClientRect();
                mouseX = e.clientX - rect.left;
                mouseY = e.clientY - rect.top;
            });
            heroSection.addEventListener('mouseleave', () => {
                mouseX = null;
                mouseY = null;
            });
        }

        let isVortex = false;
        let isCapturing = false;
        let photoIndex = 0;

        function triggerPhotonCapture() {
            if (isCapturing) return;
            isCapturing = true;
            isVortex = true;

            let targetX = width * 0.75;
            let targetY = height * 0.5;
            if (lensStage) {
                const canvasRect = canvas.getBoundingClientRect();
                const lensRect = lensStage.getBoundingClientRect();
                targetX = (lensRect.left + lensRect.width / 2) - canvasRect.left;
                targetY = (lensRect.top + lensRect.height / 2) - canvasRect.top;
            }

            if (statusText) statusText.textContent = 'FOCUSING · IRIS LOCK';
            if (lensStage) lensStage.classList.add('capturing');

            // Gravitational sound hum
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (AudioCtx) {
                    if (!window._vfAudioCtx) window._vfAudioCtx = new AudioCtx();
                    const ctxA = window._vfAudioCtx;
                    if (ctxA.state === 'suspended') ctxA.resume();
                    const now = ctxA.currentTime;

                    const osc = ctxA.createOscillator();
                    const gain = ctxA.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(80, now);
                    osc.frequency.exponentialRampToValueAtTime(320, now + 0.8);
                    gain.gain.setValueAtTime(0.01, now);
                    gain.gain.linearRampToValueAtTime(0.18, now + 0.7);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.85);
                    osc.connect(gain);
                    gain.connect(ctxA.destination);
                    osc.start(now);
                    osc.stop(now + 0.9);
                }
            } catch(e) {}

            setTimeout(() => {
                if (exposureFlash) {
                    exposureFlash.classList.add('flash');
                    setTimeout(() => exposureFlash.classList.remove('flash'), 160);
                }

                if (typeof playMechanicalShutterSound === 'function') {
                    playMechanicalShutterSound();
                }

                const source = (typeof portfolioImages !== 'undefined' && portfolioImages && portfolioImages.length > 0)
                    ? portfolioImages
                    : (typeof fallbackPortfolio !== 'undefined' ? fallbackPortfolio : []);

                if (source.length > 0) {
                    const item = source[photoIndex % source.length];
                    photoIndex++;

                    if (item.filename && capturedImg) {
                        capturedImg.src = `assets/portfolio/${item.filename}`;
                        capturedImg.style.display = 'block';
                        capturedImg.classList.add('revealed');
                        if (placeholderLogo) placeholderLogo.style.opacity = '0';
                    } else if (placeholderLogo) {
                        capturedImg.style.display = 'none';
                        placeholderLogo.style.opacity = '1';
                    }

                    if (expText) {
                        const f = item.focal_length || '50mm';
                        const a = item.aperture || 'f/1.2';
                        const s = item.shutter_speed || '1/500s';
                        expText.textContent = `${f} · ${a} · ${s}`;
                    }
                }

                if (statusText) statusText.textContent = 'PHOTONS CAPTURED';

                setTimeout(() => {
                    isVortex = false;
                    if (lensStage) lensStage.classList.remove('capturing');
                    if (statusText) statusText.textContent = 'STANDBY · READY';

                    particles.forEach(p => {
                        const angle = Math.random() * Math.PI * 2;
                        const spd = 1.2 + Math.random() * 2.5;
                        p.vx = Math.cos(angle) * spd;
                        p.vy = Math.sin(angle) * spd;
                        p.alpha = p.baseAlpha;
                    });

                    setTimeout(() => { isCapturing = false; }, 800);
                }, 3200);
            }, 850);
        }

        if (triggerBtn) triggerBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            triggerPhotonCapture();
        });

        if (lensStage) lensStage.addEventListener('click', (e) => {
            e.stopPropagation();
            triggerPhotonCapture();
        });

        function renderLoop() {
            ctx.clearRect(0, 0, width, height);

            let targetX = width * 0.75;
            let targetY = height * 0.5;
            if (lensStage) {
                const canvasRect = canvas.getBoundingClientRect();
                const lensRect = lensStage.getBoundingClientRect();
                targetX = (lensRect.left + lensRect.width / 2) - canvasRect.left;
                targetY = (lensRect.top + lensRect.height / 2) - canvasRect.top;
            }

            for (let i = 0; i < particles.length; i++) {
                particles[i].update(mouseX, mouseY, isVortex, targetX, targetY);
                particles[i].draw(ctx);
            }

            requestAnimationFrame(renderLoop);
        }

        requestAnimationFrame(renderLoop);
    }

    initPhotonCaptureEngine();



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
        const allImgs = document.querySelectorAll('.gallery-item img, .editorial-gallery-grid .gallery-card img');
        visibleImages = Array.from(allImgs).filter(img => {
            const item = img.closest('.gallery-item, .gallery-card');
            return item && !item.classList.contains('hide');
        });
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
        lightboxCaption.textContent = img.alt || '';
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
