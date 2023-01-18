<?php

use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Home routes
Route::get('/', [Controllers\HomeController::class, 'landing'])->middleware('guest')->name('landing');
Route::get('/terms/{document}', [Controllers\HomeController::class, 'document'])->name('document');
Route::get('/stats', [Controllers\HomeController::class, 'stats'])->name('stats');

// Authentication routes (login, register, etc.)
Auth::routes();

// User routes
Route::prefix('my')->middleware('auth')->group(function () {
    Route::get('/dashboard', [Controllers\UsersController::class, 'dashboard'])->name('my.dashboard');
    Route::get('/friends', [Controllers\UsersController::class, 'friendListView'])->name('my.friends');

    Route::get('/settings', [Controllers\UsersController::class, 'settings'])->name('my.settings');
    Route::post('/settings', [Controllers\UsersController::class, 'settings']);

    Route::post('/settings/password', [Controllers\UsersController::class, 'changepassword'])->name('my.changepassword');

    Route::get('/settings/discord/link', [Controllers\UsersController::class, 'discordlink'])->name('my.discordlink');
    Route::get('/settings/discord/callback', [Controllers\UsersController::class, 'discordcallback']);

    Route::get('/keys', [Controllers\UsersController::class, 'invitekeys'])->name('my.keys');
    Route::post('/keys', [Controllers\UsersController::class, 'purchaseinvitekey']);
});

Route::prefix('users')->middleware('auth')->group(function () {
    Route::get('/', [Controllers\UsersController::class, 'list'])->name('users.list');
    Route::get('/{id}/profile', [Controllers\UsersController::class, 'profile'])->name('users.profile');
    Route::get('/{id}/friends', [Controllers\UsersController::class, 'profileFriends'])->name('users.friends');
});

Route::get('/users/{id}/thumbnail', [Controllers\UsersController::class, 'user_thumbnail'])->name('users.thumbnail');

Route::prefix('friends')->group(function() {
    Route::get('/status/{id}', [Controllers\UsersController::class, 'friendStatus'])->name('friends.jsonstatus');
    Route::get('/list/{id}', [Controllers\UsersController::class, 'friendList'])->name('friends.jsonlist');

    Route::post('/add/{id}', [Controllers\UsersController::class, 'addFriend'])->name('friends.add')->middleware('throttle:5,1');
    Route::post('/remove/{id}', [Controllers\UsersController::class, 'removeFriend'])->name('friends.remove');
    Route::post('/togglebestfriend/{id}', [Controllers\UsersController::class, 'friendToggleBest'])->name('friends.togglebestfriend');

    Route::post('/accept/{id}', [Controllers\UsersController::class, 'friendAccept'])->name('friends.accept');
    Route::post('/deny/{id}', [Controllers\UsersController::class, 'friendDeny'])->name('friends.deny');
});

Route::middleware('auth')->group(function() {
    Route::get('/banned', [Controllers\UsersController::class, 'banned'])->name('users.banned');
    Route::post('/banned', [Controllers\UsersController::class, 'banned'])->name('users.unban');
});


Route::get('/pinger', [App\Http\Controllers\UsersController::class, 'ping'])->name('users.pinger');

// Catalog routes
Route::prefix('catalog')->middleware('auth')->group(function () {
    Route::get('/upload', [Controllers\CatalogController::class, 'upload'])->name('catalog.upload');
    Route::post('/upload', [Controllers\CatalogController::class, 'upload'])->middleware('throttle:6,1');

    Route::get('/{category}', [Controllers\CatalogController::class, 'list'])->name('catalog.category');
    Route::get('/json/{category}', [Controllers\CatalogController::class, 'json']);

    // this comes LAST
    Route::get('/', function () {
        return redirect(route('catalog.category', 'hats'));
    })->name('catalog.list');
});

Route::prefix('item')->middleware('auth')->group(function () {
    Route::get('/{id}', [Controllers\CatalogController::class, 'item'])->name('item.view');
    Route::post('/{id}/buy', [Controllers\CatalogController::class, 'buy'])->name('item.buy');
    Route::post('/{id}/comment', [Controllers\CatalogController::class, 'comment'])->name('item.comment')->middleware('throttle:3,5');

    Route::get('/{id}/thumbnail', [Controllers\CatalogController::class, 'item_thumbnail'])->name('item.thumbnail');
    Route::get('/{id}/template', [Controllers\CatalogController::class, 'template'])->name('item.template');
    Route::get('/{id}/configure', [Controllers\CatalogController::class, 'configure'])->name('item.configure');
    Route::post('/{id}/delete', [Controllers\CatalogController::class, 'delete'])->name('item.delete');
    Route::post('/{id}/configure', [Controllers\CatalogController::class, 'configure']);
});

