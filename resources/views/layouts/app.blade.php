@extends('layouts.school', ['title' => $title ?? 'Lycée Privé Pagnidibsom'])

@section('body')
    @php
        $activeKey = $active ?? '';
        $activeIn = fn (array $keys) => in_array($activeKey, $keys, true);
    @endphp

    <a class="skip-link" href="#main-content">Aller au contenu principal</a>

    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">{{ $schoolSettings?->short_name ?? 'LPP' }}</div>
                <div>
                    <strong>{{ $schoolSettings?->school_name ?? 'Lycée Privé Pagnidibsom' }}</strong>
                    <span>{{ $academicYear?->name ?? 'Année non configurée' }}</span>
                </div>
                <button class="sidebar-toggle" type="button" aria-controls="sidebar-navigation" aria-expanded="false" title="Afficher le menu">
                    <span aria-hidden="true">&#9776;</span>
                </button>
            </div>

            <nav class="nav" id="sidebar-navigation">
                <div class="nav-section {{ $activeIn(['dashboard', 'help']) ? 'active-section' : '' }}">
                    <p class="nav-section-title">Accueil</p>
                    <a class="{{ $activeKey === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-dot"></span>Tableau de bord</a>
                    <a class="{{ $activeKey === 'help' ? 'active' : '' }}" href="{{ route('help.index') }}"><span class="nav-dot"></span>Documentation</a>
                </div>

                @can('students.view')
                    <div class="nav-section {{ $activeIn(['students']) ? 'active-section' : '' }}">
                        <p class="nav-section-title">Élèves</p>
                        <a class="{{ $activeKey === 'students' ? 'active' : '' }}" href="{{ route('students.index') }}"><span class="nav-dot"></span>Dossiers élèves</a>
                    </div>
                @endcan

                @canany(['teachers.view', 'teacher_attendance.view', 'teacher_fees.view'])
                    <div class="nav-section {{ $activeIn(['teachers', 'teacher-work-sessions', 'teacher-attendance-sheets', 'teacher-fees']) ? 'active-section' : '' }}">
                        <p class="nav-section-title">Professeurs</p>
                        @can('teachers.view')
                            <a class="{{ $activeKey === 'teachers' ? 'active' : '' }}" href="{{ route('teachers.index') }}"><span class="nav-dot"></span>Dossiers professeurs</a>
                        @endcan
                        @can('teacher_attendance.view')
                            <a class="{{ $activeKey === 'teacher-work-sessions' ? 'active' : '' }}" href="{{ route('teacher-work-sessions.index') }}"><span class="nav-dot"></span>Heures effectuées</a>
                        @endcan
                        @can('teacher_attendance.view')
                            <a class="{{ $activeKey === 'teacher-attendance-sheets' ? 'active' : '' }}" href="{{ route('teacher-attendance-sheets.index') }}"><span class="nav-dot"></span>Fiches d’émargement</a>
                        @endcan
                        @can('teacher_fees.view')
                            <a class="{{ $activeKey === 'teacher-fees' ? 'active' : '' }}" href="{{ route('teacher-fees.index') }}"><span class="nav-dot"></span>Honoraires</a>
                        @endcan
                    </div>
                @endcanany

                @canany(['classes.manage', 'enrollments.view', 'timetables.view', 'timetables.print', 'attendance.view', 'students.export'])
                    <div class="nav-section {{ $activeIn(['classes', 'enrollments', 'timetables', 'attendance', 'exit-authorizations']) ? 'active-section' : '' }}">
                        <p class="nav-section-title">Scolarité</p>
                        @can('classes.manage')
                            <a class="{{ $activeKey === 'classes' ? 'active' : '' }}" href="{{ route('classes.index') }}"><span class="nav-dot"></span>Classes</a>
                        @endcan
                        @can('enrollments.view')
                            <a class="{{ $activeKey === 'enrollments' ? 'active' : '' }}" href="{{ route('enrollments.index') }}"><span class="nav-dot"></span>Inscriptions</a>
                        @endcan
                        @can('timetables.view')
                            <a class="{{ $activeKey === 'timetables' ? 'active' : '' }}" href="{{ route('timetables.index') }}"><span class="nav-dot"></span>Emplois du temps</a>
                        @endcan
                        @can('attendance.view')
                            <a class="{{ $activeKey === 'attendance' ? 'active' : '' }}" href="{{ route('attendance.index') }}"><span class="nav-dot"></span>Absences</a>
                        @endcan
                        @canany(['attendance.view', 'students.export'])
                            <a class="{{ $activeKey === 'exit-authorizations' ? 'active' : '' }}" href="{{ route('exit-authorizations.index') }}"><span class="nav-dot"></span>Autorisations</a>
                        @endcanany
                    </div>
                @endcanany

                @canany(['payments.view', 'payments.reports', 'settings.manage'])
                    <div class="nav-section {{ $activeIn(['payments', 'accounting', 'tariffs']) ? 'active-section' : '' }}">
                        <p class="nav-section-title">Finances</p>
                        @can('payments.view')
                            <a class="{{ $activeKey === 'payments' ? 'active' : '' }}" href="{{ route('payments.index') }}"><span class="nav-dot"></span>Paiements</a>
                        @endcan
                        @can('payments.reports')
                            <a class="{{ $activeKey === 'accounting' ? 'active' : '' }}" href="{{ route('accounting.cash-journal') }}"><span class="nav-dot"></span>Comptabilité</a>
                        @endcan
                        @can('settings.manage')
                            <a class="{{ $activeKey === 'tariffs' ? 'active' : '' }}" href="{{ route('tariffs.index') }}"><span class="nav-dot"></span>Tarifs</a>
                        @endcan
                    </div>
                @endcanany

                @canany(['grades.view', 'report_cards.view', 'settings.manage'])
                    <div class="nav-section {{ $activeIn(['grades', 'report-cards', 'subjects']) ? 'active-section' : '' }}">
                        <p class="nav-section-title">Notes / Bulletins</p>
                        @can('grades.view')
                            <a class="{{ $activeKey === 'grades' ? 'active' : '' }}" href="{{ route('grades.index') }}"><span class="nav-dot"></span>Notes</a>
                        @endcan
                        @can('report_cards.view')
                            <a class="{{ $activeKey === 'report-cards' ? 'active' : '' }}" href="{{ route('report-cards.index') }}"><span class="nav-dot"></span>Bulletins</a>
                        @endcan
                        @can('settings.manage')
                            <a class="{{ $activeKey === 'subjects' ? 'active' : '' }}" href="{{ route('subjects.index') }}"><span class="nav-dot"></span>Matières</a>
                        @endcan
                    </div>
                @endcanany

                @can('mock_exams.view')
                    <div class="nav-section {{ $activeIn(['mock-exams']) ? 'active-section' : '' }}">
                        <p class="nav-section-title">Examens</p>
                        <a class="{{ $activeKey === 'mock-exams' ? 'active' : '' }}" href="{{ route('mock-exams.index') }}"><span class="nav-dot"></span>Examens blancs</a>
                    </div>
                @endcan

                @canany(['students.import', 'students.export', 'payments.reports', 'grades.view', 'report_cards.view', 'mock_exams.view', 'mock_exams.print', 'attendance.reports', 'attendance.view'])
                    <div class="nav-section {{ $activeIn(['certificates', 'reports', 'print-center', 'exports']) ? 'active-section' : '' }}">
                        <p class="nav-section-title">Documents</p>
                        @can('students.export')
                            <a class="{{ $activeKey === 'certificates' ? 'active' : '' }}" href="{{ route('certificates.index') }}"><span class="nav-dot"></span>Certificats</a>
                        @endcan
                        @canany(['students.export', 'payments.reports'])
                            <a class="{{ $activeKey === 'reports' ? 'active' : '' }}" href="{{ auth()->user()->can('students.export') ? route('reports.class-list') : route('reports.payment-situation') }}"><span class="nav-dot"></span>Rapports</a>
                        @endcanany
                        @canany(['students.export', 'payments.reports', 'mock_exams.print', 'report_cards.print', 'attendance.reports'])
                            <a class="{{ $activeKey === 'print-center' ? 'active' : '' }}" href="{{ route('print-center.index') }}"><span class="nav-dot"></span>Centre d’impression</a>
                        @endcanany
                        @canany(['students.import', 'students.export', 'payments.reports', 'grades.view', 'report_cards.view', 'mock_exams.view', 'mock_exams.print', 'attendance.reports', 'attendance.view'])
                            <a class="{{ $activeKey === 'exports' ? 'active' : '' }}" href="{{ route('exports.index') }}"><span class="nav-dot"></span>Imports / Exports</a>
                        @endcanany
                    </div>
                @endcanany

                @can('communications.view')
                    <div class="nav-section {{ $activeIn(['communications']) ? 'active-section' : '' }}">
                        <p class="nav-section-title">Communication</p>
                        <a class="{{ $activeKey === 'communications' ? 'active' : '' }}" href="{{ route('communications.index') }}"><span class="nav-dot"></span>Notifications</a>
                    </div>
                @endcan

                @canany(['users.manage', 'activity_logs.view', 'settings.manage', 'academic_years.manage'])
                    <div class="nav-section {{ $activeIn(['staff', 'activity-logs', 'settings', 'academic-years', 'profile']) ? 'active-section' : '' }}">
                        <p class="nav-section-title">Administration</p>
                        @can('users.manage')
                            <a class="{{ $activeKey === 'staff' ? 'active' : '' }}" href="{{ route('staff.index') }}"><span class="nav-dot"></span>Personnel</a>
                        @endcan
                        @can('activity_logs.view')
                            <a class="{{ $activeKey === 'activity-logs' ? 'active' : '' }}" href="{{ route('activity-logs.index') }}"><span class="nav-dot"></span>Journal</a>
                        @endcan
                        @can('settings.manage')
                            <a class="{{ $activeKey === 'settings' ? 'active' : '' }}" href="{{ route('settings.edit') }}"><span class="nav-dot"></span>Paramètres</a>
                        @endcan
                        @can('academic_years.manage')
                            <a class="{{ $activeKey === 'academic-years' ? 'active' : '' }}" href="{{ route('academic-years.index') }}"><span class="nav-dot"></span>Années scolaires</a>
                        @endcan
                    </div>
                @endcanany
            </nav>
        </aside>

        <main class="main" id="main-content" tabindex="-1">
            <header class="topbar">
                <div>
                    <h1>{{ $pageTitle ?? 'Tableau de bord' }}</h1>
                    <p>{{ $pageSubtitle ?? 'Gestion scolaire' }}</p>
                </div>

                <div class="top-actions">
                    @yield('page_actions')
                    <a class="user-pill" href="{{ route('profile.show') }}">{{ auth()->user()->name }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-subtle" type="submit">Déconnexion</button>
                    </form>
                </div>
            </header>

            @if (session('success'))
                <p class="notice">{{ session('success') }}</p>
            @endif

            @yield('content')
        </main>
    </div>

    @stack('dialogs')

    <script>
        (() => {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.sidebar-toggle');

            if (! sidebar || ! toggle) {
                return;
            }

            toggle.addEventListener('click', () => {
                const open = sidebar.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.title = open ? 'Masquer le menu' : 'Afficher le menu';
            });
        })();
    </script>
@endsection
