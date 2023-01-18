@extends('layouts.app')

@section('title')
Configure Item
@endsection

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">{{ __('Configure Item') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('item.configure', $item->id) }}" enctype="multipart/form-data">
                        @csrf

                        @if (session()->has('error'))
                            <div class="alert alert-danger">
                                {{ session()->get('error') }}
                            </div>
                        @endif

                        <div class="form-group row">
                            <label for="name" class="col-md-4 col-form-label text-md-right">{{ __('Item Name') }}</label>

                            <div class="col-md-6">
                                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ $item->name }}" required autofocus>

                                @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="thumbnail" class="col-md-4 col-form-label text-md-right">{{ __('Thumbnail') }}</label>

                            <div class="col-md-6">
                                <img data-tadah-thumbnail-id="{{ $item->id }}" data-tadah-thumbnail-type="item-thumbnail" src="{{ asset('images/thumbnail/blank.png') }}" style="object-fit: contain;" alt="{{ $item->name }}" width="250" height="250">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="description" class="col-md-4 col-form-label text-md-right">{{ __('Description') }}</label>

                            <div class="col-md-6">
                                <textarea id="description" type="text" class="form-control @error('description') is-invalid @enderror" name="description" required>{{ $item->description }}</textarea>

                                @error('description')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        @if ($item->isXmlAsset() && Auth::user()->isAdmin())
                        <div class="form-group row">
                            <label for="thumbnailurl" class="col-md-4 col-form-label text-md-right">{{ __('Thumbnail URL') }}</label>

                            <div class="col-md-6">
                                <input id="thumbnailurl" type="text" class="form-control @error('thumbnailurl') is-invalid @enderror" name="thumbnailurl" value="{{ $item->thumbnail_url }}">

                                @error('thumbnailurl')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        @endif

                        @if ($item->isXmlAsset() || $item->type == "Lua")
                            @if (Auth::user()->isAdmin())
                            <div class="form-group row">
                                <label for="xml" class="col-md-4 col-form-label text-md-right">{{ __('XML Data') }}</label>

                                <div class="col-md-6">
                                    <textarea id="xml" type="text" class="form-control @error('xml') is-invalid @enderror" name="xml" rows="3" required>{{ $item->getContents() }}</textarea>

                                    @error('xml')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            @endif
                        @endif

                        <div class="form-group row">
                            <label for="price" class="col-md-4 col-form-label text-md-right">{{ __('Price') }}</label>

                            <div class="col-md-6">
                                <input type="number" onwheel="this.blur()" class="form-control @error('price') is-invalid @enderror" name="price" value="{{ $item->price }}" required>

                                @error('price')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        @if ($item->type == "Lua")
                        <div class="form-group row">
                            <div class="col-md-6 offset-md-4">
                                <div class="form-check">
                                    <input class="form-check-input {{ $item->new_signature ? 'active' : '' }}" type="checkbox" name="newsignature" id="newsignature" {{ $item->new_signature ? 'checked' : '' }}>

                                    <label class="form-check-label {{ $item->new_signature ? 'active' : '' }}" for="newsignature">
                                        {{ __('New Signature') }}
                                    </label>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="form-group row">
                            <div class="col-md-6 offset-md-4">
                                <div class="form-check">
                                    <input class="form-check-input {{ $item->onsale ? 'active' : '' }}" type="checkbox" name="onsale" id="onsale" {{ $item->onsale ? 'checked' : '' }}>

                                    <label class="form-check-label {{ $item->onsale ? 'active' : '' }}" for="onsale">
                                        {{ __('For Sale') }}
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group row mb-0 justify-content-center">
                            <div class="col-md-6 text-center">
                                <button type="submit" class="btn btn-primary shadow-sm">
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
