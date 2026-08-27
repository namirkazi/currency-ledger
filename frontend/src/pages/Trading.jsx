import { useEffect, useMemo, useState } from "react";
import { api } from "../services/api";
import styles from "./Trading.module.css";

function createRequestId() {
  if (crypto?.randomUUID) {
    return crypto.randomUUID();
  }

  return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function formatNumber(value) {
  if (value === "" || value === null || value === undefined) {
    return "0.000000";
  }

  const number = Number(value);

  if (!Number.isFinite(number)) {
    return "0.000000";
  }

  return number.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 6,
  });
}

export default function Trading() {
  const [currencies, setCurrencies] = useState([]);

  const [currencyId, setCurrencyId] = useState("");
  const [amount, setAmount] = useState("");
  const [rate, setRate] = useState("");

  const [loadingCurrencies, setLoadingCurrencies] = useState(true);
  const [loading, setLoading] = useState(false);

  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const [activeType, setActiveType] = useState("BUY");

  const selectedCurrency = useMemo(
    () =>
      currencies.find((currency) => Number(currency.id) === Number(currencyId)),
    [currencies, currencyId],
  );

  const usdAmount = useMemo(() => {
    const numericAmount = Number(amount);
    const numericRate = Number(rate);

    if (
      !Number.isFinite(numericAmount) ||
      !Number.isFinite(numericRate) ||
      numericAmount <= 0 ||
      numericRate <= 0
    ) {
      return "";
    }

    return numericAmount * numericRate;
  }, [amount, rate]);

  useEffect(() => {
    loadCurrencies();
  }, []);

  async function loadCurrencies() {
    try {
      setLoadingCurrencies(true);
      setError("");

      const response = await api.currencies();

      const list = response.currencies || response.data?.currencies || [];

      const tradable = list.filter(
        (currency) =>
          Number(currency.id) !==
          Number(
            list.find((item) => String(item.code).toUpperCase() === "USD")?.id,
          ),
      );

      setCurrencies(tradable);

      if (tradable.length > 0) {
        setCurrencyId(String(tradable[0].id));
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoadingCurrencies(false);
    }
  }

  function resetForm() {
    setAmount("");
    setRate("");
  }

  async function handleSubmit(event) {
    event.preventDefault();

    setError("");
    setMessage("");

    if (!currencyId) {
      setError("Please select a currency.");
      return;
    }

    if (!amount || Number(amount) <= 0) {
      setError("Enter a valid amount.");
      return;
    }

    if (!rate || Number(rate) <= 0) {
      setError("Enter a valid rate.");
      return;
    }

    const requestId = createRequestId();

    try {
      setLoading(true);

      const result =
        activeType === "BUY"
          ? await api.buy(Number(currencyId), amount, rate, requestId)
          : await api.sell(Number(currencyId), amount, rate, requestId);

      if (activeType === "BUY") {
        setMessage(
          `Bought ${formatNumber(amount)} ${
            selectedCurrency?.code || ""
          } for ${formatNumber(result.usd_amount)} USD.`,
        );
      } else {
        setMessage(
          `Sold ${formatNumber(amount)} ${
            selectedCurrency?.code || ""
          } for ${formatNumber(result.usd_amount)} USD. Profit: ${formatNumber(
            result.profit,
          )} USD.`,
        );
      }

      resetForm();
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className={styles.page}>
      <div className={styles.header}>
        <div>
          <h1>Trading</h1>
          <p>Buy and sell currencies against USD.</p>
        </div>
      </div>

      <div className={styles.tradeTabs}>
        <button
          type="button"
          className={activeType === "BUY" ? styles.activeTab : ""}
          onClick={() => {
            setActiveType("BUY");
            setError("");
            setMessage("");
          }}
        >
          Buy
        </button>

        <button
          type="button"
          className={activeType === "SELL" ? styles.activeTab : ""}
          onClick={() => {
            setActiveType("SELL");
            setError("");
            setMessage("");
          }}
        >
          Sell
        </button>
      </div>

      <form className={styles.tradeCard} onSubmit={handleSubmit}>
        <div className={styles.formGrid}>
          <div className={styles.field}>
            <label>Currency</label>

            <select
              value={currencyId}
              onChange={(event) => setCurrencyId(event.target.value)}
              disabled={loadingCurrencies || loading}
            >
              {loadingCurrencies && (
                <option value="">Loading currencies...</option>
              )}

              {!loadingCurrencies && currencies.length === 0 && (
                <option value="">No currencies available</option>
              )}

              {currencies.map((currency) => (
                <option key={currency.id} value={currency.id}>
                  {currency.code}
                  {currency.name ? ` — ${currency.name}` : ""}
                </option>
              ))}
            </select>
          </div>

          <div className={styles.field}>
            <label>{selectedCurrency?.code || "Currency"} Amount</label>

            <input
              type="text"
              inputMode="decimal"
              value={amount}
              onChange={(event) => setAmount(event.target.value)}
              placeholder="0.000000"
              disabled={loading}
              required
            />
          </div>

          <div className={styles.field}>
            <label>USD Rate per {selectedCurrency?.code || "currency"}</label>

            <input
              type="text"
              inputMode="decimal"
              value={rate}
              onChange={(event) => setRate(event.target.value)}
              placeholder="0.000000"
              disabled={loading}
              required
            />
          </div>
        </div>

        <div className={styles.calculation}>
          <div>
            <span>{activeType === "BUY" ? "You pay" : "You receive"}</span>

            <strong>{formatNumber(usdAmount)} USD</strong>
          </div>
        </div>

        {activeType === "SELL" && (
          <div className={styles.info}>
            Profit is calculated by the server from the actual inventory
            acquisition cost.
          </div>
        )}

        {error && <div className={styles.error}>{error}</div>}

        {message && <div className={styles.success}>{message}</div>}

        <button
          type="submit"
          className={styles.submit}
          disabled={loading || loadingCurrencies || !currencyId}
        >
          {loading
            ? "Processing..."
            : activeType === "BUY"
              ? `Buy ${selectedCurrency?.code || ""}`
              : `Sell ${selectedCurrency?.code || ""}`}
        </button>
      </form>
    </div>
  );
}
