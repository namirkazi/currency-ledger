import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom";

import Layout from "./components/Layout";
import ProtectedRoute from "./components/ProtectedRoute";
import Currencies from "./pages/Currencies";
import Login from "./pages/Login";
import Dashboard from "./pages/Dashboard";
import Trading from "./pages/Trading";
import Transactions from "./pages/Transactions";
import Reports from "./pages/Reports";
import Reconciliation from "./pages/Reconciliation";
import Users from "./pages/Users/Users";
import OpeningBalance from "./pages/OpeningBalance/OpeningBalance";
import BalanceManagement from "./pages/BalanceManagement";
import FinancialFacilities from "./pages/FinancialFacilities/FinancialFacilities";
import Companies from "./pages/Companies/Companies";

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<Login />} />

        <Route element={<ProtectedRoute />}>
          <Route element={<Layout />}>
            <Route path="/" element={<Dashboard />} />

            <Route path="/trading" element={<Trading />} />

            <Route path="/transactions" element={<Transactions />} />

            <Route path="/opening-balances" element={<OpeningBalance />} />
            <Route
              path="/financial-facilities"
              element={<FinancialFacilities />}
            />
            <Route path="/reports" element={<Reports />} />
            <Route path="/currencies" element={<Currencies />} />
            <Route path="/reconciliation" element={<Reconciliation />} />
            <Route path="/balance-management" element={<BalanceManagement />} />
            <Route element={<ProtectedRoute adminOnly />}>
              <Route path="/users" element={<Users />} />
              <Route path="/companies" element={<Companies />} />
            </Route>
          </Route>
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
