// Calendar JavaScript - Variante B Design
document.addEventListener('DOMContentLoaded', () => {
    // Current date & minimum booking lead time (at least 1 full month in advance)
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Exactly 1 month from today (e.g. Nov 13 -> Dec 13)
    const minBookingDate = new Date(today.getFullYear(), today.getMonth() + 1, today.getDate());
    minBookingDate.setHours(0, 0, 0, 0);

    // Calendar starts on the month containing the earliest bookable date
    let currentDate = new Date(minBookingDate.getFullYear(), minBookingDate.getMonth(), 1);
    let selectedDate = formatDate(minBookingDate.getFullYear(), minBookingDate.getMonth(), minBookingDate.getDate());
    let selectedTime = "10:00";
    let currentLanguage = localStorage.getItem('preferredLang') || 'de';
    let selectedPackageId = 'event';
    let availabilityData = {};
    let pricingData = null;
    let selectedExtras = new Set();

    // Check package: prioritize URL parameter on arrival, otherwise sessionStorage
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('package')) {
        selectedPackageId = urlParams.get('package');
        sessionStorage.setItem('selectedPackage', selectedPackageId);
        // Clean URL so reloading won't revert to a stale query param
        window.history.replaceState({}, document.title, window.location.pathname);
    } else {
        const stored = sessionStorage.getItem('selectedPackage');
        if (stored) {
            selectedPackageId = stored;
        }
    }

    // DOM Elements
    const elements = {
        currentMonth: document.getElementById('currentMonth'),
        prevMonthBtn: document.getElementById('prevMonth'),
        nextMonthBtn: document.getElementById('nextMonth'),
        weekdayLabels: document.getElementById('weekdayLabels'),
        daysGrid: document.getElementById('daysGrid'),
        packageName: document.getElementById('packageName'),
        serviceSelect: document.getElementById('service'),
        durationField: document.getElementById('durationField'),
        durationSelect: document.getElementById('event_duration'),
        slotDateKicker: document.getElementById('slotDateKicker'),
        slotsPillGroup: document.getElementById('slotsPillGroup'),
        displaySelectedDate: document.getElementById('displaySelectedDate'),
        displaySelectedTime: document.getElementById('displaySelectedTime'),
        dateInput: document.getElementById('date'),
        timeInput: document.getElementById('time'),
        extrasContainer: document.getElementById('extras-container'),
        summaryPackageLabel: document.getElementById('summaryPackageLabel'),
        summaryPackagePrice: document.getElementById('summaryPackagePrice'),
        summaryExtrasRow: document.getElementById('summaryExtrasRow'),
        summaryExtrasPrice: document.getElementById('summaryExtrasPrice'),
        summaryDiscountRow: document.getElementById('summaryDiscountRow'),
        summaryDiscountLabel: document.getElementById('summaryDiscountLabel'),
        summaryDiscountPrice: document.getElementById('summaryDiscountPrice'),
        summaryTotalPrice: document.getElementById('summaryTotalPrice'),
        totalPriceInput: document.getElementById('total_price_input'),
        priceDetailsInput: document.getElementById('price_details_input'),
        basePriceInput: document.getElementById('base_price_input'),
        extrasBreakdownInput: document.getElementById('extras_breakdown_input'),
        discountBreakdownInput: document.getElementById('discount_breakdown_input')
    };

    const monthNamesDe = [
        "Januar", "Februar", "März", "April", "Mai", "Juni",
        "Juli", "August", "September", "Oktober", "November", "Dezember"
    ];
    const monthNamesEn = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    const weekdayNamesDe = ["So", "Mo", "Di", "Mi", "Do", "Fr", "Sa"];
    const weekdayNamesEn = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

    // Helper: Determine if package is hourly-based (event or unit containing hour/stunde)
    function isHourlyPackage(pkg) {
        if (!pkg) return selectedPackageId === 'event';
        if (pkg.id === 'event') return true;
        if (pkg.priceUnit) {
            const uDe = (pkg.priceUnit.de || '').toLowerCase();
            const uEn = (pkg.priceUnit.en || '').toLowerCase();
            if (uDe.includes('stunde') || uDe.includes('std') || uEn.includes('hour')) return true;
        }
        return false;
    }

    // Helper: Parse hours from duration dropdown
    function getSelectedDurationHours() {
        if (!elements.durationSelect) return 2;
        const val = elements.durationSelect.value || '2h';
        if (val.endsWith('h')) return parseInt(val, 10);
        if (val === 'open') return 8; // Open End calculates base as 8 hours
        return parseFloat(val) || 2;
    }

    // Render duration select options dynamically with discount badges
    function renderDurationOptions(pkg) {
        if (!elements.durationSelect) return;
        const currentVal = elements.durationSelect.value || '2h';

        const getDiscountForHours = (h) => {
            if (!pkg || !pkg.bulkDiscounts) return 0;
            const extraHours = Math.max(0, h - 2);
            const perks = extraHours >= 6 ? 3 : (extraHours >= 3 ? 2 : (extraHours >= 1 ? 1 : 0));
            const tier = pkg.bulkDiscounts.filter(d => d.count <= perks).sort((a,b) => b.count - a.count)[0];
            return tier ? tier.discountPercent : 0;
        };

        const durationDefs = [
            { value: '2h', hours: 2, labelDe: '2 Stunden (Mindestdauer)', labelEn: '2 Hours (Minimum)' },
            { value: '3h', hours: 3, labelDe: '3 Stunden', labelEn: '3 Hours' },
            { value: '4h', hours: 4, labelDe: '4 Stunden', labelEn: '4 Hours' },
            { value: '5h', hours: 5, labelDe: '5 Stunden', labelEn: '5 Hours' },
            { value: '6h', hours: 6, labelDe: '6 Stunden', labelEn: '6 Hours' },
            { value: '8h', hours: 8, labelDe: '8 Stunden', labelEn: '8 Hours' },
            { value: '10h', hours: 10, labelDe: '10 Stunden', labelEn: '10 Hours' },
            { value: 'open', hours: 8, labelDe: 'Open End (ab 8 Stunden)', labelEn: 'Open End (from 8 Hours)' }
        ];

        elements.durationSelect.innerHTML = durationDefs.map(d => {
            const discountPct = getDiscountForHours(d.hours);
            const tagDe = discountPct > 0 ? ` (-${discountPct}% Rabatt)` : '';
            const tagEn = discountPct > 0 ? ` (-${discountPct}% discount)` : '';
            const text = currentLanguage === 'en' ? `${d.labelEn}${tagEn}` : `${d.labelDe}${tagDe}`;
            return `<option value="${d.value}" ${d.value === currentVal ? 'selected' : ''}>${text}</option>`;
        }).join('');
    }

    // Find the first available date on or after minBookingDate
    function getFirstAvailableDate() {
        let checkDate = new Date(minBookingDate);
        for (let i = 0; i < 90; i++) {
            const dStr = formatDate(checkDate.getFullYear(), checkDate.getMonth(), checkDate.getDate());
            if (availabilityData[dStr]?.status !== 'booked') {
                return dStr;
            }
            checkDate.setDate(checkDate.getDate() + 1);
        }
        return formatDate(minBookingDate.getFullYear(), minBookingDate.getMonth(), minBookingDate.getDate());
    }

    // Initialize
    async function init() {
        if (elements.serviceSelect) {
            elements.serviceSelect.value = selectedPackageId;
            elements.serviceSelect.addEventListener('change', (e) => {
                selectedPackageId = e.target.value;
                sessionStorage.setItem('selectedPackage', selectedPackageId);
                selectedExtras.clear();
                updatePackageDisplay();
                renderExtras();
                updatePricing();
            });
        }

        if (elements.durationSelect) {
            elements.durationSelect.addEventListener('change', () => {
                updatePricing();
            });
        }

        setupMonthNav();
        setupTimeSlots();
        await loadAvailabilityData();
        await loadPricingData();
        
        selectedDate = getFirstAvailableDate();
        renderCalendar();
        updatePackageDisplay();
        renderExtras();
        updateDateTimeDisplays();
        updatePricing();
    }

    // Fetch calendar availability
    async function loadAvailabilityData() {
        try {
            const res = await fetch('data/calendar.json');
            if (res.ok) {
                availabilityData = await res.json();
            }
        } catch (e) {
            console.warn('Using default availability calendar data', e);
        }
    }

    // Fetch pricing data
    async function loadPricingData() {
        try {
            const res = await fetch('api/get-pricing.php?t=' + Date.now());
            if (res.ok) {
                pricingData = await res.json();
            } else {
                const fallbackRes = await fetch('data/pricing.json');
                if (fallbackRes.ok) pricingData = await fallbackRes.json();
            }
        } catch (e) {
            console.warn('Using fallback pricing', e);
            pricingData = {
                packages: [
                    { id: "event", title: { de: "Event-Begleitung", en: "Event Coverage" }, price: 100, priceUnit: { de: "/Stunde", en: "/hour" } },
                    { id: "portrait", title: { de: "Porträt-Session", en: "Portrait Session" }, price: 125, priceUnit: { de: "", en: "" } },
                    { id: "pet", title: { de: "Tierfotografie", en: "Pet Photography" }, price: 125, priceUnit: { de: "", en: "" } }
                ]
            };
        }
    }

    // Format date string YYYY-MM-DD
    function formatDate(year, month, day) {
        const m = String(month + 1).padStart(2, '0');
        const d = String(day).padStart(2, '0');
        return `${year}-${m}-${d}`;
    }

    // Setup Month Nav
    function setupMonthNav() {
        if (elements.prevMonthBtn) {
            elements.prevMonthBtn.addEventListener('click', () => {
                const prevMonthDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
                const currentMonthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                if (prevMonthDate >= currentMonthStart) {
                    currentDate.setMonth(currentDate.getMonth() - 1);
                    renderCalendar();
                }
            });
        }
        if (elements.nextMonthBtn) {
            elements.nextMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                renderCalendar();
            });
        }
    }

    // Render Calendar
    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        // Prev month button state (don't navigate earlier than current month)
        if (elements.prevMonthBtn) {
            const prevMonthDate = new Date(year, month - 1, 1);
            const currentMonthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            if (prevMonthDate < currentMonthStart) {
                elements.prevMonthBtn.style.opacity = '0.3';
                elements.prevMonthBtn.style.cursor = 'not-allowed';
            } else {
                elements.prevMonthBtn.style.opacity = '1';
                elements.prevMonthBtn.style.cursor = 'pointer';
            }
        }

        // Header
        const monthList = currentLanguage === 'en' ? monthNamesEn : monthNamesDe;
        if (elements.currentMonth) {
            elements.currentMonth.textContent = `${monthList[month]} ${year}`;
        }

        if (!elements.daysGrid) return;
        elements.daysGrid.innerHTML = '';

        // Month start & length (Monday = 0)
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        let startingDay = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;

        // Empty cells before start
        for (let i = 0; i < startingDay; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'day-cell empty';
            elements.daysGrid.appendChild(emptyCell);
        }

        // Days
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = formatDate(year, month, day);
            const cellDate = new Date(year, month, day);
            cellDate.setHours(0, 0, 0, 0);

            const cell = document.createElement('div');
            cell.className = 'day-cell';
            cell.textContent = day;

            const isLessThanMonthAhead = cellDate < minBookingDate;
            const isBooked = availabilityData[dateStr]?.status === 'booked';

            if (isLessThanMonthAhead) {
                cell.classList.add('unavailable', 'disabled');
                cell.title = currentLanguage === 'en'
                    ? 'Bookings must be made at least 1 month in advance'
                    : 'Buchungen müssen mindestens 1 Monat im Voraus erfolgen';
            } else if (isBooked) {
                cell.classList.add('booked');
                cell.title = currentLanguage === 'en' ? 'Already booked' : 'Bereits gebucht';
            } else {
                cell.classList.add('available');
                cell.addEventListener('click', () => {
                    selectedDate = dateStr;
                    document.querySelectorAll('.day-cell.selected').forEach(c => c.classList.remove('selected'));
                    cell.classList.add('selected');
                    updateDateTimeDisplays();
                });
            }

            if (selectedDate === dateStr && !isLessThanMonthAhead && !isBooked) {
                cell.classList.add('selected');
            }

            elements.daysGrid.appendChild(cell);
        }
    }

    // Setup Time Slot Buttons (no brackets)
    function setupTimeSlots() {
        if (!elements.slotsPillGroup) return;
        const pills = elements.slotsPillGroup.querySelectorAll('.slot-pill');
        pills.forEach(pill => {
            pill.addEventListener('click', () => {
                pills.forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                selectedTime = pill.dataset.time || pill.textContent.replace(/[\[\]]/g, '').trim();
                updateDateTimeDisplays();
            });
        });
    }

    // Update Date & Time Text Displays
    function updateDateTimeDisplays() {
        if (!selectedDate) return;
        const [y, m, d] = selectedDate.split('-').map(Number);
        const dt = new Date(y, m - 1, d);
        const dayOfWeek = dt.getDay();
        const monthName = (currentLanguage === 'en' ? monthNamesEn : monthNamesDe)[m - 1];
        const dayName = (currentLanguage === 'en' ? weekdayNamesEn : weekdayNamesDe)[dayOfWeek];

        // Format for left slot kicker e.g. "SAMSTAG, 21. NOVEMBER"
        const fullDayName = dt.toLocaleDateString(currentLanguage === 'en' ? 'en-US' : 'de-DE', { weekday: 'long' });
        if (elements.slotDateKicker) {
            elements.slotDateKicker.textContent = `${fullDayName.toUpperCase()}, ${d}. ${monthName.toUpperCase()}`;
        }

        // Format for right column display: "Sa, 21. November 2026"
        if (elements.displaySelectedDate) {
            elements.displaySelectedDate.textContent = `${dayName}, ${d}. ${monthName} ${y}`;
        }
        if (elements.displaySelectedTime) {
            elements.displaySelectedTime.textContent = `${selectedTime} Uhr`;
        }

        if (elements.dateInput) elements.dateInput.value = selectedDate;
        if (elements.timeInput) elements.timeInput.value = selectedTime;
    }

    // Update Package Name & Duration visibility
    function updatePackageDisplay() {
        let title = "Event-Begleitung";
        let pkg = null;
        if (pricingData && pricingData.packages) {
            pkg = pricingData.packages.find(p => p.id === selectedPackageId);
            if (pkg && pkg.title) {
                title = pkg.title[currentLanguage] || pkg.title.de || pkg.title;
            }
        } else {
            if (selectedPackageId === 'portrait') title = "Porträt-Session";
            if (selectedPackageId === 'pet') title = "Tierfotografie";
            if (selectedPackageId === 'other') title = "Sonstiges / Individuell";
        }

        // Show duration field for hourly packages (event)
        const isHourly = isHourlyPackage(pkg);
        if (elements.durationField) {
            elements.durationField.style.display = isHourly ? 'block' : 'none';
            if (isHourly) {
                renderDurationOptions(pkg);
            }
        }

        if (elements.packageName) elements.packageName.textContent = title;
        if (elements.serviceSelect) elements.serviceSelect.value = selectedPackageId;
    }

    // Render Extras in Editorial Style
    function renderExtras() {
        if (!elements.extrasContainer) return;
        elements.extrasContainer.innerHTML = '';

        if (!pricingData || !pricingData.packages) return;
        const pkg = pricingData.packages.find(p => p.id === selectedPackageId);
        if (!pkg || !pkg.extras || pkg.extras.length === 0) {
            elements.extrasContainer.innerHTML = `<p style="font-size: 13px; color: var(--text-secondary); padding: 0.5rem 0;">Keine optionalen Extras für dieses Paket verfügbar.</p>`;
            return;
        }

        pkg.extras.forEach(extra => {
            const label = extra.label[currentLanguage] || extra.label.de || extra.label;
            let price = extra.price || 0;

            if (extra.type === 'select' && extra.options) {
                const paidOpt = extra.options.find(o => o.price > 0) || extra.options[1] || extra.options[0];
                if (paidOpt) {
                    price = paidOpt.price;
                }
            }

            const row = document.createElement('div');
            row.className = 'extra-row-item';
            if (selectedExtras.has(extra.id)) {
                row.classList.add('selected');
            }
            row.innerHTML = `
                <span>${label}</span>
                <span class="extra-price-tag">+ ${price} €</span>
            `;

            row.addEventListener('click', () => {
                if (selectedExtras.has(extra.id)) {
                    selectedExtras.delete(extra.id);
                    row.classList.remove('selected');
                } else {
                    selectedExtras.add(extra.id);
                    row.classList.add('selected');
                }
                updatePricing();
            });

            elements.extrasContainer.appendChild(row);
        });
    }

    // Calculate & Update Price Summary Card with Hourly Scaling & Session Discounts
    function updatePricing() {
        if (!pricingData || !pricingData.packages) return;
        const pkg = pricingData.packages.find(p => p.id === selectedPackageId);
        if (!pkg) {
            if (selectedPackageId === 'other') {
                if (elements.summaryPackageLabel) elements.summaryPackageLabel.textContent = "Individuelles Angebot";
                if (elements.summaryPackagePrice) elements.summaryPackagePrice.textContent = "Auf Anfrage";
                if (elements.summaryExtrasPrice) elements.summaryExtrasPrice.textContent = "0 €";
                if (elements.summaryDiscountRow) elements.summaryDiscountRow.style.display = "none";
                if (elements.summaryTotalPrice) elements.summaryTotalPrice.textContent = "Auf Anfrage";
                if (elements.totalPriceInput) elements.totalPriceInput.value = "Auf Anfrage";
                if (elements.basePriceInput) elements.basePriceInput.value = "0€";
            }
            return;
        }

        const isHourly = isHourlyPackage(pkg);
        const unitRate = pkg.price || (isHourly ? 50 : 100);
        let hours = 1;
        let basePrice = unitRate;

        if (isHourly) {
            hours = getSelectedDurationHours();
            basePrice = unitRate * hours;
        }

        let extrasTotal = 0;
        let extrasCount = 0;
        let breakdown = [];

        if (pkg.extras) {
            pkg.extras.forEach(extra => {
                if (selectedExtras.has(extra.id)) {
                    let p = extra.price || 0;
                    if (extra.type === 'select' && extra.options) {
                        const paidOpt = extra.options.find(o => o.price > 0) || extra.options[1];
                        if (paidOpt) p = paidOpt.price;
                    }
                    extrasTotal += p;
                    extrasCount++;
                    const label = extra.label[currentLanguage] || extra.label.de || extra.label;
                    breakdown.push(`${label}: +${p}€`);
                }
            });
        }

        // Count extra hours towards bulk discounts as perks
        let totalPerks = extrasCount;
        if (isHourly) {
            const extraHours = Math.max(0, hours - 2);
            const hourPerks = extraHours >= 6 ? 3 : (extraHours >= 3 ? 2 : (extraHours >= 1 ? 1 : 0));
            totalPerks += hourPerks;
        }

        let discountPercent = 0;
        if (pkg.bulkDiscounts && totalPerks > 0) {
            const applicable = pkg.bulkDiscounts
                .filter(d => d.count <= totalPerks)
                .sort((a, b) => b.count - a.count);
            if (applicable.length > 0) {
                discountPercent = parseFloat(applicable[0].discountPercent) || 0;
            }
        }

        const subtotal = basePrice + extrasTotal;
        const discountAmount = discountPercent > 0 ? Math.round(subtotal * (discountPercent / 100)) : 0;
        const total = subtotal - discountAmount;
        const title = pkg.title[currentLanguage] || pkg.title.de || pkg.title;

        if (elements.summaryPackageLabel) {
            if (isHourly) {
                const durText = elements.durationSelect
                    ? elements.durationSelect.options[elements.durationSelect.selectedIndex]?.text.split('(')[0].trim()
                    : `${hours} Std.`;
                elements.summaryPackageLabel.textContent = `${title} (${durText || `${hours} Std.`} à ${unitRate} €)`;
            } else {
                elements.summaryPackageLabel.textContent = title;
            }
        }

        if (elements.summaryPackagePrice) {
            elements.summaryPackagePrice.textContent = `${basePrice} €`;
        }
        if (elements.summaryExtrasPrice) {
            elements.summaryExtrasPrice.textContent = `${extrasTotal} €`;
        }

        // Discount display
        if (elements.summaryDiscountRow) {
            if (discountAmount > 0) {
                elements.summaryDiscountRow.style.display = 'flex';
                const discountTitle = isHourly && extrasCount === 0
                    ? (currentLanguage === 'en' ? `Session Discount (-${discountPercent}%)` : `Laufzeit-Rabatt (-${discountPercent}%)`)
                    : (currentLanguage === 'en' ? `Bundle Discount (-${discountPercent}%)` : `Paket-Vorteil (-${discountPercent}%)`);
                if (elements.summaryDiscountLabel) elements.summaryDiscountLabel.textContent = discountTitle;
                if (elements.summaryDiscountPrice) elements.summaryDiscountPrice.textContent = `- ${discountAmount} €`;
            } else {
                elements.summaryDiscountRow.style.display = 'none';
            }
        }

        if (elements.summaryTotalPrice) {
            elements.summaryTotalPrice.textContent = `${total} €`;
        }

        // Hidden inputs
        if (elements.totalPriceInput) elements.totalPriceInput.value = `${total}€`;
        if (elements.basePriceInput) elements.basePriceInput.value = `${basePrice}€`;
        if (elements.extrasBreakdownInput) elements.extrasBreakdownInput.value = breakdown.join(', ');
        if (elements.discountBreakdownInput) elements.discountBreakdownInput.value = discountAmount > 0 ? `Rabatt: -${discountAmount}€ (${discountPercent}%)` : '';
        if (elements.priceDetailsInput) {
            elements.priceDetailsInput.value = isHourly
                ? `Basis: ${basePrice}€ (${hours} Std. à ${unitRate}€), Extras: ${extrasTotal}€${discountAmount > 0 ? `, Rabatt: -${discountAmount}€ (${discountPercent}%)` : ''}`
                : `Basis: ${basePrice}€, Extras: ${extrasTotal}€${discountAmount > 0 ? `, Rabatt: -${discountAmount}€ (${discountPercent}%)` : ''}`;
        }
    }

    // Re-render when language changes
    window.addEventListener('languageChanged', (e) => {
        currentLanguage = e.detail.language;
        renderCalendar();
        updatePackageDisplay();
        renderExtras();
        updateDateTimeDisplays();
        updatePricing();
    });

    init();
});
