const API = 'http://localhost/currency-ledger/backend/api';

async function request(path, options = {}) {
    const response = await fetch(`${API}/${path}`, {
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            ...(options.headers || {})
        },
        ...options
    });

    const data = await response.json();

    if (!response.ok || data.success === false) {
        throw new Error(data.message || 'Request failed.');
    }

    return data;
}

export const api = {
    login: (username, password) =>
        request('auth/login.php', {
            method: 'POST',
            body: JSON.stringify({ username, password })
        }),

    logout: () =>
        request('auth/logout.php', {
            method: 'POST'
        }),

    me: () =>
        request('auth/me.php'),

    users: () =>
        request('users/list.php'),

    createUser: (data) =>
        request('users/create.php', {
            method: 'POST',
            body: JSON.stringify(data)
        }),

    deactivateUser: (userId) =>
        request('users/deactivate.php', {
            method: 'POST',
            body: JSON.stringify({
                user_id: userId
            })
        }),

    dashboard: () =>
        request('dashboard.php'),

    balances: () =>
        request('balances.php'),

    transactions: () =>
        request('transactions.php'),

    openingBalance: (currency, amount) =>
        request('opening-balance.php', {
            method: 'POST',
            body: JSON.stringify({
                currency,
                amount
            })
        }),

    buy: (usdtAmount, rate, requestId) =>
        request('buy.php', {
            method: 'POST',
            headers: {
                'X-Idempotency-Key': requestId
            },
            body: JSON.stringify({
                usdt_amount: usdtAmount,
                rate,
                request_id: requestId
            })
        }),

    sell: (usdtAmount, rate, requestId) =>
        request('sell.php', {
            method: 'POST',
            headers: {
                'X-Idempotency-Key': requestId
            },
            body: JSON.stringify({
                usdt_amount: usdtAmount,
                rate,
                request_id: requestId
            })
        })

};
