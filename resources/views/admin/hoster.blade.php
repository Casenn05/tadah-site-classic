@inject('user', 'App\Http\Controllers\UsersController')

@extends('layouts.admin')

@section('title')
Toggle Hoster
@endsection

@section('content')
<div class="container">
    <h1><b>Toggle Verified Hoster</b></h1>
    <p>Toggles Verified Hoster on a user. They get their log-in bonus increased by 1.25x, and they get more place slots. On-site, they get a special badge and higher priority.</p>
    <hr>
    @if (session()->has('error'))
        <div class="alert alert-danger">
            {{ session()->get('error') }}
        </div>
    @endif
    @if (session()->has('success'))
        <div class="alert alert-success">
            {{ session()->get('success') }}
        </div>
    @endif
    <form method="POST" action="{{ route('admin.togglehoster') }}" enctype="multipart/form-data">
        @csrf

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" name="username" class="form-control" id="username" placeholder="Username">
        </div>
        <button type="submit" class="btn btn-info shadow-sm">Toggle Verified Hoster</button>
    </form>
</div>
@endsection