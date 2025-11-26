// Calendar JavaScript - Dedicated calendar functionality
// Relies on main.js for translations and global functionality

document.addEventListener('DOMContentLoaded', () => {
    // Calendar State
    let currentDate = new Date();
    let selectedDate = null;
    let currentLanguage = localStorage.getItem('preferredLang') || 'de';
    let selectedPackage = null;

    // Get package from URL
    const urlParams = new URLSearchParams(window.location.search);
    selectedPackage = urlParams.get('package') || null;

    // Mock availability data (replace with API call later)
    const availabilityData = {
        "2025-11-15": { status: "booked" },
        "2025-11-18": { status: "limited" },
        "2025-11-20": { status: "reserved" },
        "2025-11-25": { status: "available" },
        "2025-11-26": { status: "available" },
        "2025-11-28": { status: "limited" },
        "2025-12-05": { status: "available" },
        "2025-12-10": { status: "limited" },
        "2025-12-15": { status: "booked" },
        "2025-12-20": { status: "available" }
    };

    // Initialize calendar
    function initCalendar() {
        renderCalendar();
        setupEventListeners();
        updatePackageDisplay();
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

        dateDisplay.textContent = formatDisplayDate(dateString);
        panel.classList.add('active');

        // Scroll to panel
        setTimeout(() => {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }

    // Hide booking panel
    function hideBookingPanel() {
        const panel = document.getElementById('bookingPanel');
        panel.classList.remove('active');
    }

    // Update package display
    function updatePackageDisplay() {
        const packageInfo = document.getElementById('selectedPackageInfo');
        const packageNameSpan = document.getElementById('packageName');
        const t = translations[currentLanguage];

        if (selectedPackage) {
            let packageName = selectedPackage;
            if (selectedPackage === 'portrait') packageName = t["services.portrait.title"];
            if (selectedPackage === 'event') packageName = t["services.event.title"];
            if (selectedPackage === 'pet') packageName = t["services.pet.title"];

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

        // Confirm button
        document.getElementById('confirmBtn').addEventListener('click', () => {
            if (selectedDate) {
                let url = `booking.html?date=${selectedDate}`;
                if (selectedPackage) {
                    url += `&package=${selectedPackage}`;
                }
                window.location.href = url;
            }
        });

        // Listen for language changes from main.js
        window.addEventListener('languageChanged', (e) => {
            currentLanguage = e.detail.language;
            renderCalendar();
            updatePackageDisplay();
        });
    }

    // Initialize
    initCalendar();
});
