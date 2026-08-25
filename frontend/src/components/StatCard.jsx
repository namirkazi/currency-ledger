export default function StatCard({
    label,
    value,
    currency,
    icon: Icon,
    positive
}) {
    return (
        <div className="stat-card">
            <div className="stat-icon">
                <Icon size={20} />
            </div>

            <div className="stat-info">
                <span>{label}</span>

                <strong className={positive ? 'positive' : ''}>
                    {value}
                </strong>

                {currency && <small>{currency}</small>}
            </div>
        </div>
    );
}