import { useEffect, useMemo, useState } from "react";
import "./FinancialFacilities.css";

import { financialFacilitiesApi } from "../../services/financialFacilities";

import CreateFacilityModal from "./CreateFacilityModal";
import FacilityDetailsModal from "./FacilityDetailsModal";

function FinancialFacilities() {
  const [facilities, setFacilities] = useState([]);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [activeFilter, setActiveFilter] = useState("ALL");
  const [search, setSearch] = useState("");

  const [showCreateModal, setShowCreateModal] = useState(false);

  const [selectedFacility, setSelectedFacility] = useState(null);

  /*
    |--------------------------------------------------------------------------
    | Load Facilities
    |--------------------------------------------------------------------------
    */

  const loadFacilities = async () => {
    try {
      setLoading(true);
      setError("");

      const response = await financialFacilitiesApi.list();

      const data = response.data ?? response;

      if (!data.success) {
        throw new Error(data.message || "Unable to load facilities.");
      }

      setFacilities(data.facilities || data.data || []);
    } catch (err) {
      console.error("Failed to load facilities:", err);

      setError(err.message || "Unable to load financial facilities.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadFacilities();
  }, []);

  /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    */

  const stats = useMemo(() => {
    let outstanding = 0;
    let active = 0;
    let pending = 0;
    let overdue = 0;

    const today = new Date();

    facilities.forEach((facility) => {
      const outstandingAmount = Number(facility.outstanding_amount || 0);

      if (facility.status === "ACTIVE") {
        active++;

        outstanding += outstandingAmount;
      }

      if (facility.status === "PENDING_APPROVAL") {
        pending++;
      }

      if (facility.status === "ACTIVE" && facility.due_date) {
        const dueDate = new Date(facility.due_date);

        if (dueDate < today) {
          overdue += outstandingAmount;
        }
      }
    });

    return {
      outstanding,
      active,
      pending,
      overdue,
    };
  }, [facilities]);

  /*
    |--------------------------------------------------------------------------
    | Filtered Facilities
    |--------------------------------------------------------------------------
    */

  const filteredFacilities = useMemo(() => {
    return facilities.filter((facility) => {
      const matchesStatus =
        activeFilter === "ALL" || facility.status === activeFilter;

      const searchTerm = search.toLowerCase();

      const matchesSearch =
        !searchTerm ||
        facility.reference_number?.toLowerCase().includes(searchTerm) ||
        facility.lender_company_name?.toLowerCase().includes(searchTerm) ||
        facility.borrower_company_name?.toLowerCase().includes(searchTerm) ||
        facility.facility_type?.toLowerCase().includes(searchTerm);

      return matchesStatus && matchesSearch;
    });
  }, [facilities, activeFilter, search]);

  /*
    |--------------------------------------------------------------------------
    | Currency Formatter
    |--------------------------------------------------------------------------
    */

  const formatAmount = (amount, currency = "") => {
    const value = Number(amount || 0);

    return `${currency} ${value.toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  };

  return (
    <div className="facilities-page">
      {/* ============================================================
                HEADER
            ============================================================ */}

      <div className="facilities-header">
        <div>
          <div className="page-eyebrow">TREASURY MANAGEMENT</div>

          <h1>Financial Facilities</h1>

          <p>Manage lending, borrowing and temporary bridging facilities.</p>
        </div>

        <button
          className="create-facility-btn"
          onClick={() => setShowCreateModal(true)}
        >
          <span>+</span>
          New Facility
        </button>
      </div>

      {/* ============================================================
                STATISTICS
            ============================================================ */}

      <div className="facility-stats-grid">
        <div className="facility-stat-card">
          <div className="stat-top">
            <span className="stat-label">Total Outstanding</span>

            <span className="stat-icon">◈</span>
          </div>

          <div className="stat-value">{formatAmount(stats.outstanding)}</div>

          <div className="stat-subtext">Across active facilities</div>
        </div>

        <div className="facility-stat-card">
          <div className="stat-top">
            <span className="stat-label">Active Facilities</span>

            <span className="stat-icon">↗</span>
          </div>

          <div className="stat-value">{stats.active}</div>

          <div className="stat-subtext">Currently outstanding</div>
        </div>

        <div className="facility-stat-card">
          <div className="stat-top">
            <span className="stat-label">Pending Approval</span>

            <span className="stat-icon">◷</span>
          </div>

          <div className="stat-value">{stats.pending}</div>

          <div className="stat-subtext">Awaiting decision</div>
        </div>

        <div className="facility-stat-card">
          <div className="stat-top">
            <span className="stat-label">Overdue Exposure</span>

            <span className="stat-icon">!</span>
          </div>

          <div className="stat-value">{formatAmount(stats.overdue)}</div>

          <div className="stat-subtext">Outstanding past due date</div>
        </div>
      </div>

      {/* ============================================================
                FACILITIES SECTION
            ============================================================ */}

      <div className="facilities-panel">
        <div className="facility-toolbar">
          {/* Filters */}

          <div className="facility-filters">
            {[
              { value: "ALL", label: "ALL" },
              { value: "PENDING_APPROVAL", label: "PENDING" },
              { value: "APPROVED", label: "APPROVED" },
              { value: "ACTIVE", label: "ACTIVE" },
              { value: "REPAID", label: "REPAID" },
              { value: "REJECTED", label: "REJECTED" },
              { value: "CANCELLED", label: "CANCELLED" },
            ].map((filter) => (
              <button
                key={filter.value}
                className={
                  activeFilter === filter.value
                    ? "facility-filter active"
                    : "facility-filter"
                }
                onClick={() => setActiveFilter(filter.value)}
              >
                {filter.label}
              </button>
            ))}
          </div>

          {/* Search */}

          <div className="facility-search">
            <span>⌕</span>

            <input
              type="text"
              placeholder="Search facilities..."
              value={search}
              onChange={(event) => setSearch(event.target.value)}
            />
          </div>
        </div>

        {/* ========================================================
                    CONTENT
                ======================================================== */}

        {loading ? (
          <div className="facilities-loading">
            Loading financial facilities...
          </div>
        ) : error ? (
          <div className="facilities-error">
            <h3>Unable to load facilities</h3>

            <p>{error}</p>

            <button onClick={loadFacilities}>Try Again</button>
          </div>
        ) : filteredFacilities.length === 0 ? (
          <div className="empty-facilities">
            <div className="empty-facility-icon">◫</div>

            <h3>No financial facilities found</h3>

            <p>
              {facilities.length === 0
                ? "Create a lending, borrowing or bridging facility to get started."
                : "No facilities match the current filters."}
            </p>

            {facilities.length === 0 && (
              <button
                className="empty-create-btn"
                onClick={() => setShowCreateModal(true)}
              >
                Create Facility
              </button>
            )}
          </div>
        ) : (
          <div className="facilities-table-wrapper">
            <table className="facilities-table">
              <thead>
                <tr>
                  <th>Reference</th>

                  <th>Type</th>

                  <th>Parties</th>

                  <th>Principal</th>

                  <th>Outstanding</th>

                  <th>Due Date</th>

                  <th>Status</th>

                  <th>Action</th>
                </tr>
              </thead>

              <tbody>
                {filteredFacilities.map((facility) => (
                  <tr key={facility.id}>
                    <td>
                      <div className="reference-number">
                        {facility.reference_number}
                      </div>

                      <div className="table-date">{facility.request_date}</div>
                    </td>

                    <td>
                      <span
                        className={`type-badge ${facility.facility_type?.toLowerCase()}`}
                      >
                        {facility.facility_type}
                      </span>
                    </td>

                    <td>
                      <div className="party-flow">
                        <span>{facility.lender_company_name}</span>

                        <span className="party-arrow">→</span>

                        <span>{facility.borrower_company_name}</span>
                      </div>
                    </td>

                    <td>
                      {formatAmount(
                        facility.principal_amount,
                        facility.currency_code,
                      )}
                    </td>

                    <td>
                      <strong>
                        {formatAmount(
                          facility.outstanding_amount,
                          facility.currency_code,
                        )}
                      </strong>
                    </td>

                    <td>{facility.due_date || "—"}</td>

                    <td>
                      <span
                        className={`status-badge ${facility.status?.toLowerCase()}`}
                      >
                        {facility.status}
                      </span>
                    </td>

                    <td>
                      <button
                        className="view-facility-btn"
                        onClick={() => setSelectedFacility(facility)}
                      >
                        View
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* ============================================================
                CREATE MODAL
            ============================================================ */}

      {showCreateModal && (
        <CreateFacilityModal
          onClose={() => setShowCreateModal(false)}
          onCreated={() => {
            setShowCreateModal(false);

            loadFacilities();
          }}
        />
      )}

      {/* ============================================================
                DETAILS MODAL
            ============================================================ */}

      {selectedFacility && (
        <FacilityDetailsModal
          facilityId={selectedFacility.id}
          onClose={() => setSelectedFacility(null)}
          onUpdated={() => {
            loadFacilities();

            setSelectedFacility(null);
          }}
        />
      )}
    </div>
  );
}

export default FinancialFacilities;
