// app.js - Beon Fidence API Integration Utility

const API_BASE = 'api';

/**
 * Standardized API Fetching mechanism
 */
async function apiRequest(endpoint, method = 'GET', data = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(`${API_BASE}/${endpoint}`, options);
        // Ensure successful parse even if response is empty
        const text = await response.text();
        const result = text ? JSON.parse(text) : {};
        
        if (!response.ok) {
            // Unauthenticated intercept
            if (response.status === 401 && !endpoint.includes('login.php')) {
                window.location.href = 'index.html';
                return;
            }
            throw new Error(result.error || 'API Request Failed');
        }
        return result;
    } catch (err) {
        console.error(`API Error on [${endpoint}]:`, err);
        throw err;
    }
}

// Global script execution
document.addEventListener('DOMContentLoaded', () => {
    // Connect Login Form if present
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = loginForm.querySelector('[name="username"]').value;
            const password = loginForm.querySelector('[name="password"]').value;
            
            try {
                const res = await apiRequest('login.php', 'POST', { username, password });
                if (res.success) {
                    window.location.href = 'dashboard.html';
                }
            } catch (err) {
                alert('Login Failed: ' + err.message);
            }
        });
    }

});
