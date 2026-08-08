<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#8b1e2d">
    <title>{{ $title ?? 'Lycée Privé Pagnidibsom' }}</title>
    @if (auth()->check())
        <script type="application/json" id="lpp-guided-tours-config">{!! Illuminate\Support\Js::encode([
                'userId' => auth()->id(),
                'route' => request()->route()?->getName(),
                'tours' => ($guidedTours ?? collect())->values(),
            ]) !!}</script>
    @endif
    @vite('resources/js/app.js')
    <style>
        :root {
            --ink: #1d1718;
            --muted: #6f6564;
            --line: #e7dfd8;
            --paper: #fbfaf7;
            --panel: #ffffff;
            --forest: #8b1e2d;
            --forest-2: #6f1724;
            --gold: #e6a817;
            --red: #b42318;
            --blue: #2f5f8f;
            --brand-soft: #f8edf0;
            --gold-soft: #fff6db;
            --surface: #fffdf9;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            background: var(--paper);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            letter-spacing: 0;
        }

        a { color: inherit; text-decoration: none; }

        button, input, select, textarea {
            font: inherit;
            letter-spacing: 0;
        }

        .auth-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(320px, 1fr) minmax(320px, 460px);
            background:
                linear-gradient(120deg, rgba(111, 23, 36, .95), rgba(139, 30, 45, .82)),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1200' height='800' viewBox='0 0 1200 800'%3E%3Crect fill='%23fbfaf7' width='1200' height='800'/%3E%3Cg fill='none' stroke='%23e6a817' stroke-width='12' opacity='.55'%3E%3Cpath d='M-60 720C150 550 350 500 540 610s390 90 720-170'/%3E%3Cpath d='M-30 540c220-90 420-70 600 60s360 150 690-10'/%3E%3Cpath d='M90 110h250v250H90zM780 80h260v180H780zM630 430h380v230H630z'/%3E%3C/g%3E%3C/svg%3E");
            background-size: cover;
            background-position: center;
        }

        .auth-brand {
            padding: 56px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #fff;
        }

        .brand-mark {
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255,255,255,.38);
            border-radius: 8px;
            background: rgba(230,168,23,.16);
            font-weight: 800;
        }

        .auth-brand h1 {
            max-width: 720px;
            margin: 28px 0 14px;
            font-size: clamp(38px, 7vw, 78px);
            line-height: .96;
        }

        .auth-brand p {
            max-width: 620px;
            margin: 0;
            color: rgba(255,255,255,.82);
            font-size: 20px;
            line-height: 1.5;
        }

        .auth-card-wrap {
            display: grid;
            place-items: center;
            padding: 28px;
            background: rgba(251,250,247,.96);
        }

        .auth-card {
            width: min(100%, 390px);
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 28px;
            box-shadow: 0 20px 50px rgba(22,32,25,.12);
        }

        .eyebrow {
            margin: 0 0 8px;
            color: var(--gold);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .auth-card h2 {
            margin: 0 0 22px;
            font-size: 28px;
        }

        .field {
            display: grid;
            gap: 8px;
            margin-bottom: 16px;
        }

        .field label {
            color: var(--muted);
            font-size: 14px;
            font-weight: 650;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            min-height: 46px;
            padding: 0 12px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff;
            color: var(--ink);
        }

        .field textarea {
            min-height: 108px;
            padding: 12px;
            resize: vertical;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: 3px solid rgba(139,30,45,.15);
            border-color: var(--forest-2);
        }

        .form-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 6px 0 20px;
            color: var(--muted);
            font-size: 14px;
        }

        .check {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 6px;
            padding: 0 16px;
            font-weight: 750;
            cursor: pointer;
            touch-action: manipulation;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:focus-visible,
        .user-pill:focus-visible,
        .nav a:focus-visible,
        .nav-section-title:focus-visible {
            outline: 3px solid rgba(230,168,23,.72);
            outline-offset: 2px;
        }

        .btn-primary {
            background: var(--forest);
            color: #fff;
        }

        .auth-card .btn-primary {
            width: 100%;
        }

        .btn-subtle {
            background: var(--brand-soft);
            color: var(--forest);
        }

        .btn-danger {
            background: rgba(185,71,63,.1);
            color: var(--red);
        }

        .error {
            margin: 0 0 16px;
            padding: 10px 12px;
            border: 1px solid rgba(185,71,63,.28);
            border-radius: 6px;
            color: var(--red);
            background: rgba(185,71,63,.08);
            font-size: 14px;
        }

        .app-shell {
            position: relative;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 292px minmax(0, 1fr);
            align-items: stretch;
            background: linear-gradient(90deg, var(--forest) 0 292px, var(--paper) 292px 100%);
        }

        .app-shell::before {
            display: none;
        }

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            padding: 22px 16px;
            border-right: 1px solid rgba(255,255,255,.12);
            background: linear-gradient(180deg, var(--forest-2), var(--forest));
            color: #fff;
            z-index: 1;
        }

        .sidebar .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 8px 18px;
            border-bottom: 1px solid rgba(255,255,255,.14);
            margin-bottom: 14px;
        }

        .sidebar .brand strong {
            display: block;
            line-height: 1.1;
        }

        .sidebar .brand span {
            color: rgba(255,255,255,.72);
            font-size: 13px;
        }

        .sidebar-toggle {
            display: none;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 8px;
            background: rgba(255,255,255,.08);
            color: #fff;
            font-size: 22px;
            cursor: pointer;
        }

        .nav {
            display: grid;
            gap: 8px;
        }

        .nav-section {
            overflow: hidden;
            padding: 0;
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 8px;
            background: rgba(255,255,255,.045);
        }

        .nav-section.active-section {
            border-color: rgba(230,168,23,.45);
            background: rgba(255,255,255,.09);
        }

        .nav-section-title {
            min-height: 38px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin: 0;
            padding: 8px 10px;
            color: rgba(255,255,255,.62);
            font-size: 11px;
            font-weight: 850;
            letter-spacing: .03em;
            list-style: none;
            text-transform: uppercase;
            cursor: pointer;
            touch-action: manipulation;
        }

        .nav-section-title::-webkit-details-marker {
            display: none;
        }

        .nav-section-title:hover {
            color: #fff;
            background: rgba(255,255,255,.045);
        }

        .nav-section-chevron {
            width: 7px;
            height: 7px;
            flex: 0 0 7px;
            border-right: 2px solid currentColor;
            border-bottom: 2px solid currentColor;
            transform: rotate(45deg);
            transition: transform .15s ease;
        }

        .nav-section[open] .nav-section-chevron {
            transform: rotate(225deg);
        }

        .nav-section-links {
            display: grid;
            gap: 2px;
            padding: 0 8px 8px;
        }

        .nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 38px;
            padding: 0 10px;
            border-radius: 6px;
            color: rgba(255,255,255,.84);
            font-weight: 650;
            overflow-wrap: anywhere;
        }

        .nav a.active,
        .nav a:hover {
            background: #fff;
            color: var(--forest);
        }

        .nav-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--gold);
            flex: 0 0 auto;
        }

        .nav a:not(.active):hover .nav-dot {
            background: #fff;
        }

        .main {
            position: relative;
            z-index: 0;
            min-width: 0;
            padding: 26px;
            background:
                linear-gradient(180deg, rgba(255,246,219,.32), rgba(251,250,247,0) 220px),
                var(--paper);
        }

        .main-frame {
            width: min(100%, 1680px);
            margin-inline: auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--line);
        }

        .topbar__identity {
            min-width: min(100%, 240px);
            flex: 1 1 240px;
        }

        .topbar h1 {
            margin: 0 0 4px;
            font-size: 30px;
            line-height: 1.08;
        }

        .topbar p {
            margin: 0;
            color: var(--muted);
        }

        .top-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            flex: 1 1 520px;
            flex-wrap: wrap;
        }

        .topbar__page-actions,
        .topbar__account {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .topbar__page-actions {
            padding-right: 12px;
            border-right: 1px solid var(--line);
        }

        .topbar__account {
            margin-left: auto;
        }

        .user-pill {
            padding: 9px 12px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: #fff;
            color: var(--muted);
            white-space: nowrap;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }

        .user-pill:hover {
            border-color: rgba(139,30,45,.32);
            background: var(--brand-soft);
            color: var(--forest);
        }

        .grid {
            display: grid;
            gap: 16px;
        }

        .stats {
            grid-template-columns: repeat(auto-fit, minmax(min(210px, 100%), 1fr));
        }

        .stat,
        .panel,
        .module {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .stat {
            min-width: 0;
            padding: 16px;
            border-top: 4px solid var(--gold);
        }

        .stat > span {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .stat strong {
            display: block;
            max-width: 100%;
            margin-top: 8px;
            font-size: 26px;
            line-height: 1.1;
            overflow-wrap: anywhere;
        }

        .stat .money {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 4px 7px;
            font-size: 22px;
            font-variant-numeric: tabular-nums;
        }

        .money-amount {
            min-width: 0;
            white-space: nowrap;
        }

        .money-currency {
            color: var(--muted);
            font-size: 12px;
            line-height: 1;
            white-space: nowrap;
        }

        .two-col {
            grid-template-columns: minmax(0, 1.25fr) minmax(320px, .75fr);
            margin-top: 16px;
        }

        .panel {
            padding: 18px;
            min-width: 0;
            overflow-x: auto;
        }

        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .panel h2 {
            margin: 0;
            font-size: 18px;
        }

        .page-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(6, minmax(130px, 1fr));
            gap: 12px;
        }

        .quick-action {
            min-height: 76px;
            display: grid;
            align-content: center;
            gap: 5px;
            padding: 13px;
            border: 1px solid var(--line);
            border-left: 5px solid var(--gold);
            border-radius: 8px;
            background: #fff;
            color: var(--forest);
            text-decoration: none;
        }

        .quick-action:hover {
            border-color: rgba(139,30,45,.32);
            background: #fffaf2;
        }

        .quick-action strong {
            color: var(--brand);
            font-size: 14px;
        }

        .quick-action span {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.35;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(160px, 1fr)) auto;
            gap: 12px;
            align-items: end;
        }

        .action-card {
            min-height: 86px;
            display: grid;
            align-content: center;
            gap: 6px;
            padding: 14px;
            border: 1px solid var(--line);
            border-left: 5px solid var(--gold);
            border-radius: 8px;
            background: #fff;
            transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease;
        }

        .action-card:hover {
            transform: translateY(-1px);
            border-color: rgba(139,30,45,.32);
            box-shadow: 0 14px 34px rgba(111, 23, 36, .08);
        }

        .action-card strong {
            color: var(--forest);
            font-size: 15px;
        }

        .action-card span {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.35;
        }

        .alert-stack {
            display: grid;
            gap: 10px;
        }

        .alert-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 13px 14px;
            border: 1px solid var(--line);
            border-left: 5px solid var(--gold);
            border-radius: 8px;
            background: var(--surface);
        }

        .alert-item strong {
            display: block;
            margin-bottom: 4px;
        }

        .alert-item span {
            color: var(--muted);
            font-size: 13px;
        }

        .notice {
            margin: 0 0 16px;
            padding: 12px 14px;
            border: 1px solid rgba(139,30,45,.24);
            border-radius: 6px;
            background: var(--brand-soft);
            color: var(--forest);
            font-weight: 650;
        }

        .download-toast {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 50;
            max-width: min(360px, calc(100vw - 32px));
            padding: 13px 16px;
            border: 1px solid rgba(111,23,36,.28);
            border-radius: 8px;
            background: var(--forest);
            color: #fff;
            box-shadow: 0 16px 40px rgba(22,32,25,.18);
            font-weight: 750;
            opacity: 0;
            pointer-events: none;
            transform: translateY(10px);
            transition: opacity .18s ease, transform .18s ease;
        }

        .download-toast.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .form-grid .wide {
            grid-column: 1 / -1;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 18px;
        }

        .searchbar {
            display: flex;
            gap: 10px;
            align-items: stretch;
            flex-wrap: wrap;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
        }

        .searchbar input,
        .searchbar select {
            min-height: 42px;
            padding: 0 12px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 0 9px;
            border-radius: 999px;
            background: var(--brand-soft);
            color: var(--forest);
            font-size: 12px;
            font-weight: 750;
        }

        .badge-warning {
            background: rgba(201,146,44,.16);
            color: #83580f;
        }

        .badge-danger {
            background: rgba(161,54,42,.13);
            color: #8f241b;
        }

        .inline-form {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .inline-form .field {
            min-width: 180px;
            margin-bottom: 0;
        }

        .meter {
            width: min(180px, 100%);
            height: 9px;
            overflow: hidden;
            border-radius: 999px;
            background: #f2eadf;
        }

        .meter span {
            display: block;
            height: 100%;
            width: var(--value, 0%);
            border-radius: inherit;
            background: var(--forest);
        }

        .money {
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .summary-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .detail-item {
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
        }

        .detail-item span {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 750;
            text-transform: uppercase;
        }

        .detail-item strong {
            display: block;
            font-size: 15px;
            overflow-wrap: anywhere;
        }

        .student-profile-strip {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
        }

        .student-profile-strip h3 {
            margin: 0;
            font-size: 22px;
        }

        .student-profile-strip p {
            margin: 5px 0 0;
            color: var(--muted);
            font-weight: 750;
        }

        .student-photo-frame {
            width: 92px;
            height: 110px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            overflow: hidden;
            border: 2px solid var(--line);
            border-radius: 6px;
            background: #fff;
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
        }

        .student-photo-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .pagination {
            display: flex;
            justify-content: flex-end;
            margin-top: 14px;
        }

        .pagination nav {
            width: 100%;
            color: var(--muted);
            font-size: 13px;
        }

        .pagination nav > div:first-child {
            display: none !important;
        }

        .pagination nav > div:last-child {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            flex-wrap: wrap;
        }

        .pagination nav p {
            margin: 0;
        }

        .pagination nav a,
        .pagination nav span[aria-current] > span,
        .pagination nav span[aria-disabled] > span {
            min-width: 34px;
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: -1px;
            padding: 0 10px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--forest);
            font-weight: 750;
            text-decoration: none;
        }

        .pagination nav span[aria-current] > span {
            border-color: var(--forest);
            background: var(--forest);
            color: #fff;
        }

        .pagination nav span[aria-disabled] > span {
            color: #b4aaa8;
            background: #faf7f3;
        }

        .pagination nav svg {
            width: 16px !important;
            height: 16px !important;
            display: block;
        }

        .modules {
            grid-template-columns: repeat(3, minmax(160px, 1fr));
        }

        .module {
            padding: 16px;
            min-height: 108px;
            border-left: 4px solid var(--gold);
        }

        .module strong {
            display: block;
            margin-bottom: 8px;
        }

        .module span {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.35;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
        }

        .table-compact {
            min-width: 0;
        }

        .table th,
        .table td {
            padding: 11px 8px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            font-size: 14px;
        }

        .table th {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            white-space: nowrap;
            background: #fff;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .table td:last-child {
            white-space: nowrap;
        }

        .panel > .table,
        .panel form + .table,
        .panel .searchbar + .table {
            margin-top: 10px;
        }

        .table-scroll {
            width: 100%;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .table input,
        .table select {
            width: 100%;
            min-height: 38px;
            padding: 0 10px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff;
        }

        .ledger-list {
            display: grid;
            gap: 10px;
        }

        .ledger-item {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }

        .ledger-item[open] {
            border-color: rgba(139,30,45,.24);
            box-shadow: 0 14px 35px rgba(111, 23, 36, .07);
        }

        .ledger-summary {
            display: grid;
            grid-template-columns: minmax(210px, 1.5fr) repeat(4, minmax(120px, .8fr)) minmax(140px, .9fr);
            gap: 12px;
            align-items: center;
            padding: 14px 16px;
            cursor: pointer;
            list-style: none;
        }

        .ledger-summary::-webkit-details-marker {
            display: none;
        }

        .ledger-person strong,
        .ledger-metric strong {
            display: block;
        }

        .ledger-person span,
        .ledger-metric span {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 650;
        }

        .ledger-metric strong {
            font-size: 14px;
        }

        .ledger-progress {
            display: grid;
            gap: 7px;
        }

        .ledger-progress .meter {
            width: 100%;
        }

        .ledger-toggle {
            justify-self: end;
        }

        .ledger-detail {
            padding: 0 16px 16px;
            border-top: 1px solid var(--line);
            background: var(--surface);
            overflow-x: auto;
        }

        .ledger-detail-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            padding: 14px 0 10px;
            flex-wrap: wrap;
        }

        .ledger-detail-head h3 {
            margin: 0;
            font-size: 15px;
        }

        .ledger-detail table {
            background: #fff;
            min-width: 760px;
        }

        .subject-list-scroll {
            overflow-x: auto;
            padding-bottom: 6px;
        }

        .subject-list-inner {
            min-width: 900px;
        }

        .empty {
            padding: 18px;
            border: 1px dashed var(--line);
            border-radius: 8px;
            color: var(--muted);
            background: var(--surface);
        }

        .doc-intro {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            padding: 8px 0 22px;
            border-bottom: 1px solid var(--line);
        }

        .doc-intro h2,
        .doc-article-head h2 {
            margin: 5px 0 7px;
            font-size: 28px;
        }

        .doc-intro p,
        .doc-article-head p,
        .doc-category-head p {
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
        }

        .doc-kicker,
        .doc-topic-role {
            color: var(--forest);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .doc-count {
            min-width: 150px;
            padding-left: 20px;
            border-left: 3px solid var(--gold);
        }

        .doc-count strong,
        .doc-count span {
            display: block;
        }

        .doc-count strong {
            font-size: 28px;
        }

        .doc-count span {
            color: var(--muted);
            font-size: 13px;
        }

        .doc-toolbar {
            display: grid;
            grid-template-columns: minmax(260px, 1fr) auto auto;
            align-items: end;
            gap: 10px;
            margin-top: 20px;
        }

        .doc-search {
            display: grid;
            gap: 7px;
        }

        .doc-search > span {
            color: var(--muted);
            font-size: 12px;
            font-weight: 750;
            text-transform: uppercase;
        }

        .doc-search input {
            width: 100%;
            min-height: 44px;
            padding: 0 13px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff;
        }

        .doc-tabs {
            display: flex;
            flex-wrap: wrap;
            column-gap: 22px;
            row-gap: 0;
            margin-top: 14px;
            border-bottom: 1px solid var(--line);
        }

        .doc-tabs a {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 12px 1px 10px;
            border-bottom: 3px solid transparent;
            color: var(--muted);
            font-size: 13px;
            font-weight: 750;
            white-space: nowrap;
        }

        .doc-tabs a:hover,
        .doc-tabs a.active {
            border-bottom-color: var(--forest);
            color: var(--forest);
        }

        .doc-tabs a span {
            color: inherit;
            font-size: 11px;
            opacity: .75;
        }

        .doc-category {
            margin-top: 28px;
        }

        .doc-category-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 13px;
        }

        .doc-category-head h2 {
            margin: 0 0 4px;
            font-size: 20px;
        }

        .doc-category-head > span {
            color: var(--muted);
            font-size: 12px;
            white-space: nowrap;
        }

        .doc-topic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(260px, 100%), 1fr));
            gap: 12px;
        }

        .doc-topic {
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            gap: 8px;
            min-width: 0;
            min-height: 184px;
            padding: 17px;
            border: 1px solid var(--line);
            border-left: 4px solid var(--gold);
            border-radius: 8px;
            background: var(--panel);
        }

        .doc-topic:hover {
            border-color: #d6c9bd;
            border-left-color: var(--forest);
            box-shadow: 0 8px 18px rgba(58, 34, 25, .07);
        }

        .doc-topic strong {
            font-size: 17px;
            line-height: 1.3;
        }

        .doc-topic p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .doc-topic-link {
            color: var(--forest);
            font-size: 13px;
            font-weight: 800;
        }

        .doc-empty {
            display: grid;
            gap: 5px;
            margin-top: 24px;
        }

        .doc-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: var(--muted);
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .doc-breadcrumb a {
            color: var(--forest);
            font-weight: 750;
        }

        .doc-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(220px, 280px);
            gap: 36px;
            align-items: start;
        }

        .doc-article {
            min-width: 0;
        }

        .doc-article-head {
            padding-bottom: 24px;
            border-bottom: 1px solid var(--line);
        }

        .doc-article-head p {
            max-width: 760px;
            margin-bottom: 18px;
            font-size: 16px;
        }

        .doc-steps {
            padding: 26px 0 8px;
        }

        .doc-steps h3,
        .doc-callout h3,
        .doc-related h3 {
            margin: 0 0 14px;
            font-size: 17px;
        }

        .doc-steps ol {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .doc-steps li {
            display: grid;
            grid-template-columns: 36px minmax(0, 1fr);
            gap: 13px;
            align-items: start;
            padding: 15px 0;
            border-bottom: 1px solid var(--line);
        }

        .doc-step-number {
            display: grid;
            place-items: center;
            width: 32px;
            height: 32px;
            border: 1px solid #d8c7bc;
            border-radius: 50%;
            color: var(--forest);
            background: var(--brand-soft);
            font-size: 13px;
            font-weight: 850;
        }

        .doc-steps li p {
            margin: 4px 0 0;
            line-height: 1.6;
        }

        .doc-callout {
            margin-top: 22px;
            padding: 17px 19px;
            border-left: 4px solid var(--gold);
            background: var(--gold-soft);
        }

        .doc-callout ul {
            margin: 0;
            padding-left: 19px;
        }

        .doc-callout li {
            line-height: 1.55;
        }

        .doc-article-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 24px;
        }

        .doc-related {
            position: sticky;
            top: 20px;
            padding-left: 22px;
            border-left: 1px solid var(--line);
        }

        .doc-related > a {
            display: grid;
            gap: 5px;
            padding: 12px 0;
            border-bottom: 1px solid var(--line);
        }

        .doc-related > a strong {
            color: var(--forest);
            font-size: 14px;
        }

        .doc-related > a span,
        .doc-related > p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
        }

        @media (prefers-reduced-motion: reduce) {
            .btn,
            .user-pill,
            .nav-section-chevron {
                transition: none;
            }
        }

        @media (max-width: 980px) {
            body {
                background: var(--paper);
            }

            .app-shell::before {
                display: none;
            }

            .auth-shell,
            .app-shell,
            .two-col {
                grid-template-columns: 1fr;
            }

            .app-shell {
                background: var(--paper);
            }

            .auth-brand {
                min-height: 360px;
                padding: 34px;
            }

            .sidebar {
                position: static;
                height: auto;
                min-height: auto;
                padding: 12px 16px;
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }

            .sidebar .brand {
                margin-bottom: 0;
                padding-bottom: 8px;
                border-bottom: 0;
            }

            .sidebar .brand > div:nth-child(2) {
                flex: 1;
                min-width: 0;
            }

            .sidebar-toggle {
                display: inline-flex;
            }

            .nav {
                display: none;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                align-items: start;
                margin-top: 10px;
            }

            .sidebar.is-open .nav {
                display: grid;
            }

            .stats,
            .modules,
            .quick-actions,
            .filters-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .ledger-summary {
                grid-template-columns: minmax(220px, 1.4fr) repeat(2, minmax(120px, 1fr));
            }

            .doc-layout {
                grid-template-columns: 1fr;
            }

            .doc-related {
                position: static;
                padding: 22px 0 0;
                border-top: 1px solid var(--line);
                border-left: 0;
            }
        }

        @media (max-width: 1280px) {
            .accounting-breakdown {
                grid-template-columns: 1fr;
            }

            .top-actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 620px) {
            .main {
                padding: 18px;
            }

            .topbar,
            .top-actions,
            .form-actions,
            .inline-form {
                align-items: stretch;
                flex-direction: column;
            }

            .topbar__identity,
            .top-actions {
                width: 100%;
                flex: 1 1 auto;
            }

            .topbar__page-actions,
            .topbar__account {
                width: 100%;
            }

            .topbar__page-actions {
                padding: 0 0 12px;
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }

            .topbar__account {
                justify-content: space-between;
                margin-left: 0;
            }

            .stats,
            .modules,
            .nav,
            .quick-actions,
            .filters-grid,
            .form-grid,
            .detail-grid,
            .summary-row {
                grid-template-columns: 1fr;
            }

            .alert-item {
                grid-template-columns: 1fr;
            }

            .doc-intro,
            .doc-category-head {
                align-items: stretch;
                flex-direction: column;
            }

            .doc-count {
                padding: 10px 0 0;
                border-top: 3px solid var(--gold);
                border-left: 0;
            }

            .doc-toolbar {
                grid-template-columns: 1fr;
            }

            .doc-toolbar .button {
                width: 100%;
            }

            .doc-topic-grid {
                grid-template-columns: 1fr;
            }

            .doc-breadcrumb {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .ledger-summary {
                grid-template-columns: 1fr;
            }

            .ledger-toggle {
                justify-self: stretch;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    @yield('body')
    <div class="download-toast" id="download-toast" role="status" aria-live="polite"></div>
    <script>
        (() => {
            const toast = document.getElementById('download-toast');
            let toastTimer = null;

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-download-feedback]');

                if (! trigger || ! toast) {
                    return;
                }

                toast.textContent = trigger.dataset.downloadFeedback || 'Téléchargement lancé.';
                toast.classList.add('is-visible');

                clearTimeout(toastTimer);
                toastTimer = setTimeout(() => {
                    toast.classList.remove('is-visible');
                }, 3200);
            });
        })();
    </script>
</body>
</html>
