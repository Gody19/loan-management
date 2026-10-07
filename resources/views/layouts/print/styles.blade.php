{{--
    FinancePro shared print kit stylesheet.

    Usage:
      in-app page (extends a layout):   @push('styles') @include('layouts.print.styles') @endpush
      standalone page (own <html>):     @include('layouts.print.styles', ['inApp' => false])  inside <head>

    $inApp = true  -> also hides the application chrome (sidebar, navbar, footer,
                      AI widget, ...) and reveals .print-document for printing.
    $inApp = false -> standalone document pages: only the shared document styles.
--}}
@php($inApp = $inApp ?? true)
<style>
    @page {
        margin: 14mm 12mm;
    }

    /* ===== Watermark (repeats on every printed page) ===== */
    .print-watermark {
        position: fixed;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        opacity: 0.07;
        z-index: 3;
        pointer-events: none;
        text-align: center;
    }

    .print-watermark-mark {
        display: block;
        width: 300px;
        max-width: 45%;
        height: auto;
    }

    .print-watermark-text {
        display: block;
        font-size: 96px;
        font-weight: 800;
        letter-spacing: 6px;
        line-height: 1;
        color: #0f172a;
    }

    /* ===== Branded document header ===== */
    .print-doc-header {
        position: relative;
        z-index: 1;
        margin-bottom: 14px;
    }

    .print-doc-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 10px;
        border-bottom: 3px double #0d6efd;
    }

    .print-brand {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .print-brand-mark {
        display: block;
        width: 44px;
        height: 44px;
    }

    .print-brand-text {
        line-height: 1.15;
    }

    .print-brand-text strong {
        display: block;
        font-size: 17px;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: .3px;
    }

    .print-brand-text span {
        display: block;
        font-size: 10.5px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 1.2px;
    }

    .print-doc-meta {
        text-align: right;
        font-size: 11px;
        color: #475569;
        line-height: 1.5;
    }

    .print-doc-org {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
    }

    .print-doc-title {
        position: relative;
        z-index: 1;
        margin: 14px 0 10px;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: .4px;
        color: #0f172a;
    }

    /* ===== Document body primitives ===== */
    .print-section-title {
        position: relative;
        z-index: 1;
        margin: 16px 0 6px;
        padding-bottom: 4px;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .6px;
        color: #0f172a;
        border-bottom: 1px solid #cbd5e1;
    }

    .print-table {
        position: relative;
        z-index: 1;
        width: 100%;
        border-collapse: collapse;
        margin: 0;
        font-size: 11px;
        page-break-inside: auto;
    }

    .print-table th,
    .print-table td {
        padding: 5px 7px;
        border: 1px solid #cbd5e1;
        text-align: left;
        vertical-align: top;
    }

    .print-table thead {
        display: table-header-group;
    }

    .print-table thead th {
        background: #e2e8f0;
        font-weight: 700;
    }

    .print-table tfoot th {
        background: #f1f5f9;
    }

    .print-table tr {
        page-break-inside: avoid;
    }

    .print-table .text-end {
        text-align: right;
    }

    .print-doc-details th {
        width: 15%;
        background: #f8fafc;
        color: #475569;
        font-weight: 600;
    }

    .print-doc-details td {
        width: 35%;
    }

    .print-summary td,
    .print-summary th {
        text-align: center;
    }

    .print-empty {
        position: relative;
        z-index: 1;
        margin: 4px 0;
        color: #475569;
        font-style: italic;
    }

    .print-doc-footer {
        position: relative;
        z-index: 2;
        margin-top: 18px;
        padding-top: 5px;
        border-top: 1px solid #cbd5e1;
        font-size: 9.5px;
        color: #64748b;
        text-align: center;
    }

    @media print {
        body,
        body * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Application chrome that must never appear on the printed page */
        .no-print,
        aside.vicoba-sidebar,
        header.vicoba-navbar,
        footer.px-4,
        #sidebarOverlay,
        #aiWidget,
        .ai-widget,
        .swal2-container {
            display: none !important;
        }

        body {
            background: #fff !important;
        }

        a[href]::after {
            content: none !important;
        }

        .print-doc-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            margin-top: 0;
        }
    }

    @if($inApp)
    /* Print-only document: rendered exclusively by the print media query */
    .print-document {
        display: none;
    }

    @media print {
        .vicoba-main {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        .vicoba-main > .p-4 {
            padding: 0 !important;
            display: block !important;
        }

        /* Screen-only page furniture above the document */
        .page-header,
        .alert {
            display: none !important;
        }

        .print-document {
            display: block !important;
            position: relative;
            background: #fff;
            color: #0f172a;
            font-family: "Segoe UI", Arial, Helvetica, sans-serif;
            font-size: 12px;
            z-index: 1;
            padding-bottom: 40px;
        }
    }
    @endif
</style>
