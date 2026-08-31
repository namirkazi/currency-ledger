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
  const number = Number(value);

  if (!Number.isFinite(number)) {
    return "0.00";
  }

  return number.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 6,
  });
}

export default function Trading() {
  const [currencies, setCurrencies] = useState([]);
  const [selectedCurrencyId, setSelectedCurrencyId] = useState("");

  const [amount, setAmount] = useState("");
  const [exchangeRate, setExchangeRate] = useState("");

  const [activeType, setActiveType] = useState("BUY");

  const [loadingCurrencies, setLoadingCurrencies] = useState(true);
  const [loading, setLoading] = useState(false);

  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const aedCurrency = useMemo(
    () => currencies.find((currency) => currency.code?.toUpperCase() === "AED"),
    [currencies],
  );

  const tradeCurrencies = useMemo(
    () =>
      currencies.filter((currency) => currency.code?.toUpperCase() !== "AED"),
    [currencies],
  );

  const selectedCurrency = useMemo(
    () =>
      currencies.find(
        (currency) => Number(currency.id) === Number(selectedCurrencyId),
      ),
    [currencies, selectedCurrencyId],
  );

  const aedAmount = useMemo(() => {
    const tradeAmount = Number(amount);
    const rate = Number(exchangeRate);

    if (
      !Number.isFinite(tradeAmount) ||
      !Number.isFinite(rate) ||
      tradeAmount <= 0 ||
      rate <= 0
    ) {
      return 0;
    }

    return tradeAmount * rate;
  }, [amount, exchangeRate]);

  useEffect(() => {
    loadCurrencies();
  }, []);

  async function loadCurrencies() {
    try {
      setLoadingCurrencies(true);
      setError("");

      const response = await api.currencies();

      const list = response.currencies || response.data?.currencies || [];

      setCurrencies(list);

      const firstTradeCurrency = list.find(
        (currency) => currency.code?.toUpperCase() !== "AED",
      );

      if (firstTradeCurrency) {
        setSelectedCurrencyId(String(firstTradeCurrency.id));
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoadingCurrencies(false);
    }
  }

  function resetForm() {
    setAmount("");
    setExchangeRate("");
  }

  function changeType(type) {
    setActiveType(type);
    setError("");
    setMessage("");
  }

  async function handleSubmit(event) {
    event.preventDefault();

    setError("");
    setMessage("");

    if (!aedCurrency) {
      setError("AED base currency could not be found.");
      return;
    }

    if (!selectedCurrency) {
      setError("Please select a currency.");
      return;
    }

    if (!amount || Number(amount) <= 0) {
      setError("Enter a valid amount.");
      return;
    }

    if (!exchangeRate || Number(exchangeRate) <= 0) {
      setError("Enter a valid exchange rate.");
      return;
    }

    if (!aedAmount || aedAmount <= 0) {
      setError("Calculated AED amount is invalid.");
      return;
    }

    let fromCurrencyId;
    let toCurrencyId;
    let fromAmount;
    let toAmount;

    if (activeType === "BUY") {
      // BUY foreign currency using AED
      fromCurrencyId = Number(aedCurrency.id);
      fromAmount = Number(aedAmount.toFixed(6));

      toCurrencyId = Number(selectedCurrency.id);
      toAmount = Number(Number(amount).toFixed(6));
    } else {
      // SELL foreign currency and receive AED
      fromCurrencyId = Number(selectedCurrency.id);
      fromAmount = Number(Number(amount).toFixed(6));

      toCurrencyId = Number(aedCurrency.id);
      toAmount = Number(aedAmount.toFixed(6));
    }

    const requestId = createRequestId();

    try {
      setLoading(true);

      await api.exchange(
        activeType,
        fromCurrencyId,
        fromAmount,
        toCurrencyId,
        toAmount,
        Number(exchangeRate),
        requestId,
      );

      if (activeType === "BUY") {
        setMessage(
          `Successfully bought ${formatNumber(amount)} ${selectedCurrency.code} for ${formatNumber(aedAmount)} AED.`,
        );
      } else {
        setMessage(
          `Successfully sold ${formatNumber(amount)} ${selectedCurrency.code} for ${formatNumber(aedAmount)} AED.`,
        );
      }

      resetForm();
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }

  const actionLabel = activeType === "BUY" ? "Buying" : "Selling";

  const submitLabel = activeType === "BUY" ? "Complete Buy" : "Complete Sell";

  return (
    <div className={styles.page}>
      <div className={styles.header}>
        <h1>Trading</h1>

        <p>Buy and sell currencies against AED.</p>
      </div>

      <div className={styles.tradeTabs}>
        <button
          type="button"
          className={activeType === "BUY" ? styles.activeTab : ""}
          onClick={() => changeType("BUY")}
        >
          Buy
        </button>

        <button
          type="button"
          className={activeType === "SELL" ? styles.activeTab : ""}
          onClick={() => changeType("SELL")}
        >
          Sell
        </button>
      </div>

      <form className={styles.tradeCard} onSubmit={handleSubmit}>
        <div className={styles.tradeHeading}>
          <h2>{actionLabel} Currency</h2>

          <p>
            {activeType === "BUY"
              ? "Select the currency you want to buy."
              : "Select the currency you want to sell."}
          </p>
        </div>

        <div className={styles.formGrid}>
          <div className={styles.field}>
            <label>Currency</label>

            <select
              value={selectedCurrencyId}
              onChange={(event) => setSelectedCurrencyId(event.target.value)}
              disabled={loading || loadingCurrencies}
            >
              {tradeCurrencies.map((currency) => (
                <option key={currency.id} value={currency.id}>
                  {currency.code}
                  {currency.name ? ` — ${currency.name}` : ""}
                </option>
              ))}
            </select>
          </div>

          <div className={styles.field}>
            <label>Amount {selectedCurrency?.code || ""}</label>

            <input
              type="number"
              step="0.000001"
              min="0"
              value={amount}
              onChange={(event) => setAmount(event.target.value)}
              placeholder="0.00"
              disabled={loading}
              required
            />
          </div>

          <div className={styles.field}>
            <label>Rate (AED per {selectedCurrency?.code || "currency"})</label>

            <input
              type="number"
              step="0.000001"
              min="0"
              value={exchangeRate}
              onChange={(event) => setExchangeRate(event.target.value)}
              placeholder={`1 ${selectedCurrency?.code || ""} = ? AED`}
              disabled={loading}
              required
            />
          </div>
        </div>

        <div className={styles.ratePreview}>
          1 {selectedCurrency?.code || "Currency"} ={" "}
          {formatNumber(exchangeRate)} AED
        </div>

        <div className={styles.calculation}>
          {activeType === "BUY" ? (
            <>
              <div className={styles.summaryRow}>
                <span>You Pay</span>

                <strong>{formatNumber(aedAmount)} AED</strong>
              </div>

              <div className={styles.summaryRow}>
                <span>You Receive</span>

                <strong>
                  {formatNumber(amount)} {selectedCurrency?.code || ""}
                </strong>
              </div>
            </>
          ) : (
            <>
              <div className={styles.summaryRow}>
                <span>You Sell</span>

                <strong>
                  {formatNumber(amount)} {selectedCurrency?.code || ""}
                </strong>
              </div>

              <div className={styles.summaryRow}>
                <span>You Receive</span>

                <strong>{formatNumber(aedAmount)} AED</strong>
              </div>
            </>
          )}
        </div>

        {error && <div className={styles.error}>{error}</div>}

        {message && <div className={styles.success}>{message}</div>}

        <button
          type="submit"
          className={styles.submit}
          disabled={
            loading || loadingCurrencies || !selectedCurrencyId || !aedCurrency
          }
        >
          {loading ? "Processing..." : submitLabel}
        </button>
      </form>
    </div>
  );
}
