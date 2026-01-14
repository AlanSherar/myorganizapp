@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card mt-4">
                <div class="card-header">{{ __('layouts/reset-password.title') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('updatePassword') }}">
                        @csrf

                        <!-- Email -->
                        <div class="form-group row mb-4">
                            <label for="email" class="col-md-4 col-form-label text-md-right">{{ __('layouts/reset-password.email') }}</label>

                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email', $email) }}" required readonly>

                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="form-group row">
                            <label for="password" class="col-md-4 col-form-label text-md-right">
                                {{ __('layouts/reset-password.password') }}<span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required>
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group row">
                            <label for="password_confirmation" class="col-md-4 col-form-label text-md-right">
                                {{ __('layouts/reset-password.confirm_password') }}<span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <input id="password_confirmation" type="password" class="form-control" name="password_confirmation" required>
                            </div>
                        </div>

                        <div class="form-group row mb-0 mt-2">
                            <div class="col-md-6 offset-md-4 text-end">
                                <a href="{{ url()->previous() }}" class="btn btn-secondary btn">{{ __('layouts/reset-password.actions.cancel') }}</a>
                                <button type="submit" class="btn bg_bbinc">
                                    {{ __('layouts/reset-password.actions.reset') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    html, body {
        height: 100%;
        margin: 0;
        display: flex;
        flex-direction: column;
    }

    main {
        flex: 1; /* Prend l'espace disponible entre le header et le footer */
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
</style>
@endsection
