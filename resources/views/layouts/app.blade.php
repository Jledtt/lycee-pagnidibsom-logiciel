@extends('layouts.school', ['title' => $title ?? 'Lycee Prive Pagnidibsom'])

@section('body')
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">{{ $schoolSettings?->short_name ?? 'LPP' }}</div>
                <div>
                    <strong>{{ $schoolSettings?->school_name ?? 'Lycee Prive Pagnidibsom' }}</strong>
                    <span>{{ $academicYear?->name ?? 'Annee non configuree' }}</span>
                </div>
            </div>

            <nav class="nav">
                <a class="{{ ($active ?? '') === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-dot"></span>Tableau de bord</a>
                <a class="{{ ($active ?? '') === 'help' ? 'active' : '' }}" href="{{ route('help.index') }}"><span class="nav-dot"></span>Aide</a>
                @can('students.view')
                    <a class="{{ ($active ?? '') === 'students' ? 'active' : '' }}" href="{{ route('students.index') }}"><span class="nav-dot"></span>Eleves</a>
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
                    <a class="{{ ($active ?? '') === 'accounting' ? 'active' : '' }}" href="{{ route('accounting.cash-journal') }}"><span class="nav-dot"></span>Comptabilite</a>
                @endcan
                @can('settings.manage')
                    <a class="{{ ($active ?? '') === 'tariffs' ? 'active' : '' }}" href="{{ route('tariffs.index') }}"><span class="nav-dot"></span>Tarifs</a>
                    <a class="{{ ($active ?? '') === 'subjects' ? 'active' : '' }}" href="{{ route('subjects.index') }}"><span class="nav-dot"></span>Matieres</a>
                @endcan
                @can('students.export')
                    <a class="{{ ($active ?? '') === 'certificates' ? 'active' : '' }}" href="{{ route('certificates.index') }}"><span class="nav-dot"></span>Documents</a>
                @endcan
                @canany(['students.export', 'payments.reports'])
                    <a class="{{ ($active ?? '') === 'reports' ? 'active' : '' }}" href="{{ auth()->user()->can('students.export') ? route('reports.class-list') : route('reports.payment-situation') }}"><span class="nav-dot"></span>Rapports</a>
                @endcanany
                @can('grades.view')
                    <a class="{{ ($active ?? '') === 'grades' ? 'active' : '' }}" href="{{ route('grades.index') }}"><span class="nav-dot"></span>Notes</a>
                @endcan
                @can('report_cards.view')
                    <a class="{{ ($active ?? '') === 'report-cards' ? 'active' : '' }}" href="{{ route('report-cards.index') }}"><span class="nav-dot"></span>Bulletins</a>
                @endcan
                @can('attendance.view')
                    <a class="{{ ($active ?? '') === 'attendance' ? 'active' : '' }}" href="{{ route('attendance.index') }}"><span class="nav-dot"></span>Absences</a>
                @endcan
                @can('users.manage')
                    <a class="{{ ($active ?? '') === 'staff' ? 'active' : '' }}" href="{{ route('staff.index') }}"><span class="nav-dot"></span>Personnel</a>
                @endcan
                @can('settings.manage')
                    <a class="{{ ($active ?? '') === 'settings' ? 'active' : '' }}" href="{{ route('settings.edit') }}"><span class="nav-dot"></span>Parametres</a>
                @endcan
                @can('academic_years.manage')
                    <a class="{{ ($active ?? '') === 'academic-years' ? 'active' : '' }}" href="{{ route('academic-years.index') }}"><span class="nav-dot"></span>Annees scolaires</a>
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
                    <span class="user-pill">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-subtle" type="submit">Deconnexion</button>
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
