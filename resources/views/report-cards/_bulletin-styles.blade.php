<style>
    @page { margin: 7mm 8mm; }
    body { font-size: 8px; line-height: 1.22; }
    .bulletin-page { page-break-inside: avoid; }
    .top-grid { margin: 1px 0 2px; }
    .top-grid td {
        width: 50%;
        padding: 2px 4px;
        vertical-align: top;
    }
    .top-grid td:last-child { text-align: right; }
    .bulletin-identity td {
        width: 65%;
        padding: 3px 5px;
    }
    .bulletin-identity td:last-child { width: 35%; }
    .bulletin-title {
        margin: 5px 0;
        font-size: 14px;
        font-weight: bold;
        text-align: center;
        text-decoration: underline;
    }
    .marks { table-layout: fixed; }
    .marks tr { page-break-inside: avoid; }
    .marks th,
    .marks td {
        border: 0.8px solid #222;
        padding: 2.1px 2.5px;
        vertical-align: middle;
        overflow-wrap: break-word;
    }
    .marks th {
        background: #e6e6e6;
        font-size: 6.7px;
        text-align: center;
    }
    .marks th:nth-child(1) { width: 18%; }
    .marks th:nth-child(2),
    .marks th:nth-child(3),
    .marks th:nth-child(4) { width: 7%; }
    .marks th:nth-child(5) { width: 5%; }
    .marks th:nth-child(6) { width: 7%; }
    .marks th:nth-child(7) { width: 12%; }
    .marks th:nth-child(8) { width: 16%; }
    .marks th:nth-child(9) { width: 7%; }
    .group-row td {
        background: #dedede;
        font-weight: bold;
    }
    .group-summary td {
        background: #f0f0f0;
        font-style: italic;
        text-align: right;
    }
    .total-row td { background: #e6e6e6; font-weight: bold; }
    .information-row td { background: #fafafa; }
    .term-summary-grid { margin-top: -0.8px; table-layout: fixed; }
    .term-summary-grid td {
        border: 0.8px solid #222;
        padding: 3px 4px;
        vertical-align: middle;
    }
    .term-summary-grid td:nth-child(1) { width: 22%; }
    .term-summary-grid td:nth-child(2) { width: 25%; }
    .term-summary-grid td:nth-child(3) { width: 18%; }
    .term-summary-grid td:nth-child(4) { width: 35%; }
    .recall-grid,
    .annual-grid,
    .footer-grid { margin-top: 3px; }
    .recall-grid th,
    .recall-grid td,
    .annual-grid td,
    .footer-grid > tbody > tr > td {
        border: 0.8px solid #222;
        padding: 3px 5px;
        vertical-align: middle;
    }
    .recall-grid th { width: 15%; background: #e6e6e6; }
    .annual-grid td { width: 30%; vertical-align: top; }
    .annual-grid td:nth-child(2) { width: 40%; }
    .annual-decision { text-align: center; }
    .annual-decision span {
        display: block;
        margin-bottom: 4px;
        font-weight: bold;
        text-decoration: underline;
    }
    .annual-decision strong { font-size: 8.5px; }
    .annual-note { display: block; font-size: 6.5px; font-style: italic; }
    .principal-observation {
        min-height: 24px;
        margin-top: 3px;
        border: 0.8px solid #222;
        padding: 4px 5px;
    }
    .sanctions { width: 56%; vertical-align: top !important; }
    .signature { width: 44%; text-align: center; vertical-align: top !important; }
    .section-label {
        margin-bottom: 1px;
        font-weight: bold;
        text-align: center;
        text-decoration: underline;
    }
    .sanctions-table { table-layout: fixed; }
    .sanctions-table td { padding: 1.2px 2px; vertical-align: middle; }
    .sanctions-table .check-cell { width: 16px; text-align: right; }
    .check-box {
        display: inline-block;
        width: 12px;
        height: 11px;
        border: 0.8px solid #222;
        font-weight: bold;
        line-height: 11px;
        text-align: center;
    }
    .principal-title { display: inline-block; margin-top: 3px; font-weight: bold; text-decoration: underline; }
    .signature-space { height: 45px; }
    .bulletin-note {
        margin-top: 2px;
        font-size: 6.5px;
        font-style: italic;
        text-align: center;
    }
    .page-break { page-break-after: always; }
</style>
