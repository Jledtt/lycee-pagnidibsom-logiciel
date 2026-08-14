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
                <button class="sidebar-toggle" type="button" aria-controls="sidebar-navigation" aria-expanded="false" aria-label="Afficher le menu" title="Afficher le menu">
                    <span aria-hidden="true">&#9776;</span>
                </button>
            </div>

            <nav class="nav" id="sidebar-navigation" aria-label="Navigation principale" data-tour-target="main-navigation">
                <x-navigation.section title="Accueil" :active="$activeIn(['dashboard', 'help'])">
                    <x-navigation.link :href="route('dashboard')" :active="$activeKey === 'dashboard'">Tableau de bord</x-navigation.link>
                    <x-navigation.link :href="route('help.index')" :active="$activeKey === 'help'" data-tour-target="documentation-link">Documentation</x-navigation.link>
                </x-navigation.section>

                @canany(['students.view', 'guardians.view'])
                    <x-navigation.section title="Élèves" :active="$activeIn(['students', 'guardians'])">
                        @can('students.view')
                            <x-navigation.link :href="route('students.index')" :active="$activeKey === 'students'">Dossiers élèves</x-navigation.link>
                        @endcan
                        @can('guardians.view')
                            <x-navigation.link :href="route('guardians.index')" :active="$activeKey === 'guardians'">Responsables légaux</x-navigation.link>
                        @endcan
                    </x-navigation.section>
                @endcanany

                @canany(['teachers.view', 'teacher_attendance.view', 'teacher_fees.view'])
                    <x-navigation.section title="Professeurs" :active="$activeIn(['teachers', 'teacher-work-sessions', 'teacher-attendance-sheets', 'teacher-fees'])">
                        @can('teachers.view')
                            <x-navigation.link :href="route('teachers.index')" :active="$activeKey === 'teachers'">Dossiers professeurs</x-navigation.link>
                        @endcan
                        @can('teacher_attendance.view')
                            <x-navigation.link :href="route('teacher-work-sessions.index')" :active="$activeKey === 'teacher-work-sessions'">Heures effectuées</x-navigation.link>
                        @endcan
                        @can('teacher_attendance.view')
                            <x-navigation.link :href="route('teacher-attendance-sheets.index')" :active="$activeKey === 'teacher-attendance-sheets'">Fiches d’émargement</x-navigation.link>
                        @endcan
                        @can('teacher_fees.view')
                            <x-navigation.link :href="route('teacher-fees.index')" :active="$activeKey === 'teacher-fees'">Honoraires</x-navigation.link>
                        @endcan
                    </x-navigation.section>
                @endcanany

                @canany(['classes.manage', 'enrollments.view', 'timetables.view', 'timetables.print', 'attendance.view', 'students.export'])
                    <x-navigation.section title="Scolarité" :active="$activeIn(['classes', 'enrollments', 'timetables', 'attendance', 'exit-authorizations'])">
                        @can('classes.manage')
                            <x-navigation.link :href="route('classes.index')" :active="$activeKey === 'classes'">Classes</x-navigation.link>
                        @endcan
                        @can('enrollments.view')
                            <x-navigation.link :href="route('enrollments.index')" :active="$activeKey === 'enrollments'">Inscriptions</x-navigation.link>
                        @endcan
                        @can('timetables.view')
                            <x-navigation.link :href="route('timetables.index')" :active="$activeKey === 'timetables'">Emplois du temps</x-navigation.link>
                        @endcan
                        @can('attendance.view')
                            <x-navigation.link :href="route('attendance.index')" :active="$activeKey === 'attendance'">Absences</x-navigation.link>
                        @endcan
                        @canany(['attendance.view', 'students.export'])
                            <x-navigation.link :href="route('exit-authorizations.index')" :active="$activeKey === 'exit-authorizations'">Autorisations</x-navigation.link>
                        @endcanany
                    </x-navigation.section>
                @endcanany

                @canany(['payments.view', 'payments.reports', 'settings.manage'])
                    <x-navigation.section title="Finances" :active="$activeIn(['payments', 'accounting', 'tariffs'])">
                        @can('payments.view')
                            <x-navigation.link :href="route('payments.index')" :active="$activeKey === 'payments'">Paiements</x-navigation.link>
                        @endcan
                        @can('payments.reports')
                            <x-navigation.link :href="route('accounting.cash-journal')" :active="$activeKey === 'accounting'">Comptabilité</x-navigation.link>
                        @endcan
                        @can('settings.manage')
                            <x-navigation.link :href="route('tariffs.index')" :active="$activeKey === 'tariffs'">Tarifs</x-navigation.link>
                        @endcan
                    </x-navigation.section>
                @endcanany

                @canany(['grades.view', 'report_cards.view', 'settings.manage'])
                    <x-navigation.section title="Notes / Bulletins" :active="$activeIn(['grades', 'report-cards', 'subjects'])">
                        @can('grades.view')
                            <x-navigation.link :href="route('grades.index')" :active="$activeKey === 'grades'">Notes</x-navigation.link>
                        @endcan
                        @can('report_cards.view')
                            <x-navigation.link :href="route('report-cards.index')" :active="$activeKey === 'report-cards'">Bulletins</x-navigation.link>
                        @endcan
                        @can('settings.manage')
                            <x-navigation.link :href="route('subjects.index')" :active="$activeKey === 'subjects'">Matières</x-navigation.link>
                        @endcan
                    </x-navigation.section>
                @endcanany

                @can('mock_exams.view')
                    <x-navigation.section title="Examens" :active="$activeIn(['mock-exams'])">
                        <x-navigation.link :href="route('mock-exams.index')" :active="$activeKey === 'mock-exams'">Examens blancs</x-navigation.link>
                    </x-navigation.section>
                @endcan

                @canany(['students.import', 'students.export', 'payments.reports', 'grades.view', 'report_cards.view', 'mock_exams.view', 'mock_exams.print', 'attendance.reports', 'attendance.view'])
                    <x-navigation.section title="Documents" :active="$activeIn(['certificates', 'reports', 'print-center', 'exports'])">
                        @can('students.export')
                            <x-navigation.link :href="route('certificates.index')" :active="$activeKey === 'certificates'">Certificats</x-navigation.link>
                        @endcan
                        @canany(['students.export', 'payments.reports'])
                            <x-navigation.link :href="auth()->user()->can('students.export') ? route('reports.class-list') : route('reports.payment-situation')" :active="$activeKey === 'reports'">Rapports</x-navigation.link>
                        @endcanany
                        @canany(['students.export', 'payments.reports', 'mock_exams.print', 'report_cards.print', 'attendance.reports'])
                            <x-navigation.link :href="route('print-center.index')" :active="$activeKey === 'print-center'">Centre d’impression</x-navigation.link>
                        @endcanany
                        @canany(['students.import', 'students.export', 'payments.reports', 'grades.view', 'report_cards.view', 'mock_exams.view', 'mock_exams.print', 'attendance.reports', 'attendance.view'])
                            <x-navigation.link :href="route('exports.index')" :active="$activeKey === 'exports'">Imports / Exports</x-navigation.link>
                        @endcanany
                    </x-navigation.section>
                @endcanany

                @can('communications.view')
                    <x-navigation.section title="Communication" :active="$activeIn(['communications'])">
                        <x-navigation.link :href="route('communications.index')" :active="$activeKey === 'communications'">Notifications</x-navigation.link>
                    </x-navigation.section>
                @endcan

                @canany(['users.manage', 'activity_logs.view', 'settings.manage', 'academic_years.manage'])
                    <x-navigation.section title="Administration" :active="$activeIn(['staff', 'activity-logs', 'settings', 'academic-years', 'profile'])">
                        @can('users.manage')
                            <x-navigation.link :href="route('staff.index')" :active="$activeKey === 'staff'">Personnel</x-navigation.link>
                        @endcan
                        @can('activity_logs.view')
                            <x-navigation.link :href="route('activity-logs.index')" :active="$activeKey === 'activity-logs'">Journal</x-navigation.link>
                        @endcan
                        @can('settings.manage')
                            <x-navigation.link :href="route('settings.edit')" :active="$activeKey === 'settings'">Paramètres</x-navigation.link>
                        @endcan
                        @can('academic_years.manage')
                            <x-navigation.link :href="route('academic-years.index')" :active="$activeKey === 'academic-years'">Années scolaires</x-navigation.link>
                        @endcan
                    </x-navigation.section>
                @endcanany
            </nav>
        </aside>

        <main class="main" id="main-content" tabindex="-1">
            <div class="main-frame">
            <header class="topbar">
                <div class="topbar__identity">
                    <h1>{{ $pageTitle ?? 'Tableau de bord' }}</h1>
                    <p>{{ $pageSubtitle ?? 'Gestion scolaire' }}</p>
                </div>

                <div class="top-actions topbar__controls">
                    @hasSection('page_actions')
                        <div class="topbar__page-actions">
                            @yield('page_actions')
                        </div>
                    @endif

                    <div class="topbar__account">
                        <a class="user-pill" href="{{ route('profile.show') }}" aria-label="Ouvrir le profil de {{ auth()->user()->name }}">
                            {{ auth()->user()->name }}
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-subtle" type="submit">Déconnexion</button>
                        </form>
                    </div>
                </div>
            </header>

            @if (session('success'))
                <p class="notice" role="status" aria-live="polite">{{ session('success') }}</p>
            @endif

            @yield('content')
            </div>
        </main>
    </div>

    @stack('dialogs')

    <x-ui.confirmation-dialog />

    <script>
        (() => {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.sidebar-toggle');

            if (! sidebar || ! toggle) {
                return;
            }

            const setMenuState = (open) => {
                sidebar.classList.toggle('is-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.title = open ? 'Masquer le menu' : 'Afficher le menu';
                toggle.setAttribute('aria-label', toggle.title);
            };

            toggle.addEventListener('click', () => {
                setMenuState(! sidebar.classList.contains('is-open'));
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
                    setMenuState(false);
                    toggle.focus();
                }
            });
        })();
    </script>
@endsection
