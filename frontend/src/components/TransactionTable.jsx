function formatNumber(value) {
  return Number(value || 0).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 6,
  });
}

export default function TransactionTable({ transactions = [] }) {
  return (
    <div className="table-card">
      <div className="table-header">
        <div>
          <h3>Recent Transactions</h3>
          <p>Latest trading activity and realized profit</p>
        </div>
      </div>

      <div className="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Type</th>
              <th>From</th>
              <th>To</th>
              <th>Rate</th>
              <th>Realized Profit</th>
              <th>User</th>
              <th>Date</th>
            </tr>
          </thead>

          <tbody>
            {transactions.length === 0 ? (
              <tr>
                <td colSpan="7" className="empty">
                  No transactions yet.
                </td>
              </tr>
            ) : (
              transactions.map((tx) => {
                const isSell = tx.type === "SELL";

                return (
                  <tr key={tx.id}>
                    <td>
                      <span
                        className={`trade-badge ${
                          tx.type === "BUY" ? "buy" : "sell"
                        }`}
                      >
                        {tx.type}
                      </span>
                    </td>

                    <td>
                      {formatNumber(tx.from_amount)} {tx.from_currency_code}
                    </td>

                    <td>
                      {formatNumber(tx.to_amount)} {tx.to_currency_code}
                    </td>

                    <td>{formatNumber(tx.exchange_rate)}</td>

                    <td
                      className={
                        isSell
                          ? Number(tx.realized_profit) >= 0
                            ? "profit"
                            : "loss"
                          : ""
                      }
                    >
                      {isSell ? (
                        <>
                          {Number(tx.realized_profit) > 0 ? "+" : ""}
                          {formatNumber(tx.realized_profit)} AED
                        </>
                      ) : (
                        "—"
                      )}
                    </td>

                    <td>{tx.user_name}</td>

                    <td>{new Date(tx.created_at).toLocaleString()}</td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
