(function () {
    // Initialize EmailJS
    // IMPORTANT: Replace 'YOUR_PUBLIC_KEY' with your actual EmailJS public key
    // It usually starts with "user_" or is a random string like "aB3cD..."
    emailjs.init("BLgjskyJFItoCIGST");
})();

// Helper function to create payment order
async function createPaymentOrder(orderData) {
    try {
        console.log('Creating payment order:', orderData);

        const response = await fetch('/payment/api/create-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(orderData)
        });

        console.log('API Response status:', response.status);

        const result = await response.json();
        console.log('API Response data:', result);

        if (!result.success) {
            throw new Error(result.error || 'Failed to create order');
        }

        console.log('Payment order created successfully:', result);
        return result;
    } catch (error) {
        console.error('Error creating payment order:', error);
        // Don't throw - we don't want to block booking if payment order fails
        return null;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const bookingForm = document.getElementById('booking-form');

    // Helper to generate Order ID
    const generateOrderId = () => {
        const now = new Date();
        const dateStr = now.toISOString().slice(0, 10).replace(/-/g, ''); // YYYYMMDD
        const randomStr = Math.random().toString(36).substring(2, 6).toUpperCase(); // 4 random chars
        return `ORD-${dateStr}-${randomStr}`;
    };

    if (bookingForm) {
        bookingForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const submitBtn = bookingForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerText;
            submitBtn.innerText = 'Sende...';
            submitBtn.disabled = true;

            // 1. Gather Data
            const service = document.getElementById('service').value;
            const date = document.getElementById('date').value;
            const time = document.getElementById('time').value;
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const message = document.getElementById('message').value;

            // Generate Order ID
            const orderId = generateOrderId();

            // Get selected service name text
            const serviceSelect = document.getElementById('service');
            let serviceName = serviceSelect.options[serviceSelect.selectedIndex].text;

            // Fix for "Other" showing wrong text if translation hasn't updated or if it's cached
            if (service === 'other') {
                serviceName = "Sonstiges / Allgemeine Frage";
            }

            // Gather Extras
            let extrasList = [];
            const extrasContainer = document.getElementById('extras-container');

            // Helper to get text from select inputs
            const getSelectedText = (selectName) => {
                const select = extrasContainer.querySelector(`select[name="${selectName}"]`);
                if (select && select.value !== 'none' && select.value !== 'no' && select.value !== 'standard') {
                    return select.options[select.selectedIndex].text;
                }
                return null;
            };

            // Check for each possible extra
            const extraPhotos = getSelectedText('extra_photos');
            if (extraPhotos) extrasList.push(extraPhotos);

            const extraLocation = getSelectedText('extra_location');
            if (extraLocation) extrasList.push(extraLocation);

            const extraTime = getSelectedText('extra_time');
            if (extraTime) extrasList.push(extraTime);

            const extraAlbum = getSelectedText('extra_album');
            if (extraAlbum) extrasList.push(extraAlbum);

            const extraVideo = getSelectedText('extra_video');
            if (extraVideo) extrasList.push(extraVideo);

            const eventDuration = getSelectedText('event_duration');
            if (eventDuration) extrasList.push(eventDuration);

            const extraExpress = getSelectedText('extra_express');
            if (extraExpress) extrasList.push(extraExpress);

            const extrasString = extrasList.length > 0 ? extrasList.join(', ') : 'Keine Extras';

            // 2. Prepare Template Parameters
            // These keys must match the variables in your EmailJS template
            let templateParams = {
                order_id: orderId,
                submission_date: new Date().toLocaleString('de-DE'), // Format: DD.MM.YYYY, HH:MM
                service_name: serviceName,
                date: service !== 'other' ? date : 'N/A',
                time: service !== 'other' ? time : 'N/A',
                extras: extrasString,
                user_name: name,
                user_email: email,
                message: message,
                total_price: document.getElementById('total_price_input').value,
                price_details: document.getElementById('price_details_input').value,
                base_price_component: document.getElementById('base_price_input').value,
                extras_component: document.getElementById('extras_breakdown_input').value,
                discount_component: document.getElementById('discount_breakdown_input').value
            };

            // Failsafe: If service is "other", force the correct values
            // This prevents stale data if updatePricing() didn't run correctly
            if (service === 'other') {
                templateParams.total_price = "0€";
                templateParams.base_price_component = "Allgemeine Anfrage (0€)";
                templateParams.extras_component = "Keine Extras";
                templateParams.discount_component = "Kein Rabatt";
                templateParams.price_details = "Allgemeine Anfrage: 0€";
            }

            // 3. Send Email
            // Determine language and template ID
            const currentLang = localStorage.getItem('preferredLang') || 'de';

            // IMPORTANT: Replace 'YOUR_ENGLISH_TEMPLATE_ID' with your actual English Template ID
            const templateId = currentLang === 'en' ? 'template_kfk6b3h' : 'template_5ajrphd';

            // Debugging: Log params to console
            console.log(`Sending EmailJS params (${currentLang} - ${templateId}):`, templateParams);

            // The Service ID you found (service_mtw49rh) goes here as the first argument!
            emailjs.send("service_mtw49rh", templateId, templateParams)
                .then(function () {
                    // Email sent successfully, now create payment order
                    const totalPriceStr = document.getElementById('total_price_input').value || '0€';
                    const totalAmount = parseFloat(totalPriceStr.replace('€', '').replace(',', '.')) || 0;

                    // Parse duration from extras (for event bookings)
                    let duration = 0;
                    const durationSelect = document.querySelector('select[name="event_duration"]');
                    if (durationSelect && durationSelect.value) {
                        const durationValue = durationSelect.value;
                        if (durationValue.endsWith('h')) {
                            duration = parseInt(durationValue);
                        } else if (durationValue === 'open') {
                            duration = 8;
                        }
                    }

                    // Create payment order if there's a price
                    if (totalAmount > 0 && service !== 'other') {
                        createPaymentOrder({
                            orderId: orderId,
                            name: name,
                            email: email,
                            service: serviceName,
                            date: date,
                            totalAmount: totalAmount,
                            extras: extrasList,
                            duration: duration
                        }).then((result) => {
                            const successMsg = currentLang === 'en'
                                ? `Thank you! Your request has been sent successfully.\\nYour Order ID: ${orderId}\\n\\nYou can pay your deposit at: /payment/`
                                : `Vielen Dank! Deine Anfrage wurde erfolgreich gesendet.\\nDeine Bestellnummer: ${orderId}\\n\\nZahle deine Anzahlung unter: /payment/`;

                            alert(successMsg);
                            bookingForm.reset();
                            submitBtn.innerText = originalBtnText;
                            submitBtn.disabled = false;

                            // Log result
                            if (result) {
                                console.log('Payment order created:', result.orderId);
                            } else {
                                console.warn('Payment order creation failed - check console for details');
                            }
                        });
                    } else {
                        // No payment needed or general inquiry
                        const successMsg = currentLang === 'en'
                            ? `Thank you! Your request has been sent successfully.\\nYour Order ID: ${orderId}`
                            : `Vielen Dank! Deine Anfrage wurde erfolgreich gesendet.\\nDeine Bestellnummer: ${orderId}`;

                        alert(successMsg);
                        bookingForm.reset();
                        submitBtn.innerText = originalBtnText;
                        submitBtn.disabled = false;
                    }
                }, function (error) {
                    console.error('FAILED...', error);
                    // Show the exact error message to help debugging
                    const errorMsg = currentLang === 'en'
                        ? 'Error: ' + JSON.stringify(error)
                        : 'Fehler: ' + JSON.stringify(error);

                    alert(errorMsg);
                    submitBtn.innerText = originalBtnText;
                    submitBtn.disabled = false;
                })
                ;
        });
    }

    // --- Contact Form Handler (Index Page) ---
    const contactForm = document.getElementById('contact-form');

    if (contactForm) {
        contactForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const submitBtn = contactForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerText;
            submitBtn.innerText = 'Sende...';
            submitBtn.disabled = true;

            // 1. Gather Data
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const message = document.getElementById('message').value;

            const serviceSelect = document.getElementById('service');
            let serviceName = serviceSelect.options[serviceSelect.selectedIndex].text;

            // Fix for "Other" text
            if (serviceSelect.value === 'other') {
                serviceName = "Sonstiges / Allgemeine Frage";
            }

            // 2. Prepare Template Parameters
            // Reusing booking templates, so we need to provide all expected fields
            const templateParams = {
                order_id: 'N/A',
                submission_date: new Date().toLocaleString('de-DE'),
                service_name: serviceName,
                date: 'N/A',
                time: 'N/A',
                extras: 'N/A',
                user_name: name,
                user_email: email,
                message: message,
                total_price: 'N/A',
                price_details: 'N/A',
                base_price_component: 'N/A',
                extras_component: 'N/A',
                discount_component: 'N/A'
            };

            // 3. Send Email
            const currentLang = localStorage.getItem('preferredLang') || 'de';

            // Reusing the same booking templates for contact form
            const templateId = currentLang === 'en' ? 'template_kfk6b3h' : 'template_5ajrphd';

            console.log(`Sending Contact Form params (${currentLang} - ${templateId}):`, templateParams);

            emailjs.send("service_mtw49rh", templateId, templateParams)
                .then(function () {
                    const successMsg = currentLang === 'en'
                        ? 'Thank you! Your message has been sent successfully.'
                        : 'Vielen Dank! Deine Nachricht wurde erfolgreich gesendet.';

                    alert(successMsg);
                    contactForm.reset();
                    submitBtn.innerText = originalBtnText;
                    submitBtn.disabled = false;
                }, function (error) {
                    console.error('FAILED...', error);
                    const errorMsg = currentLang === 'en'
                        ? 'Error: ' + JSON.stringify(error)
                        : 'Fehler: ' + JSON.stringify(error);

                    alert(errorMsg);
                    submitBtn.innerText = originalBtnText;
                    submitBtn.disabled = false;
                });
        });
    }
});
