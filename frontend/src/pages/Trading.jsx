import { useState } from 'react';
import { ArrowDownToLine, ArrowUpFromLine } from 'lucide-react';
import { api } from '../services/api';

function requestId() {
    if (crypto.randomUUID) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random()
        .toString(16)
        .slice(2)}`;
}

export default function Trading() {
    const [type, setType] = useState('BUY_USDT');
    const [usdt, setUsdt] = useState('');
    const [rate, setRate] = useState('');
    const [result, setResult] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const aed =
        usdt && rate
            ? Number(usdt) * Number(rate)
            : 0;

    async function submit(e) {
        e.preventDefault();

        setError('');
        setResult(null);

        if (!usdt || !rate || Number(usdt) <= 0 || Number(rate) <= 0) {
            setError('Enter a valid USDT amount and rate.');
            return;
        }

        setLoading(true);

        try {
            const id = requestId();

            const response =
                type === 'BUY_USDT'
                    ? await api.buy(usdt, rate, id)
                    : await api.sell(usdt, rate, id);

            setResult(response);
            setUsdt('');
            setRate('');
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }

    return (
        <>
            <div className="page-heading">
                <div>
                    <h2>Trading</h2>
                    <p>Record physical AED / USDT trades.</p>
                </div>
            </div>

            <div className="trade-layout">
                <div className="trade-card">
                    <div className="trade-tabs">
                        <button
                            className={type === 'BUY_USDT' ? 'active buy-tab' : ''}
                            onClick={() => setType('BUY_USDT')}
                            type="button"
                        >
                            <ArrowDownToLine size={18} />
                            Buy USDT
                        </button>

                        <button
                            className={type === 'SELL_USDT' ? 'active sell-tab' : ''}
                            onClick={() => setType('SELL_USDT')}
                            type="button"
                        >
                            <ArrowUpFromLine size={18} />
                            Sell USDT
                        </button>
                    </div>

                    <form onSubmit={submit}>
                        <label>
                            USDT Amount
                            <input
                                type="number"
                                min="0"
                                step="0.000001"
                                value={usdt}
                                onChange={(e) => setUsdt(e.target.value)}
                                placeholder="100000"
                            />
                        </label>

                        <label>
                            Rate
                            <input
                                type="number"
                                min="0"
                                step="0.000001"
                                value={rate}
                                onChange={(e) => setRate(e.target.value)}
                                placeholder="3.66"
                            />
                        </label>

                        <div className="calculation">
                            <span>AED Amount</span>
                            <strong>
                                {aed.toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 6
                                })}
                                {' AED'}
                            </strong>
                        </div>

                        {error && (
                            <div className="form-error">
                                {error}
                            </div>
                        )}

                        {result && (
                            <div className="form-success">
                                Transaction completed.
                                {result.profit && (
                                    <> Profit: {result.profit} AED</>
                                )}
                            </div>
                        )}

                        <button
                            className={`primary-button ${type === 'SELL_USDT'
                                ? 'sell-button'
                                : ''
                                }`}
                            disabled={loading}
                        >
                            {loading
                                ? 'Processing...'
                                : type === 'BUY_USDT'
                                    ? 'Complete Buy'
                                    : 'Complete Sell'}
                        </button>
                    </form>
                </div>

                <div className="trade-info">
                    <h3>
                        {type === 'BUY_USDT'
                            ? 'Buy USDT'
                            : 'Sell USDT'}
                    </h3>

                    <p>
                        {type === 'BUY_USDT'
                            ? 'AED decreases and USDT increases.'
                            : 'USDT decreases and AED increases.'}
                    </p>

                    <div className="formula">
                        <span>USDT</span>
                        <b>×</b>
                        <span>Rate</span>
                        <b>=</b>
                        <strong>AED</strong>
                    </div>
                </div>
            </div>
        </>
    );
}