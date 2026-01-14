<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'OrganizApp') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- ClockPicker CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/clockpicker/0.0.7/bootstrap-clockpicker.min.css">

    <!-- JS -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- ClockPicker JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clockpicker/0.0.7/bootstrap-clockpicker.min.js"></script>

    {{-- @livewireStyles --}}
</head>

<body>
    @auth
    <header>
        @include('layouts.header')
    </header>
    @endauth

    @guest
    <header>
        <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
            <div class="container">
                <a class="navbar-brand text-danger fw-bold" href="{{ url('/') }}">OrganizApp</a>
                <div class="ms-auto">
                     @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="btn btn-outline-secondary me-2">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-danger">Register</a>
                        @endif
                    @endif
                </div>
            </div>
        </nav>
    </header>
    @endguest

    <main class="py-4">
        @include('layouts.message')
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-auto">
        <div class="container text-center">
            <p class="mb-0">
                &copy; {{ date('Y') }} OrganizApp. Todos los derechos reservados.
            </p>
        </div>
    </footer>

    <!-- Scripts -->
    {{-- @livewireScripts --}}
    @stack('scripts')
</body>

</html>

<style>
    html,
    body {
        height: 100%;
        margin: 0;
        display: flex;
        flex-direction: column;
    }

    main {
        flex: 1;
    }

    .bg-custom-red {
        background-color: #dc3545 !important; /* Bootstrap Danger Red */
        color: white;
    }
    
    .text-custom-red {
        color: #dc3545 !important;
    }

    .btn-hover:hover {
        border: 1px solid rgb(99, 98, 98);
    }
</style>