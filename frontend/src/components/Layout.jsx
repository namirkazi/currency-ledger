import { NavLink, Outlet, useNavigate } from "react-router-dom";
import {
  LayoutDashboard,
  ArrowLeftRight,
  ReceiptText,
  BarChart3,
  RefreshCcw,
  LogOut,
  Menu,
  X,
  SquareChartGantt,
} from "lucide-react";
import { useState } from "react";
import { api } from "../services/api";
import { WalletCards } from "lucide-react";
export default function Layout() {
  const [open, setOpen] = useState(false);
  const navigate = useNavigate();

  const links = [
    { to: "/", label: "Dashboard", icon: LayoutDashboard, end: true },
    { to: "/trading", label: "Trading", icon: ArrowLeftRight },
    { to: "/transactions", label: "Transactions", icon: ReceiptText },
  ];

  async function logout() {
    await api.logout();
    navigate("/login");
  }

  return (
    <div className="app-shell">
      <aside className={`sidebar ${open ? "open" : ""}`}>
        <div className="brand">
          <div className="brand-mark">CL</div>
          <div>
            <strong>Currency Ledger</strong>
            <span>AED / USDT</span>
          </div>
        </div>

        <nav>
          {links.map(({ to, label, icon: Icon, end }) => (
            <NavLink key={to} to={to} end={end} onClick={() => setOpen(false)}>
              <Icon size={19} />
              {label}
            </NavLink>
          ))}
        </nav>
        <nav>
          <NavLink to="/opening-balances" onClick={() => setOpen(false)}>
            <SquareChartGantt size={19} />
            Opening Balance
          </NavLink>
        </nav>
        <nav>
          <NavLink to="/balance-management" onClick={() => setOpen(false)}>
            <WalletCards size={19} />
            Balance Management
          </NavLink>
        </nav>
        <button className="logout-button" onClick={logout}>
          <LogOut size={18} />
          Logout
        </button>
      </aside>

      {open && (
        <div className="mobile-overlay" onClick={() => setOpen(false)} />
      )}

      <main className="main-content">
        <header className="topbar">
          <button className="mobile-menu" onClick={() => setOpen(!open)}>
            {open ? <X /> : <Menu />}
          </button>

          <div>
            <h1>Currency Ledger</h1>
            <p>Physical AED / USDT trading</p>
          </div>
        </header>

        <section className="page-content">
          <Outlet />
        </section>
      </main>
    </div>
  );
}
