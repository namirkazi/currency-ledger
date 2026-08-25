import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { LockKeyhole, LogIn } from 'lucide-react';
import { api } from '../services/api';

export default function Login() {
    const navigate = useNavigate();

    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    async function submit(e) {
        e.preventDefault();

        setError('');
        setLoading(true);

        try {
            await api.login(username, password);
            navigate('/');
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="login-page">
            <div className="login-card">
                <div className="login-brand">
                    <div className="brand-mark">CL</div>
                    <h1>Currency Ledger</h1>
                    <p>AED / USDT Trading System</p>
                </div>

                <form onSubmit={submit}>
                    <label>
                        Username
                        <input
                            value={username}
                            onChange={(e) => setUsername(e.target.value)}
                            autoComplete="username"
                            required
                        />
                    </label>

                    <label>
                        Password
                        <input
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            autoComplete="current-password"
                            required
                        />
                    </label>

                    {error && (
                        <div className="form-error">
                            {error}
                        </div>
                    )}

                    <button
                        className="primary-button"
                        disabled={loading}
                    >
                        <LockKeyhole size={18} />
                        {loading ? 'Signing in...' : 'Sign in'}
                    </button>
                </form>
            </div>
        </div>
    );
}