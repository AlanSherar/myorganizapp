@if(app()->environment('dev') || app()->environment('local'))
<div style="background: orange; color: white; padding: 5px; text-align:center;">
    ⚠️ Environment DEV ⚠️
</div>
@endif

<nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom px-4">
    <a class="navbar-brand text-danger fw-bold" href="{{ url('/') }}">OrganizApp</a>
    
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
        <!-- Main Navigation -->
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
                <a class="nav-link" href="#rutina">Rutina</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#calendario">Calendario</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#finanzas">Finanzas</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('tasks.index') }}">Tareas</a>
            </li>
        </ul>

        @auth
        <div class="d-flex align-items-center gap-3">
            <!-- User Dropdown -->
            <div class="dropdown">
                <a href="#" class="nav-link dropdown-toggle text-dark fw-semibold" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-1"></i>
                    {{ Auth::user()->name }}
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userDropdown">
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
        @else
        <div class="d-flex gap-2">
            <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-sm">Log in</a>
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn btn-danger btn-sm">Register</a>
            @endif
        </div>
        @endauth
    </div>
</nav>

<style>
    .border-bottom {
        border-bottom: 1px solid #dee2e6;
    }
</style>
