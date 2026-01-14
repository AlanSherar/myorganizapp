<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Logikli') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <!-- Bootstrap (Single Version) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
</head>

<body>

    <main>
        @include('layouts.message')
        <div class="container h-75 d-flex justify-content-center align-items-center">
            <div class="row w-100 justify-content-center align-items-stretch">

                {{-- Clock In Card --}}
                <div class="col-md-10">
                    <div class="card shadow-sm  h-100">
                        <div class="card-header fw-bold">{{ __('layouts/login.clock_in.title') }}</div>

                        <div class="card-body d-flex flex-column gap-3 align-items-center">
                            <form id="clockForm" method="POST" action="{{ route('clockActionPin') }}" class="w-100">
                                @csrf

                                {{-- Pin Input --}}
                                <div class="form-group row mb-4">
                                    <label for="secret_pin" class="col-md-4 col-form-label text-md-end">
                                        {{ __('layouts/login.clock_in.enter_pin') }}
                                    </label>

                                    <div class="col-md-6 d-flex">
                                        <input 
                                            id="secret_pin" 
                                            type="tel" 
                                            inputmode="numeric" 
                                            pattern="[0-9]*"
                                            class="form-control @error('secret_pin') is-invalid @enderror" 
                                            name="secret_pin" 
                                            value="{{ old('secret_pin') }}" 
                                            required
                                        >

                                        @error('secret_pin')
                                            <span class="invalid-feedback" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Hidden Action Type --}}
                                <input type="hidden" id="action_type" name="action_type" value="">

                                {{-- Buttons --}}
                                <div class="row mb-3 p-3 mx-2 border rounded bg-light shadow-sm align-items-center">
                                    <!-- Colonne gauche : nom de l'utilisateur -->
                                    <div class="col-md-4 d-flex align-items-center gap-2">
                                        <label class="mb-0 fw-semibold text-secondary">{{ __('layouts/login.clock_in.user_label') }}</label>
                                        <p id="user_div" class="mb-0"></p>
                                    </div>
                                
                                    <!-- Colonne droite : boutons -->
                                    <div class="col-md-8 d-flex justify-content-end align-items-center gap-2" id="buttons_div">
                                        {{-- Boutons dynamiques JS ici --}}
                                    </div>
                                </div>
                                
                                                    
                            </form>
                            @if (session('success'))
                                <div id="success-message" class="alert alert-success text-center" role="alert">
                                    {{ session('success') }}
                                </div>
                            @endif

                            <button type="submit" class="btn btn-sm bg_bbinc mx-2" onclick="handleDisplayButton()">
                                {{ __('layouts/login.clock_in.submit') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal Bootstrap -->
                <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                        
                        <div class="modal-header">
                            <h5 class="modal-title" id="loginModalLabel">Clock Time Exit</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        
                        <div class="modal-body">
                            <form method="POST" action="{{ route('login') }}">
                            @csrf
                            
                            <div class="mb-3">
                                <label for="login" class="form-label">{{ __('layouts/login.login.email') }}</label>
                                <input 
                                    id="email" 
                                    type="email" 
                                    class="form-control @error('email') is-invalid @enderror" 
                                    name="email" 
                                    value="{{ old('email') }}" 
                                    required 
                                    autofocus
                                >

                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">{{ __('layouts/login.login.password') }}</label>
                                <input 
                                    id="password" 
                                    type="password" 
                                    class="form-control @error('password') is-invalid @enderror" 
                                    name="password" 
                                    required
                                >

                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                            
                            <div class="form-group row mb-0 mt-2">
                                <div class="col-md-6 offset-md-4 text-end">
                                    <button type="submit" class="btn btn-sm bg_bbinc">
                                        {{ __('layouts/login.login.button') }}
                                    </button>
                                </div>
                            </div>
                            </form>
                        </div>
                        
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Optional Footer -->
    <footer class="bg_bbinc text-white py-2 mt-2">
        <div class="container text-center">
            <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" class="text-decoration-none text-body">
                <p class="m-0">&copy; {{ date('Y') }} {{ __('layouts/app.footer.copyright') }}</p>
            </a>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        /* Prend l'espace disponible entre le header et le footer */
    }

    .bg_bbinc {
        color: white;
        background-color: #00598A !important;
    }

    .bg_bbinc:hover {
        color: white;
    }

    .bg_bbinc_light {
        color: white;
        background-color: #449AAC !important;
    }

    .btn-hover:hover {
        border: 1px solid rgb(99, 98, 98);
        /* Ajoute une bordure pour surligner */
    }

    .title_sort {
        cursor: pointer;
        /* Changer le curseur en main */
        transition: background-color 0.3s;
        /* Animation douce pour le changement de fond */
    }

    .title_sort_hover {
        background-color: #f8f9fa;
        /* Change la couleur de fond au survol */
    }
</style>

{{-- JS --}}
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const successDiv = document.getElementById("success-message");

        if (successDiv) {
            setTimeout(() => {
                successDiv.style.display = 'none';
            }, 5000); // 60000 ms = 1 minute
        }
    });

    async function handleDisplayButton() {
        const pin = document.getElementById('secret_pin').value;
        const buttonsDiv = document.getElementById('buttons_div');

        try {
            if (pin) {
                buttonsDiv.innerHTML = `
                    <div class="text-center w-100">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">{{ __('layouts/login.clock_in.loading') }}</span>
                        </div>
                    </div>
                `;

                const response = await fetch(`/check-clock-status/${pin}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                });

                if (!response.ok) throw new Error('Network response was not ok');

                const data = await response.json();

                if (data.status == 'error') {
                    buttonsDiv.innerHTML = ''; // reset
                    toastr.error(data.message);
                }

                if (data.action) {
                    updateButtons(data.action, data.user);
                }
            }
        } catch (error) {
            console.error('Error:', error);
            buttonsDiv.innerHTML = '';
            buttonsDiv.innerHTML = error;
        }
    }

    function updateButtons(action, user) {
        const buttonsDiv = document.getElementById('buttons_div');
        const userDiv = document.getElementById('user_div');
        
        buttonsDiv.innerHTML = ''; // reset

        if(user){
            userDiv.innerHTML = user;
        }

        const templates = {
            'clock_in': `<button type="submit" class="btn btn-sm bg-success text-white" onclick="setAction('clock_in')">{{ __('layouts/login.clock_in.actions.clock_in') }}</button>`,
            'clock_out': `<button type="submit" class="btn btn-sm btn-danger" onclick="setAction('clock_out')">{{ __('layouts/login.clock_in.actions.clock_out') }}</button>`,
            'clock_out_break_in': `
                <button type="submit" class="btn btn-sm btn-warning mx-2" onclick="setAction('break_in')">{{ __('layouts/login.clock_in.actions.take_break') }}</button>
                <button type="submit" class="btn btn-sm btn-danger" onclick="setAction('clock_out')">{{ __('layouts/login.clock_in.actions.clock_out') }}</button>
            `,
            'clock_out_break_out': `
                <button type="submit" class="btn btn-sm btn-warning mx-2" onclick="setAction('break_out')">{{ __('layouts/login.clock_in.actions.break_out') }}</button>
                <button type="submit" class="btn btn-sm btn-danger" onclick="setAction('clock_out')">{{ __('layouts/login.clock_in.actions.clock_out') }}</button>
            `,
            'break_out': `<button type="submit" class="btn btn-sm btn-warning" onclick="setAction('break_out')">{{ __('layouts/login.clock_in.actions.break_out') }}</button>`
        };

        if (templates[action]) {
            buttonsDiv.innerHTML = templates[action];
        }
    }

    function setAction(value) {
        document.getElementById('action_type').value = value;
    }
</script>