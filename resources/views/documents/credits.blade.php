@extends('layouts.app')

@section('title')
Contributors
@endsection

@section('content')
<div class="container">
    <div class="card shadow-sm">
        <div class="card-header">{{ config('app.name') }} Credits</div>
        <div class="card-body">
            <h1>{{ config('app.name') }} Developers</h1>
            <ul>
                <li><a href="{{ route('users.profile', 1) }}"><b>kinery</b></a> - Project Lead</li>
                <li><a href="{{ route('users.profile', 59) }}"><b>spike</b></a> - Tadah lead artist, designed the logo and Token icon</li>
                <li><a href="{{ route('users.profile', 137) }}"><b>taskmanager</b></a> - Frontend development</li>
                <li><a href="{{ route('users.profile', 45) }}"><b>Iago</b></a> - Client and frontend development</li>
                <li><a href="{{ route('users.profile', 44) }}"><b>hitius</b></a> - Dedicated servers</li>
                <li><a href="{{ route('users.profile', 66) }}"><b>Carrot</b></a> - Backend engineer</li>
                <li><a href="{{ route('users.profile', 108) }}"><b>pizzaboxer</b></a> - Client development</li>
                <li><a href="{{ route('users.profile', 46) }}"><b>Ahead</b></a> - Backend development</li>
            </ul>
            <h4>Special thanks</h4>
            <ul>
                <li><b>Anonymous</b> - Helped clean up code, client help</li>
                <li><a href="{{ route('users.profile', 5) }}"><b>splat</b></a> - Ideas guy and helped found Tadah</li>
                <li><a href="{{ route('users.profile', 84) }}"><b>past</b></a> - Catalog upload, event staff</li>
                <li><a href="{{ route('users.profile', 95) }}"><b>warden</b></a> - Catalog uploader, event host</li>
                <li><a href="{{ route('users.profile', 79) }}"><b>cole</b></a> - Main catalog manager</li>
                <li><b>You</b> - for using Tadah!</li>
            </ul>

            <p>Without these people lending their help, {{ config('app.name') }} would not be as good as it is today. Thanks, everyone.</p>
        </div>
    </div>
</div>
@endsection
