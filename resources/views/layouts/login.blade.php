@extends('layouts.app')

@section('content')
<div class="container h-75 d-flex justify-content-center align-items-center">
    <div class="row w-100 justify-content-center">

        {{-- Login Card --}}
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-danger text-white text-center fw-bold py-3">
                    {{ __('Login') }}
                </div>

                <div class="card-body p-4">
                    <form method="POST" action="{{ route('login.submit') }}">
                        @csrf

                        {{-- Email --}}
                        <div class="form-group mb-3">
                            <label for="email" class="form-label text-secondary">{{ __('Email Address') }}</label>
                            <input 
                                id="email" 
                                type="email" 
                                class="form-control @error('email') is-invalid @enderror" 
                                name="email" 
                                value="{{ old('email') }}" 
                                required 
                                autofocus
                                placeholder="name@example.com"
                            >

                            @error('email')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        {{-- Password --}}
                        <div class="form-group mb-4">
                            <label for="password" class="form-label text-secondary">{{ __('Password') }}</label>
                            <input 
                                id="password" 
                                type="password" 
                                class="form-control @error('password') is-invalid @enderror" 
                                name="password" 
                                required
                                placeholder="********"
                            >

                            @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        {{-- Footer actions --}}
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a class="text-decoration-none text-muted small" href="{{ route('register') }}">
                                {{ __('Don\'t have an account?') }}
                            </a>

                            <button type="submit" class="btn btn-danger px-4 fw-bold shadow-sm">
                                {{ __('Login') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
