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

          body {
            margin: 0;
            padding: 0;
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
            min-height: 297mm;
            margin: 20px auto;
            background: white;
            padding: 45px;
          }

          .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 28px;
            border-bottom: 2px solid #172033;
          }

          .company-name {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
          }

          .document-title {
            margin-top: 8px;
            font-size: 12px;
            color: #667085;
            letter-spacing: 1.5px;
          }

          .receipt-label {
            text-align: right;
          }

          .receipt-label span {
            display: block;
            font-size: 11px;
            color: #667085;
            text-transform: uppercase;
            letter-spacing: 1px;
          }

          .receipt-number {
            margin-top: 6px;
            font-size: 16px;
            font-weight: 700;
          }

          .status {
            display: inline-block;
            margin-top: 12px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #ecfdf3;
            color: #027a48;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.7px;
          }

          .section {
            margin-top: 34px;
          }

          .section-title {
            margin-bottom: 16px;
            font-size: 11px;
            font-weight: 700;
            color: #667085;
            letter-spacing: 1.2px;
            text-transform: uppercase;
          }

          .transaction-type {
            padding: 22px;
            border: 1px solid #e4e7ec;
            border-radius: 10px;
          }

          .transaction-type span {
            display: block;
            font-size: 12px;
            color: #667085;
            margin-bottom: 7px;
          }

          .transaction-type strong {
            font-size: 22px;
          }

          .amount-box {
            margin-top: 18px;
            padding: 24px;
            background: #172033;
            border-radius: 10px;
            color: white;
          }

          .amount-box span {
            display: block;
            font-size: 11px;
            opacity: 0.7;
            text-transform: uppercase;
            letter-spacing: 1px;
          }

          .amount-box strong {
            display: block;
            margin-top: 8px;
            font-size: 28px;
          }

          .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
          }

          .field {
            padding: 16px;
            border: 1px solid #e4e7ec;
            border-radius: 8px;
          }

          .field-label {
            display: block;
            font-size: 10px;
            color: #667085;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 7px;
          }

          .field-value {
            font-size: 14px;
            font-weight: 600;
            line-height: 1.5;
          }

          .remarks {
            padding: 18px;
            background: #f8fafc;
            border-left: 3px solid #172033;
            line-height: 1.6;
            color: #475467;
          }

          .footer {
            margin-top: 70px;
            padding-top: 20px;
            border-top: 1px solid #e4e7ec;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #98a2b3;
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

          @media print {
            body {
              background: white;
            }

            .page {
              margin: 0;
              width: 100%;
              min-height: auto;
              padding: 35px;
            }

            .print-button {
              display: none;
            }

            @page {
              size: A4;
              margin: 0;
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

                <span class="field-label">
                  Facility Reference
                </span>

                <div class="field-value">
                  ${facility.reference_number || "—"}
                </div>

              </div>


              <div class="field">

                <span class="field-label">
                  Facility Type
                </span>

                <div class="field-value">
                  ${facility.facility_type || "—"}
                </div>

              </div>


              <div class="field">

                <span class="field-label">
                  Lender
                </span>

                <div class="field-value">
                  ${facility.lender_company_name || "—"}
                </div>

              </div>


              <div class="field">

                <span class="field-label">
                  Borrower
                </span>

                <div class="field-value">
                  ${facility.borrower_company_name || "—"}
                </div>

              </div>


              <div class="field">

                <span class="field-label">
                  Principal Amount
                </span>

                <div class="field-value">
                  ${formatAmount(
                    facility.principal_amount,
                    facility.currency_code,
                  )}
                </div>

              </div>


              <div class="field">

                <span class="field-label">
                  Outstanding Balance
                </span>

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

                <span class="field-label">
                  Date & Time
                </span>

                <div class="field-value">
                  ${formatDate(transaction.created_at)}
                </div>

              </div>


              <div class="field">

                <span class="field-label">
                  Performed By
                </span>

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
