document.addEventListener('DOMContentLoaded', async () => {
    // --- Load Dynamic Portfolio ---
    const galleryGrid = document.querySelector('.gallery-grid');
    if (galleryGrid) {
        try {
            const response = await fetch('api/get-portfolio.php');
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.images && data.images.length > 0) {
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
        } catch(e) { console.error("Error loading dynamic portfolio:", e); }
    }

    // --- Load Dynamic Pricing ---
    const pricingGrid = document.getElementById('pricing-grid-container');
    const customReq = document.getElementById('custom-request-container');
    let loadedPricingData = null;

    async function fetchPricing() {
        if (!pricingGrid || !customReq) return;
        try {
            const res = await fetch('api/get-pricing.php');
            if (res.ok) {
                loadedPricingData = await res.json();
                renderPricing();
            }
        } catch(e) { console.error("Error loading pricing:", e); }
    }

    function renderPricing() {
        if (!loadedPricingData || !pricingGrid || !customReq) return;
        const lang = localStorage.getItem('preferredLang') || 'de';
        
        // Render Packages
        pricingGrid.innerHTML = '';
        if (loadedPricingData.packages) {
            loadedPricingData.packages.forEach(pkg => {
                const card = document.createElement('div');
                card.className = 'pricing-card';
                
                let featuresHtml = '';
                pkg.features.forEach(feat => {
                    if (feat[lang]) {
                        featuresHtml += `<li>${feat[lang]}</li>`;
                    }
                });

                const unitHtml = pkg.priceUnit[lang] ? `<span>${pkg.priceUnit[lang]}</span>` : '';
                const customizeText = lang === 'en' ? 'Customize Package (Extras)' : 'Paket anpassen (Extras)';
                const btnText = lang === 'en' ? 'Send Request' : 'Anfrage senden';

                card.innerHTML = `
                    <h3>${pkg.title[lang]}</h3>
                    <div class="price">${pkg.price}${unitHtml}</div>
                    <ul class="pricing-features">
                        ${featuresHtml}
                    </ul>
                    <a href="javascript:void(0)" class="customize-link" data-package="${pkg.id}">${customizeText}</a>
                    <a href="${pkg.buttonUrl}" class="cta-button" style="background: transparent; border: 1px solid var(--accent-color); color: var(--accent-color);">${btnText}</a>
                `;
                pricingGrid.appendChild(card);
            });
        }

        // Render Custom Request
        if (loadedPricingData.custom) {
            const cust = loadedPricingData.custom;
            customReq.innerHTML = `
                <h3 style="margin-bottom: 0.5rem;">${cust.title[lang]}</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                    ${cust.desc[lang]}
                </p>
                <a href="${cust.buttonUrl}" class="cta-button" style="background: transparent; border: 1px solid var(--text-primary); color: var(--text-primary);">
                    ${cust.btn[lang]}
                </a>
            `;
        }
    }

    fetchPricing();
    
    // Listen for language changes to re-render texts
    window.addEventListener('languageChanged', renderPricing);

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

    window.addEventListener('scroll', () => {
        const windowHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const scrolled = (window.scrollY / windowHeight) * 100;
        scrollProgressBar.style.width = scrolled + '%';
    });

    // --- Dark Mode Toggle ---
    const darkModeToggle = document.querySelector('.dark-mode-toggle');
    const sunIcon = document.querySelector('.sun');
    const moonIcon = document.querySelector('.moon');

    // Check for saved theme preference or default to dark mode
    // Check for saved theme preference or default to dark mode
    // Changed key to 'theme_pref' to reset users to default dark mode
    const currentTheme = localStorage.getItem('theme_pref') || 'dark';

    if (currentTheme === 'light') {
        document.body.classList.add('light-mode');
        sunIcon.style.display = 'block';
        moonIcon.style.display = 'none';
    } else {
        // Default is dark mode
        document.body.classList.remove('light-mode');
        sunIcon.style.display = 'none';
        moonIcon.style.display = 'block';
    }

    darkModeToggle.addEventListener('click', () => {
        document.body.classList.toggle('light-mode');

        if (document.body.classList.contains('light-mode')) {
            localStorage.setItem('theme_pref', 'light');
            sunIcon.style.display = 'block';
            moonIcon.style.display = 'none';
        } else {
            localStorage.setItem('theme_pref', 'dark');
            sunIcon.style.display = 'none';
            moonIcon.style.display = 'block';
        }
    });

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
        } catch(e) { console.error("Error loading FAQ:", e); }
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
            navLinks.classList.toggle('active');
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
            langToggle.addEventListener('click', () => {
                const newLang = currentLang === 'de' ? 'en' : 'de';
                updateContent(newLang);
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
                // Close mobile menu
                navLinks.classList.remove('active');
                hamburger.classList.remove('active');

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

    // --- Gallery Filtering ---
    const filterBtns = document.querySelectorAll('.filter-btn');
    const galleryItems = document.querySelectorAll('.gallery-item');

    const activateFilter = (filterValue) => {
        filterBtns.forEach(b => b.classList.remove('active'));
        const activeBtn = document.querySelector(`.filter-btn[data-filter="${filterValue}"]`);
        if (activeBtn) activeBtn.classList.add('active');

        galleryItems.forEach(item => {
            const category = item.getAttribute('data-category');

            if (filterValue === 'all' || category === filterValue) {
                item.classList.remove('hide');
                item.classList.add('show');
            } else {
                item.classList.remove('show');
                item.classList.add('hide');
            }
        });
    };

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const filterValue = btn.getAttribute('data-filter');
            activateFilter(filterValue);
            initializeCarousel(filterValue); // Rebuild carousel with filtered items
        });
    });

    // --- Service Card Click to Filter Gallery ---
    const serviceCards = document.querySelectorAll('.service-card');

    serviceCards.forEach(card => {
        card.addEventListener('click', () => {
            const category = card.getAttribute('data-category');

            // Apply the filter
            activateFilter(category);
            initializeCarousel(category);

            // Scroll to gallery section smoothly
            const gallerySection = document.getElementById('gallery');
            if (gallerySection) {
                gallerySection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        // Add cursor pointer to indicate clickability
        card.style.cursor = 'pointer';
    });

    // --- Auto-Scrolling Gallery Carousel ---
    let currentUniqueItems = []; // Track unique items for lightbox navigation

    function initializeCarousel(filterValue = 'all') {
        const row1 = document.getElementById('gallery-row-1');
        const row2 = document.getElementById('gallery-row-2');

        if (!row1 || !row2) {
            // Check if we even have a gallery to build upon
            const galleryGrid = document.querySelector('.gallery-grid');
            if (!galleryGrid) return; // Exit if no gallery grid exists (e.g. payment page)

            // If carousel rows don't exist, create them
            const wrapper = document.querySelector('.gallery-carousel-wrapper');
            if (!wrapper) {
                const wrapper = document.createElement('div');
                wrapper.className = 'gallery-carousel-wrapper';
                wrapper.innerHTML = `
                    <div class="gallery-row gallery-row-right" id="gallery-row-1"></div>
                    <div class="gallery-row gallery-row-left" id="gallery-row-2"></div>
                `;
                galleryGrid.parentNode.insertBefore(wrapper, galleryGrid);
                galleryGrid.style.display = 'none';
            }
            // Try again after creation
            initializeCarousel(filterValue);
            return;
        }

        // Clear existing content
        row1.innerHTML = '';
        row2.innerHTML = '';

        // Get filtered items
        const filteredItems = Array.from(galleryItems).filter(item => {
            const category = item.getAttribute('data-category');
            return filterValue === 'all' || category === filterValue;
        });

        currentUniqueItems = filteredItems; // Store for lightbox navigation

        if (filteredItems.length === 0) return;

        // Shuffle function using Fisher-Yates algorithm
        function shuffleArray(array) {
            for (let i = array.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [array[i], array[j]] = [array[j], array[i]];
            }
            return array;
        }

        // Use all items for both rows to maximize variety
        // Create copies for row 1 and row 2
        let row1Items = [...filteredItems];
        let row2Items = [...filteredItems];

        // Shuffle both rows independently to ensure different orders
        if (row1Items.length > 1) {
            row1Items = shuffleArray(row1Items);
            row2Items = shuffleArray(row2Items);

            // Ensure row 2 starts with a different item than row 1 to avoid vertical duplicates at start
            if (row1Items[0].src === row2Items[0].src) {
                row2Items.push(row2Items.shift());
            }
        }

        // Duplicate items for seamless loop (need at least 3 copies for smooth scrolling)
        // We use the full pool now, so repetition is much less frequent
        const row1Content = [row1Items, row1Items, row1Items].flat();
        const row2Content = [row2Items, row2Items, row2Items].flat();

        row1Content.forEach(item => {
            const clone = item.cloneNode(true);
            row1.appendChild(clone);

            // Re-add lightbox click handlers
            const img = clone.querySelector('img');
            if (img) {
                img.addEventListener('click', () => {
                    openLightbox(img);
                });
            }
        });

        row2Content.forEach(item => {
            const clone = item.cloneNode(true);
            row2.appendChild(clone);

            // Re-add lightbox click handlers
            const img = clone.querySelector('img');
            if (img) {
                img.addEventListener('click', () => {
                    openLightbox(img);
                });
            }
        });
    }

    // Helper function to open lightbox
    function openLightbox(img) {
        const lightbox = document.getElementById('lightbox');
        const lightboxImg = document.getElementById('lightbox-img');
        const lightboxCaption = document.querySelector('.lightbox-caption');

        if (lightbox && lightboxImg) {
            // Update visible images list to match the current carousel content (unique items)
            // Map to img elements to be consistent with grid logic
            visibleImages = currentUniqueItems.map(item => item.querySelector('img'));

            // Find index by matching src
            currentImageIndex = visibleImages.findIndex(item => item.src === img.src);

            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
            lightboxImg.src = img.src;
            if (lightboxCaption) {
                lightboxCaption.textContent = img.alt || '';
            }
        }
    }

    // Initialize carousel on page load
    initializeCarousel('all');



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
        visibleImages = Array.from(galleryImages).filter(img => {
            const item = img.closest('.gallery-item');
            // Check if the item is currently displayed (not hidden by filter)
            // Use class check instead of style.display for consistency
            return !item.classList.contains('hide');
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

        acceptCookiesBtn.addEventListener('click', () => {
            if (typeof CookieManager !== 'undefined') {
                CookieManager.acceptAll();
            } else {
                localStorage.setItem('cookieConsent', 'accepted');
            }
            cookieBanner.classList.remove('show');
        });

        declineCookiesBtn.addEventListener('click', () => {
            if (typeof CookieManager !== 'undefined') {
                CookieManager.declineAll();
            } else {
                localStorage.setItem('cookieConsent', 'declined');
            }
            cookieBanner.classList.remove('show');
        });

        cookieSettingsBtn.addEventListener('click', () => {
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
const fadeInSections = document.querySelectorAll('.about-section, .services-section, .gallery-section, .pricing-section, .faq-section, .contact-section');

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
