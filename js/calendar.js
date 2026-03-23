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
    // --- Dynamic Pricing & Extras Logic (Unified with Admin) ---
    let pricingData = null;

    async function fetchPricingData() {
        try {
            const res = await fetch('api/get-pricing.php?t=' + Date.now());
            pricingData = await res.json();
            // Trigger initial render of extras if package is already selected
            if (bookingValues.service && bookingValues.service.value) {
                renderExtras(bookingValues.service.value);
            }
        } catch (e) {
            console.error('Failed to fetch pricing for calendar', e);
        }
    }

    function renderExtras(service) {
        if (!bookingValues.extrasContainer || !pricingData) return;
        
        bookingValues.extrasContainer.innerHTML = '';
        const pkg = pricingData.packages.find(p => p.id === service);
        
        const t = (typeof translations !== 'undefined' && translations[currentLanguage]) ? translations[currentLanguage] : {};

        if (!pkg || !pkg.extras || pkg.extras.length === 0) {
            if (service !== 'other') {
                bookingValues.extrasContainer.innerHTML = `<p style="color: var(--text-secondary); font-style: italic;">${t['contact.form.extras.none'] || 'Keine speziellen Extras für diese Auswahl.'}</p>`;
            }
            updatePricing();
            return;
        }

        pkg.extras.forEach(ex => {
            let html = '';
            const label = ex.label[currentLanguage] || ex.label.de || ex.label;
            
            if (ex.type === 'select') {
                const optionsHtml = ex.options.map(opt => `
                    <option value="${opt.id}" data-price="${opt.price}">
                        ${opt.label[currentLanguage] || opt.label.de || opt.label}
                    </option>
                `).join('');
                
                html = `
                    <div class="form-group">
                        <label>${label}</label>
                        <select name="extra_${ex.id}" class="form-control extra-input" data-extra-id="${ex.id}" data-type="select">
                            ${optionsHtml}
                        </select>
                    </div>`;
            } else {
                html = `
                    <div class="form-group" style="display:flex; align-items:center; gap:0.75rem; background:rgba(255,255,255,0.03); padding:0.75rem; border-radius:8px; cursor:pointer;">
                        <input type="checkbox" name="extra_${ex.id}" class="extra-input" data-extra-id="${ex.id}" data-type="checkbox" data-price="${ex.price || 0}" style="width:20px; height:20px; cursor:pointer;">
                        <label style="margin-bottom:0; cursor:pointer; flex:1;">${label} (+${ex.price}€)</label>
                    </div>`;
            }
            bookingValues.extrasContainer.insertAdjacentHTML('beforeend', html);
        });

        // Add special Duration for Event if not handled by extras
        if (service === 'event') {
            const hasDuration = pkg.extras.some(e => e.id === 'event_duration');
            if (!hasDuration) {
                const durationHtml = `
                    <div class="form-group">
                        <label>${t['contact.form.extras.duration.label'] || 'Buchungsdauer'}</label>
                        <select name="event_duration" class="form-control extra-input">
                            <option value="2h">2 Stunden (Minimum)</option>
                            <option value="3h">3 Stunden</option>
                            <option value="4h">4 Stunden</option>
                            <option value="5h">5 Stunden</option>
                            <option value="6h">6 Stunden</option>
                            <option value="8h">8 Stunden</option>
                            <option value="open">Open End</option>
                        </select>
                        <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary); font-style: italic;">${t['contact.form.extras.duration.open_note'] || 'Hinweis: Open End basiert auf 8 Stunden.'}</small>
                    </div>`;
                bookingValues.extrasContainer.insertAdjacentHTML('afterbegin', durationHtml);
            }
        }

        // Attach listeners
        const inputs = bookingValues.extrasContainer.querySelectorAll('.extra-input');
        inputs.forEach(input => {
            input.addEventListener('change', updatePricing);
        });

        updatePricing();
    }

    function updatePricing() {
        if (!bookingValues.service || !pricingData) return;

        const priceSummary = document.getElementById('price-summary');
        const priceBreakdown = document.getElementById('price-breakdown');
        const priceDiscount = document.getElementById('price-discount');
        const priceTotal = document.getElementById('price-total');

        const selectedService = bookingValues.service.value;
        const pkg = pricingData.packages.find(p => p.id === selectedService);
        if (!pkg) {
            priceSummary.style.display = 'none';
            return;
        }

        let basePrice = pkg.price || 0;
        let durationHours = 1;
        
        // Event Logic
        const durationSelect = bookingValues.extrasContainer.querySelector('select[name="event_duration"]');
        if (selectedService === 'event' && durationSelect) {
            const durationValue = durationSelect.value;
            if (durationValue.endsWith('h')) durationHours = parseInt(durationValue);
            else if (durationValue === 'open') durationHours = 8;
            basePrice = pkg.price * durationHours;
        }

        let extrasTotal = 0;
        let extrasCount = 0;
        let breakdown = [];

        // Collect Extras
        const inputs = bookingValues.extrasContainer.querySelectorAll('.extra-input');
        inputs.forEach(input => {
            if (input.name === 'event_duration') return;

            const type = input.dataset.type;
            if (type === 'select') {
                const opt = input.options[input.selectedIndex];
                const price = parseFloat(opt.dataset.price) || 0;
                if (price > 0 || (opt.value !== 'none' && opt.value !== 'standard')) {
                    extrasTotal += price;
                    if (opt.value !== 'none' && opt.value !== 'standard') {
                        extrasCount++;
                        breakdown.push({ name: opt.text.replace(/\s*\(\+\d+€\)/, ''), price });
                    }
                }
            } else if (type === 'checkbox' && input.checked) {
                const price = parseFloat(input.dataset.price) || 0;
                extrasTotal += price;
                extrasCount++;
                breakdown.push({ name: input.nextElementSibling.innerText.replace(/\s*\(\+\d+€\)/, ''), price });
            }
        });

        let subtotal = basePrice + extrasTotal;
        let totalPercentDiscount = 0;
        let totalFlatDiscount = 0;

        // Apply Percent-First Discounts
        // 1. Bulk Discount
        if (pkg.bulkDiscounts && extrasCount > 0) {
            const bd = pkg.bulkDiscounts.find(d => d.count === extrasCount);
            if (bd) totalPercentDiscount += bd.discountPercent;
        }

        // 2. Global Discount (if percent)
        if (pricingData.globalDiscount && pricingData.globalDiscount.active) {
            if (pricingData.globalDiscount.type === 'percent') {
                totalPercentDiscount += pricingData.globalDiscount.value;
            } else {
                totalFlatDiscount += pricingData.globalDiscount.value;
            }
        }

        let finalPrice = subtotal * (1 - (totalPercentDiscount / 100));
        finalPrice -= totalFlatDiscount;
        finalPrice = Math.max(0, finalPrice);

        // Rendering Summary
        priceSummary.style.display = 'block';
        let breakdownHTML = '';
        const serviceName = bookingValues.service.options[bookingValues.service.selectedIndex].text;
        breakdownHTML += `<div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-weight: 500;"><span>${serviceName}${selectedService === 'event' ? ` (${durationHours}h)` : ''}</span><span>${basePrice}€</span></div>`;
        
        if (breakdown.length > 0) {
            breakdownHTML += `<div style="border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 0.5rem 0; padding-top: 0.5rem;"></div>`;
            breakdownHTML += breakdown.map(item => `<div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;"><span>${item.name}</span><span>+${item.price}€</span></div>`).join('');
        }
        priceBreakdown.innerHTML = breakdownHTML;

        // Discount Section
        if (totalPercentDiscount > 0 || totalFlatDiscount > 0) {
            priceDiscount.style.display = 'block';
            let discText = [];
            if (totalPercentDiscount > 0) discText.push(`${totalPercentDiscount}%`);
            if (totalFlatDiscount > 0) discText.push(`-${totalFlatDiscount}€`);
            priceDiscount.innerHTML = `<div style="display: flex; justify-content: space-between;"><span>🎉 Rabatt (${discText.join(' / ')})</span><span>-${Math.round(subtotal - finalPrice)}€</span></div>`;
        } else {
            priceDiscount.style.display = 'none';
        }

        const t = translations[currentLanguage] || {};
        priceTotal.innerHTML = `<div style="display: flex; justify-content: space-between;"><span>${t['booking.price_summary.total'] || 'Gesamt:'}</span><span>${Math.round(finalPrice)}€</span></div>`;

        updateHiddenInputs(Math.round(finalPrice), breakdownHTML.replace(/<[^>]*>/g, ' '), basePrice, breakdown.map(i => `${i.name}: ${i.price}€`).join(', '), (subtotal - finalPrice) > 0 ? `-${Math.round(subtotal - finalPrice)}€` : 'None');
    }

    function updateHiddenInputs(total, details, base, extras, discount) {
        if (document.getElementById('total_price_input')) document.getElementById('total_price_input').value = `${total}€`;
        if (document.getElementById('price_details_input')) document.getElementById('price_details_input').value = details;
        if (document.getElementById('base_price_input')) document.getElementById('base_price_input').value = `${base}€`;
        if (document.getElementById('extras_breakdown_input')) document.getElementById('extras_breakdown_input').value = extras;
        if (document.getElementById('discount_breakdown_input')) document.getElementById('discount_breakdown_input').value = discount;
    }

    // Initialize
    fetchPricingData();
    initCalendar();
 // Initialize
    initCalendar();
});
