import { useEffect, useMemo, useState } from "react";
import {
  Building2,
  Plus,
  Search,
  Pencil,
  Landmark,
  CheckCircle2,
  XCircle,
} from "lucide-react";

import { getCompanies } from "../../services/companies";
import CompanyModal from "./CompanyModal";
import "./Companies.css";

export default function Companies() {
  const [companies, setCompanies] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState("ALL");

  const [showModal, setShowModal] = useState(false);
  const [selectedCompany, setSelectedCompany] = useState(null);

  const loadCompanies = async () => {
    try {
      setLoading(true);
      setError("");

      const response = await getCompanies();

      setCompanies(response.data || []);
    } catch (err) {
      setError(err?.message || "Failed to load companies.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadCompanies();
  }, []);

  const stats = useMemo(() => {
    const active = companies.filter(
      (company) => company.status === "ACTIVE",
    ).length;

    const inactive = companies.filter(
      (company) => company.status === "INACTIVE",
    ).length;

    return {
      total: companies.length,
      active,
      inactive,
    };
  }, [companies]);

  const filteredCompanies = useMemo(() => {
    return companies.filter((company) => {
      const matchesFilter = filter === "ALL" || company.status === filter;

      const searchValue = search.toLowerCase();

      const matchesSearch =
        company.name?.toLowerCase().includes(searchValue) ||
        company.legal_name?.toLowerCase().includes(searchValue) ||
        company.company_code?.toLowerCase().includes(searchValue);

      return matchesFilter && matchesSearch;
    });
  }, [companies, filter, search]);

  const openCreateModal = () => {
    setSelectedCompany(null);
    setShowModal(true);
  };

  const openEditModal = (company) => {
    setSelectedCompany(company);
    setShowModal(true);
  };

  if (loading) {
    return (
      <div className="companies-page">
        <div className="companies-loading">Loading companies...</div>
      </div>
    );
  }

  return (
    <div className="companies-page">
      {/* Header */}

      <div className="companies-header">
        <div>
          <div className="page-eyebrow">ORGANIZATION MANAGEMENT</div>

          <h1>Companies</h1>

          <p>Manage companies participating in treasury operations.</p>
        </div>

        <button className="create-company-btn" onClick={openCreateModal}>
          <Plus size={17} />
          New Company
        </button>
      </div>

      {/* Stats */}

      <div className="company-stats-grid">
        <div className="company-stat-card">
          <div className="stat-top">
            <span className="stat-label">Total Companies</span>

            <Building2 className="stat-icon" />
          </div>

          <div className="stat-value">{stats.total}</div>

          <div className="stat-subtext">Registered entities</div>
        </div>

        <div className="company-stat-card">
          <div className="stat-top">
            <span className="stat-label">Active Companies</span>

            <CheckCircle2 className="stat-icon" />
          </div>

          <div className="stat-value">{stats.active}</div>

          <div className="stat-subtext">Available for operations</div>
        </div>

        <div className="company-stat-card">
          <div className="stat-top">
            <span className="stat-label">Inactive Companies</span>

            <XCircle className="stat-icon" />
          </div>

          <div className="stat-value">{stats.inactive}</div>

          <div className="stat-subtext">Currently unavailable</div>
        </div>
      </div>

      {/* Main Panel */}

      <div className="companies-panel">
        <div className="company-toolbar">
          <div className="company-filters">
            {["ALL", "ACTIVE", "INACTIVE"].map((item) => (
              <button
                key={item}
                className={`company-filter ${filter === item ? "active" : ""}`}
                onClick={() => setFilter(item)}
              >
                {item}
              </button>
            ))}
          </div>

          <div className="company-search">
            <Search size={16} />

            <input
              type="text"
              placeholder="Search companies..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
        </div>

        {error ? (
          <div className="companies-error">
            {error}

            <button className="retry-btn" onClick={loadCompanies}>
              Try Again
            </button>
          </div>
        ) : filteredCompanies.length === 0 ? (
          <div className="empty-companies">
            <Landmark className="empty-company-icon" />

            <h3>No companies found</h3>

            <p>
              {companies.length === 0
                ? "Create your first company to begin managing treasury operations."
                : "No companies match your current filters."}
            </p>

            {companies.length === 0 && (
              <button className="empty-create-btn" onClick={openCreateModal}>
                Create Company
              </button>
            )}
          </div>
        ) : (
          <div className="companies-table-wrapper">
            <table className="companies-table">
              <thead>
                <tr>
                  <th>Company</th>
                  <th>Legal Name</th>
                  <th>Company Code</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th></th>
                </tr>
              </thead>

              <tbody>
                {filteredCompanies.map((company) => (
                  <tr key={company.id}>
                    <td>
                      <div className="company-name-cell">
                        <div className="company-avatar">
                          {company.name?.charAt(0)?.toUpperCase()}
                        </div>

                        <strong>{company.name}</strong>
                      </div>
                    </td>

                    <td>
                      {company.legal_name || (
                        <span className="muted-value">—</span>
                      )}
                    </td>

                    <td>
                      <span className="company-code">
                        {company.company_code}
                      </span>
                    </td>

                    <td>
                      <span
                        className={`company-status-badge ${company.status?.toLowerCase()}`}
                      >
                        {company.status}
                      </span>
                    </td>

                    <td>
                      {company.created_at
                        ? new Date(company.created_at).toLocaleDateString()
                        : "—"}
                    </td>

                    <td className="company-actions-cell">
                      <button
                        className="edit-company-btn"
                        onClick={() => openEditModal(company)}
                      >
                        <Pencil size={14} />
                        Edit
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Modal */}

      {showModal && (
        <CompanyModal
          company={selectedCompany}
          onClose={() => {
            setShowModal(false);
            setSelectedCompany(null);
          }}
          onSuccess={loadCompanies}
        />
      )}
    </div>
  );
}
