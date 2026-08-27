import { useEffect, useState } from "react";
import { createBalanceMovement, getBalanceMovements } from "../services/api";

import styles from "./BalanceManagement.module.css";

export default function BalanceManagement() {
  const [currency, setCurrency] = useState("AED");
  const [type, setType] = useState("INFLOW");
  const [amount, setAmount] = useState("");
  const [reason, setReason] = useState("");

  const [movements, setMovements] = useState([]);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState("");

  async function loadMovements() {
    try {
      const data = await getBalanceMovements();

      setMovements(data.movements || []);
    } catch (error) {
      setMessage(error.message);
    }
  }

  useEffect(() => {
    loadMovements();
  }, []);

  async function handleSubmit(event) {
    event.preventDefault();

    if (!amount || Number(amount) <= 0) {
      setMessage("Enter a valid amount.");
      return;
    }
    setLoading(true);
    setMessage("");

    try {
      await createBalanceMovement({
        currency_id: currencyId,
        movement_type: type,
        amount,
      });

      setAmount("");

      setMessage("Balance movement recorded.");

      await loadMovements();
    } catch (error) {
      setMessage(error.message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className={styles.page}>
      <div className={styles.header}>
        <h1>Balance Management</h1>

        <p>Record AED or USD entering or leaving the trading operation.</p>
      </div>

      <div className={styles.grid}>
        <form className={styles.card} onSubmit={handleSubmit}>
          <h2>New Balance Movement</h2>

          <div className={styles.field}>
            <label htmlFor="currency">Currency</label>

            <select
              id="currency"
              value={currency}
              onChange={(event) => setCurrency(event.target.value)}
            >
              <option value="AED">AED</option>

              <option value="USD">USD</option>
            </select>
          </div>

          <div className={styles.field}>
            <label htmlFor="type">Movement</label>

            <select
              id="type"
              value={type}
              onChange={(event) => setType(event.target.value)}
            >
              <option value="INFLOW">Money In</option>

              <option value="OUTFLOW">Money Out</option>
            </select>
          </div>

          <div className={styles.field}>
            <label htmlFor="amount">Amount</label>

            <input
              id="amount"
              type="text"
              inputMode="decimal"
              value={amount}
              onChange={(event) => setAmount(event.target.value)}
              placeholder="0.00"
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
            />
          </div>

          {message && <div className={styles.message}>{message}</div>}

          <button className={styles.button} type="submit" disabled={loading}>
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
                    <span className={styles.currency}>{movement.currency}</span>

                    <span className={styles.reason}>{movement.reason}</span>

                    <span className={styles.user}>{movement.user_name}</span>
                  </div>

                  <div className={styles.rowRight}>
                    <span className={styles.amount}>
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