// Forum routes
Route::middleware('auth')->group(function() {
    Route::get('/forum', [App\Http\Controllers\ForumController::class, 'index'])->name('forum.index');
    Route::get('/forum/{id}', [App\Http\Controllers\ForumController::class, 'getcategory'])->name('forum.category');
    Route::get('/forum/thread/{id}', [App\Http\Controllers\ForumController::class, 'getthread'])->name('forum.getthread');
    Route::get('/forum/create/{id}', [App\Http\Controllers\ForumController::class, 'createthread'])->name('forum.createthread');
    Route::post('/forum/create/{id}', [App\Http\Controllers\ForumController::class, 'docreatethread'])->name('forum.docreatethread');
    Route::get('/forum/createreply/{id}', [App\Http\Controllers\ForumController::class, 'createreply'])->name('forum.createreply');
    Route::post('/forum/createreply/{id}', [App\Http\Controllers\ForumController::class, 'docreatereply'])->name('forum.docreatereply');
    Route::get('/forum/editthread/{id}', [App\Http\Controllers\ForumController::class, 'editthread'])->name('forum.editthread');
    Route::post('/forum/editthread/{id}', [App\Http\Controllers\ForumController::class, 'doeditthread'])->name('forum.doeditthread');
    Route::post('/forum/togglestickythread/{id}', [App\Http\Controllers\ForumController::class, 'togglestickythread'])->name('forum.togglesticky');
    Route::post('/forum/togglelock/{id}', [App\Http\Controllers\ForumController::class, 'togglelock'])->name('forum.togglelock');
    Route::post('/forum/deletethread/{id}', [App\Http\Controllers\ForumController::class, 'deletethread'])->name('forum.deletethread');
    Route::get('/forum/editreply/{id}', [App\Http\Controllers\ForumController::class, 'editreply'])->name('forum.editreply');
    Route::post('/forum/editreply/{id}', [App\Http\Controllers\ForumController::class, 'doeditreply'])->name('forum.doeditreply');
    Route::post('/forum/deletereply/{id}', [App\Http\Controllers\ForumController::class, 'deletereply'])->name('forum.deletereply');
});


