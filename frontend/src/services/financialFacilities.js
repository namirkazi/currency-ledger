import { api } from "./api";

const BASE_URL = "financial-facilities";

export const financialFacilitiesApi = {
  // ─────────────────────────────────────────────
  // GET ALL FACILITIES
  // ─────────────────────────────────────────────

  list: (params = {}) => {
    const query = new URLSearchParams(params).toString();

    const endpoint = query
      ? `${BASE_URL}/list.php?${query}`
      : `${BASE_URL}/list.php`;

    return api.request(endpoint);
  },

  // ─────────────────────────────────────────────
  // GET ONE FACILITY WITH HISTORY
  // ─────────────────────────────────────────────

  details: (id) => api.request(`${BASE_URL}/details.php?id=${id}`),

  // ─────────────────────────────────────────────
  // GET META DATA
  // ─────────────────────────────────────────────

  meta: () => api.request(`${BASE_URL}/meta.php`),

  // ─────────────────────────────────────────────
  // CREATE FACILITY
  // ─────────────────────────────────────────────

  create: (data) =>
    api.request(`${BASE_URL}/create.php`, {
      method: "POST",
      body: JSON.stringify(data),
    }),

  // ─────────────────────────────────────────────
  // APPROVE
  // ─────────────────────────────────────────────

  approve: (facilityId, remarks = "") =>
    api.request(`${BASE_URL}/approve.php`, {
      method: "POST",
      body: JSON.stringify({
        facility_id: facilityId,
        remarks,
      }),
    }),

  // ─────────────────────────────────────────────
  // REJECT
  // ─────────────────────────────────────────────

  reject: (facilityId, remarks) =>
    api.request(`${BASE_URL}/reject.php`, {
      method: "POST",
      body: JSON.stringify({
        facility_id: facilityId,
        remarks,
      }),
    }),

  // ─────────────────────────────────────────────
  // CANCEL
  // ─────────────────────────────────────────────

  cancel: (facilityId, remarks) =>
    api.request(`${BASE_URL}/cancel.php`, {
      method: "POST",
      body: JSON.stringify({
        facility_id: facilityId,
        remarks,
      }),
    }),

  // ─────────────────────────────────────────────
  // DISBURSE
  // ─────────────────────────────────────────────

  disburse: (facilityId, remarks = "") =>
    api.request(`${BASE_URL}/disburse.php`, {
      method: "POST",
      body: JSON.stringify({
        facility_id: facilityId,
        remarks,
      }),
    }),
  // ─────────────────────────────────────────────
  // RECORD REPAYMENT
  // ─────────────────────────────────────────────

  repayment: (data) =>
    api.request(`${BASE_URL}/repayment.php`, {
      method: "POST",
      body: JSON.stringify(data),
    }),

  ledger: (facilityId) =>
    api.request(`${BASE_URL}/ledger.php?facility_id=${facilityId}`),
};
