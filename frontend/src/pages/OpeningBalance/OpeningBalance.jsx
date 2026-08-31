import { useEffect, useState } from "react";
import { Save, Plus, Trash2, RefreshCw } from "lucide-react";

import { api } from "../../services/api";
import styles from "./OpeningBalance.module.css";

export default function OpeningBalance() {
  const [currencies, setCurrencies] = useState([]);
  const [balances, setBalances] = useState([]);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  useEffect(() => {
    loadCurrencies();
  }, []);

  async function loadCurrencies() {
    setLoading(true);
    setError("");

    try {
      const response = await api.currencies();

      const currencyList = response.currencies || response.data || response;

      setCurrencies(Array.isArray(currencyList) ? currencyList : []);
    } catch (err) {
      setError(err.message || "Failed to load currencies.");
    } finally {
      setLoading(false);
    }
  }

  function addBalanceRow() {
    setBalances([
      ...balances,
      {
        currency_id: "",
        amount: "",
      },
    ]);
  }

  function removeBalanceRow(index) {
    setBalances(balances.filter((_, i) => i !== index));
  }

  function updateBalance(index, field, value) {
    const updated = [...balances];

    updated[index] = {
      ...updated[index],
      [field]: value,
    };

    setBalances(updated);
  }

  function getAvailableCurrencies(index) {
    const selectedIds = balances
      .filter((_, i) => i !== index)
      .map((balance) => String(balance.currency_id))
      .filter(Boolean);

    return currencies.filter(
      (currency) => !selectedIds.includes(String(currency.id)),
    );
  }

  async function saveBalances(event) {
    event.preventDefault();

    setMessage("");
    setError("");

    if (balances.length === 0) {
      setError("Add at least one opening balance.");
      return;
    }

    for (const balance of balances) {
      if (!balance.currency_id) {
        setError("Please select a currency for every row.");
        return;
      }

      if (balance.amount === "" || Number(balance.amount) <= 0) {
        setError("Opening balance amounts must be greater than zero.");
        return;
      }
    }

    const summary = balances
      .map((balance) => {
        const currency = currencies.find(
          (c) => String(c.id) === String(balance.currency_id),
        );

        return `${currency?.code || "Currency"}: ${balance.amount}`;
      })
      .join("\n");

    if (!window.confirm(`Create these opening balances?\n\n${summary}`)) {
      return;
    }

    setSaving(true);

    try {
      for (const balance of balances) {
        await api.openingBalance(Number(balance.currency_id), balance.amount);
      }

      setBalances([]);
      setMessage("Opening balances created successfully.");
    } catch (err) {
      setError(err.message || "Failed to create opening balances.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className={styles.page}>
      <div className={styles.heading}>
        <div>
          <h2>Opening Balances</h2>

          <p>
            Set the initial physical currency holdings when starting the ledger.
          </p>
        </div>

        <button
          type="button"
          className={styles.refreshButton}
          onClick={loadCurrencies}
          disabled={loading}
        >
          <RefreshCw size={18} />
          Refresh
        </button>
      </div>

      {message && <div className={styles.success}>{message}</div>}

      {error && <div className={styles.error}>{error}</div>}

      <form className={styles.card} onSubmit={saveBalances}>
        <div className={styles.tableHeader}>
          <span>Currency</span>
          <span>Opening Balance</span>
          <span></span>
        </div>

        {loading ? (
          <div className={styles.loading}>Loading currencies...</div>
        ) : balances.length === 0 ? (
          <div className={styles.empty}>No opening balances added yet.</div>
        ) : (
          balances.map((balance, index) => (
            <div className={styles.balanceRow} key={index}>
              <select
                value={balance.currency_id}
                onChange={(event) =>
                  updateBalance(index, "currency_id", event.target.value)
                }
                required
              >
                <option value="">Select currency</option>

                {getAvailableCurrencies(index).map((currency) => (
                  <option key={currency.id} value={currency.id}>
                    {currency.code} — {currency.name}
                  </option>
                ))}
              </select>

              <input
                type="number"
                min="0"
                step="0.000001"
                value={balance.amount}
                onChange={(event) =>
                  updateBalance(index, "amount", event.target.value)
                }
                placeholder="0.00"
                required
              />

              <button
                type="button"
                className={styles.deleteButton}
                onClick={() => removeBalanceRow(index)}
                disabled={saving}
                title="Remove"
              >
                <Trash2 size={18} />
              </button>
            </div>
          ))
        )}

        <button
          type="button"
          className={styles.addButton}
          onClick={addBalanceRow}
          disabled={loading || saving}
        >
          <Plus size={18} />
          Add Currency
        </button>

        <div className={styles.warning}>
          Opening balances establish the initial physical holdings of the
          business. Each currency is recorded separately in the immutable
          ledger.
        </div>

        <button
          type="submit"
          className={styles.saveButton}
          disabled={saving || loading || balances.length === 0}
        >
          <Save size={18} />

          {saving ? "Saving..." : "Set Opening Balances"}
        </button>
      </form>
    </div>
  );
}
