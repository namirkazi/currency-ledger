import { useEffect, useState } from "react";
import { addCurrency, getCurrencies } from "../services/api";

import styles from "./Currencies.module.css";

export default function Currencies() {
  const [currencies, setCurrencies] = useState([]);

  const [code, setCode] = useState("");
  const [name, setName] = useState("");
  const [symbol, setSymbol] = useState("");

  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState("");

  async function loadCurrencies() {
    try {
      const data = await getCurrencies();

      setCurrencies(data.currencies || []);
    } catch (error) {
      setMessage(error.message);
    }
  }

  useEffect(() => {
    loadCurrencies();
  }, []);

  async function handleSubmit(event) {
    event.preventDefault();

    setLoading(true);
    setMessage("");

    try {
      await addCurrency({
        code: code.toUpperCase(),
        name,
        symbol,
      });

      setCode("");
      setName("");
      setSymbol("");

      setMessage("Currency added successfully.");

      await loadCurrencies();
    } catch (error) {
      setMessage(error.message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className={styles.page}>
      <div className={styles.header}>
        <h1>Currencies</h1>

        <p>Manage currencies available throughout the ledger.</p>
      </div>

      <div className={styles.grid}>
        <form className={styles.card} onSubmit={handleSubmit}>
          <h2>Add Currency</h2>

          <label>
            Currency Code
            <input
              value={code}
              onChange={(event) =>
                setCode(
                  event.target.value
                    .toUpperCase()
                    .replace(/[^A-Z]/g, "")
                    .slice(0, 3),
                )
              }
              placeholder="INR"
              maxLength={3}
              required
            />
          </label>

          <label>
            Currency Name
            <input
              value={name}
              onChange={(event) => setName(event.target.value)}
              placeholder="Indian Rupee"
              required
            />
          </label>

          <label>
            Symbol
            <input
              value={symbol}
              onChange={(event) => setSymbol(event.target.value)}
              placeholder="₹"
              maxLength={10}
            />
          </label>

          {message && <div className={styles.message}>{message}</div>}

          <button type="submit" disabled={loading}>
            {loading ? "Adding..." : "Add Currency"}
          </button>
        </form>

        <div className={styles.card}>
          <h2>Available Currencies</h2>

          <div className={styles.list}>
            {currencies.map((currency) => (
              <div className={styles.row} key={currency.id}>
                <div className={styles.currency}>
                  <strong>{currency.code}</strong>

                  <span>{currency.name}</span>
                </div>

                <div className={styles.symbol}>{currency.symbol || "—"}</div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
