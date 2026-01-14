<!-- resources/views/auth/login.blade.php -->

@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card mt-4">
                <div class="card-header">{{ __('layouts/send-token.title') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('sendToken') }}">
                        @csrf

                        <!-- email -->
                        <div class="form-group row mb-4">
                            <label for="email" class="col-md-4 col-form-label text-md-right">{{ __('layouts/send-token.email') }}</label>

                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autofocus>

                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row mb-0 mt-2">
                            <div class="col-md-6 offset-md-4 text-end">
                                <a href="{{ url()->previous() }}" class="btn btn-secondary btn">{{ __('layouts/send-token.actions.cancel') }}</a>
                                <button type="submit" class="btn bg_bbinc">
                                    {{ __('layouts/send-token.actions.send') }}
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
