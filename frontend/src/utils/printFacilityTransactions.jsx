export function printFacilityTransaction({ facility, transaction, company }) {
  const formatAmount = (amount, currency) => {
    return `${currency || ""} ${Number(amount || 0).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  };

  const formatDate = (date) => {
    if (!date) return "—";

    return new Date(date).toLocaleString(undefined, {
      year: "numeric",
      month: "long",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  const printWindow = window.open("", "_blank", "width=900,height=1000");

  if (!printWindow) {
    alert("Please allow popups to print the transaction.");
    return;
  }

  const receiptNumber = `TXN-${String(
    transaction.id || transaction.ledger_entry_id || "0",
  ).padStart(6, "0")}`;

  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>${receiptNumber}</title>

        <style>
          * {
            box-sizing: border-box;
          }

          html,
          body {
            margin: 0;
            padding: 0;
            width: 100%;
            background: #f3f4f6;
            font-family:
              Inter,
              -apple-system,
              BlinkMacSystemFont,
              "Segoe UI",
              sans-serif;
            color: #172033;
          }

          .page {
            width: 210mm;
            height: 297mm;
            margin: 20px auto;
            background: white;
            padding: 28px 32px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
          }

          /* ================= HEADER ================= */

          .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 18px;
            border-bottom: 2px solid #172033;
            flex-shrink: 0;
          }

          .company-name {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
          }

          .document-title {
            margin-top: 5px;
            font-size: 10px;
            color: #667085;
            letter-spacing: 1.4px;
          }

          .receipt-label {
            text-align: right;
          }

          .receipt-label span {
            display: block;
            font-size: 9px;
            color: #667085;
            text-transform: uppercase;
            letter-spacing: 1px;
          }

          .receipt-number {
            margin-top: 5px;
            font-size: 14px;
            font-weight: 700;
          }

          .status {
            display: inline-block;
            margin-top: 8px;
            padding: 5px 10px;
            border-radius: 999px;
            background: #ecfdf3;
            color: #027a48;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.7px;
          }

          /* ================= SECTIONS ================= */

          .section {
            margin-top: 20px;
            flex-shrink: 0;
          }

          .section-title {
            margin-bottom: 10px;
            font-size: 10px;
            font-weight: 700;
            color: #667085;
            letter-spacing: 1.1px;
            text-transform: uppercase;
          }

          /* ================= TRANSACTION ================= */

          .transaction-type {
            padding: 15px 18px;
            border: 1px solid #e4e7ec;
            border-radius: 8px;
          }

          .transaction-type span {
            display: block;
            font-size: 10px;
            color: #667085;
            margin-bottom: 5px;
          }

          .transaction-type strong {
            font-size: 18px;
          }

          .amount-box {
            margin-top: 10px;
            padding: 17px 20px;
            background: #172033;
            border-radius: 8px;
            color: white;
          }

          .amount-box span {
            display: block;
            font-size: 9px;
            opacity: 0.7;
            text-transform: uppercase;
            letter-spacing: 1px;
          }

          .amount-box strong {
            display: block;
            margin-top: 5px;
            font-size: 24px;
          }

          /* ================= GRID ================= */

          .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 9px;
          }

          .field {
            padding: 11px 14px;
            border: 1px solid #e4e7ec;
            border-radius: 7px;
            min-height: 60px;
          }

          .field-label {
            display: block;
            font-size: 9px;
            color: #667085;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin-bottom: 5px;
          }

          .field-value {
            font-size: 12px;
            font-weight: 600;
            line-height: 1.35;
            word-break: break-word;
          }

          /* ================= REMARKS ================= */

          .remarks {
            padding: 12px 15px;
            background: #f8fafc;
            border-left: 3px solid #172033;
            line-height: 1.45;
            font-size: 12px;
            color: #475467;
          }

          /* ================= FOOTER ================= */

          .footer {
            margin-top: auto;
            padding-top: 14px;
            border-top: 1px solid #e4e7ec;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #98a2b3;
            flex-shrink: 0;
          }

          .print-button {
            position: fixed;
            right: 25px;
            bottom: 25px;
            padding: 12px 22px;
            border: none;
            border-radius: 8px;
            background: #172033;
            color: white;
            font-weight: 600;
            cursor: pointer;
          }

          /* ================= PRINT ================= */

          @page {
            size: A4 portrait;
            margin: 0;
          }

          @media print {
            html,
            body {
              width: 210mm;
              height: 297mm;
              background: white;
              overflow: hidden;
            }

            .page {
              width: 210mm;
              height: 297mm;
              min-height: 297mm;
              margin: 0;
              padding: 28px 32px;
              overflow: hidden;
              page-break-after: avoid;
              page-break-before: avoid;
            }

            .section {
              break-inside: avoid;
              page-break-inside: avoid;
            }

            .footer {
              break-inside: avoid;
              page-break-inside: avoid;
            }

            .print-button {
              display: none;
            }
          }
        </style>
      </head>

      <body>

        <div class="page">

          <div class="header">

            <div>
              <div class="company-name">
                ${company || "Financial Ledger"}
              </div>

              <div class="document-title">
                FINANCIAL TRANSACTION RECEIPT
              </div>
            </div>

            <div class="receipt-label">

              <span>Transaction Reference</span>

              <div class="receipt-number">
                ${receiptNumber}
              </div>

              <div class="status">
                RECORDED
              </div>

            </div>

          </div>


          <div class="section">

            <div class="section-title">
              Transaction Details
            </div>

            <div class="transaction-type">

              <span>Transaction Type</span>

              <strong>
                ${(transaction.entry_type || "TRANSACTION").replaceAll(
                  "_",
                  " ",
                )}
              </strong>

            </div>

            <div class="amount-box">

              <span>Transaction Amount</span>

              <strong>
                ${formatAmount(transaction.amount, facility.currency_code)}
              </strong>

            </div>

          </div>


          <div class="section">

            <div class="section-title">
              Facility Information
            </div>

            <div class="grid">

              <div class="field">
                <span class="field-label">Facility Reference</span>
                <div class="field-value">
                  ${facility.reference_number || "—"}
                </div>
              </div>

              <div class="field">
                <span class="field-label">Facility Type</span>
                <div class="field-value">
                  ${facility.facility_type || "—"}
                </div>
              </div>

              <div class="field">
                <span class="field-label">Lender</span>
                <div class="field-value">
                  ${facility.lender_company_name || "—"}
                </div>
              </div>

              <div class="field">
                <span class="field-label">Borrower</span>
                <div class="field-value">
                  ${facility.borrower_company_name || "—"}
                </div>
              </div>

              <div class="field">
                <span class="field-label">Principal Amount</span>
                <div class="field-value">
                  ${formatAmount(
                    facility.principal_amount,
                    facility.currency_code,
                  )}
                </div>
              </div>

              <div class="field">
                <span class="field-label">Outstanding Balance</span>
                <div class="field-value">
                  ${formatAmount(
                    facility.outstanding_amount,
                    facility.currency_code,
                  )}
                </div>
              </div>

            </div>

          </div>


          <div class="section">

            <div class="section-title">
              Transaction Record
            </div>

            <div class="grid">

              <div class="field">
                <span class="field-label">Date & Time</span>
                <div class="field-value">
                  ${formatDate(transaction.created_at)}
                </div>
              </div>

              <div class="field">
                <span class="field-label">Performed By</span>
                <div class="field-value">
                  ${transaction.performed_by_name || "System"}
                </div>
              </div>

            </div>

          </div>


          ${
            transaction.remarks
              ? `
                <div class="section">

                  <div class="section-title">
                    Remarks
                  </div>

                  <div class="remarks">
                    ${transaction.remarks}
                  </div>

                </div>
              `
              : ""
          }


          <div class="footer">

            <span>
              Generated from Financial Facility Ledger
            </span>

            <span>
              ${formatDate(new Date())}
            </span>

          </div>

        </div>

        <button
          class="print-button"
          onclick="window.print()"
        >
          Print Receipt
        </button>

      </body>
    </html>
  `);

  printWindow.document.close();
}
