@extends('layouts.app')

@section('content')
<div class="container h-75 d-flex justify-content-center align-items-center">
    <div class="row w-100 justify-content-center">

        {{-- Register Card --}}
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-danger text-white text-center fw-bold py-3">
                    {{ __('Register') }}
                </div>

                <div class="card-body p-4">
                    <form method="POST" action="{{ route('register') }}">
                        @csrf

                        {{-- Name --}}
                        <div class="form-group mb-3">
                            <label for="name" class="form-label text-secondary">{{ __('Name') }}</label>
                            <input 
                                id="name" 
                                type="text" 
                                class="form-control @error('name') is-invalid @enderror" 
                                name="name" 
                                value="{{ old('name') }}" 
                                required 
                                autofocus
                                placeholder="Your Name"
                            >
                            @error('name')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

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
                                placeholder="name@example.com"
                            >
                            @error('email')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        {{-- Password --}}
                        <div class="form-group mb-3">
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

                        {{-- Confirm Password --}}
                        <div class="form-group mb-4">
                            <label for="password-confirm" class="form-label text-secondary">{{ __('Confirm Password') }}</label>
                            <input 
                                id="password-confirm" 
                                type="password" 
                                class="form-control" 
                                name="password_confirmation" 
                                required
                                placeholder="********"
                            >
                        </div>

                        {{-- Footer actions --}}
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a class="text-decoration-none text-muted small" href="{{ route('login') }}">
                                {{ __('Already have an account?') }}
                            </a>

                            <button type="submit" class="btn btn-danger px-4 fw-bold shadow-sm">
                                {{ __('Register') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
