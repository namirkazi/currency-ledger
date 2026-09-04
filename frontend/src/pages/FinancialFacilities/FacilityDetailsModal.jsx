import { useEffect, useState } from "react";

import { financialFacilitiesApi } from "../../services/financialFacilities";
import { useAuth } from "../../hooks/useAuth";
import { printFacilityTransaction } from "../../utils/printFacilityTransactions";

function FacilityDetailsModal({ facilityId, onClose, onUpdated }) {
  const { user } = useAuth();

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [ledgerEntries, setLedgerEntries] = useState([]);

  const [actionLoading, setActionLoading] = useState(false);
  const [confirmationAction, setConfirmationAction] = useState(null);

  const [remarks, setRemarks] = useState("");
  const [repaymentAmount, setRepaymentAmount] = useState("");
  const [actionError, setActionError] = useState("");

  const [activeTab, setActiveTab] = useState("OVERVIEW");

  const isAdmin = user?.role?.toUpperCase() === "ADMIN";

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
    | Open Confirmation Modal
    |--------------------------------------------------------------------------
    */

  const handleAction = (action) => {
    if (!data?.facility) return;

    setConfirmationAction(action);
    setRemarks("");
    setRepaymentAmount("");
    setActionError("");
  };

  /*
    |--------------------------------------------------------------------------
    | Close Confirmation Modal
    |--------------------------------------------------------------------------
    */

  const closeConfirmation = () => {
    if (actionLoading) return;

    setConfirmationAction(null);
    setRemarks("");
    setRepaymentAmount("");
    setActionError("");
  };

  /*
    |--------------------------------------------------------------------------
    | Confirm Action
    |--------------------------------------------------------------------------
    */

  const confirmAction = async () => {
    if (!data?.facility || !confirmationAction) {
      return;
    }

    const facility = data.facility;

    setActionError("");

    /*
      |--------------------------------------------------------------------------
      | Validate Reject / Cancel
      |--------------------------------------------------------------------------
      */

    if (["reject", "cancel"].includes(confirmationAction) && !remarks.trim()) {
      setActionError("Please provide a reason before continuing.");

      return;
    }

    /*
      |--------------------------------------------------------------------------
      | Validate Repayment
      |--------------------------------------------------------------------------
      */

    if (confirmationAction === "repayment") {
      const amount = Number(repaymentAmount);
      const outstanding = Number(facility.outstanding_amount || 0);

      if (!amount || amount <= 0) {
        setActionError("Please enter a valid repayment amount.");

        return;
      }

      if (amount > outstanding) {
        setActionError(
          "Repayment amount cannot exceed the outstanding balance.",
        );

        return;
      }
    }

    try {
      setActionLoading(true);

      /*
        |--------------------------------------------------------------------------
        | Approve
        |--------------------------------------------------------------------------
        */

      if (confirmationAction === "approve") {
        await financialFacilitiesApi.approve(
          facility.id,
          remarks.trim() || "Facility approved",
        );
      }

      /*
        |--------------------------------------------------------------------------
        | Reject
        |--------------------------------------------------------------------------
        */

      if (confirmationAction === "reject") {
        await financialFacilitiesApi.reject(facility.id, remarks.trim());
      }

      /*
        |--------------------------------------------------------------------------
        | Cancel
        |--------------------------------------------------------------------------
        */

      if (confirmationAction === "cancel") {
        await financialFacilitiesApi.cancel(facility.id, remarks.trim());
      }

      /*
        |--------------------------------------------------------------------------
        | Disburse
        |--------------------------------------------------------------------------
        */

      if (confirmationAction === "disburse") {
        await financialFacilitiesApi.disburse(facility.id);
      }

      /*
        |--------------------------------------------------------------------------
        | Repayment
        |--------------------------------------------------------------------------
        */

      if (confirmationAction === "repayment") {
        await financialFacilitiesApi.repayment({
          facility_id: facility.id,
          amount: Number(repaymentAmount),
        });
      }

      setConfirmationAction(null);
      setRemarks("");
      setRepaymentAmount("");
      setActionError("");

      await loadDetails();

      if (onUpdated) {
        onUpdated();
      }
    } catch (err) {
      console.error(err);

      setActionError(err.message || "Unable to complete this action.");
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

  /*
    |--------------------------------------------------------------------------
    | Confirmation Configuration
    |--------------------------------------------------------------------------
    */

  const confirmationConfig = {
    approve: {
      eyebrow: "Approval Required",
      title: "Approve financial facility?",
      description:
        "This will approve the facility and allow the funds to be disbursed.",
      confirmLabel: "Approve Facility",
      icon: "✓",
      tone: "success",
    },

    reject: {
      eyebrow: "Approval Decision",
      title: "Reject financial facility?",
      description:
        "This request will be rejected and will not proceed for disbursement.",
      confirmLabel: "Reject Facility",
      icon: "×",
      tone: "danger",
    },

    disburse: {
      eyebrow: "Funds Disbursement",
      title: "Confirm disbursement",
      description:
        "This will record the funds as disbursed and create a permanent ledger transaction.",
      confirmLabel: "Disburse Funds",
      icon: "↓",
      tone: "primary",
    },

    repayment: {
      eyebrow: "Repayment Transaction",
      title: "Record repayment",
      description:
        "Enter the amount received. The outstanding balance will be updated and a permanent ledger entry will be created.",
      confirmLabel: "Record Repayment",
      icon: "↑",
      tone: "primary",
    },

    cancel: {
      eyebrow: "Cancellation",
      title: "Cancel financial facility?",
      description:
        "This will cancel the facility. Please provide a reason for the cancellation.",
      confirmLabel: "Cancel Facility",
      icon: "×",
      tone: "danger",
    },
  };

  const activeConfirmation = confirmationAction
    ? confirmationConfig[confirmationAction]
    : null;

  /*
    |--------------------------------------------------------------------------
    | Loading
    |--------------------------------------------------------------------------
    */

  if (loading) {
    return (
      <div className="modal-overlay">
        <div className="facility-modal loading-modal">
          Loading facility details...
        </div>
      </div>
    );
  }

  /*
    |--------------------------------------------------------------------------
    | Error
    |--------------------------------------------------------------------------
    */

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
          {/* PENDING APPROVAL */}

          {facility.status === "PENDING_APPROVAL" &&
            (isAdmin ? (
              <>
                <button
                  type="button"
                  className="action-btn approve"
                  onClick={() => handleAction("approve")}
                  disabled={actionLoading}
                >
                  Approve
                </button>

                <button
                  type="button"
                  className="action-btn reject"
                  onClick={() => handleAction("reject")}
                  disabled={actionLoading}
                >
                  Reject
                </button>
              </>
            ) : (
              <div className="waiting-approval">Waiting for admin approval</div>
            ))}

          {/* APPROVED */}

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

          {/* DISBURSED / PARTIALLY REPAID */}

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
          {/* OVERVIEW */}

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

          {/* APPROVAL HISTORY */}

          {activeTab === "APPROVALS" && (
            <div className="timeline">
              {!data.approval_history?.length && (
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

          {/* LEDGER */}

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

      {/* CONFIRMATION MODAL */}

      {confirmationAction && activeConfirmation && (
        <div className="confirmation-overlay" onMouseDown={closeConfirmation}>
          <div
            className={`confirmation-modal ${activeConfirmation.tone}`}
            onMouseDown={(event) => event.stopPropagation()}
          >
            {/* TOP */}

            <div className="confirmation-top">
              <div className={`confirmation-icon ${activeConfirmation.tone}`}>
                {activeConfirmation.icon}
              </div>

              <button
                type="button"
                className="confirmation-close"
                disabled={actionLoading}
                onClick={closeConfirmation}
              >
                ×
              </button>
            </div>

            {/* CONTENT */}

            <div className="confirmation-content">
              <span className="confirmation-eyebrow">
                {activeConfirmation.eyebrow}
              </span>

              <h3>{activeConfirmation.title}</h3>

              <p>{activeConfirmation.description}</p>
            </div>

            {/* FINANCIAL SUMMARY */}

            <div className="confirmation-summary">
              <div>
                <span>Facility</span>

                <strong>{facility.reference_number}</strong>
              </div>

              <div>
                <span>
                  {confirmationAction === "repayment"
                    ? "Outstanding Balance"
                    : "Facility Amount"}
                </span>

                <strong>
                  {formatAmount(
                    confirmationAction === "repayment"
                      ? facility.outstanding_amount
                      : facility.principal_amount,
                    facility.currency_code,
                  )}
                </strong>
              </div>
            </div>

            {/* REPAYMENT AMOUNT */}

            {confirmationAction === "repayment" && (
              <div className="confirmation-input-group">
                <label>Repayment Amount</label>

                <div className="amount-input-wrap">
                  <span>{facility.currency_code}</span>

                  <input
                    type="number"
                    min="0"
                    max={facility.outstanding_amount}
                    step="0.01"
                    autoFocus
                    value={repaymentAmount}
                    onChange={(event) => {
                      setRepaymentAmount(event.target.value);
                      setActionError("");
                    }}
                    placeholder="0.00"
                  />
                </div>

                <small>
                  Maximum:{" "}
                  {formatAmount(
                    facility.outstanding_amount,
                    facility.currency_code,
                  )}
                </small>
              </div>
            )}

            {/* REMARKS */}

            {["approve", "reject", "cancel"].includes(confirmationAction) && (
              <div className="confirmation-input-group">
                <label>
                  Remarks
                  {["reject", "cancel"].includes(confirmationAction) && (
                    <span className="required-mark">*</span>
                  )}
                </label>

                <textarea
                  value={remarks}
                  autoFocus
                  onChange={(event) => {
                    setRemarks(event.target.value);
                    setActionError("");
                  }}
                  placeholder={
                    confirmationAction === "approve"
                      ? "Optional approval remarks..."
                      : "Provide a reason..."
                  }
                  rows="3"
                />
              </div>
            )}

            {/* ERROR */}

            {actionError && (
              <div className="confirmation-error">{actionError}</div>
            )}

            {/* ACTIONS */}

            <div className="confirmation-actions">
              <button
                type="button"
                className="confirmation-cancel"
                disabled={actionLoading}
                onClick={closeConfirmation}
              >
                Cancel
              </button>

              <button
                type="button"
                className={`confirmation-confirm ${activeConfirmation.tone}`}
                disabled={actionLoading}
                onClick={confirmAction}
              >
                {actionLoading
                  ? "Processing..."
                  : activeConfirmation.confirmLabel}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default FacilityDetailsModal;
