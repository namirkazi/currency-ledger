import { useState } from "react";
import { Save } from "lucide-react";

import { api } from "../../services/api";
import styles from "./OpeningBalance.module.css";

export default function OpeningBalance() {
  const [aed, setAed] = useState("");
  const [usdt, setUsdt] = useState("");

  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  async function saveBalances(event) {
    event.preventDefault();

    setMessage("");
    setError("");

    if (aed === "" || usdt === "") {
      setError("Enter both opening balances.");
      return;
    }

    if (Number(aed) < 0 || Number(usdt) < 0) {
      setError("Opening balances cannot be negative.");
      return;
    }

    if (!window.confirm(`Set opening balances?\n\nAED: ${aed}\nUSD: ${usdt}`)) {
      return;
    }

    setSaving(true);

    try {
      await api.openingBalance("AED", aed);
      await api.openingBalance("USDT", usdt);

      setAed("");
      setUsdt("");

      setMessage("Opening balances created successfully.");
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className={styles.page}>
      <div className={styles.heading}>
        <div>
          <h2>Opening Balances</h2>
          <p>Set the physical AED and USD holdings when starting the ledger.</p>
        </div>
      </div>

      {message && <div className={styles.success}>{message}</div>}

      {error && <div className={styles.error}>{error}</div>}

      <form className={styles.card} onSubmit={saveBalances}>
        <div className={styles.field}>
          <label>AED Opening Balance</label>

          <div className={styles.inputWrapper}>
            <span>AED</span>

            <input
              type="number"
              min="0"
              step="0.000001"
              value={aed}
              onChange={(event) => setAed(event.target.value)}
              placeholder="0.00"
              required
            />
          </div>
        </div>

        <div className={styles.field}>
          <label>USD Opening Balance</label>

          <div className={styles.inputWrapper}>
            <span>USD</span>

            <input
              type="number"
              min="0"
              step="0.000001"
              value={usdt}
              onChange={(event) => setUsdt(event.target.value)}
              placeholder="0.00"
              required
            />
          </div>
        </div>

        <div className={styles.warning}>
          Opening balances establish the initial physical holdings of the
          business. They are recorded in the ledger and cannot be created twice.
        </div>

        <button type="submit" className={styles.saveButton} disabled={saving}>
          <Save size={18} />

          {saving ? "Saving..." : "Set Opening Balances"}
        </button>
      </form>
    </div>
  );
}