// Admin routes
Route::get('/admin', [App\Http\Controllers\AdminController::class, 'index'])->name('admin.index');
Route::get('/admin/truncategametokens', [App\Http\Controllers\AdminController::class, 'truncategametokens'])->name('admin.truncategametokens');
Route::get('/admin/truncateservers', [App\Http\Controllers\AdminController::class, 'truncateservers'])->name('admin.truncateservers');
Route::get('/admin/invitekeys', [App\Http\Controllers\AdminController::class, 'invitekeys'])->name('admin.invitekeys');
Route::post('/admin/invitekeys/{id}/disable', [App\Http\Controllers\AdminController::class, 'disableinvitekey'])->name('admin.disableinvitekey');
Route::get('/admin/createinvitekey', [App\Http\Controllers\AdminController::class, 'createinvitekey'])->name('admin.createinvitekey');
Route::post('/admin/createinvitekey', [App\Http\Controllers\AdminController::class, 'generateinvitekey'])->name('admin.generateinvitekey')->middleware('throttle:10,1');;
Route::get('/admin/ban', [App\Http\Controllers\AdminController::class, 'ban'])->name('admin.ban');
Route::post('/admin/ban', [App\Http\Controllers\AdminController::class, 'banuser'])->name('admin.banuser');
Route::get('/admin/unban', [App\Http\Controllers\AdminController::class, 'unban'])->name('admin.unban');
Route::post('/admin/unban', [App\Http\Controllers\AdminController::class, 'unbanuser'])->name('admin.unbanuser');
Route::get('/admin/newxmlitem', [App\Http\Controllers\AdminController::class, 'xmlitem'])->name('admin.xmlitem');
Route::post('/admin/newxmlitem', [App\Http\Controllers\AdminController::class, 'createxmlitem'])->name('admin.createxmlitem');
Route::get('/admin/money', [App\Http\Controllers\AdminController::class, 'money'])->name('admin.money');
Route::post('/admin/money', [App\Http\Controllers\AdminController::class, 'changemoney'])->name('admin.changemoney');
Route::get('/admin/rewarditem', [App\Http\Controllers\AdminController::class, 'item'])->name('admin.item');
Route::post('/admin/rewarditem', [App\Http\Controllers\AdminController::class, 'rewarditem'])->name('admin.rewarditem');
Route::get('/admin/forcewearitem', [App\Http\Controllers\AdminController::class, 'wearitem'])->name('admin.wearitem');
Route::get('/admin/renderasset', [App\Http\Controllers\AdminController::class, 'renderasset'])->name('admin.renderasset');
Route::post('/admin/renderasset', [App\Http\Controllers\AdminController::class, 'renderasset']);
Route::post('/admin/forcewearitem', [App\Http\Controllers\AdminController::class, 'forcewearitem'])->name('admin.forcewearitem');
Route::get('/admin/robloxitemdata/{id}', [App\Http\Controllers\AdminController::class, 'robloxitemdata']);
Route::get('/admin/robloxxmldata/{id}/{version}', [App\Http\Controllers\AdminController::class, 'robloxxmldata']);
Route::get('/admin/regenalluserthumbs', [App\Http\Controllers\AdminController::class, 'regenalluserthumbs']);
Route::get('/admin/banlist', [App\Http\Controllers\AdminController::class, 'banlist'])->name('admin.banlist');
Route::get('/admin/booster', [App\Http\Controllers\AdminController::class, 'booster'])->name('admin.booster');
Route::post('/admin/booster', [App\Http\Controllers\AdminController::class, 'togglebooster'])->name('admin.togglebooster');
Route::get('/admin/scribbler', [App\Http\Controllers\AdminController::class, 'scribbler'])->name('admin.scribbler');
Route::post('/admin/scribbler', [App\Http\Controllers\AdminController::class, 'toggle_scribbler'])->name('admin.toggle_scribbler');
Route::get('/admin/clientsettings', [App\Http\Controllers\AdminController::class, 'clientsettings'])->name('admin.clientsettings');
Route::post('/admin/togglefflag', [App\Http\Controllers\AdminController::class, 'togglefflag'])->name('admin.togglefflag');
Route::get('/admin/sitealert', [App\Http\Controllers\AdminController::class, 'sitealert'])->name('admin.sitealert');
Route::post('/admin/createsitealert', [App\Http\Controllers\AdminController::class, 'createsitealert'])->name('admin.createsitealert');
Route::get('/admin/assets', [App\Http\Controllers\AdminController::class, 'assets'])->name('admin.assets');
Route::post('/admin/{id}/approve', [App\Http\Controllers\AdminController::class, 'approve'])->name('admin.approve');
Route::get('/admin/alts/{id}', [Controllers\AdminController::class, 'alts'])->name('admin.alts');
Route::get('/admin/gamejoins', [Controllers\AdminController::class, 'gamejoins'])->name('admin.gamejoins');
Route::get('/admin/unlinkdiscord', [Controllers\AdminController::class, 'forceunlinkdiscord'])->name('admin.forceunlinkdiscord');
Route::post('/admin/unlinkdiscord', [Controllers\AdminController::class, 'forceunlinkdiscord']);

// Yoo.
// Servers routes

Route::middleware(['servers', 'guestpassthrough'])->group(function() {
    Route::get('/servers', [App\Http\Controllers\ServersController::class, 'index'])->name('servers.index');
    Route::get('/server/{id}', [App\Http\Controllers\ServersController::class, 'server'])->name('servers.server');
    Route::get('/server/{id}/configure', [App\Http\Controllers\ServersController::class, 'configure'])->middleware('auth')->name('servers.configure');
    Route::post('/server/{id}/configure', [App\Http\Controllers\ServersController::class, 'processconfigure'])->middleware('auth')->name('servers.processconfigure');
    Route::post('/server/{id}/delete', [App\Http\Controllers\ServersController::class, 'delete'])->middleware('auth')->name('servers.delete');
    Route::get('/servers/create', function () { return view('servers.create'); })->middleware('auth');
    Route::post('/servers/create', [App\Http\Controllers\ServersController::class, 'create'])->middleware('auth')->name('servers.create')->middleware('throttle:5,1');
});

