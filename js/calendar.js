// Calendar JavaScript - Variante B Design
document.addEventListener('DOMContentLoaded', () => {
    // Current date state - default to November 2026 as in mockup
    let currentDate = new Date(2026, 10, 1); // Month 10 is November (0-indexed)
    let selectedDate = "2026-11-21";
    let selectedTime = "10:00";
    let currentLanguage = localStorage.getItem('preferredLang') || 'de';
    let selectedPackageId = 'event';
    let availabilityData = {};
    let pricingData = null;
    let selectedExtras = new Set();

    // Check URL parameters for package
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('package')) {
        selectedPackageId = urlParams.get('package');
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

    // Initialize
    async function init() {
        if (elements.serviceSelect) {
            elements.serviceSelect.value = selectedPackageId;
            elements.serviceSelect.addEventListener('change', (e) => {
                selectedPackageId = e.target.value;
                selectedExtras.clear();
                updatePackageDisplay();
                renderExtras();
                updatePricing();
            });
        }

        setupMonthNav();
        setupTimeSlots();
        await loadAvailabilityData();
        await loadPricingData();
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
                    { id: "event", title: { de: "Event-Begleitung", en: "Event Coverage" }, price: 100 },
                    { id: "portrait", title: { de: "Porträt-Session", en: "Portrait Session" }, price: 125 },
                    { id: "pet", title: { de: "Tierfotografie", en: "Pet Photography" }, price: 125 }
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
                currentDate.setMonth(currentDate.getMonth() - 1);
                renderCalendar();
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
            const cell = document.createElement('div');
            cell.className = 'day-cell';
            cell.textContent = day;

            // Check mock / real status
            const status = availabilityData[dateStr]?.status || 'available';
            if (status === 'booked') {
                cell.classList.add('booked');
            } else {
                cell.classList.add('available');
                cell.addEventListener('click', () => {
                    selectedDate = dateStr;
                    document.querySelectorAll('.day-cell.selected').forEach(c => c.classList.remove('selected'));
                    cell.classList.add('selected');
                    updateDateTimeDisplays();
                });
            }

            if (selectedDate === dateStr) {
                cell.classList.add('selected');
            }

            elements.daysGrid.appendChild(cell);
        }
    }

    // Setup Time Slot Buttons
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
            elements.displaySelectedTime.textContent = `[${selectedTime}] Uhr`;
        }

        if (elements.dateInput) elements.dateInput.value = selectedDate;
        if (elements.timeInput) elements.timeInput.value = selectedTime;
    }

    // Update Package Name
    function updatePackageDisplay() {
        let title = "Event-Begleitung";
        if (pricingData && pricingData.packages) {
            const pkg = pricingData.packages.find(p => p.id === selectedPackageId);
            if (pkg && pkg.title) {
                title = pkg.title[currentLanguage] || pkg.title.de || pkg.title;
            }
        } else {
            if (selectedPackageId === 'portrait') title = "Porträt-Session";
            if (selectedPackageId === 'pet') title = "Tierfotografie";
        }

        if (elements.packageName) elements.packageName.textContent = title;
        if (elements.summaryPackageLabel) elements.summaryPackageLabel.textContent = title;
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
                // If options exist, pick the first paid upgrade option as a sample or show row
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

    // Calculate & Update Price Summary Card
    function updatePricing() {
        if (!pricingData || !pricingData.packages) return;
        const pkg = pricingData.packages.find(p => p.id === selectedPackageId);
        if (!pkg) return;

        let basePrice = pkg.price || 100;
        let extrasTotal = 0;
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
                    const label = extra.label[currentLanguage] || extra.label.de || extra.label;
                    breakdown.push(`${label}: +${p}€`);
                }
            });
        }

        const total = basePrice + extrasTotal;

        if (elements.summaryPackagePrice) {
            elements.summaryPackagePrice.textContent = `${basePrice} €`;
        }
        if (elements.summaryExtrasPrice) {
            elements.summaryExtrasPrice.textContent = `${extrasTotal} €`;
        }
        if (elements.summaryTotalPrice) {
            elements.summaryTotalPrice.textContent = `${total} €`;
        }

        // Hidden inputs
        if (elements.totalPriceInput) elements.totalPriceInput.value = `${total}€`;
        if (elements.basePriceInput) elements.basePriceInput.value = `${basePrice}€`;
        if (elements.extrasBreakdownInput) elements.extrasBreakdownInput.value = breakdown.join(', ');
        if (elements.priceDetailsInput) elements.priceDetailsInput.value = `Basis: ${basePrice}€, Extras: ${extrasTotal}€`;
    }

    init();
});
