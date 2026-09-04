import { useEffect, useState } from "react";

import { financialFacilitiesApi } from "../../services/financialFacilities";
import useauth from "../../hooks/useAuth";
import { printFacilityTransaction } from "../../utils/printFacilityTransactions";

function FacilityDetailsModal({ facilityId, onClose, onUpdated }) {
  const [data, setData] = useState(null);
  const { user } = useAuth();

  const isAdmin = user?.role?.toUpperCase() === "ADMIN";
  const [loading, setLoading] = useState(true);

  const [error, setError] = useState("");
  const [ledgerEntries, setLedgerEntries] = useState([]);
  const [actionLoading, setActionLoading] = useState(false);
  const [confirmationAction, setConfirmationAction] = useState(null);
  const [remarks, setRemarks] = useState("");
  const [activeTab, setActiveTab] = useState("OVERVIEW");

  /*
    |--------------------------------------------------------------------------
    | Load Details
    |--------------------------------------------------------------------------
    */

  const loadDetails = async () => {
    try {
      setLoading(true);
      setError("");

      const [detailsResponse, ledgerResponse] = await Promise.all([
        financialFacilitiesApi.details(facilityId),
        financialFacilitiesApi.ledger(facilityId),
      ]);

      const result = detailsResponse.data ?? detailsResponse;

      if (!result.success) {
        throw new Error(result.message || "Unable to load facility.");
      }

      setData(result);

      const ledgerResult = ledgerResponse.data ?? ledgerResponse;

      if (ledgerResult.success) {
        setLedgerEntries(ledgerResult.entries || ledgerResult.data || []);
      } else {
        setLedgerEntries([]);
      }
    } catch (err) {
      console.error(err);

      setError(err.message || "Unable to load facility.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadDetails();
  }, [facilityId]);

  /*
    |--------------------------------------------------------------------------
    | Action Handler
    |--------------------------------------------------------------------------
    */

  const handleAction = async (action) => {
    if (!data?.facility) return;

    const facility = data.facility;

    // Actions that need a proper confirmation modal
    if (["approve", "reject", "cancel"].includes(action)) {
      setConfirmationAction(action);
      setRemarks("");
      return;
    }

    if (action === "repayment") {
      const amount = window.prompt("Repayment amount:");

      if (!amount || Number(amount) <= 0) {
        return;
      }

      try {
        setActionLoading(true);

        await financialFacilitiesApi.repayment({
          facility_id: facility.id,
          amount,
        });

        await loadDetails();
      } catch (err) {
        alert(err.message || "Unable to record repayment.");
      } finally {
        setActionLoading(false);
      }

      return;
    }

    try {
      setActionLoading(true);

      if (action === "disburse") {
        await financialFacilitiesApi.disburse(facility.id);
      }

      await loadDetails();
    } catch (err) {
      console.error(err);
      alert(err.message || "Action failed.");
    } finally {
      setActionLoading(false);
    }
  };
  const confirmAction = async () => {
    if (!data?.facility || !confirmationAction) return;

    const facility = data.facility;

    // Require remarks for rejection/cancellation
    if (["reject", "cancel"].includes(confirmationAction) && !remarks.trim()) {
      return;
    }

    try {
      setActionLoading(true);

      if (confirmationAction === "approve") {
        await financialFacilitiesApi.approve(
          facility.id,
          remarks.trim() || "Facility approved",
        );
      }

      if (confirmationAction === "reject") {
        await financialFacilitiesApi.reject(facility.id, remarks.trim());
      }

      if (confirmationAction === "cancel") {
        await financialFacilitiesApi.cancel(facility.id, remarks.trim());
      }

      setConfirmationAction(null);
      setRemarks("");

      await loadDetails();

      if (onUpdated) {
        onUpdated();
      }
    } catch (err) {
      console.error(err);
      alert(err.message || "Action failed.");
    } finally {
      setActionLoading(false);
    }
  };
  /*
    |--------------------------------------------------------------------------
    | Formatter
    |--------------------------------------------------------------------------
    */

  const formatAmount = (amount, currency) => {
    return `${currency || ""} ${Number(amount || 0).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  };

  if (loading) {
    return (
      <div className="modal-overlay">
        <div className="facility-modal loading-modal">
          Loading facility details...
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="modal-overlay">
        <div className="facility-modal">
          <div className="modal-header">
            <h2>Unable to load facility</h2>

            <button className="modal-close" onClick={onClose}>
              ×
            </button>
          </div>

          <div className="form-error">{error}</div>
        </div>
      </div>
    );
  }

  const facility = data.facility;

  return (
    <div className="modal-overlay" onMouseDown={onClose}>
      <div
        className="facility-modal details-modal"
        onMouseDown={(event) => event.stopPropagation()}
      >
        {/* HEADER */}

        <div className="modal-header">
          <div>
            <div className="modal-eyebrow">{facility.reference_number}</div>

            <h2>Financial Facility</h2>

            <p>
              {facility.lender_company_name}
              {" → "}
              {facility.borrower_company_name}
            </p>
          </div>

          <div className="details-header-actions">
            <span className={`status-badge ${facility.status?.toLowerCase()}`}>
              {facility.status}
            </span>

            <button className="modal-close" onClick={onClose}>
              ×
            </button>
          </div>
        </div>

        {/* SUMMARY */}

        <div className="facility-summary-grid">
          <div>
            <span>Principal</span>

            <strong>
              {formatAmount(facility.principal_amount, facility.currency_code)}
            </strong>
          </div>

          <div>
            <span>Outstanding</span>

            <strong>
              {formatAmount(
                facility.outstanding_amount,
                facility.currency_code,
              )}
            </strong>
          </div>

          <div>
            <span>Total Repaid</span>

            <strong>
              {formatAmount(data.summary?.total_repaid, facility.currency_code)}
            </strong>
          </div>

          <div>
            <span>Due Date</span>

            <strong>{facility.due_date || "—"}</strong>
          </div>
        </div>

        {/* ACTIONS */}

        <div className="facility-actions">
          {facility.status === "PENDING_APPROVAL" &&
            (isAdmin ? (
              <>
                <button
                  type="button"
                  onClick={() => handleAction("approve")}
                  disabled={actionLoading}
                >
                  {actionLoading ? "Processing..." : "Approve"}
                </button>

                <button
                  type="button"
                  onClick={() => handleAction("reject")}
                  disabled={actionLoading}
                >
                  Reject
                </button>
              </>
            ) : (
              <div
                style={{
                  padding: "10px 14px",
                  borderRadius: "8px",
                  background: "#fff7ed",
                  border: "1px solid #fed7aa",
                  color: "#9a3412",
                  fontSize: "14px",
                  fontWeight: 500,
                }}
              >
                Waiting for admin approval
              </div>
            ))}

          {facility.status === "APPROVED" && (
            <>
              <button
                className="action-btn disburse"
                disabled={actionLoading}
                onClick={() => handleAction("disburse")}
              >
                Disburse Funds
              </button>

              <button
                className="action-btn cancel"
                disabled={actionLoading}
                onClick={() => handleAction("cancel")}
              >
                Cancel
              </button>
            </>
          )}

          {(facility.status === "DISBURSED" ||
            facility.status === "PARTIALLY_REPAID") && (
            <button
              className="action-btn repayment"
              disabled={actionLoading}
              onClick={() => handleAction("repayment")}
            >
              Record Repayment
            </button>
          )}
        </div>

        {/* TABS */}

        <div className="facility-tabs">
          {["OVERVIEW", "APPROVALS", "LEDGER"].map((tab) => (
            <button
              key={tab}
              className={
                activeTab === tab ? "facility-tab active" : "facility-tab"
              }
              onClick={() => setActiveTab(tab)}
            >
              {tab}
            </button>
          ))}
        </div>

        {/* TAB CONTENT */}

        <div className="facility-tab-content">
          {activeTab === "OVERVIEW" && (
            <div className="facility-overview">
              <div className="overview-row">
                <span>Facility Type</span>

                <strong>{facility.facility_type}</strong>
              </div>

              <div className="overview-row">
                <span>Lender</span>

                <strong>{facility.lender_company_name}</strong>
              </div>

              <div className="overview-row">
                <span>Borrower</span>

                <strong>{facility.borrower_company_name}</strong>
              </div>

              <div className="overview-row">
                <span>Interest Rate</span>

                <strong>
                  {facility.interest_rate
                    ? `${facility.interest_rate}%`
                    : "Not specified"}
                </strong>
              </div>

              <div className="overview-row">
                <span>Request Date</span>

                <strong>{facility.request_date}</strong>
              </div>

              <div className="overview-row">
                <span>Disbursement Date</span>

                <strong>{facility.disbursement_date || "Not disbursed"}</strong>
              </div>

              <div className="overview-purpose">
                <span>Purpose</span>

                <p>{facility.purpose || "—"}</p>
              </div>
            </div>
          )}

          {activeTab === "APPROVALS" && (
            <div className="timeline">
              {data.approval_history?.length === 0 && (
                <div className="empty-history">No approval history.</div>
              )}

              {data.approval_history?.map((item) => (
                <div key={item.id} className="timeline-item">
                  <div className="timeline-marker" />

                  <div>
                    <strong>{item.action}</strong>

                    <p>{item.performed_by_name || "System"}</p>

                    {item.remarks && (
                      <p className="timeline-remarks">{item.remarks}</p>
                    )}

                    <small>{item.created_at}</small>
                  </div>
                </div>
              ))}
            </div>
          )}

          {activeTab === "LEDGER" && (
            <div className="facility-ledger">
              {ledgerEntries.length === 0 && (
                <div className="empty-history">
                  No financial transactions recorded yet.
                </div>
              )}

              {ledgerEntries.map((entry) => (
                <div key={entry.id} className="facility-ledger-item">
                  <div className="ledger-entry-main">
                    <div
                      className={`ledger-entry-icon ${entry.entry_type?.toLowerCase()}`}
                    >
                      {entry.entry_type === "DISBURSEMENT" && "↓"}

                      {entry.entry_type === "REPAYMENT" && "↑"}

                      {entry.entry_type === "INTEREST" && "%"}

                      {entry.entry_type === "ADJUSTMENT" && "±"}

                      {entry.entry_type === "SETTLEMENT" && "✓"}
                    </div>

                    <div className="ledger-entry-info">
                      <strong>{entry.entry_type?.replaceAll("_", " ")}</strong>

                      <span>{entry.created_at}</span>

                      {entry.remarks && <p>{entry.remarks}</p>}
                    </div>
                  </div>

                  <div className="ledger-entry-right">
                    <strong>
                      {formatAmount(entry.amount, facility.currency_code)}
                    </strong>

                    <button
                      className="print-transaction-btn"
                      onClick={() =>
                        printFacilityTransaction({
                          facility,
                          transaction: entry,
                          company: "Currency Ledger",
                        })
                      }
                    >
                      Print Receipt
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* FOOTER */}

        <div className="modal-footer">
          <button className="secondary-btn" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
      {confirmationAction && (
        <div
          className="action-confirmation-overlay"
          onMouseDown={() => {
            if (!actionLoading) {
              setConfirmationAction(null);
              setRemarks("");
            }
          }}
        >
          <div
            className="action-confirmation-modal"
            onMouseDown={(event) => event.stopPropagation()}
          >
            <div className="confirmation-icon">
              {confirmationAction === "approve" && "✓"}
              {confirmationAction === "reject" && "!"}
              {confirmationAction === "cancel" && "×"}
            </div>

            <div className="confirmation-content">
              <span className="confirmation-eyebrow">CONFIRM ACTION</span>

              <h3>
                {confirmationAction === "approve" && "Approve Facility?"}
                {confirmationAction === "reject" && "Reject Facility?"}
                {confirmationAction === "cancel" && "Cancel Facility?"}
              </h3>

              <p>
                {confirmationAction === "approve" &&
                  "This facility will be approved and moved to the next stage."}

                {confirmationAction === "reject" &&
                  "This facility will be rejected. Please provide a reason below."}

                {confirmationAction === "cancel" &&
                  "This facility will be cancelled. Please provide a reason below."}
              </p>

              <div className="confirmation-remarks">
                <label>
                  Remarks
                  {["reject", "cancel"].includes(confirmationAction) && (
                    <span>Required</span>
                  )}
                </label>

                <textarea
                  value={remarks}
                  onChange={(event) => setRemarks(event.target.value)}
                  placeholder={
                    confirmationAction === "approve"
                      ? "Add approval remarks (optional)..."
                      : "Enter the reason..."
                  }
                  rows="4"
                  autoFocus
                />
              </div>

              <div className="confirmation-actions">
                <button
                  className="confirmation-cancel-btn"
                  disabled={actionLoading}
                  onClick={() => {
                    setConfirmationAction(null);
                    setRemarks("");
                  }}
                >
                  Cancel
                </button>

                <button
                  className={`confirmation-submit-btn ${confirmationAction}`}
                  disabled={
                    actionLoading ||
                    (["reject", "cancel"].includes(confirmationAction) &&
                      !remarks.trim())
                  }
                  onClick={confirmAction}
                >
                  {actionLoading
                    ? "Processing..."
                    : confirmationAction === "approve"
                      ? "Approve Facility"
                      : confirmationAction === "reject"
                        ? "Reject Facility"
                        : "Cancel Facility"}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default FacilityDetailsModal;
