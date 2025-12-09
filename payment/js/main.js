// Shared logic for payment portal

// Load orders from API
async function loadOrders() {
    try {
        const response = await fetch('/payment/api/get-orders.php');
        const data = await response.json();

        if (data.orders) {
            return data.orders;
        }
        return [];
    } catch (error) {
        console.error('Error loading orders:', error);
        return [];
    }
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}

// Format date
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('de-DE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(date);
}

// Get order by ID
async function getOrder(orderId) {
    const orders = await loadOrders();
    return orders.find(o => o.orderId === orderId);
}

// Update order (via API)
async function updateOrder(orderId, updates) {
    try {
        const response = await fetch('/payment/api/update-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                orderId: orderId,
                updates: updates
            })
        });

        const result = await response.json();
        return result.success || false;
    } catch (error) {
        console.error('Error updating order:', error);
        return false;
    }
}

// Admin Auth
const AdminAuth = {
    login(password) {
        if (password === 'photon2024') {
            sessionStorage.setItem('photon_payment_admin_auth', 'true');
            return true;
        }
        return false;
    },

    logout() {
        sessionStorage.removeItem('photon_payment_admin_auth');
        window.location.href = 'orders.html';
    },

    requireAuth() {
        if (sessionStorage.getItem('photon_payment_admin_auth') !== 'true') {
            return false;
        }
        return true;
    }
};
