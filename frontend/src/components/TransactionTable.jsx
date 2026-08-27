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
          <p>Latest trading activity</p>
        </div>
      </div>

      <div className="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Type</th>
              <th>USD</th>
              <th>Rate</th>
              <th>AED</th>
              <th>Profit</th>
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
              transactions.map((tx) => (
                <tr key={tx.id}>
                  <td>
                    <span
                      className={`trade-badge ${
                        tx.type === "BUY_USDT" ? "buy" : "sell"
                      }`}
                    >
                      {tx.type === "BUY_USDT" ? "BUY" : "SELL"}
                    </span>
                  </td>

                  <td>{formatNumber(tx.usdt_amount)}</td>
                  <td>{formatNumber(tx.rate)}</td>
                  <td>{formatNumber(tx.aed_amount)}</td>

                  <td className="profit">{formatNumber(tx.realized_profit)}</td>

                  <td>{tx.user_name}</td>

                  <td>{new Date(tx.created_at).toLocaleString()}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
