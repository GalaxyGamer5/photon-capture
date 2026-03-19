// Helper function to create payment order
async function createPaymentOrder(orderData) {
    try {
        console.log('Creating payment order:', orderData);
        // Updated path from portal to gallery
        const response = await fetch('gallery/api/create-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderData)
        });

        const result = await response.json();
        if (!result.success) throw new Error(result.error || 'Failed to create order');

        return result;
    } catch (error) {
        console.error('Error creating payment order:', error);
        return null; // Don't block if billing fails
    }
}

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

            // --- 2. Create Order in Backend FIRST (Critical Path) ---
            if (totalAmount > 0 && service !== 'other') {
                try {
                    const result = await createPaymentOrder({
                        orderId, name, email, service: serviceName, date, totalAmount, extras: extrasList, duration
                    });

                    if (result && result.success) {
                        const paymentUrl = result.paymentUrl || `/payment/order.html?id=${orderId}`;
                        window.location.href = paymentUrl;
                        return;
                    } else {
                        throw new Error(result ? result.error : 'Unknown backend error');
                    }
                } catch (error) {
                    console.error('Backend order creation failed:', error);
                    alert(currentLang === 'en' ? 'Error creating booking. Please try again.' : 'Fehler bei der Buchung. Bitte versuche es erneut.');
                    submitBtn.innerText = originalBtnText;
                    submitBtn.disabled = false;
                    return;
                }
            } else {
                // --- Fallback for "Other" inquiries (No Payment) ---
                try {
                    const fullMessage = `Datum: ${date}\nZeit: ${time}\nExtras: ${extrasString}\n\nNachricht: ${message}`;
                    const res = await fetch('api/save-inquiry.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name, email, service: serviceName, message: fullMessage })
                    });
                    
                    if (res.ok) {
                        const successMsg = currentLang === 'en'
                            ? `Thank you! Your request has been sent successfully.`
                            : `Vielen Dank! Deine Anfrage wurde erfolgreich gesendet.`;
                        alert(successMsg);
                        bookingForm.reset();
                    } else {
                        throw new Error('Server error');
                    }
                } catch(err) {
                    console.error('Failed to save inquiry to DB:', err);
                    alert('Fehler beim Senden.');
                } finally {
                    submitBtn.innerText = originalBtnText;
                    submitBtn.disabled = false;
                }
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
                        ? 'Thank you! Your message has been sent directly to our inbox.'
                        : 'Vielen Dank! Deine Nachricht wurde direkt in unser Postfach gesendet.';
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
