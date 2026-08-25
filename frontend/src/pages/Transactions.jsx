import { useEffect, useState } from 'react';
import TransactionTable from '../components/TransactionTable';
import { api } from '../services/api';

export default function Transactions() {
    const [transactions, setTransactions] = useState([]);

    useEffect(() => {
        api.transactions()
            .then((data) => setTransactions(data.transactions))
            .catch(console.error);
    }, []);

    return (
        <>
            <div className="page-heading">
                <div>
                    <h2>Transactions</h2>
                    <p>Complete trading history.</p>
                </div>
            </div>

            <TransactionTable transactions={transactions} />
        </>
    );
}