import { useEffect, useState } from "react";

import { financialFacilitiesApi } from "../../services/financialFacilities";

function CreateFacilityModal({ onClose, onCreated }) {
  const [meta, setMeta] = useState({
    companies: [],
    currencies: [],
    facility_types: [],
  });

  const [loading, setLoading] = useState(false);

  const [loadingMeta, setLoadingMeta] = useState(true);

  const [error, setError] = useState("");

  const [form, setForm] = useState({
    facility_type: "LENDING",

    lender_company_id: "",
    borrower_company_id: "",

    currency_id: "",

    principal_amount: "",

    interest_rate: "",

    request_date: new Date().toISOString().split("T")[0],

    due_date: "",

    purpose: "",
  });

  /*
    |--------------------------------------------------------------------------
    | Load Form Metadata
    |--------------------------------------------------------------------------
    */

  useEffect(() => {
    const loadMeta = async () => {
      try {
        setLoadingMeta(true);

        const response = await financialFacilitiesApi.meta();

        const data = response.data ?? response;

        if (!data.success) {
          throw new Error(data.message || "Unable to load form data.");
        }

        setMeta({
          companies: data.companies || [],

          currencies: data.currencies || [],

          facility_types: data.facility_types || [],
        });
      } catch (err) {
        console.error(err);

        setError(err.message || "Unable to load form data.");
      } finally {
        setLoadingMeta(false);
      }
    };

    loadMeta();
  }, []);

  /*
    |--------------------------------------------------------------------------
    | Handle Input
    |--------------------------------------------------------------------------
    */

  const handleChange = (event) => {
    const { name, value } = event.target;

    setForm((previous) => ({
      ...previous,
      [name]: value,
    }));
  };

  /*
    |--------------------------------------------------------------------------
    | Submit
    |--------------------------------------------------------------------------
    */

  const handleSubmit = async (e) => {
    e.preventDefault();

    setError("");

    // Basic validation
    if (!form.lender_company_id) {
      setError("Please select a lender.");
      return;
    }

    if (!form.borrower_company_id) {
      setError("Please select a borrower.");
      return;
    }

    if (!form.currency_id) {
      setError("Please select a currency.");
      return;
    }

    if (!form.principal_amount || Number(form.principal_amount) <= 0) {
      setError("Please enter a valid principal amount.");
      return;
    }

    try {
      setLoading(true);

      const payload = {
        facility_type: form.facility_type,

        lender_company_id: Number(form.lender_company_id),

        borrower_company_id: Number(form.borrower_company_id),

        currency_id: Number(form.currency_id),

        principal_amount: Number(form.principal_amount),

        // New facility starts with full amount outstanding
        outstanding_amount: Number(form.principal_amount),

        // Optional field defaults to zero
        interest_rate: Number(form.interest_rate || 0),

        request_date: form.request_date,

        due_date: form.due_date || null,

        purpose: form.purpose || null,
      };

      console.log("Creating facility with payload:", payload);

      const response = await financialFacilitiesApi.create(payload);

      const data = response.data ?? response;

      if (!data.success) {
        throw new Error(data.message || "Failed to create financial facility.");
      }

      onCreated?.(data);

      onClose();
    } catch (err) {
      console.error("Create facility error:", err);

      setError(err.message || "Failed to create financial facility.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="modal-overlay" onMouseDown={onClose}>
      <div
        className="facility-modal create-facility-modal"
        onMouseDown={(event) => event.stopPropagation()}
      >
        {/* Header */}

        <div className="modal-header">
          <div>
            <div className="modal-eyebrow">NEW RECORD</div>

            <h2>Create Financial Facility</h2>

            <p>
              Record a lending, borrowing or temporary bridging arrangement.
            </p>
          </div>

          <button className="modal-close" onClick={onClose}>
            ×
          </button>
        </div>

        {/* Content */}

        {loadingMeta ? (
          <div className="modal-loading">Loading form data...</div>
        ) : (
          <form onSubmit={handleSubmit} className="facility-form">
            {error && <div className="form-error">{error}</div>}

            {/* Type */}

            <div className="form-section">
              <h3>Facility Type</h3>

              <div className="type-selector">
                {meta.facility_types.map((type) => (
                  <button
                    type="button"
                    key={type.value}
                    className={
                      form.facility_type === type.value
                        ? "type-option selected"
                        : "type-option"
                    }
                    onClick={() =>
                      setForm((previous) => ({
                        ...previous,
                        facility_type: type.value,
                      }))
                    }
                  >
                    <strong>{type.label}</strong>

                    <span>
                      {type.value === "LENDING" &&
                        "Provide funds to another company."}

                      {type.value === "BORROWING" &&
                        "Receive funds from another company."}

                      {type.value === "BRIDGING" &&
                        "Temporary liquidity facility."}
                    </span>
                  </button>
                ))}
              </div>
            </div>

            {/* Parties */}

            <div className="form-section">
              <h3>Parties</h3>

              <div className="form-grid two-columns">
                <div className="form-group">
                  <label>Lender</label>

                  <select
                    name="lender_company_id"
                    value={form.lender_company_id}
                    onChange={handleChange}
                  >
                    <option value="">Select lender</option>

                    {meta.companies.map((company) => (
                      <option key={company.id} value={company.id}>
                        {company.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="form-group">
                  <label>Borrower</label>

                  <select
                    name="borrower_company_id"
                    value={form.borrower_company_id}
                    onChange={handleChange}
                  >
                    <option value="">Select borrower</option>

                    {meta.companies.map((company) => (
                      <option key={company.id} value={company.id}>
                        {company.name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
            </div>

            {/* Financial Details */}

            <div className="form-section">
              <h3>Financial Details</h3>

              <div className="form-grid three-columns">
                <div className="form-group">
                  <label>Currency</label>

                  <select
                    name="currency_id"
                    value={form.currency_id}
                    onChange={handleChange}
                  >
                    <option value="">Select currency</option>

                    {meta.currencies.map((currency) => (
                      <option key={currency.id} value={currency.id}>
                        {currency.code}
                        {" — "}
                        {currency.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="form-group">
                  <label>Principal Amount</label>

                  <input
                    type="number"
                    name="principal_amount"
                    value={form.principal_amount}
                    onChange={handleChange}
                    placeholder="0.00"
                    min="0"
                    step="0.01"
                  />
                </div>

                <div className="form-group">
                  <label>Interest Rate %</label>

                  <input
                    type="number"
                    name="interest_rate"
                    value={form.interest_rate}
                    onChange={handleChange}
                    placeholder="Optional"
                    min="0"
                    step="0.01"
                  />
                </div>
              </div>
            </div>

            {/* Dates */}

            <div className="form-section">
              <h3>Timeline</h3>

              <div className="form-grid two-columns">
                <div className="form-group">
                  <label>Request Date</label>

                  <input
                    type="date"
                    name="request_date"
                    value={form.request_date}
                    onChange={handleChange}
                  />
                </div>

                <div className="form-group">
                  <label>Due Date</label>

                  <input
                    type="date"
                    name="due_date"
                    value={form.due_date}
                    onChange={handleChange}
                  />
                </div>
              </div>
            </div>

            {/* Purpose */}

            <div className="form-section">
              <div className="form-group">
                <label>Purpose / Remarks</label>

                <textarea
                  name="purpose"
                  value={form.purpose}
                  onChange={handleChange}
                  placeholder="Describe the purpose of this facility..."
                  rows="4"
                />
              </div>
            </div>

            {/* Actions */}

            <div className="modal-footer">
              <button
                type="button"
                className="secondary-btn"
                onClick={onClose}
                disabled={loading}
              >
                Cancel
              </button>

              <button type="submit" className="primary-btn" disabled={loading}>
                {loading ? "Creating..." : "Create Facility"}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}

export default CreateFacilityModal;
