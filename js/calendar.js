// Calendar JavaScript - Dedicated calendar functionality
// Relies on main.js for translations and global functionality

document.addEventListener('DOMContentLoaded', () => {
    // Calendar State
    let currentDate = new Date();
    let selectedDate = null;
    let currentLanguage = localStorage.getItem('preferredLang') || 'de';
    let selectedPackage = null;
    let availabilityData = {};

    // Get package from URL
    const urlParams = new URLSearchParams(window.location.search);
    selectedPackage = urlParams.get('package') || null;

    // Elements
    const bookingValues = {
        service: document.getElementById('service'),
        extrasContainer: document.getElementById('extras-container'),
        bookingTips: document.getElementById('booking-tips')
    };

    // Initialize calendar
    function initCalendar() {
        renderCalendar(); // Render immediately with defaults
        loadAvailabilityData(); // Fetch real data
        setupEventListeners();
        updatePackageDisplay();

        // Initial setup for the form if it exists
        if (bookingValues.service) {
            if (selectedPackage) {
                bookingValues.service.value = selectedPackage;
            }
            renderExtras(bookingValues.service.value);
        }
    }

    // Fetch availability data
    async function loadAvailabilityData() {
        try {
            const response = await fetch('data/calendar.json');
            if (!response.ok) throw new Error('Network response was not ok');
            availabilityData = await response.json();
            renderCalendar(); // Re-render after data load
        } catch (error) {
            console.error('Error loading calendar data:', error);
            // Fallback empty data is already set
        }
    }

    // Render calendar grid
    function renderCalendar() {
        const t = translations[currentLanguage];
        if (!t) return;

        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        // Update month header
        document.getElementById('currentMonth').textContent = `${t["calendar.months"][month]} ${year}`;

        // Render weekday labels
        const weekdayLabels = document.getElementById('weekdayLabels');
        weekdayLabels.innerHTML = '';
        t["calendar.weekdays"].forEach(day => {
            const label = document.createElement('div');
            label.className = 'weekday-label';
            label.textContent = day;
            weekdayLabels.appendChild(label);
        });

        // Calculate calendar grid
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDay = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1; // Monday start

        const daysGrid = document.getElementById('daysGrid');
        daysGrid.innerHTML = '';

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Empty cells for previous month
        for (let i = 0; i < startingDay; i++) {
            const cell = document.createElement('div');
            cell.className = 'day-cell empty';
            daysGrid.appendChild(cell);
        }

        // Day cells
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month, day);
            const dateString = formatDate(date);
            const cell = document.createElement('div');
            cell.className = 'day-cell';

            // Check if past
            if (date < today) {
                cell.classList.add('past');
            } else {
                // Check availability
                const status = getAvailabilityStatus(dateString) || 'available';
                cell.classList.add(status);

                // Only clickable if available or limited
                if (status === 'available' || status === 'limited') {
                    cell.addEventListener('click', () => selectDate(date, dateString));
                }
            }

            // Check if today
            if (date.getTime() === today.getTime()) {
                cell.classList.add('today');
            }

            // Check if selected
            if (selectedDate === dateString) {
                cell.classList.add('selected');
            }

            // Create cell content
            const dayNum = document.createElement('div');
            dayNum.className = 'day-number';
            dayNum.textContent = day;

            const dayStatus = document.createElement('div');
            dayStatus.className = 'day-status';
            const statusKey = getAvailabilityStatus(dateString) || 'available';
            dayStatus.textContent = t[`calendar.${statusKey}`];

            cell.appendChild(dayNum);
            cell.appendChild(dayStatus);
            daysGrid.appendChild(cell);
        }
    }

    // Format date to YYYY-MM-DD
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    // Format display date
    function formatDisplayDate(dateString) {
        const t = translations[currentLanguage];
        const date = new Date(dateString + 'T00:00:00');
        const day = date.getDate();
        const month = t["calendar.months"][date.getMonth()];
        const year = date.getFullYear();
        return `${day}. ${month} ${year}`;
    }

    // Get availability status
    function getAvailabilityStatus(dateString) {
        return availabilityData[dateString]?.status || null;
    }

    // Select date
    function selectDate(date, dateString) {
        // Remove previous selection
        document.querySelectorAll('.day-cell.selected').forEach(c => c.classList.remove('selected'));

        // Toggle selection
        if (selectedDate === dateString) {
            selectedDate = null;
            hideBookingPanel();
        } else {
            selectedDate = dateString;

            // Add selected class to clicked cell
            const cells = document.querySelectorAll('.day-cell');
            cells.forEach(cell => {
                if (cell.textContent.includes(date.getDate()) && !cell.classList.contains('empty')) {
                    const cellDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), date.getDate());
                    if (formatDate(cellDate) === dateString) {
                        cell.classList.add('selected');
                    }
                }
            });

            showBookingPanel(dateString);
        }
    }

    // Show booking panel
    function showBookingPanel(dateString) {
        const panel = document.getElementById('bookingPanel');
        const dateDisplay = document.getElementById('selectedDateDisplay');

        // Reset view to initial state
        document.getElementById('booking-initial-view').style.display = 'block';
        document.getElementById('booking-form-container').style.display = 'none';

        dateDisplay.textContent = formatDisplayDate(dateString);
        panel.classList.add('active');

        // Scroll to panel
        setTimeout(() => {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);

        // Update hidden input in form
        const dateInput = document.getElementById('date');
        if (dateInput) dateInput.value = dateString;
    }

    // Hide booking panel
    function hideBookingPanel() {
        const panel = document.getElementById('bookingPanel');
        panel.classList.remove('active');
        // Reset state
        document.getElementById('booking-initial-view').style.display = 'block';
        document.getElementById('booking-form-container').style.display = 'none';

        // Update text again in case we canceled mid-booking
        const confirmBtn = document.getElementById('confirmBtn');
        if (confirmBtn) confirmBtn.style.display = 'inline-block';
    }

    // Update package display
    function updatePackageDisplay() {
        const packageInfo = document.getElementById('selectedPackageInfo');
        const packageNameSpan = document.getElementById('packageName');
        const t = translations[currentLanguage];

        if (selectedPackage) {
            let packageName = selectedPackage;
            if (selectedPackage === 'portrait') packageName = t["services.portrait.title"] || "Porträt-Session";
            if (selectedPackage === 'event') packageName = t["services.event.title"] || "Event-Begleitung";
            if (selectedPackage === 'pet') packageName = t["services.pet.title"] || "Tierfotografie";
            if (selectedPackage === 'other' || selectedPackage === 'custom') packageName = t["contact.form.service.other"] || "Sonstiges / Allgemeine Frage";

            packageNameSpan.textContent = packageName;
            packageInfo.style.display = 'inline-block';
        } else {
            packageInfo.style.display = 'none';
        }
    }

    // Setup event listeners
    function setupEventListeners() {
        // Month navigation
        document.getElementById('prevMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        });

        document.getElementById('nextMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        });

        // Cancel button
        document.getElementById('cancelBtn').addEventListener('click', () => {
            selectedDate = null;
            hideBookingPanel();
            document.querySelectorAll('.day-cell.selected').forEach(c => c.classList.remove('selected'));
        });

        // Confirm button (Shows form now, doesn't redirect)
        document.getElementById('confirmBtn').addEventListener('click', () => {
            if (selectedDate) {
                document.getElementById('booking-initial-view').style.display = 'none';
                document.getElementById('booking-form-container').style.display = 'block';
                // Scroll slightly to show form top
                document.getElementById('bookingPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        // Back to Calendar button
        const backBtn = document.getElementById('backToCalendarBtn');
        if (backBtn) {
            backBtn.addEventListener('click', () => {
                document.getElementById('booking-initial-view').style.display = 'block';
                document.getElementById('booking-form-container').style.display = 'none';
            });
        }

        // Service Selection Change
        if (bookingValues.service) {
            bookingValues.service.addEventListener('change', (e) => {
                renderExtras(e.target.value);
            });
        }

        // Listen for language changes from main.js
        window.addEventListener('languageChanged', (e) => {
            currentLanguage = e.detail.language;
            renderCalendar();
            updatePackageDisplay();
            // Also update form extras if visible
            if (bookingValues.service) {
                renderExtras(bookingValues.service.value);
            }
        });
    }

    // --- Dynamic Pricing & Extras Logic (Migrated from booking.html) ---

    // Guard against infinite loop: renderExtras -> updatePricing -> updateContent -> languageChanged -> renderExtras
    let isRenderingExtras = false;

    // Extras Configuration
    const extrasConfig = {
        portrait: ['photos', 'location', 'time_standard'],
        pet: ['photos', 'location', 'time_standard'],
        event: ['duration_event', 'express'],
        other: []
    };

    const pricing = {
        photos: { plus10: 10, plus25: 20, plus50: 35 },
        location: { plus1: 30, plus2: 55 },
        time: { plus30m: 25, plus1h: 50, plus2h: 90 },
        express: { express: 60, overnight: 120 }
    };

    function renderExtras(service) {
        if (!bookingValues.extrasContainer) return;
        if (isRenderingExtras) return; // Prevent infinite loop
        isRenderingExtras = true;

        bookingValues.extrasContainer.innerHTML = '';
        const config = extrasConfig[service] || extrasConfig['portrait'];

        // Handle "other" visibility
        const dateInput = document.getElementById('date');
        const timeInput = document.getElementById('time');

        // Always require date and time when using the calendar interface
        if (dateInput) dateInput.setAttribute('required', 'required');
        if (timeInput) timeInput.setAttribute('required', 'required');

        const t = (typeof translations !== 'undefined' && translations[currentLanguage]) ? translations[currentLanguage] : {};

        if (config.length === 0) {
            bookingValues.extrasContainer.innerHTML = `<p style="color: var(--text-secondary); font-style: italic;">${t['contact.form.extras.none'] || 'Keine speziellen Extras für diese Auswahl.'}</p>`;
            updatePricing();
            isRenderingExtras = false;
            return;
        }

        config.forEach(type => {
            let html = '';
            // (Same HTML generation logic as before, just compact)
            if (type === 'photos') {
                html = `<div class="form-group"><label>${t['contact.form.extras.photos.label'] || 'Mehr bearbeitete Bilder'}</label><select name="extra_photos" class="form-control"><option value="none">${t['contact.form.extras.photos.opt0'] || 'Keine zusätzlichen Bilder'}</option><option value="plus10">${t['contact.form.extras.photos.opt1'] || '+10 Bilder'}</option><option value="plus25">${t['contact.form.extras.photos.opt2'] || '+25 Bilder'}</option><option value="plus50">${t['contact.form.extras.photos.opt3'] || '+50 Bilder'}</option></select></div>`;
            } else if (type === 'location') {
                html = `<div class="form-group"><label>${t['contact.form.extras.location.label'] || 'Zusätzliche Location'}</label><select name="extra_location" class="form-control"><option value="none">${t['contact.form.extras.location.opt0'] || 'Keine zusätzliche Location'}</option><option value="plus1">${t['contact.form.extras.location.opt1'] || '+1 Location'}</option><option value="plus2">${t['contact.form.extras.location.opt2'] || '+2 Locations'}</option></select></div>`;
            } else if (type === 'time_standard') {
                html = `<div class="form-group"><label>${t['contact.form.extras.time.label'] || 'Verlängerung der Shooting-Zeit'}</label><select name="extra_time" class="form-control"><option value="none">${t['contact.form.extras.time.opt0'] || 'Keine Verlängerung'}</option><option value="plus30m">${t['contact.form.extras.time.opt1'] || '+30 Minuten'}</option><option value="plus1h">${t['contact.form.extras.time.opt2'] || '+1 Stunde'}</option><option value="plus2h">${t['contact.form.extras.time.opt3'] || '+2 Stunden'}</option></select></div>`;
            } else if (type === 'duration_event') {
                html = `<div class="form-group"><label>${t['contact.form.extras.duration.label'] || 'Buchungsdauer'}</label><select name="event_duration" class="form-control"><option value="2h">${t['contact.form.extras.duration.opt2'] || '2 Stunden (Minimum)'}</option><option value="3h">${t['contact.form.extras.duration.opt3'] || '3 Stunden'}</option><option value="4h">${t['contact.form.extras.duration.opt4'] || '4 Stunden'}</option><option value="5h">${t['contact.form.extras.duration.opt5'] || '5 Stunden'}</option><option value="6h">${t['contact.form.extras.duration.opt6'] || '6 Stunden'}</option><option value="8h">${t['contact.form.extras.duration.opt8'] || '8 Stunden'}</option><option value="open">${t['contact.form.extras.duration.open'] || 'Open End'}</option></select><small style="display: block; margin-top: 0.5rem; color: var(--text-secondary); font-style: italic;">${t['contact.form.extras.duration.open_note'] || 'Hinweis: Open End basiert auf 8 Stunden. Jede weitere Stunde wird mit 50€ berechnet.'}</small></div>`;
            } else if (type === 'express') {
                html = `<div class="form-group"><label>${t['contact.form.extras.express.label'] || 'Express-Bearbeitung'}</label><select name="extra_express" class="form-control"><option value="standard">${t['contact.form.extras.express.opt0'] || 'Standard (1-2 Wochen)'}</option><option value="express">${t['contact.form.extras.express.opt1'] || 'Express (48 Stunden)'}</option><option value="overnight">${t['contact.form.extras.express.opt2'] || 'Overnight (24 Stunden)'}</option></select></div>`;
            }
            bookingValues.extrasContainer.innerHTML += html;
        });

        // Attach listeners to new inputs
        const inputs = bookingValues.extrasContainer.querySelectorAll('select');
        inputs.forEach(input => {
            input.addEventListener('change', updatePricing);
        });

        updatePricing();
        isRenderingExtras = false;
    }

    function updatePricing() {
        if (!bookingValues.service) return;

        const priceSummary = document.getElementById('price-summary');
        const priceBreakdown = document.getElementById('price-breakdown');
        const priceDiscount = document.getElementById('price-discount');
        const priceTotal = document.getElementById('price-total');

        const selectedService = bookingValues.service.value;
        const extrasContainer = bookingValues.extrasContainer;

        const basePrices = { portrait: 125, pet: 125, event: 100, other: 0 };
        let basePrice = basePrices[selectedService] || 0;
        let durationHours = 0;
        let durationDiscount = 0;
        let durationDiscountPercent = 0;

        // Event Logic
        const durationSelect = extrasContainer.querySelector('select[name="event_duration"]');
        if (selectedService === 'event' && durationSelect) {
            const durationValue = durationSelect.value;
            if (durationValue.endsWith('h')) durationHours = parseInt(durationValue);
            else if (durationValue === 'open') durationHours = 8;

            basePrice = 100 * durationHours;

            // Discounts
            if (durationHours >= 8) { durationDiscountPercent = 25; durationDiscount = Math.round(basePrice * 0.25); }
            else if (durationHours >= 6) { durationDiscountPercent = 20; durationDiscount = Math.round(basePrice * 0.20); }
            else if (durationHours >= 4) { durationDiscountPercent = 15; durationDiscount = Math.round(basePrice * 0.15); }

            basePrice -= durationDiscount;
        }

        let extrasTotal = 0;
        let breakdown = [];
        let selectedCount = 0;

        // Collect Extras
        const selectors = ['extra_photos', 'extra_location', 'extra_time', 'extra_express'];
        const pricingGroups = { 'extra_photos': pricing.photos, 'extra_location': pricing.location, 'extra_time': pricing.time, 'extra_express': pricing.express };

        selectors.forEach(name => {
            const select = extrasContainer.querySelector(`select[name="${name}"]`);
            if (select && select.value !== 'none' && select.value !== 'standard') {
                const price = pricingGroups[name][select.value] || 0;
                if (price > 0) {
                    breakdown.push({ name: select.options[select.selectedIndex].text, price });
                    extrasTotal += price;
                    selectedCount++;
                }
            }
        });

        if (basePrice === 0 && breakdown.length === 0) {
            priceSummary.style.display = 'none';
            updateHiddenInputs(0, 'Allgemeine Anfrage: 0€', '0€', 'Allgemeine Anfrage', 'Keine Extras', 'Kein Rabatt');
            return;
        }

        priceSummary.style.display = 'block';

        let breakdownHTML = '';
        if (basePrice > 0) {
            const serviceName = bookingValues.service.options[bookingValues.service.selectedIndex].text;
            breakdownHTML += `<div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-weight: 500;"><span>${serviceName}${selectedService === 'event' ? ` (${durationHours}h)` : ''}</span><span>${basePrice}€</span></div>`;
            if (durationDiscount > 0) breakdownHTML += `<div style="font-size: 0.85rem; color: var(--accent-color); margin-bottom: 0.5rem;">✨ ${durationDiscountPercent}% Rabatt für ${durationHours}h Buchung</div>`;
            if (breakdown.length > 0) breakdownHTML += `<div style="border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 0.5rem 0; padding-top: 0.5rem;"></div>`;
        }

        breakdownHTML += breakdown.map(item => `<div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;"><span>${item.name.replace(/\s*\(\+\d+€\)/, '')}</span><span>+${item.price}€</span></div>`).join('');
        priceBreakdown.innerHTML = breakdownHTML;

        // Bundle Discount
        let extrasDiscount = 0;
        let extrasDiscountPercentage = 0;
        if (selectedCount >= 3) { extrasDiscountPercentage = 20; extrasDiscount = Math.round(extrasTotal * 0.20); }
        else if (selectedCount === 2) { extrasDiscountPercentage = 15; extrasDiscount = Math.round(extrasTotal * 0.15); }

        if (extrasDiscount > 0) {
            priceDiscount.style.display = 'block';
            priceDiscount.innerHTML = `<div style="display: flex; justify-content: space-between;"><span>🎉 Bundle-Rabatt (${extrasDiscountPercentage}%)</span><span>-${extrasDiscount}€</span></div>`;
        } else {
            priceDiscount.style.display = 'none';
        }

        const finalTotal = basePrice + extrasTotal - extrasDiscount;
        const t = (typeof translations !== 'undefined' && translations[currentLanguage]) ? translations[currentLanguage] : {};
        priceTotal.innerHTML = `<div style="display: flex; justify-content: space-between;"><span>${t['booking.price_summary.total'] || 'Gesamt:'}</span><span>${finalTotal}€</span></div>`;

        // Populate inputs for EmailJS
        updateHiddenInputs(finalTotal,
            priceBreakdown.innerText, // Simple text representation
            basePrice > 0 ? `${selectedService}: ${basePrice}€` : 'Preis auf Anfrage',
            breakdown.map(i => `${i.name}: ${i.price}€`).join(', ') || 'Keine Extras',
            extrasDiscount > 0 ? `-${extrasDiscount}€` : 'Kein Rabatt'
        );
    }

    function updateHiddenInputs(total, details, base, extras, discount) {
        if (document.getElementById('total_price_input')) document.getElementById('total_price_input').value = `${total}€`;
        if (document.getElementById('price_details_input')) document.getElementById('price_details_input').value = details;
        if (document.getElementById('base_price_input')) document.getElementById('base_price_input').value = base;
        if (document.getElementById('extras_breakdown_input')) document.getElementById('extras_breakdown_input').value = extras;
        if (document.getElementById('discount_breakdown_input')) document.getElementById('discount_breakdown_input').value = discount;
    }

    // Initialize
    initCalendar();
});
