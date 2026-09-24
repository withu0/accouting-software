<style>
    @page {
        margin: 18mm 15mm 22mm 15mm;
    }

    body {
        font-family: 'noto sans jp', sans-serif;
        font-size: 10px;
        color: #111;
        line-height: 1.4;
    }

    .header {
        position: relative;
        text-align: center;
        margin-bottom: 14px;
        padding-bottom: 8px;
    }

    .unit-label {
        position: absolute;
        top: 0;
        right: 0;
        font-size: 10px;
    }

    .company-name {
        font-size: 16px;
        font-weight: bold;
        letter-spacing: 0.05em;
    }

    .company-address {
        font-size: 10px;
        margin-top: 4px;
    }

    .representative {
        font-size: 10px;
        margin-top: 2px;
    }

    .report-title {
        font-size: 14px;
        font-weight: bold;
        margin-top: 10px;
    }

    .period-line {
        font-size: 10px;
        margin-top: 6px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
    }

    th, td {
        border: 1px solid #333;
        padding: 4px 6px;
        vertical-align: middle;
    }

    th {
        background: #eee;
        text-align: center;
        font-weight: bold;
    }

    .text-right {
        text-align: right;
    }

    .text-center {
        text-align: center;
    }

    .text-left {
        text-align: left;
    }

    .section-title {
        font-weight: bold;
        margin-top: 10px;
        margin-bottom: 4px;
        font-size: 11px;
    }

    .total-row td {
        font-weight: bold;
        border-top: 2px solid #000;
        page-break-inside: avoid;
    }

    .amount-col {
        width: 100px;
        white-space: nowrap;
    }

    .code-col {
        width: 70px;
        white-space: nowrap;
    }

    .ledger-account-block {
        page-break-inside: avoid;
    }

    .ledger-account-block + .ledger-account-block {
        page-break-before: always;
    }

    .footer-left {
        position: fixed;
        bottom: 0;
        left: 0;
        font-size: 9px;
        color: #555;
    }

    .bs-outer {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .bs-outer th,
    .bs-outer td {
        border: 1px solid #333;
    }

    .bs-outer .bs-side-head th {
        font-size: 11px;
    }

    .bs-outer .bs-divider-right {
        border-right: 2px solid #333;
    }

    .bs-outer .bs-name-col {
        width: auto;
    }

    td.bs-section-head {
        font-weight: bold;
        background: #f5f5f5;
        text-align: left;
    }

    td.bs-pad-row {
        height: 18px;
    }

    .statement {
        page-break-after: always;
    }

    .statement:last-child {
        page-break-after: auto;
    }

    .equity-table {
        font-size: 9px;
    }

    .equity-table .amount-col {
        width: 72px;
    }
</style>