Route::get('/servers/caution', [App\Http\Controllers\ServersController::class, 'caution'])->name('servers.caution');
Route::post('/servers/caution', [App\Http\Controllers\ServersController::class, 'caution']);
Route::get('/servers/guestpassthrough', [App\Http\Controllers\ServersController::class, 'guest_passthrough'])->name('servers.guest_passthrough');
Route::post('/servers/guestpassthrough', [App\Http\Controllers\ServersController::class, 'guest_passthrough']);


// Launcher routes
Route::get('/client/versionstring', function () { return config('app.clients.2010'); });
Route::get('/client/download/{client}', [App\Http\Controllers\ClientController::class, 'download'])->name('client.downloadversion');
Route::get('/client/{client}', [App\Http\Controllers\ClientController::class, 'client'])->name('client.client');
Route::get('/client/join/{token}', [App\Http\Controllers\ClientController::class, 'joinlegacy'])->name('client.join');
Route::get('/game/newjoin/{token}', [App\Http\Controllers\ClientController::class, 'newjoin'])->name('client.newjoin');
Route::get('/client/generate/{serverId}', [App\Http\Controllers\ClientController::class, 'generate'])->name('client.generate');

// test stuff
Route::get('/game/newjointest', [App\Http\Controllers\ClientController::class, 'newjointest']);
Route::get('/game/newhosttest', [App\Http\Controllers\ClientController::class, 'newhosttest']);

// breaks script sig check in clients if removed
Route::get('/Asset/GetScriptState.ashx', function () { return '0 0 0 0'; });
Route::get('/asset/GetScriptState.ashx', function () { return '0 0 0 0'; });
Route::get('/download', function () { return view('client.download'); })->name('client.download');
Route::get('/Game/KeepAlivePinger.ashx', function () { return ''; });
Route::get('/game/logout.aspx', function () { return ''; });
Route::get('/game/GetCurrentUser.ashx', function (Illuminate\Http\Request $request) { $user = $request->user(); if($user) { return $user->id; } else { return null; }});
Route::get('/Game/Tools/ThumbnailAsset.ashx', [App\Http\Controllers\ClientController::class, 'thumbnailasset'])->name('client.thumbnailasset');
Route::get('/Game/Tools/InsertAsset.ashx', [App\Http\Controllers\AssetController::class, 'insertasset'])->name('client.insertasset');
Route::get('/UploadMedia/PostImage.aspx', function () { return 'lol, this stupid person meant to screenshot using their normal screenshotting tool but instead fired the old Roblox one'; });
Route::get('/game/visit.ashx', [App\Http\Controllers\ClientController::class, 'playsolo'])->name('client.playsolo');
Route::get('/game/gameserver.ashx', [App\Http\Controllers\ClientController::class, 'gameserver']);
Route::get('/game/join.ashx', [App\Http\Controllers\ClientController::class, 'studiojoin']);
Route::get('/game/psolo.ashx', [App\Http\Controllers\ClientController::class, 'newplaysolo'])->name('client.newplaysolo');
Route::get('/game/studio.ashx', [App\Http\Controllers\ClientController::class, 'studioscript'])->name('client.studioscript');
Route::get('/game/global.ashx', [App\Http\Controllers\ClientController::class, 'newstudioscript'])->name('client.newstudioscript');
Route::get('/IDE/ClientToolbox.aspx', [App\Http\Controllers\ClientController::class, 'toolbox']);
Route::get('/IDE/Landing.aspx', function () { return view('client.landing'); });
Route::get('/IDE/Upload.aspx', function () { return 'Soon.'; });
Route::get('/thumbs/avatar.ashx', [App\Http\Controllers\ClientController::class, 'getuserthumbnail'])->name('client.userthumbnail');
Route::get('/thumbs/asset.ashx', [App\Http\Controllers\ClientController::class, 'getitemthumbnail'])->name('client.itemthumbnail');
Route::get('/game/newhost/{secret}', [App\Http\Controllers\ClientController::class, 'newhost'])->name('client.newhost');
Route::get('/server/host/{secret}', [App\Http\Controllers\ClientController::class, 'host'])->name('client.host');
Route::get('/server/ping/{secret}', [App\Http\Controllers\ClientController::class, 'ping'])->middleware('roblox')->name('client.ping');
Route::get('/server/admin/{secret}', [App\Http\Controllers\ClientController::class, 'admin'])->name('client.admin');
Route::get('/server/verifyuser/{token}', [App\Http\Controllers\ClientController::class, 'verifyuser'])->name('client.verifyuser');
Route::get('/negotiate', [App\Http\Controllers\ClientController::class, 'negotiate'])->name('client.negotiate');

