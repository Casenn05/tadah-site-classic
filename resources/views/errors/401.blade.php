@extends('layouts.error')

@section('title')
401
@endsection

@section('content')
<div class="card card-body shadow-sm">
    <div class="text-center px-3 py-3">
        <img class="error-blob-img" src="/images/blobs/dead.png">
        <hr>
        <h3>401</h3>
        <span>You do not have the authorization for the requested resource.</span>
        <div class="d-flex justify-content-center mt-3">
            <button class="btn btn-secondary mr-2 shadow-sm" role="button" onclick="window.history.back()">Go back</button>
            <a href="/" class="btn btn-primary" role="button">Home</a>
        </div>
    </div>
</div>
@endsection