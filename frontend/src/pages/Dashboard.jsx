import { useEffect, useState } from 'react';
import {
    Banknote,
    Coins,
    TrendingUp,
    ArrowDownToLine,
    ArrowUpFromLine
} from 'lucide-react';
import StatCard from '../components/StatCard';
import TransactionTable from '../components/TransactionTable';
import { api } from '../services/api';

export default function Dashboard() {
    const [data, setData] = useState(null);
    const [error, setError] = useState('');

    async function load() {
        try {
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
                    <p>Current position and trading activity.</p>
                </div>
            </div>

            <div className="stats-grid">
                <StatCard
                    label="AED Balance"
                    value={Number(data.balances.AED).toLocaleString()}
                    currency="AED"
                    icon={Banknote}
                />

                <StatCard
                    label="USDT Balance"
                    value={Number(data.balances.USDT).toLocaleString()}
                    currency="USDT"
                    icon={Coins}
                />

                <StatCard
                    label="Today's Profit"
                    value={Number(data.today.profit).toLocaleString()}
                    currency="AED"
                    icon={TrendingUp}
                    positive
                />

                <StatCard
                    label="Monthly Profit"
                    value={Number(data.month_profit).toLocaleString()}
                    currency="AED"
                    icon={TrendingUp}
                    positive
                />

                <StatCard
                    label="USDT Bought Today"
                    value={Number(data.today.buy_usdt).toLocaleString()}
                    currency="USDT"
                    icon={ArrowDownToLine}
                />

                <StatCard
                    label="USDT Sold Today"
                    value={Number(data.today.sell_usdt).toLocaleString()}
                    currency="USDT"
                    icon={ArrowUpFromLine}
                />
            </div>

            <TransactionTable transactions={data.recent} />
        </>
    );
}