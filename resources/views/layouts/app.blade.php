@extends('layouts.school', ['title' => $title ?? 'Lycee Prive Pagnidibsom'])

@section('body')
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">LPP</div>
                <div>
                    <strong>Lycee Prive<br>Pagnidibsom</strong>
                    <span>{{ $academicYear?->name ?? 'Annee non configuree' }}</span>
                </div>
            </div>

            <nav class="nav">
                <a class="{{ ($active ?? '') === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-dot"></span>Tableau de bord</a>
                <a class="{{ ($active ?? '') === 'students' ? 'active' : '' }}" href="{{ route('students.index') }}"><span class="nav-dot"></span>Eleves</a>
                <a class="{{ ($active ?? '') === 'classes' ? 'active' : '' }}" href="{{ route('classes.index') }}"><span class="nav-dot"></span>Classes</a>
                <a class="{{ ($active ?? '') === 'enrollments' ? 'active' : '' }}" href="{{ route('enrollments.index') }}"><span class="nav-dot"></span>Inscriptions</a>
                <a class="{{ ($active ?? '') === 'payments' ? 'active' : '' }}" href="{{ route('payments.index') }}"><span class="nav-dot"></span>Paiements</a>
                <a class="{{ ($active ?? '') === 'tariffs' ? 'active' : '' }}" href="{{ route('tariffs.index') }}"><span class="nav-dot"></span>Tarifs</a>
                <a class="{{ ($active ?? '') === 'certificates' ? 'active' : '' }}" href="{{ route('certificates.index') }}"><span class="nav-dot"></span>Documents</a>
                <a href="#"><span class="nav-dot"></span>Notes</a>
                <a href="#"><span class="nav-dot"></span>Bulletins</a>
                <a href="#"><span class="nav-dot"></span>Absences</a>
                <a href="#"><span class="nav-dot"></span>Parametres</a>
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
