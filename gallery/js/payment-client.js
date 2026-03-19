/**
 * Payment Client Logic
 * Handles fetching order data and formatting for display
 */

// Fetch order by ID
async function getOrder(orderId) {
    try {
        const response = await fetch(`api/get-order.php?id=${orderId}`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const order = await response.json();

        if (order.error) {
            throw new Error(order.error);
        }

        return order;
    } catch (error) {
        console.error('Failed to fetch order:', error);
        throw error;
    }
}

// Format currency (EUR)
function formatCurrency(amount) {
    return new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}

// Format date
function formatDate(dateString) {
    if (!dateString) return 'N/A';

    const date = new Date(dateString);
    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(date);
}
