document.addEventListener('DOMContentLoaded', () => {
    const bookingForm = document.getElementById('booking-form');
    const generateOrderId = () => {
        const now = new Date();
        const dateStr = now.toISOString().slice(0, 10).replace(/-/g, '');
        const randomStr = Math.random().toString(36).substring(2, 6).toUpperCase();
        return `ORD-${dateStr}-${randomStr}`;
    };

    if (bookingForm) {
        bookingForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = bookingForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerText;
            submitBtn.innerText = 'Sende...';
            submitBtn.disabled = true;

            const service = document.getElementById('service').value;
            const date = document.getElementById('date').value;
            const time = document.getElementById('time').value;
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const message = document.getElementById('message').value;

            const orderId = generateOrderId();
            const serviceSelect = document.getElementById('service');
            let serviceName = serviceSelect.options[serviceSelect.selectedIndex].text;
            if (service === 'other') serviceName = "Sonstiges / Allgemeine Frage";

            // Extras
            let extrasList = [];
            const extrasContainer = document.getElementById('extras-container');
            const getSelectedText = (selectName) => {
                const select = extrasContainer.querySelector(`select[name="${selectName}"]`);
                if (select && select.value !== 'none' && select.value !== 'no' && select.value !== 'standard') {
                    return select.options[select.selectedIndex].text;
                }
                return null;
            };

            const extraKeys = ['extra_photos', 'extra_location', 'extra_time', 'extra_album', 'extra_video', 'event_duration', 'extra_express'];
            extraKeys.forEach(key => {
                const txt = getSelectedText(key);
                if (txt) extrasList.push(txt);
            });
            const extrasString = extrasList.length > 0 ? extrasList.join(', ') : 'Keine Extras';

            const totalPriceStr = document.getElementById('total_price_input').value || '0€';
            const totalAmount = parseFloat(totalPriceStr.replace('€', '').replace(',', '.')) || 0;

            let duration = 0;
            const durationSelect = document.querySelector('select[name="event_duration"]');
            if (durationSelect && durationSelect.value) {
                const dv = durationSelect.value;
                if (dv.endsWith('h')) duration = parseInt(dv);
                else if (dv === 'open') duration = 8;
            }

            const currentLang = localStorage.getItem('preferredLang') || 'de';

            // Combine all booking details into the message payload
            const fullMessage = `Gewünschtes Paket: ${serviceName}
Datum: ${date}
Uhrzeit: ${time}
Preis: ${totalPriceStr}
Extras: ${extrasString}

Nachricht vom Kunden:
${message}`;

            try {
                const res = await fetch('api/save-inquiry.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        name, 
                        email, 
                        service: serviceName, 
                        message: fullMessage,
                        bookingDate: date // Added for automated calendar reservation
                    })
                });

                if (res.ok) {
                    const successMsg = currentLang === 'en'
                        ? `Thank you! Your booking request has been sent successfully. I will get back to you shortly via email.`
                        : `Vielen Dank! Deine Buchungsanfrage wurde erfolgreich gesendet. Ich werde mich zeitnah per Mail bei dir melden.`;
                    alert(successMsg);
                    bookingForm.reset();
                    // If there's a back button to reset the UI, trigger it
                    const backBtn = document.getElementById('backToCalendarBtn');
                    if (backBtn) backBtn.click();
                } else {
                    throw new Error('Server error');
                }
            } catch (err) {
                console.error('Failed to save inquiry to DB:', err);
                const errorMsg = currentLang === 'en' ? 'Error creating booking. Please try again.' : 'Fehler bei der Buchung. Bitte versuche es erneut.';
                alert(errorMsg);
            } finally {
                submitBtn.innerText = originalBtnText;
                submitBtn.disabled = false;
            }
        });
    }

    // --- Contact Form Handler (Index Page) ---
    const contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = contactForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerText;
            submitBtn.innerText = 'Sende...';
            submitBtn.disabled = true;

            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const message = document.getElementById('message').value;

            const serviceSelect = document.getElementById('service');
            let serviceName = serviceSelect.options[serviceSelect.selectedIndex].text;
            if (serviceSelect.value === 'other') serviceName = "Sonstiges / Allgemeine Frage";

            try {
                const res = await fetch('api/save-inquiry.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, email, service: serviceName, message })
                });

                const currentLang = localStorage.getItem('preferredLang') || 'de';
                
                if (res.ok) {
                    const successMsg = currentLang === 'en'
                        ? 'Thank you! Your message has been sent successfully. I will get back to you shortly via email.'
                        : 'Vielen Dank! Deine Nachricht wurde erfolgreich gesendet. Ich werde mich zeitnah per Mail bei dir melden.';
                    alert(successMsg);
                    contactForm.reset();
                } else {
                    throw new Error('Server returned an error');
                }
            } catch(err) {
                console.error('FAILED...', err);
                const currentLang = localStorage.getItem('preferredLang') || 'de';
                const errorMsg = currentLang === 'en' ? 'Error sending message.' : 'Fehler beim Senden.';
                alert(errorMsg);
            } finally {
                submitBtn.innerText = originalBtnText;
                submitBtn.disabled = false;
            }
        });
    }
});
