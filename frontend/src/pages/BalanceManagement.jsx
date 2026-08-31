import { useEffect, useState } from "react";

import {
  createBalanceMovement,
  getBalanceMovements,
  api,
} from "../services/api";

import styles from "./BalanceManagement.module.css";

export default function BalanceManagement() {
  const [currencies, setCurrencies] = useState([]);
  const [currencyId, setCurrencyId] = useState("");

  const [type, setType] = useState("INFLOW");
  const [amount, setAmount] = useState("");
  const [reason, setReason] = useState("");

  const [movements, setMovements] = useState([]);

  const [loading, setLoading] = useState(false);
  const [loadingCurrencies, setLoadingCurrencies] = useState(true);

  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  async function loadCurrencies() {
    try {
      setLoadingCurrencies(true);

      const response = await api.currencies();

      const list = response.currencies || response.data?.currencies || [];

      setCurrencies(list);

      if (list.length > 0) {
        setCurrencyId(String(list[0].id));
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoadingCurrencies(false);
    }
  }

  async function loadMovements() {
    try {
      const data = await getBalanceMovements();

      setMovements(data.movements || []);
    } catch (error) {
      setError(error.message);
    }
  }

  useEffect(() => {
    loadCurrencies();
    loadMovements();
  }, []);

  async function handleSubmit(event) {
    event.preventDefault();

    setMessage("");
    setError("");

    if (!currencyId) {
      setError("Please select a currency.");
      return;
    }

    if (!amount || Number(amount) <= 0) {
      setError("Enter a valid amount.");
      return;
    }

    setLoading(true);

    try {
      await createBalanceMovement({
        currency_id: Number(currencyId),
        movement_type: type,
        amount,
        reason,
      });

      setAmount("");
      setReason("");

      setMessage("Balance movement recorded.");

      await loadMovements();
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className={styles.page}>
      <div className={styles.header}>
        <h1>Balance Management</h1>

        <p>Record money entering or leaving any currency balance.</p>
      </div>

      <div className={styles.grid}>
        <form className={styles.card} onSubmit={handleSubmit}>
          <h2>New Balance Movement</h2>

          <div className={styles.field}>
            <label htmlFor="currency">Currency</label>

            <select
              id="currency"
              value={currencyId}
              onChange={(event) => setCurrencyId(event.target.value)}
              disabled={loadingCurrencies || loading}
            >
              {currencies.map((currency) => (
                <option key={currency.id} value={currency.id}>
                  {currency.code}
                  {currency.name ? ` — ${currency.name}` : ""}
                </option>
              ))}
            </select>
          </div>

          <div className={styles.field}>
            <label htmlFor="type">Movement</label>

            <select
              id="type"
              value={type}
              onChange={(event) => setType(event.target.value)}
              disabled={loading}
            >
              <option value="INFLOW">Money In</option>

              <option value="OUTFLOW">Money Out</option>
            </select>
          </div>

          <div className={styles.field}>
            <label htmlFor="amount">Amount</label>

            <input
              id="amount"
              type="number"
              step="0.000001"
              min="0"
              value={amount}
              onChange={(event) => setAmount(event.target.value)}
              placeholder="0.00"
              disabled={loading}
            />
          </div>

          <div className={styles.field}>
            <label htmlFor="reason">Reason</label>

            <textarea
              id="reason"
              value={reason}
              onChange={(event) => setReason(event.target.value)}
              placeholder="Reason for this movement"
              maxLength={255}
              disabled={loading}
            />
          </div>

          {error && <div className={styles.error}>{error}</div>}

          {message && <div className={styles.message}>{message}</div>}

          <button
            className={styles.button}
            type="submit"
            disabled={loading || loadingCurrencies || !currencyId}
          >
            {loading ? "Saving..." : "Record Movement"}
          </button>
        </form>

        <div className={styles.card}>
          <h2>Recent Movements</h2>

          {movements.length === 0 ? (
            <p className={styles.empty}>No balance movements yet.</p>
          ) : (
            <div className={styles.list}>
              {movements.map((movement) => (
                <div className={styles.row} key={movement.id}>
                  <div className={styles.rowLeft}>
                    <span className={styles.currency}>
                      {movement.currency_code}
                    </span>

                    <span className={styles.reason}>
                      {movement.currency_name}
                    </span>

                    <span className={styles.user}>{movement.user_name}</span>
                  </div>

                  <div className={styles.rowRight}>
                    <span
                      className={`${styles.amount} ${
                        movement.movement_type === "INFLOW"
                          ? styles.inflow
                          : styles.outflow
                      }`}
                    >
                      {movement.movement_type === "INFLOW" ? "+" : "-"}
                      {movement.amount}
                    </span>

                    <span className={styles.user}>
                      {movement.movement_type}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
