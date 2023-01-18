@inject('user', 'App\Http\Controllers\UsersController')

@extends('layouts.admin')

@section('title')
New XML Asset
@endsection

@section('content')
<div class="container">
    <h1><b>New XML Asset</b></h1>
    <p>Create a new XML asset on {{ config('app.name') }}.</p>
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
    <form method="POST" action="{{ route('admin.createxmlitem') }}" enctype="multipart/form-data">
        @csrf

        <div class="form-group">
            <label for="name">Item Name</label>
            <input type="text" name="name" class="form-control" id="itemname" placeholder="Item Name">
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" class="form-control" id="description" rows="2"></textarea>
        </div>

        <div class="form-group">
            <label for="xml">XML Data</label>
            <textarea name="xml" class="form-control" id="xml" rows="4"></textarea>
        </div>

        <div class="form-group">
            <label for="robloxid">Roblox ID - <button class="btn btn-sm btn-secondary" type="button" id="robloxItemInfo">Get item info</button></label>
            <input type="number" onwheel="this.blur()" name="robloxid" class="form-control" id="robloxid" placeholder="Roblox Asset ID">
        </div>

        <div class="form-group">
            <label for="robloxversion">Roblox Version</label>
            <input type="number" onwheel="this.blur()" name="robloxversion" class="form-control" id="robloxversion" placeholder="Roblox Version" value="1">
        </div>

        <div class="form-group">
            <label for="thumbnailurl">Thumbnail URL</label>
            <input type="text" name="thumbnailurl" class="form-control" id="thumbnailurl" placeholder="Thumbnail URL">
        </div>

        <div class="form-group">
            <label for="type">Type</label>
            <select class="form-control" id="type" name="type" required>
                <option>Hat</option>
                <option>Gear</option>
                <option>Head</option>
                <option>Package</option>
                <option>Model</option>
                <option>Lua</option>
            </select>
        </div>

        <div class="form-group">
            <label for="price">Price</label>
            <input type="number" onwheel="this.blur()" onwheel="this.blur()" name="price" class="form-control" id="price" placeholder="Price">
        </div>
        
        <div class="form-check">
            <input class="form-check-input active" type="checkbox" name="announce" id="announce" checked>
            <label class="form-check-label active" for="announce">Announce item upload in the {{ config('app.name') }} Discord</label>
        </div>

        <hr>
        <h2><b>Scheduled <i>"Hatching"</i></b></h2>
        <p>This will make a {{ config('app.name') }} asset "hatch" into another once a specified date comes - similar to some of the ROBLOX Egg Hunt eggs from the past.</p>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="shouldhatch" id="hatchcheck">
            <label class="form-check-label" for="hatchcheck">Should hatch</label>
        </div>
        <div style="display: none" id="hatch">
            <hr>
            <div class="form-group">
                <label for="hatchname">Hatched Name</label>
                <input type="text" name="hatchname" class="form-control" id="hatchname" placeholder="Hatched Name">
            </div>
            <div class="form-group">
                <label for="hatchdesc">Hatched Description</label>
                <textarea name="hatchdesc" class="form-control" id="hatchdesc" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label for="hatchtype">New Item Type</label>
                <select class="form-control" id="hatchtype" name="hatchtype" required>
                    <option>Hat</option>
                    <option>Gear</option>
                    <option>Head</option>
                    <option>Package</option>
                </select>
            </div>
            <div class="form-group">
                <label for="hatchxml">XML Hatch Data</label>
                <textarea name="hatchxml" class="form-control" id="hatchxml" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label for="hatchdate">Hatch on <i>(timezone is {{config('app.timezone')}})</i></label>
                <div class="input-group">
                    <input type="text" name="hatchdate" class="form-control" id="hatchdate" placeholder="yyyy-mm-dd">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <hr>
        
        <button type="submit" class="btn btn-success shadow-sm">Create XML Asset</button>
    </form>
</div>
@endsection

@section('scripts')
<script>
    $('#hatchdate').datepicker({
        format: "yyyy-mm-dd"
    });
    $('#hatchcheck').click(function() {
        $('#hatch').toggle();
    })
    $('#robloxItemInfo').click(function(event) {
        $.ajax({
            type: "GET",
            url: "/admin/robloxitemdata/" + $('#robloxid').val(),
            dataType: "json",
            success: function (data) {
                $("#itemname").val(data["Name"]);
                $("#description").text(data["Description"]);
                $("#thumbnailurl").val("https://www.roblox.com/Thumbs/Asset.ashx?width=420&height=420&assetId=" + $('#robloxid').val());
            }
        });

        $.ajax({
            type: "GET",
            url: "/admin/robloxxmldata/" + $('#robloxid').val() + "/" + $('#robloxversion').val(),
            success: function (data){
                $("#xml").html(data.replaceAll("http://www.roblox.com/asset", "http://tadah.rocks/asset").replaceAll("class=\"Accessory\"", "class=\"Hat\""));
            }
        });
    });
</script>
@endsection