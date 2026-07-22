@extends('layouts.school', ['title' => $title ?? 'Lycée Privé Pagnidibsom'])

@section('body')
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">{{ $schoolSettings?->short_name ?? 'LPP' }}</div>
                <div>
                    <strong>{{ $schoolSettings?->school_name ?? 'Lycée Privé Pagnidibsom' }}</strong>
                    <span>{{ $academicYear?->name ?? 'Année non configurée' }}</span>
                </div>
            </div>

            <nav class="nav">
                <a class="{{ ($active ?? '') === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-dot"></span>Tableau de bord</a>
                <a class="{{ ($active ?? '') === 'help' ? 'active' : '' }}" href="{{ route('help.index') }}"><span class="nav-dot"></span>Aide</a>
                @can('students.view')
                    <a class="{{ ($active ?? '') === 'students' ? 'active' : '' }}" href="{{ route('students.index') }}"><span class="nav-dot"></span>Élèves</a>
                @endcan
                @can('classes.manage')
                    <a class="{{ ($active ?? '') === 'classes' ? 'active' : '' }}" href="{{ route('classes.index') }}"><span class="nav-dot"></span>Classes</a>
                @endcan
                @can('enrollments.view')
                    <a class="{{ ($active ?? '') === 'enrollments' ? 'active' : '' }}" href="{{ route('enrollments.index') }}"><span class="nav-dot"></span>Inscriptions</a>
                @endcan
                @can('payments.view')
                    <a class="{{ ($active ?? '') === 'payments' ? 'active' : '' }}" href="{{ route('payments.index') }}"><span class="nav-dot"></span>Paiements</a>
                @endcan
                @can('payments.reports')
                    <a class="{{ ($active ?? '') === 'accounting' ? 'active' : '' }}" href="{{ route('accounting.cash-journal') }}"><span class="nav-dot"></span>Comptabilité</a>
                @endcan
                @can('settings.manage')
                    <a class="{{ ($active ?? '') === 'tariffs' ? 'active' : '' }}" href="{{ route('tariffs.index') }}"><span class="nav-dot"></span>Tarifs</a>
                    <a class="{{ ($active ?? '') === 'subjects' ? 'active' : '' }}" href="{{ route('subjects.index') }}"><span class="nav-dot"></span>Matières</a>
                @endcan
                @can('timetables.view')
                    <a class="{{ ($active ?? '') === 'timetables' ? 'active' : '' }}" href="{{ route('timetables.index') }}"><span class="nav-dot"></span>Emplois du temps</a>
                @endcan
                @can('timetables.print')
                    <a class="{{ ($active ?? '') === 'teacher-attendance-sheets' ? 'active' : '' }}" href="{{ route('teacher-attendance-sheets.index') }}"><span class="nav-dot"></span>Émargements</a>
                @endcan
                @can('students.export')
                    <a class="{{ ($active ?? '') === 'certificates' ? 'active' : '' }}" href="{{ route('certificates.index') }}"><span class="nav-dot"></span>Documents</a>
                @endcan
                @canany(['students.export', 'payments.reports'])
                    <a class="{{ ($active ?? '') === 'reports' ? 'active' : '' }}" href="{{ auth()->user()->can('students.export') ? route('reports.class-list') : route('reports.payment-situation') }}"><span class="nav-dot"></span>Rapports</a>
                @endcanany
                @canany(['students.export', 'payments.reports', 'mock_exams.print', 'report_cards.print', 'attendance.reports'])
                    <a class="{{ ($active ?? '') === 'print-center' ? 'active' : '' }}" href="{{ route('print-center.index') }}"><span class="nav-dot"></span>Impressions</a>
                @endcanany
                @can('grades.view')
                    <a class="{{ ($active ?? '') === 'grades' ? 'active' : '' }}" href="{{ route('grades.index') }}"><span class="nav-dot"></span>Notes</a>
                @endcan
                @can('mock_exams.view')
                    <a class="{{ ($active ?? '') === 'mock-exams' ? 'active' : '' }}" href="{{ route('mock-exams.index') }}"><span class="nav-dot"></span>Examens blancs</a>
                @endcan
                @can('report_cards.view')
                    <a class="{{ ($active ?? '') === 'report-cards' ? 'active' : '' }}" href="{{ route('report-cards.index') }}"><span class="nav-dot"></span>Bulletins</a>
                @endcan
                @can('attendance.view')
                    <a class="{{ ($active ?? '') === 'attendance' ? 'active' : '' }}" href="{{ route('attendance.index') }}"><span class="nav-dot"></span>Absences</a>
                @endcan
                @canany(['attendance.view', 'students.export'])
                    <a class="{{ ($active ?? '') === 'exit-authorizations' ? 'active' : '' }}" href="{{ route('exit-authorizations.index') }}"><span class="nav-dot"></span>Autorisations</a>
                @endcanany
                @can('users.manage')
                    <a class="{{ ($active ?? '') === 'staff' ? 'active' : '' }}" href="{{ route('staff.index') }}"><span class="nav-dot"></span>Personnel</a>
                @endcan
                @can('activity_logs.view')
                    <a class="{{ ($active ?? '') === 'activity-logs' ? 'active' : '' }}" href="{{ route('activity-logs.index') }}"><span class="nav-dot"></span>Journal</a>
                @endcan
                @can('settings.manage')
                    <a class="{{ ($active ?? '') === 'settings' ? 'active' : '' }}" href="{{ route('settings.edit') }}"><span class="nav-dot"></span>Paramètres</a>
                @endcan
                @can('academic_years.manage')
                    <a class="{{ ($active ?? '') === 'academic-years' ? 'active' : '' }}" href="{{ route('academic-years.index') }}"><span class="nav-dot"></span>Années scolaires</a>
                @endcan
            </nav>
        </aside>

        <main class="main">
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
@endsection