// mobile stuff eventually NO
Route::get('/mobile/test', function () { return '<a href=/games/start?placeid=5232>IAGO IS A MONKEY</a>'; });
Route::get('/Game/PlaceLauncher.ashx', [App\Http\Controllers\ClientController::class, 'placelauncher']);

// Asset routes
Route::get('/asset', [App\Http\Controllers\AssetController::class, 'getasset'])->name('asset.getasset');
Route::get('/v1/asset', [App\Http\Controllers\AssetController::class, 'getasset'])->name('asset.getasset');
Route::get('/asset/', [App\Http\Controllers\AssetController::class, 'getasset'])->name('asset.getasset');
Route::get('/server/{id}/place', [App\Http\Controllers\AssetController::class, 'getserverplace'])->name('asset.getserverplace');
Route::get('/xmlasset', [App\Http\Controllers\AssetController::class, 'getxmlasset'])->name('asset.getxmlasset');

Route::get('/thumbnail/clothingcharapp/{id}', [App\Http\Controllers\AssetController::class, 'clothingCharApp']);

// literally why
Route::get('/Asset', [App\Http\Controllers\AssetController::class, 'getasset'])->name('asset.robloxredirect');

// Character routes
Route::get('/character', [App\Http\Controllers\BodyColorsController::class, 'characterBodyColors'])->name('users.characterbodycolors');
Route::get('/character/json', [App\Http\Controllers\UsersController::class, 'jsonCharacterItems']);
Route::post('/character/toggle/{id}', [App\Http\Controllers\UsersController::class, 'toggleWearing'])->name('users.togglewearing');
Route::post('/character/setcolor', [App\Http\Controllers\BodyColorsController::class, 'changeBodyColor'])->name('users.setbodycolor');
Route::post('/character/regen', [App\Http\Controllers\UsersController::class, 'regenThumbnail'])->middleware('throttle:15,10')->name('users.regenthumbnail');
Route::get('/users/{id}/bodycolors', [App\Http\Controllers\ClientController::class, 'body_colors'])->name('users.bodycolors');
Route::get('/users/{id}/character', [App\Http\Controllers\ClientController::class, 'charapp'])->name('users.getcharacter');

Route::get('/Asset/BodyColors.ashx', [App\Http\Controllers\ClientController::class, 'body_colors_asset']);
Route::get('/Asset/CharacterFetch.ashx', [App\Http\Controllers\ClientController::class, 'charapp_asset']);

// Discord authentication routes
Route::get('/discord', [Controllers\DiscordController::class, 'authenticated_user_account']);
Route::get('/discord/match', [Controllers\DiscordController::class, 'match']);
Route::get('/discord/userinfo', [Controllers\DiscordController::class, 'user_info']);

// Event routes
Route::get('/event/award', [Controllers\EventController::class, 'award']);
Route::post('/event/award', [Controllers\EventController::class, 'processaward']);
Route::get('/event/getbalance', [Controllers\EventController::class, 'getBalance']);
Route::get('/event/awardmoney', [Controllers\EventController::class, 'awardMoney']);

// Test routes
Route::get('/game/newtest', [Controllers\TestController::class, 'jointest']);
Route::get('/test/sha256fail', [Controllers\TestController::class, 'sha256fail']);
Route::get('/test/sha256succ', [Controllers\TestController::class, 'sha256succ']);
Route::get('/test/sha1fail', [Controllers\TestController::class, 'sha1fail']);
Route::get('/test/sha1succ', [Controllers\TestController::class, 'sha1succ']);
Route::get('/test/sha512fail', [Controllers\TestController::class, 'sha512fail']);
Route::get('/test/sha512succ', [Controllers\TestController::class, 'sha512succ']);
