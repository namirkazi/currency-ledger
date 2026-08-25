import { useEffect, useState } from 'react';
import {
    UserPlus,
    UserX
} from 'lucide-react';

import { api } from '../../services/api';
import styles from './Users.module.css';

const emptyForm = {
    name: '',
    username: '',
    password: '',
    role: 'USER'
};

export default function Users() {
    const [users, setUsers] = useState([]);
    const [form, setForm] = useState(emptyForm);
    const [showForm, setShowForm] = useState(false);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    async function loadUsers() {
        try {
            setLoading(true);
            const data = await api.users();
            setUsers(data.users);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        loadUsers();
    }, []);

    function handleChange(event) {
        const { name, value } = event.target;

        setForm((current) => ({
            ...current,
            [name]: value
        }));
    }

    async function handleSubmit(event) {
        event.preventDefault();

        setError('');
        setSaving(true);

        try {
            await api.createUser(form);

            setForm(emptyForm);
            setShowForm(false);

            await loadUsers();
        } catch (err) {
            setError(err.message);
        } finally {
            setSaving(false);
        }
    }

    async function handleDeactivate(id) {
        if (!window.confirm(
            'Deactivate this user? They will no longer be able to log in.'
        )) {
            return;
        }

        try {
            setError('');

            await api.deactivateUser(id);
            await loadUsers();
        } catch (err) {
            setError(err.message);
        }
    }

    return (
        <div className={styles.page}>

            <div className={styles.heading}>
                <div>
                    <h2>Users</h2>
                    <p>
                        Manage who can access the ledger.
                    </p>
                </div>

                <button
                    className={styles.primaryButton}
                    onClick={() => {
                        setShowForm((value) => !value);
                        setError('');
                    }}
                >
                    <UserPlus size={18} />
                    Add User
                </button>
            </div>

            {error && (
                <div className={styles.error}>
                    {error}
                </div>
            )}

            {showForm && (
                <form
                    className={styles.form}
                    onSubmit={handleSubmit}
                >
                    <div className={styles.field}>
                        <label>Name</label>
                        <input
                            name="name"
                            value={form.name}
                            onChange={handleChange}
                            required
                        />
                    </div>

                    <div className={styles.field}>
                        <label>Username</label>
                        <input
                            name="username"
                            value={form.username}
                            onChange={handleChange}
                            required
                        />
                    </div>

                    <div className={styles.field}>
                        <label>Password</label>
                        <input
                            type="password"
                            name="password"
                            value={form.password}
                            onChange={handleChange}
                            minLength={8}
                            required
                        />
                    </div>

                    <div className={styles.field}>
                        <label>Role</label>

                        <select
                            name="role"
                            value={form.role}
                            onChange={handleChange}
                        >
                            <option value="USER">
                                User
                            </option>

                            <option value="ADMIN">
                                Administrator
                            </option>
                        </select>
                    </div>

                    <button
                        className={styles.primaryButton}
                        disabled={saving}
                    >
                        {saving
                            ? 'Creating...'
                            : 'Create User'}
                    </button>
                </form>
            )}

            <div className={styles.card}>
                {loading ? (
                    <div className={styles.empty}>
                        Loading users...
                    </div>
                ) : users.length === 0 ? (
                    <div className={styles.empty}>
                        No users found.
                    </div>
                ) : (
                    <div className={styles.tableWrapper}>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th />
                                </tr>
                            </thead>

                            <tbody>
                                {users.map((user) => (
                                    <tr key={user.id}>
                                        <td>{user.name}</td>
                                        <td>{user.username}</td>

                                        <td>
                                            <span
                                                className={
                                                    user.role === 'ADMIN'
                                                        ? styles.admin
                                                        : styles.user
                                                }
                                            >
                                                {user.role}
                                            </span>
                                        </td>

                                        <td>
                                            <span
                                                className={
                                                    user.is_active
                                                        ? styles.active
                                                        : styles.inactive
                                                }
                                            >
                                                {user.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </span>
                                        </td>

                                        <td>
                                            {new Date(
                                                user.created_at
                                            ).toLocaleDateString()}
                                        </td>

                                        <td>
                                            {user.is_active === 1 && (
                                                <button
                                                    className={styles.iconButton}
                                                    title="Deactivate user"
                                                    onClick={() =>
                                                        handleDeactivate(user.id)
                                                    }
                                                >
                                                    <UserX size={17} />
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

        </div>
    );
}