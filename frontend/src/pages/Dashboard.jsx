import { useEffect, useState } from "react";
import { Banknote, ArrowRightLeft, CalendarDays } from "lucide-react";

import StatCard from "../components/StatCard";
import TransactionTable from "../components/TransactionTable";
import { api } from "../services/api";

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [error, setError] = useState("");

  async function load() {
    try {
      setError("");
      setData(await api.dashboard());
    } catch (err) {
      setError(err.message);
    }
  }

  useEffect(() => {
    load();
  }, []);

  if (error) {
    return <div className="alert error">{error}</div>;
  }

  if (!data) {
    return <div className="loading">Loading dashboard...</div>;
  }

  return (
    <>
      <div className="page-heading">
        <div>
          <h2>Dashboard</h2>
          <p>Current currency positions and exchange activity.</p>
        </div>
      </div>

      {/* CURRENCY BALANCES */}

      <div className="stats-grid">
        {(data.balances || []).map((balance) => (
          <StatCard
            key={balance.currency_id}
            label={`${balance.code} Balance`}
            value={Number(balance.balance).toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 6,
            })}
            currency={balance.code}
            icon={Banknote}
          />
        ))}
      </div>

      {/* ACTIVITY */}

      <div className="stats-grid">
        <StatCard
          label="Today's Exchanges"
          value={Number(data.today?.total_transactions || 0).toLocaleString()}
          icon={ArrowRightLeft}
        />

        <StatCard
          label="This Month's Exchanges"
          value={Number(data.month?.total_transactions || 0).toLocaleString()}
          icon={CalendarDays}
        />
      </div>

      <div className="page-heading" style={{ marginTop: 32 }}>
        <div>
          <h2>Recent Exchanges</h2>
          <p>Latest currency movements in the ledger.</p>
        </div>
      </div>

      <TransactionTable transactions={data.recent || []} />
    </>
  );
}
