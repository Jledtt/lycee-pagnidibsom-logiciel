<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Lycee Prive Pagnidibsom' }}</title>
    <style>
        :root {
            --ink: #162019;
            --muted: #65706a;
            --line: #dfe6e1;
            --paper: #f7f8f5;
            --panel: #ffffff;
            --forest: #164534;
            --forest-2: #20624a;
            --gold: #c9922c;
            --red: #b9473f;
            --blue: #2f629b;
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
                linear-gradient(120deg, rgba(22, 69, 52, .92), rgba(22, 69, 52, .76)),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1200' height='800' viewBox='0 0 1200 800'%3E%3Crect fill='%23eef2ec' width='1200' height='800'/%3E%3Cg fill='none' stroke='%23c9922c' stroke-width='12' opacity='.55'%3E%3Cpath d='M-60 720C150 550 350 500 540 610s390 90 720-170'/%3E%3Cpath d='M-30 540c220-90 420-70 600 60s360 150 690-10'/%3E%3Cpath d='M90 110h250v250H90zM780 80h260v180H780zM630 430h380v230H630z'/%3E%3C/g%3E%3C/svg%3E");
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
            background: rgba(255,255,255,.12);
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
            background: rgba(247,248,245,.96);
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
            outline: 3px solid rgba(32,98,74,.14);
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
        }

        .btn-primary {
            background: var(--forest);
            color: #fff;
        }

        .auth-card .btn-primary {
            width: 100%;
        }

        .btn-subtle {
            background: #edf2ee;
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
            min-height: 100vh;
            display: grid;
            grid-template-columns: 260px minmax(0, 1fr);
        }

        .sidebar {
            min-height: 100vh;
            padding: 22px 16px;
            border-right: 1px solid var(--line);
            background: #ffffff;
        }

        .sidebar .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 8px 24px;
        }

        .sidebar .brand strong {
            display: block;
            line-height: 1.1;
        }

        .sidebar .brand span {
            color: var(--muted);
            font-size: 13px;
        }

        .nav {
            display: grid;
            gap: 4px;
        }

        .nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 40px;
            padding: 0 10px;
            border-radius: 6px;
            color: #435048;
            font-weight: 650;
        }

        .nav a.active,
        .nav a:hover {
            background: #edf2ee;
            color: var(--forest);
        }

        .nav-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--gold);
        }

        .main {
            min-width: 0;
            padding: 26px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }

        .topbar h1 {
            margin: 0 0 4px;
            font-size: 28px;
        }

        .topbar p {
            margin: 0;
            color: var(--muted);
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-pill {
            padding: 9px 12px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: #fff;
            color: var(--muted);
            white-space: nowrap;
        }

        .grid {
            display: grid;
            gap: 16px;
        }

        .stats {
            grid-template-columns: repeat(5, minmax(140px, 1fr));
        }

        .stat,
        .panel,
        .module {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .stat {
            padding: 16px;
        }

        .stat span {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .stat strong {
            display: block;
            margin-top: 8px;
            font-size: 26px;
        }

        .two-col {
            grid-template-columns: minmax(0, 1.25fr) minmax(320px, .75fr);
            margin-top: 16px;
        }

        .panel {
            padding: 18px;
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

        .notice {
            margin: 0 0 16px;
            padding: 12px 14px;
            border: 1px solid rgba(32,98,74,.24);
            border-radius: 6px;
            background: rgba(32,98,74,.08);
            color: var(--forest);
            font-weight: 650;
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
            background: #edf2ee;
            color: var(--forest);
            font-size: 12px;
            font-weight: 750;
        }

        .badge-warning {
            background: rgba(201,146,44,.16);
            color: #83580f;
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
            background: #edf2ee;
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
            background: #fbfcfa;
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

        .pagination {
            display: flex;
            justify-content: flex-end;
            margin-top: 14px;
        }

        .modules {
            grid-template-columns: repeat(3, minmax(160px, 1fr));
        }

        .module {
            padding: 16px;
            min-height: 108px;
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

        .empty {
            padding: 18px;
            border: 1px dashed var(--line);
            border-radius: 8px;
            color: var(--muted);
            background: #fbfcfa;
        }

        @media (max-width: 980px) {
            .auth-shell,
            .app-shell,
            .two-col {
                grid-template-columns: 1fr;
            }

            .auth-brand {
                min-height: 360px;
                padding: 34px;
            }

            .sidebar {
                min-height: auto;
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }

            .nav {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .stats,
            .modules {
                grid-template-columns: repeat(2, minmax(0, 1fr));
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

            .stats,
            .modules,
            .nav,
            .form-grid,
            .detail-grid,
            .summary-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    @yield('body')
</body>
</html>
