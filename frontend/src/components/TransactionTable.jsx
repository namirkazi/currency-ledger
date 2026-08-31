function formatNumber(value) {
  return Number(value || 0).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 6,
  });
}

function formatCurrency(amount, code) {
  return `${formatNumber(amount)} ${code || ""}`;
}

export default function TransactionTable({ transactions = [] }) {
  return (
    <div className="table-card">
      <div className="table-header">
        <div>
          <h3>Recent Exchanges</h3>
          <p>Latest currency exchange activity</p>
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
              <th>User</th>
              <th>Date</th>
            </tr>
          </thead>

          <tbody>
            {transactions.length === 0 ? (
              <tr>
                <td colSpan="6" className="empty">
                  No exchanges yet.
                </td>
              </tr>
            ) : (
              transactions.map((tx) => (
                <tr key={tx.id}>
                  <td>
                    <span className="trade-badge buy">EXCHANGE</span>
                  </td>

                  <td>
                    {formatCurrency(tx.from_amount, tx.from_currency_code)}
                  </td>

                  <td>{formatCurrency(tx.to_amount, tx.to_currency_code)}</td>

                  <td>{formatNumber(tx.exchange_rate)}</td>

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
