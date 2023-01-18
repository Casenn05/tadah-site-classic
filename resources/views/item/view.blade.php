@extends('layouts.app')

@section('title')
{{ $item->name }}
@endsection

@section('content')
<div class="container-lg">
    @if (session()->has('message'))
        <div class="alert alert-success">
            {{ session()->get('message') }}
        </div>
    @endif
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header d-sm-flex">
                    {{ config('app.name') }} {{ $item->type }}
                    @if(Auth::user()->id == $item->user->id || Auth::user()->admin)
                        <div class="col px-0 d-flex justify-content-end">
                            <div class="dropdown">
                                <a class="btn btn-light btn-sm border dropdown-toggle" href="#" role="button" id="settingsDropdown{{ $item->id }}" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-h"></i>
                                </a>

                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="settingsDropdown{{ $item->id }}">
                                    <a class="dropdown-item" href="{{ route('item.configure', $item->id) }}"><i class="fas fa-cog mr-1"></i>Configure</a>
                                    <button data-toggle="modal" data-target="#deleteModal" class="dropdown-item" style="color: red;" type="submit"><i class="far fa-trash-alt mr-1"></i>Delete</button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="card-body text-center">
                    <h2>{{ $item->name }}</h2>
                    
                    <div id="thumbnail-container" class="my-2 position-relative">
                        @if ($item->type != 'Model' && $item->type != 'Audio' && $item->type != 'T-Shirt' && $item->type != 'Face')
                        <button class="position-absolute btn btn-outline-secondary disabled" disabled id="toggle-item-3d" style="bottom:5; right:0;">3D</button>
                        @endif
                        @if ($item->type == 'Audio')
                        <img src="{{ asset('images/thumbnail/audio.png') }}" style="object-fit: contain;" alt="{{ $item->name }}" width="250" height="250">
                        @else
                        <img src="{{ asset('/images/thumbnail/blank.png') }}" data-tadah-thumbnail-type="item-thumbnail" data-tadah-thumbnail-id="{{ $item->id }}" style="object-fit: contain;" alt="{{ $item->name }}" width="250" height="250">
                        @endif
                        <div class="d-none" id="three-dee-spinner">
                            <div class="text-center d-inline-flex align-items-center justify-content-center" style="height: 250px; width: 250px">
                                <div class="spinner-border text-dark" role="status" style="width: 50px; height: 50px;">
                                    <span class="sr-only">Loading 3D Thumbnail...</span>
                                </div>
                            </div>
                        </div>
                    </div>  

                    @if ($item->type == "Audio")
                    <audio controls src="{{ route('asset.getasset') . '?id=' . $item->id }}">If you're seeing this, your browser doesn't support the audio tag.</audio>
                    @endif
                    <hr>
                    <span class="text-muted"><small>Description:</span></small><br><span>{{ $item->description }}</span>
                </div>
            </div>
            <hr>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-header text-center">Details</div>
                <div class="card-body text-center">
                    <a href="{{ route('users.profile', $item->user->id) }}"><img class="img-responsive" style="object-fit: contain;" src="{{ asset('images/thumbnail/blank.png') }}" data-tadah-thumbnail-type="user-thumbnail" data-tadah-thumbnail-id="{{ $item->user->id }}" alt="{{ $item->user->username }}" width="100" height="100"></a>
                    <p class="text-muted mt-0 pd-0"><small>Creator: <a href="{{ route('users.profile', $item->user->id) }}">{{ $item->user->username }}</a><br>Sales: {{ $item->sales }}</small></p>
                    <hr>
                    @if ($item->approved == 1)
                        @if ($item->onsale || Auth::user()->isAdmin())
                            @if (!$ownedItem && (Auth::user()->money - $item->price) >= 0)
                                <button data-toggle="modal" data-target="#purchaseModal" class="btn btn-lg btn-success shadow-sm {{ $ownedItem ? 'disabled':''}}" style="width: 85%" type="submit"><img src="/images/dahllor_white.png" width="20" height="20"> {{ number_format($item->price) }}</button>
                            @elseif((Auth::user()->money - $item->price) < 0)
                                <span class="text-muted">You cannot afford this.</span>
                            @else
                                <span class="text-muted">You already own this.</span>
                            @endif
                        @else
                            <span class="text-muted">Item not for sale.</span>
                        @endif

                        @if($item->hatchdate != null)
                            <hr>                            
                            <small class="text-muted"><i class="fas fa-stopwatch mr-1" aria-hidden="true"></i>This gift will open in {{Carbon\Carbon::now()->diffInDays($item->hatchdate, false)}} days.</small>
                        @endif

                        @if (Auth::user()->isAdmin() && !$ownedItem && !$item->onsale)
                            <br>
                            <small class="text-muted">This item is offsale, but since you are an admin, you may purchase it.</small>
                        @endif
                    @else
                    <span class="text-muted">Item not for sale.</span>
                    @endif
                </div>
            </div>
        </div>
    </div>    
    <div class="row justify-content-center">
        <div class="col-md-8 mt-3 mt-md-0">
            <nav id="tabs" class="nav nav-pills nav-justified">
                <a href="#recommendations" class="nav-link active" data-toggle="tab" role="tab">Recommendations</a>
                <a href="#commentary" class="nav-link" data-toggle="tab" role="tab">Commentary</a>
            </nav>
            <hr>
            <div class="tab-content">
                <div id="commentary" class="tab-pane fade" role="tabpanel">
                    <div class="card card-body d-flex justify-content-center">
                        <div class="col-auto p-0">
                            <form method="POST" action="{{ route('item.comment', $item->id) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="row m-0">
                                    <img class="img-responsive headshot-bg border rounded-circle" style="object-fit: contain;" data-tadah-thumbnail-id="{{ Auth::user()->id }}" data-tadah-thumbnail-type="user-headshot" src="{{ asset('images/thumbnail/blank.png') }}"  width="80" height="80" title="">
                                    <div class="col p-0 ml-3 pl-3">
                                        <textarea placeholder="Please keep the {{config('app.name')}} rules in mind." style="resize: none" id="comment" type="text" class="form-control h-100" name="comment" required=""></textarea>
                                    </div>                            
                                </div>
                                <div class="w-100 d-flex justify-content-end pt-3">
                                    <button type="submit" class="d-block btn btn-primary shadow-sm">
                                        <i class="fas fa-comment mr-1" aria-hidden="true"></i>Comment
                                    </button>
                                </div>
                            </form>
                        </div>
                        @foreach($comments as $comment)
                        <div class="border-bottom">
                            <div class="col-auto my-3">
                                <div class="row">
                                    <a href="/users/{{$comment->user_id}}/profile">
                                        <img class="img-responsive border headshot-bg rounded-circle" style="object-fit: contain;" data-tadah-thumbnail-id="{{ $comment->user_id }}" data-tadah-thumbnail-type="user-headshot" src="{{ asset('images/thumbnail/blank.png') }}"  width="80" height="80" title="">
                                    </a>
                                    <div class="col pl-3 ml-3 p-0">
                                        <div class="d-inline-block">
                                            <p class="p-0 m-0 text-muted"><i>Posted {{$comment->created_at->ago()}} by <a href="/users/{{$comment->user_id}}/profile">{{ $comment->user->username }}</a></i></p>
                                        </div>
                                        <div id="comment" type="text" class="d-block w-100">
                                            {{$comment->body}}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div id="recommendations" class="tab-pane show active fade" role="tabpanel">
                    <div class="row">
                        @if($recommended->count() > 0)
                            @foreach($recommended as $recommended_item)
                            <div class="col-xl-2 col-lg-3 col-md-3 col-sm-4 col-6 pb-3">
                                <div class="card card-body shadow-sm p-2">
                                    <a href="{{ route('item.view', $recommended_item->id) }}" class="text-decoration-none">
                                        <img data-tadah-thumbnail-id="{{ $recommended_item->id }}" data-tadah-thumbnail-type="item-thumbnail" src="{{ asset('images/thumbnail/blank.png') }}" alt="{{ $recommended_item->name }} {{ __('thumbnail') }}" class="card-img-top p-2" width="120" style="border-radius: .75rem">
                                        <div class="mt-1 text-truncate">{{ $recommended_item->name }}</div>
                                    </a>
                                    <hr class="my-0">
                                    <div class="text-muted mt-1 mb-1">
                                        <small>
                                            {{ __('Creator') }}:
                                            <a href="{{ route('users.profile', $recommended_item->user->id) }}" class="text-decoration-none">{{ $recommended_item->user->username }}</a>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <!-- WORST WORKAROUND GRID EVER !-->
        <div class="col-md-3"></div>
    </div>
    @if(Auth::user()->id == $item->user->id || Auth::user()->admin)
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="row text-center justify-content-center">                        
                        <div class="justify-content-center">
                            <p><img class="mr-1" data-tadah-thumbnail-id="{{ $item->id }}" data-tadah-thumbnail-type="item-thumbnail" src="{{ asset('images/thumbnail/blank.png') }}" style="object-fit: contain;" alt="{{ $item->name }}" width="80" height="80"></p>
                            <p class="m-0 p-0">Are you sure you want to delete {{ $item->name }}?</p>
                            <p class="text-danger m-0 p-0"><i class="fas fa-exclamation-triangle mr-1"></i>This action cannot be reversed.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">                                    
                    <form method="POST" action="{{ route('item.delete', $item->id) }}">
                        @csrf                        
                        <button class="btn btn-danger shadow-sm" type="submit">Delete</button>
                    </form>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    @endif
    <div class="modal fade" id="purchaseModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="row text-center justify-content-center">                        
                        <div class="justify-content-center">
                            <p><img class="mr-1" data-tadah-thumbnail-id="{{ $item->id }}" data-tadah-thumbnail-type="item-thumbnail" src="{{ asset('images/thumbnail/blank.png') }}" style="object-fit: contain;" alt="{{ $item->name }}" width="80" height="80"></p>
                            <p class="m-0 p-0">Would you like to purchase {{ $item->name }} for <img src="/images/currency.png" width="20" height="20" class="mx-1">{{ number_format($item->price) }} {{ config('app.currency_name_multiple') }}?</p>
                            <p class="text-muted m-0 p-0">Your balance after this transaction will be<img src="/images/currency.png" width="20" height="20" class="mx-1">{{ number_format((Auth::user()->money - $item->price)) }}</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">                                    
                    <form method="POST" action="{{ route('item.buy', $item->id) }}">
                        @csrf
                        <button class="btn btn-success shadow-sm {{ $ownedItem ? 'disabled':''}}" type="submit">Purchase</button>
                    </form>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection