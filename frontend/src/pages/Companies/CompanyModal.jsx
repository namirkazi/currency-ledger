import { useEffect, useState } from "react";
import { createCompany, updateCompany } from "../../services/companies";
import "./Companies.css";

const initialForm = {
  name: "",
  legal_name: "",
  company_code: "",
  status: "ACTIVE",
};

export default function CompanyModal({ company, onClose, onSuccess }) {
  const [form, setForm] = useState(initialForm);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  const isEditing = Boolean(company);

  useEffect(() => {
    if (company) {
      setForm({
        name: company.name || "",
        legal_name: company.legal_name || "",
        company_code: company.company_code || "",
        status: company.status || "ACTIVE",
      });
    }
  }, [company]);

  const handleChange = (e) => {
    const { name, value } = e.target;

    setForm((prev) => ({
      ...prev,
      [name]: name === "company_code" ? value.toUpperCase() : value,
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    setError("");

    if (!form.name.trim()) {
      setError("Company name is required.");
      return;
    }

    if (!form.company_code.trim()) {
      setError("Company code is required.");
      return;
    }

    try {
      setSaving(true);

      const payload = {
        name: form.name.trim(),
        legal_name: form.legal_name.trim(),
        company_code: form.company_code.trim().toUpperCase(),
        status: form.status,
      };

      console.log("Company payload:", payload);

      if (isEditing) {
        await updateCompany({
          id: company.id,
          ...payload,
        });
      } else {
        await createCompany(payload);
      }

      onSuccess?.();
      onClose();
    } catch (err) {
      console.error("Company save error:", err);
      setError(err.message || "Failed to save company.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="modal-overlay">
      <div className="company-modal">
        <div className="modal-header">
          <div>
            <div className="modal-eyebrow">COMPANY MANAGEMENT</div>

            <h2>{isEditing ? "Edit Company" : "Add New Company"}</h2>

            <p>
              {isEditing
                ? "Update the company information below."
                : "Create a company for use across treasury operations."}
            </p>
          </div>

          <button className="modal-close" onClick={onClose} type="button">
            ×
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="company-form">
            {error && <div className="form-error">{error}</div>}

            <div className="form-section">
              <h3>Company Information</h3>

              <div className="form-grid two-columns">
                <div className="form-group">
                  <label>Company Name *</label>

                  <input
                    type="text"
                    name="name"
                    value={form.name}
                    onChange={handleChange}
                    placeholder="e.g. Ambitious Holdings"
                  />
                </div>

                <div className="form-group">
                  <label>Company Code *</label>

                  <input
                    type="text"
                    name="company_code"
                    value={form.company_code}
                    onChange={handleChange}
                    placeholder="e.g. AMB"
                    maxLength={20}
                  />
                </div>
              </div>

              <div className="form-grid">
                <div className="form-group">
                  <label>Legal Company Name</label>

                  <input
                    type="text"
                    name="legal_name"
                    value={form.legal_name}
                    onChange={handleChange}
                    placeholder="Registered legal entity name"
                  />
                </div>
              </div>
            </div>

            {isEditing && (
              <div className="form-section">
                <h3>Company Status</h3>

                <div className="company-status-selector">
                  <button
                    type="button"
                    className={`status-option ${
                      form.status === "ACTIVE" ? "selected" : ""
                    }`}
                    onClick={() =>
                      setForm((prev) => ({
                        ...prev,
                        status: "ACTIVE",
                      }))
                    }
                  >
                    <strong>Active</strong>
                    <span>Available for treasury operations</span>
                  </button>

                  <button
                    type="button"
                    className={`status-option ${
                      form.status === "INACTIVE" ? "selected" : ""
                    }`}
                    onClick={() =>
                      setForm((prev) => ({
                        ...prev,
                        status: "INACTIVE",
                      }))
                    }
                  >
                    <strong>Inactive</strong>
                    <span>Hidden from new operations</span>
                  </button>
                </div>
              </div>
            )}
          </div>

          <div className="modal-footer">
            <button type="button" className="secondary-btn" onClick={onClose}>
              Cancel
            </button>

            <button type="submit" className="primary-btn" disabled={saving}>
              {saving
                ? "Saving..."
                : isEditing
                  ? "Save Changes"
                  : "Create Company"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
