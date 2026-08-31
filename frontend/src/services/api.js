const API_BASE = import.meta.env.VITE_API_URL;

async function request(path, options = {}) {
  const response = await fetch(`${API}/${path}`, {
    credentials: "include",
    headers: {
      "Content-Type": "application/json",
      ...(options.headers || {}),
    },
    ...options,
  });

  const data = await response.json();

  if (!response.ok || data.success === false) {
    throw new Error(data.message || "Request failed.");
  }

  return data;
}

export const api = {
  // ─────────────────────────────────────────────
  // AUTH
  // ─────────────────────────────────────────────

  login: (username, password) =>
    request("auth/login.php", {
      method: "POST",
      body: JSON.stringify({
        username,
        password,
      }),
    }),

  logout: () =>
    request("auth/logout.php", {
      method: "POST",
    }),

  me: () => request("auth/me.php"),

  // ─────────────────────────────────────────────
  // USERS
  // ─────────────────────────────────────────────

  users: () => request("users/list.php"),

  createUser: (data) =>
    request("users/create.php", {
      method: "POST",
      body: JSON.stringify(data),
    }),

  deactivateUser: (userId) =>
    request("users/deactivate.php", {
      method: "POST",
      body: JSON.stringify({
        user_id: userId,
      }),
    }),

  // ─────────────────────────────────────────────
  // DASHBOARD
  // ─────────────────────────────────────────────

  dashboard: () => request("dashboard.php"),

  // ─────────────────────────────────────────────
  // BALANCES
  // ─────────────────────────────────────────────

  balances: () => request("balances.php"),

  // ─────────────────────────────────────────────
  // TRANSACTIONS
  // ─────────────────────────────────────────────

  transactions: () => request("transactions.php"),

  // ─────────────────────────────────────────────
  // CURRENCIES
  // ─────────────────────────────────────────────

  currencies: () => request("currencies.php"),

  // ─────────────────────────────────────────────
  // OPENING BALANCE
  // ─────────────────────────────────────────────

  openingBalance: (currencyId, amount) =>
    request("opening-balance.php", {
      method: "POST",
      body: JSON.stringify({
        currency_id: currencyId,
        amount,
      }),
    }),

  // EXCHANGE
  exchange: (
    type,
    fromCurrencyId,
    fromAmount,
    toCurrencyId,
    toAmount,
    exchangeRate,
    requestId,
  ) =>
    request("exchange.php", {
      method: "POST",
      headers: {
        "X-Idempotency-Key": requestId,
      },
      body: JSON.stringify({
        type,
        from_currency_id: fromCurrencyId,
        from_amount: fromAmount,
        to_currency_id: toCurrencyId,
        to_amount: toAmount,
        exchange_rate: exchangeRate,
        request_id: requestId,
      }),
    }),
};

// ─────────────────────────────────────────────
// BALANCE MANAGEMENT
// ─────────────────────────────────────────────

export async function createBalanceMovement(data) {
  return request("balance-movement.php", {
    method: "POST",
    body: JSON.stringify(data),
  });
}

export async function getBalanceMovements() {
  return request("balance-movements.php");
}

// ─────────────────────────────────────────────
// CURRENCY MANAGEMENT
// ─────────────────────────────────────────────

export async function getCurrencies() {
  return request("currencies.php");
}

export async function addCurrency(data) {
  return request("add-currency.php", {
    method: "POST",
    body: JSON.stringify(data),
  });
}
