@extends('layouts.app')

@section('meta')
<meta property="og:title" content="{{ config('app.name') }} - Welcome">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current(); }}">
<meta property="og:image" content="/images/logos/small.png">
<meta property="og:description" content="{{ config('app.name') }} is a tight-knit community of like-minded people.">
<meta name="theme-color" content="#0000FF">
@endsection

@section('content')
<main class="landing-page vw-100 vh-100 justify-content-center align-items-center d-flex" style="background-image: url('{{ asset('images/YASSSSS.png') }}'); background-size: cover; background-repeat: no-repeat;">
    <div class="container-fluid text-center">
        <img src="{{ asset('images/logos/full.png') }}" class="img-fluid" width="500">
        <p class="lead my-3 motto user-select-none">
            A tight-knit community of like-minded people.
        </p>
        <a href="{{ route('login') }}" class="btn btn-secondary btn-lg shadow-lg mr-3"><i class="fas fa-sign-in-alt mr-1"></i>Login</a>
        <a href="{{ route('register') }}" class="btn btn-primary btn-lg shadow-lg"><i class="fas fa-user-plus mr-1"></i>Sign Up</a>
    </div>
</main>
@endsection
