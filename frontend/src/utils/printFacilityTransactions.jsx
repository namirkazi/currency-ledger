export function printFacilityTransaction({
  facility,
  transaction,
  company,
  transactions = [],
}) {
  /*
   * ============================================================
   * AMOUNT FORMATTER
   * ============================================================
   */

  const formatAmount = (amount, currency) => {
    return `${currency || ""} ${Number(amount || 0).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  };

  /*
   * ============================================================
   * DATE FORMATTER
   * ============================================================
   *
   * Database timestamps are stored as UTC.
   * Convert explicitly to Dubai time.
   */

  const formatDate = (date) => {
    if (!date) return "—";

    let parsedDate;

    if (date instanceof Date) {
      parsedDate = date;
    } else {
      let normalizedDate = String(date).replace(" ", "T");

      /*
       * MySQL returns:
       * 2026-09-03 11:49:00
       *
       * Treat it as UTC if no timezone exists.
       */

      if (
        !normalizedDate.endsWith("Z") &&
        !/[+-]\d{2}:\d{2}$/.test(normalizedDate)
      ) {
        normalizedDate += "Z";
      }

      parsedDate = new Date(normalizedDate);
    }

    if (Number.isNaN(parsedDate.getTime())) {
      return "—";
    }

    return new Intl.DateTimeFormat("en-US", {
      timeZone: "Asia/Dubai",
      year: "numeric",
      month: "long",
      day: "numeric",
      hour: "numeric",
      minute: "2-digit",
      hour12: true,
    }).format(parsedDate);
  };

  /*
   * ============================================================
   * LEDGER ENTRY
   * ============================================================
   *
   * The transaction passed into this function is already the
   * exact facility_ledger_entries row selected by the user.
   */

  const ledgerEntry = transaction;

  /*
   * ============================================================
   * BASIC VALUES
   * ============================================================
   */

  const currency =
    ledgerEntry?.currency_code ||
    transaction?.currency_code ||
    facility?.currency_code ||
    "";

  const principalAmount = Number(facility?.principal_amount || 0);

  const interestRate = Number(facility?.interest_rate || 0);

  /*
   * ============================================================
   * FACILITY INTEREST
   * ============================================================
   *
   * IMPORTANT:
   *
   * Do NOT use ledgerEntry.interest_amount for repayments.
   *
   * A repayment entry normally has:
   *
   * interest_amount = 0
   *
   * That does NOT mean the facility has zero interest.
   *
   * We want the actual agreed facility interest.
   */

  let interestAmount = 0;

  if (
    facility?.interest_amount !== undefined &&
    facility?.interest_amount !== null
  ) {
    interestAmount = Number(facility.interest_amount);
  } else if (
    facility?.calculated_interest_amount !== undefined &&
    facility?.calculated_interest_amount !== null
  ) {
    interestAmount = Number(facility.calculated_interest_amount);
  } else {
    interestAmount = (principalAmount * interestRate) / 100;
  }

  /*
   * ============================================================
   * TOTAL FACILITY AMOUNT
   * ============================================================
   */

  const totalFacilityAmount = principalAmount + interestAmount;

  /*
   * ============================================================
   * HISTORICAL OUTSTANDING BALANCE
   * ============================================================
   *
   * THIS IS THE CRITICAL PART.
   *
   * Do not calculate.
   * Do not subtract repayments.
   * Do not use the current facility balance.
   *
   * We directly use the value stored in:
   *
   * facility_ledger_entries.outstanding_after
   *
   * Example:
   *
   * DISBURSEMENT
   * outstanding_after = 330,000
   *
   * REPAYMENT 30,000
   * outstanding_after = 300,000
   *
   * REPAYMENT 100,000
   * outstanding_after = 200,000
   */
  const outstandingAtTransaction = Number(
    ledgerEntry?.outstanding_after ?? transaction?.outstanding_after ?? 0,
  );

  /*
   * ============================================================
   * TRANSACTION VALUES
   * ============================================================
   */

  const transactionType =
    ledgerEntry?.entry_type || transaction?.entry_type || "TRANSACTION";

  const transactionAmount = ledgerEntry?.amount ?? transaction?.amount ?? 0;

  const transactionDate =
    ledgerEntry?.created_at || transaction?.created_at || null;

  const performedBy =
    ledgerEntry?.performed_by_name ||
    transaction?.performed_by_name ||
    "System";

  const remarks = ledgerEntry?.remarks || transaction?.remarks || "";

  /*
   * ============================================================
   * RECEIPT NUMBER
   * ============================================================
   */

  const receiptId =
    ledgerEntry?.id || transaction?.ledger_entry_id || transaction?.id || "0";

  const receiptNumber = `TXN-${String(receiptId).padStart(6, "0")}`;

  /*
   * ============================================================
   * OPEN PRINT WINDOW
   * ============================================================
   */

  const printWindow = window.open("", "_blank", "width=900,height=1000");

  if (!printWindow) {
    alert("Please allow popups to print the transaction.");

    return;
  }

  /*
   * ============================================================
   * PRINT DOCUMENT
   * ============================================================
   */

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

            margin: 0 auto;

            background: white;

            padding: 20px 30px;

            overflow: hidden;

            display: flex;
            flex-direction: column;
          }


          /* =========================
             HEADER
          ========================= */

          .header {
            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            padding-bottom: 14px;

            border-bottom:
              2px solid #172033;

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


          /* =========================
             SECTIONS
          ========================= */

          .section {
            margin-top: 14px;

            flex-shrink: 0;
          }


          .section-title {
            margin-bottom: 7px;

            font-size: 10px;

            font-weight: 700;

            color: #667085;

            letter-spacing: 1.1px;

            text-transform: uppercase;
          }


          /* =========================
             TRANSACTION
          ========================= */

          .transaction-type {
            padding: 11px 16px;

            border:
              1px solid #e4e7ec;

            border-radius: 8px;
          }


          .transaction-type span {
            display: block;

            font-size: 10px;

            color: #667085;

            margin-bottom: 5px;
          }


          .transaction-type strong {
            font-size: 17px;
          }


          .amount-box {
            margin-top: 8px;

            padding: 13px 18px;

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

            font-size: 23px;
          }


          /* =========================
             GRID
          ========================= */

          .grid {
            display: grid;

            grid-template-columns:
              1fr 1fr;

            gap: 8px;
          }


          .field {
            padding: 10px 13px;

            border:
              1px solid #e4e7ec;

            border-radius: 7px;

            min-height: 54px;
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


          /* =========================
             FINANCIAL SUMMARY
          ========================= */

          .financial-summary {
            padding: 13px 16px;

            border:
              1px solid #e4e7ec;

            border-radius: 8px;

            background: #fafafa;
          }


          .summary-row {
            display: flex;

            justify-content: space-between;

            padding: 7px 0;

            font-size: 12px;

            border-bottom:
              1px solid #eaecf0;
          }


          .summary-row:last-child {
            border-bottom: none;
          }


          .summary-label {
            color: #667085;
          }


          .summary-value {
            font-weight: 700;
          }


          .summary-total {
            margin-top: 4px;

            padding-top: 10px;

            font-size: 14px;

            border-top:
              2px solid #172033;
          }


          /* =========================
             REMARKS
          ========================= */

          .remarks {
            padding: 11px 14px;

            background: #f8fafc;

            border-left:
              3px solid #172033;

            line-height: 1.45;

            font-size: 12px;

            color: #475467;
          }


          /* =========================
             FOOTER
          ========================= */

          .footer {
            margin-top: auto;

            padding-top: 10px;

            border-top:
              1px solid #e4e7ec;

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

              padding: 20px 30px;

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


          <!-- HEADER -->

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

              <span>
                Transaction Reference
              </span>


              <div class="receipt-number">
                ${receiptNumber}
              </div>


              <div class="status">
                RECORDED
              </div>

            </div>

          </div>


          <!-- TRANSACTION DETAILS -->

          <div class="section">

            <div class="section-title">
              Transaction Details
            </div>


            <div class="transaction-type">

              <span>
                Transaction Type
              </span>


              <strong>
                ${String(transactionType).replaceAll("_", " ").toUpperCase()}
              </strong>

            </div>


            <div class="amount-box">

              <span>
                Transaction Amount
              </span>


              <strong>
                ${formatAmount(transactionAmount, currency)}
              </strong>

            </div>

          </div>


          <!-- FACILITY INFORMATION -->

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
                  ${facility?.reference_number || "—"}
                </div>

              </div>


              <div class="field">

                <span class="field-label">
                  Facility Type
                </span>


                <div class="field-value">
                  ${facility?.facility_type || "—"}
                </div>

              </div>


              <div class="field">

                <span class="field-label">
                  Lender
                </span>


                <div class="field-value">
                  ${facility?.lender_company_name || "—"}
                </div>

              </div>


              <div class="field">

                <span class="field-label">
                  Borrower
                </span>


                <div class="field-value">
                  ${facility?.borrower_company_name || "—"}
                </div>

              </div>

            </div>

          </div>


          <!-- FINANCIAL SUMMARY -->

          <div class="section">

            <div class="section-title">
              Financial Summary
            </div>


            <div class="financial-summary">


              <div class="summary-row">

                <span class="summary-label">
                  Principal Amount
                </span>


                <span class="summary-value">
                  ${formatAmount(principalAmount, currency)}
                </span>

              </div>


              <div class="summary-row">

                <span class="summary-label">
                  Interest Rate
                </span>


                <span class="summary-value">
                  ${interestRate.toFixed(2)}%
                </span>

              </div>


              <div class="summary-row">

                <span class="summary-label">
                  Interest Amount
                </span>


                <span class="summary-value">
                  ${formatAmount(interestAmount, currency)}
                </span>

              </div>


              <div class="summary-row summary-total">

                <span class="summary-label">
                  Total Facility Amount
                </span>


                <span class="summary-value">
                  ${formatAmount(totalFacilityAmount, currency)}
                </span>

              </div>


              <div class="summary-row">

                <span class="summary-label">
                  Outstanding Balance After Transaction
                </span>


                <span class="summary-value">
                  ${formatAmount(outstandingAtTransaction, currency)}
                </span>

              </div>

            </div>

          </div>


          <!-- TRANSACTION RECORD -->

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
                  ${formatDate(transactionDate)}
                </div>

              </div>


              <div class="field">

                <span class="field-label">
                  Performed By
                </span>


                <div class="field-value">
                  ${performedBy}
                </div>

              </div>

            </div>

          </div>


          ${
            remarks
              ? `
                <div class="section">

                  <div class="section-title">
                    Remarks
                  </div>


                  <div class="remarks">
                    ${remarks}
                  </div>

                </div>
              `
              : ""
          }


          <!-- FOOTER -->

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
