<?php
// You're a douchebag
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\User;
use App\Models\Server;
use App\Models\GameToken;
use App\Models\InviteKey;
use App\Models\Item;
use App\Models\OwnedItems;
use App\Models\Ban;
use App\Models\RenderQueue;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use App\Jobs\RenderJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Log;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    private function storageUsage()
    {
        function beautify($category, $bytes)
        {
            $map = [
                'other' => 'Other',
                'items' => 'Items',
                'clients' => 'Clients',
                'places' => 'Places',
                'cdn' => 'CDN',
                'database' => 'Database',
                'logs' => 'Logs',
                'laravel' => 'Laravel',
                'tadah' => 'Tadah',
                'renders' => 'Renders',
                '3d_renders' => '3D Renders',
                'total' => 'Total' // hidden
            ];

            return [
                'pretty' => parse_bytes($bytes),
                'bytes' => $bytes,
                'human' => $map[$category]
            ];
        }

        $sizes = [
            'items' => get_directory_size(storage_path('app/public/items')),
            'clients' => get_directory_size(storage_path('app/public/clients')),
            'places' => get_directory_size(storage_path('app/public/places')) + get_directory_size(storage_path('app/public/serverplaces')),
            'cdn' => get_directory_size(storage_path('cdn')),
            'database' => get_directory_size(storage_path('database')),
            'logs' => get_directory_size(storage_path('logs')),
            'laravel' => get_directory_size(storage_path('framework')) + get_directory_size(storage_path('sessions'))
        ];
        
        $renders = get_directory_size(storage_path('app/renders'));
        $threeDeeRenders = get_directory_size(storage_path('app/renders/3d'));
        $sizes['renders'] = max($renders - $threeDeeRenders, 0);
        $sizes['3d_renders'] = $threeDeeRenders;

        $tadah = get_directory_size(base_path());
        foreach ($sizes as $size)
        {
            $tadah -= $size;
        }
        $sizes['tadah'] = max($tadah, 0);

        $other = disk_total_space('/') - disk_free_space('/');
        foreach ($sizes as $size)
        {
            $other -= $size;
        }
        $sizes['other'] = max($other, 0);

        $total = 0;
        foreach ($sizes as $category => $size)
        {
            $sizes[$category] = beautify($category, $size);
            
            if ($category == 'other')
            {
                continue;
            }

            $total += $size;
        }
        $total = beautify('total', $total);

        $disk = [
            'size' => disk_total_space('/'),
            'free' => disk_free_space('/'),
            'used' => max(disk_total_space('/') - disk_free_space('/'), 0)
        ];

        return [
            'sizes' => $sizes,
            'disk' => $disk,
            'total' => $total
        ];
    }

    public function index(Request $request)
    {
        $totals = Cache::remember('storage_size', (60 * 30), function () {
            return $this->storageUsage();
        });

        // no, we can't just pass $totals
        return view('admin.index', [
            'sizes' => $totals['sizes'],
            'disk' => $totals['disk'],
            'total' => $totals['total']
        ]);
    }

    public function truncategametokens(Request $request) {
        GameToken::Truncate();

        return redirect('/admin')->with('message', 'Cleared all Game Tokens from the database.');
    }

    public function truncateservers(Request $request) {
        Server::Truncate();

        return redirect('/admin')->with('message', 'Cleared all Servers from the database.');
    }

    public function invitekeys(Request $request) {
        $invitekeys = InviteKey::query();

        return view('admin.invitekeys')->with('invitekeys', $invitekeys->orderBy('created_at', 'DESC')->paginate(10)->appends($request->all()));
    }

    public function viewkey(Request $request, $token)
    {
        
    }

    public function createinvitekey(Request $request) {
        return view('admin.createinvitekey');
    }

    public function generateinvitekey(Request $request) {
        $request->validate([
            'uses' => ['required', 'min:1', 'max:20', 'integer']
        ]);

        $inviteKey = InviteKey::create([
            'creator' => $request->user()->id,
            'token' => sprintf('%sKey-%s', config('app.name'), Str::random(25)),
            'uses' => $request['uses']
        ]);

        return redirect('/admin/createinvitekey')->with('success', 'Created invite key. Key: "' . $inviteKey->token  . '"');
    }

    public function disableinvitekey(Request $request, $id) {
        $invitekey = InviteKey::find($id);

        if (!$invitekey) {
            return abort(404);
        }

        $invitekey->uses = 0;
        $invitekey->save();

        return redirect('/admin/invitekeys')->with('message', 'Invite key ID: ' . $invitekey->id . ', Token: ' . $invitekey->token . ' disabled.');
    }

    public function ban(Request $request) {        
        return view('admin.ban');
    }

    public function banuser(Request $request) {
        $request->validate([
            'username' => ['required', 'string'],
            'banreason' => ['required', 'max:2000'],
            'unbandate' => ['required', 'date']
        ]);

        $user = User::where('username', $request['username'])->first();

        if($user) {
            $checkforban = Ban::where(['user_id' => $user->id, 'banned' => true])->first();
        }

        if (!$user) {
            return redirect('/admin/ban')->with('error', 'That user does not exist. Name: ' . $request['username']);
        }

        if ($checkforban) {
            return redirect('/admin/ban')->with('error', 'That user is already banned. Reason: ' . $user->ban_reason);
        }

        if ($user->isAdmin()) {
            return redirect('/admin/ban')->with('error', 'If you do not like another admin, you should probably bring it up.');
        }

        if ($request->user()->id == $user->id) {
            return redirect('/admin/ban')->with('error', 'You\'re trying to ban yourself?');
        }

        $ban = new Ban;
        $ban->user_id = $user->id;
        $ban->banned = true;
        $ban->ban_reason = $request['banreason'];
        $ban->banned_until = Carbon::parse($request['unbandate']);
        $ban->save();

        return redirect('/admin/ban')->with('success', $user->username . '  has been banned until ' . $ban->banned_until);
    }

    public function unban(Request $request) {
        return view('admin.unban');
    }

    public function unbanuser(Request $request) {
        $request->validate([
            'username' => ['required', 'string']
        ]);

        $user = User::where('username', $request['username'])->first();
        $ban = Ban::where(['user_id' => $user->id, 'banned' => true])->first();

        if (!$user) {
            return redirect('/admin/unban')->with('error', 'That user does not exist. Name: ' . $request['username']);
        }

        if (!$ban) {
            return redirect('/admin/unban')->with('error', 'That user is not banned.');
        }

        if ($request->user()->id == $user->id) {
            return redirect('/admin/unban')->with('error', 'but... but... but... you are not banned......');
        }

        $ban->banned = false;
        $ban->pardon_user_id = $request->user()->id;
        $ban->save();

        return redirect('/admin/unban')->with('success', $user->username . '  has been unbanned.');
    }

    public function xmlitem(Request $request)
    {
        return view('admin.newxmlitem');
    }

    public function createxmlitem(Request $request)
    {
        $shouldHatch = $request->has('shouldhatch');
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['string', 'max:2000'],
            'price' => ['required', 'integer', 'min:0', 'max:999999'],
            'xml' => ['required', 'string'],
            'type' => ['required', 'string'],
            'hatchname' => ['string', 'max:100', 'nullable'],
            'hatchdesc' => ['string', 'max:2000', 'nullable'],
            'hatchxml' => ['string', 'nullable'],
            'hatchdate' => ['date', 'nullable'],
            'hatchtype' => ['string', 'nullable']
        ]);

        $item = Item::create([
            'name' => $request['name'],
            'description' => $request['description'],
            'creator' => $request->user()->id,
            'thumbnail_url' => $request['thumbnailurl'],
            'price' => $request['price'],
            'type' => $request['type'],
            'hatchtype' => ($shouldHatch ? $request['hatchtype'] : null),
            'hatchdate' => ($shouldHatch ? Carbon::parse($request['hatchdate']) : null),
            'hatchname' => ($shouldHatch ? $request['hatchname'] : null),
            'hatchdesc' => ($shouldHatch ? $request['hatchdesc'] : null),
            'sales' => 0,
            'onsale' => true,
            'approved' => (config('app.assets_approved_by_default') ? 1 : ($request->user()->isAdmin() ? 1 : 0))
        ]);

        if($shouldHatch) {
            Storage::disk('public')->put('hatch_items/' . $item->id, $request['hatchxml']);
        }
        Storage::disk('public')->put('items/' . $item->id, $request['xml']);

        if ($item->type == "Hat" || $item->type == "Model" || $item->type == "Gear") {
            $this->dispatch(new RenderJob('xml', $item->id));
        }

        if($item->type == "Head") {
            $this->dispatch(new RenderJob('head', $item->id));
        }

        if ($item->type == "Package") {
            $this->dispatch(new RenderJob('clothing', $item->id));
        }

        OwnedItems::create([
            'user_id' => $request->user()->id,
            'item_id' => $item->id,
            'wearing' => false
        ]);

        /*
        if (config('app.discord_webhook_enabled') && $request->has('announce')) {
            // sanitize title/desc for basic all pings
            $name = str_replace('@here', '`@here`', str_replace('@everyone', '`@everyone`', $request['name']));
            $description = str_replace('@here', '`@here`', str_replace('@everyone', '`@everyone`', $request['description']));
            Http::post(sprintf('https://discord.com/api/v7/webhooks/%s/%s', config('app.discord_webhook_id'), config('app.discord_webhook_token')), [
                'embeds' => [
                    [
                        'title' => $name,
                        'type' => 'rich',
                        'description' => $description,
                        'url' => route('item.view', $item->id),
                        'color' => "255",
                        'timestamp' => date("Y-m-d\TH:i:s.u"),
                        'thumbnail' => [ url(route('client.itemthumbnail', ['itemId' => $item->id], false) . sprintf('?tick=%d', time())) ],
                        'footer' => [ 'text' => sprintf("%s %s", config('app.name'), $item->type) ],
                        'author' => [
                            'name' => $request->user()->username,
                            'url' => route('users.profile', $request->user()->id),
                            'icon_url' => url(route('client.userthumbnail', ['userId' => $request->user()->id]), false) . sprintf('?tick=%d', time()))
                        ],
                        'fields' => [ ['name' => 'Price', 'value' => sprintf('<:token:896930322413932544> %s Tokens', $item->price), 'inline' => false] ]
                    ]
                ]
            ]);
        }
        */

        return redirect(route('item.view', $item->id))->with('message', ($shouldHatch ? 'XML asset successfully created and scheduled to hatch.' : 'XML asset successfully created.'));
    }

    public function robloxitemdata(Request $request, $id)
    {
        $response = Http::asForm()->get('https://api.roblox.com/marketplace/productinfo', [
            "assetId" => $id
        ]);

        return $response;
    }

    public function robloxxmldata(Request $request, $id, $version)
    {
        $response = Http::get('https://assetdelivery.roblox.com/v1/asset?id=' . intval($id) . "&version=" . intval($version));

        return $response;
    }
    
    public function regenalluserthumbs(Request $request)
    {
        if (!$request->user()->id == 1) {
            abort(404);
        }

        $users = User::all();
        $jobs = [];

        foreach ($users as $user) {
            array_push($jobs, new RenderJob('user', $user->id));
        }

        Bus::batch($jobs)->dispatch();

        return "OK";
    }

    public function forcewearitem(Request $request)
    {
        // funny
        // I can just recycle rewardItem for this
        $request->validate([
            'username' => ['required', 'string'],
            'itemid' => ['required', 'integer']
        ]);

        $force = $request->has('force');
        $user = User::where('username', $request->username)->firstOrFail();

        $this->rewarditem($request, true, $force);
        $this->dispatch(new RenderJob('user', $user->id));

        return redirect(route('admin.forcewearitem'));
    }

    public function money(Request $request)
    {
        return view('admin.money');
    }

    public function changemoney(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'amount' => ['required', 'integer', 'max:10000']
        ]);

        $user = User::where('username', $request['username'])->first();

        if (!$user) {
            return redirect(route('admin.money'))->with('error', 'That user does not exist. Name: ' . $request['username']);
        }

        $user->money = $user->money + $request['amount'];
        $user->save();

        return redirect(route('admin.money'))->with('success', $user->username . '  has been given ' . $request['amount'] . ' ' . config('app.currency_name_multiple'));
    }

    public function item(Request $request)
    {
        return view('admin.item');
    }

    public function wearitem(Request $request)
    {
        return view('admin.wearitem');
    }

    public function rewarditem(Request $request, $wear = false, $force = false)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'itemid' => ['required', 'integer']
        ]);

        $user = User::where('username', $request['username'])->first();
        $item = Item::where('id', $request['itemid'])->first();
        $ownedItem = OwnedItems::where(['user_id' => $user->id, 'item_id' => $item->id])->first();

        if (!$user) {
            return redirect(route('admin.item'))->with('error', 'That user does not exist. Name: ' . $request['username']);
        }

        if (!$item) {
            return redirect(route('admin.item'))->with('error', 'That item does not exist. Item ID: ' . $request['itemid']);
        }

        if ($ownedItem && !$wear) {
            return redirect(route('admin.item'))->with('error', $user->username . ' already owns item: ' . $item->name);
        }

        if($ownedItem && $wear) {
            $ownedItem->wearing = true;        
            $ownedItem->unequippable = $force;
            $ownedItem->save();
        } else {
            OwnedItems::create([
                'user_id' => $user->id,
                'item_id' => $item->id,
                'wearing' => $wear,
                'unequippable' => $force
            ]);
        }  

        if($wear)
        {
            $types = ["Shirt", "Pants", "T-Shirt"];            
            if(in_array($item->type, $types))
            {                
                // check if the user is already wearing shirts or pants to unequip it
                $testforclothing = OwnedItems::where(['user_id' => $user->id, 'wearing' => true])->get();
                
                foreach($testforclothing as $wornitem)
                {
                    $itemdb = Item::where('id', $wornitem->item_id)->first();
                    if($itemdb && $item->type == $itemdb->type && $item->id != $itemdb->id)
                    {                        
                        $wornitem->wearing = false;
                        $wornitem->save();
                    }
                }
            }
        }

        return redirect(route('admin.item'))->with('success', $user->username . '  has been given: ' . $item->name);
    }

    public function banlist(Request $request)
    {
        $bans = Ban::query();
        if (request('search')) {
            $users = User::where('username', 'LIKE', '%' . request('search') . '%')->get();
            if($users) {
                $bans->whereIn('user_id', $users->pluck('id'))->orderBy('updated_at', 'desc');
            }
        }
        return view('admin.banlist')->with(['bans' => $bans->orderBy('updated_at', 'DESC')->paginate(10)->appends($request->all())]);
    }

    public function booster(Request $request)
    {
        return view('admin.booster');
    }

    public function togglebooster(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string']
        ]);

        $user = User::where('username', $request['username'])->first();

        if (!$user) {
            return redirect(route('admin.booster'))->with('error', 'That user does not exist. Name: ' . $request['username']);
        }

        $user->booster = !$user->booster;
        $user->save();

        if ($user->booster) {
            return redirect(route('admin.booster'))->with('success', $user->username . ' is now a Booster Club member!');
        } else {
            return redirect(route('admin.booster'))->with('success', $user->username . ' is no longer a Booster Club member.');
        }
    }

    public function hoster(Request $request)
    {
        return view('admin.hoster');
    }

    public function togglehoster(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string']
        ]);

        $user = User::where('username', $request['username'])->first();

        if (!$user) {
            return redirect(route('admin.hoster'))->with('error', 'That user does not exist. Name: ' . $request['username']);
        }

        $user->verified_hoster = !$user->verified_hoster;
        $user->save();

        if ($user->verified_hoster) {
            return redirect(route('admin.hoster'))->with('success', $user->username . ' is now a Verified Hoster!');
        } else {
            return redirect(route('admin.hoster'))->with('success', $user->username . ' is no longer a Verified Hoster.');
        }
    }

    public function clientsettings(Request $request)
    {
        $version = ($request->version != null ? $request->version : '2014');
        $clientsettings = Storage::disk('local')->get(sprintf('fastflags/%s.json', $version));
        $fflags = collect(json_decode($clientsettings, true));
        return view('admin.clientsettings')->with('fflags', $fflags);
    }

    public function togglefflag(Request $request)
    {
        $request->validate([
            'fflag' => ['required', 'string'],
            'version' => Rule::in(['2012', '2014', '2016'])
        ]);

        $clientsettings = Storage::disk('local')->get(sprintf('fastflags/%s.json', $request->version));
        $fflags = collect(json_decode($clientsettings, JSON_PRETTY_PRINT));
       
        $fflag = $fflags->get($request->fflag);
        if($fflag) {
            $value = filter_var($fflag, FILTER_VALIDATE_BOOLEAN);
            $value = Str::ucfirst(var_export(!$value, true));              
            $fflags->forget($request->fflag);
            $fflags->put($request->fflag, $value);
        } else {
            $fflags->put($request->fflag, "True");
        }

        $fflags->toJson(JSON_PRETTY_PRINT);
        $clientsettings = Storage::disk('local')->put(sprintf('fastflags/%s.json', $request->version), $fflags);

        return redirect(route('admin.clientsettings'))->with('success', sprintf('%s is now %s', $request->fflag, Str::lower($fflags->get($request->fflag))));
    }

    public function sitealert(Request $request)
    {
        return view('admin.sitealert');
    }

    public function createsitealert(Request $request)
    {
        $colors = collect([
            'Red' => 'alert-danger',
            'Yellow' => 'alert-warning',
            'Green' => 'alert-success'
        ]);
        $request->validate([
            'alert' => ['max:100'],
            'color' => Rule::in(['Red', 'Yellow', 'Green'])
        ]);

        if(empty($request->alert))
        {
            Cache::pull('alert');
        }

        Cache::put('alert', ['alert' => $request->alert, 'color' => $colors[$request->color]]);

        return redirect(route('admin.sitealert'))->with('success', 'Successfully created alert.');
    }

    function hatchCheck()
    {
        // we check if it's time to hatch items
        $hatchItems = Item::whereNotNull('hatchdate')->get();
        foreach($hatchItems as $potentialHatch) {
            $hatchDate = Carbon::parse($potentialHatch->hatchdate);
            if($hatchDate->isPast()) {
                // it's time for this item to hatch!
                // set our new type
                if($potentialHatch->hatchtype != null) {
                    $potentialHatch->name = $potentialHatch->hatchname;
                    $potentialHatch->description = $potentialHatch->hatchdesc;          
                    $potentialHatch->type = $potentialHatch->hatchtype;
                    // check if it's a xml
                    if ($potentialHatch->isXmlAsset())
                    {
                        // replace our old xml with the new one, assuming it exists
                        $hatchXml = Storage::disk('public')->get('hatch_items/' . $potentialHatch->id);
                        Log::info($hatchXml);
                        if($hatchXml) {
                            // replace and rerender
                            Storage::disk('public')->put('items/' . $potentialHatch->id, $hatchXml); 
                            $this->dispatch(new RenderJob($potentialHatch->hatchtype, $potentialHatch->id));

                            // reset
                            $potentialHatch->hatchtype = null;
                            $potentialHatch->hatchdate = null;
                            $potentialHatch->hatchname = null;
                            $potentialHatch->hatchdesc = null;
                            $potentialHatch->save();
                            
                            // delete hatch xml
                            Storage::disk('public')->delete('hatch_items/' . $potentialHatch->id);
                        }
                    }                
                }
            }
        }
    }

    function assets(Request $request) {
        $unapproved = Item::where('approved', 0);
        if (request('search')) {
            $unapproved->where('name', 'LIKE', '%' . request('search') . '%');
        }
        return view('admin.assets', ['items' => $unapproved->paginate(18)]);
    }

    function approve(Request $request, $id) {
        $item = Item::find($id);        
        if($item) {
            $item->update([
                'approved' => ($request->submit === "Approve" ? 1 : 2),
            ]);
        } else {
            abort(404);
        }
        return back();
    }

    public function scribbler(Request $request)
    {
        return view('admin.scribbler');
    }

    public function toggle_scribbler(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string']
        ]);

        $user = User::where('username', $request['username'])->first();

        if (!$user) {
            return redirect(route('admin.scribbler'))->with('error', 'That user does not exist. Name: ' . $request['username']);
        }

        $user->scribbler = !$user->scribbler;
        $user->save();

        if ($user->scribbler) {
            return redirect(route('admin.scribbler'))->with('success', $user->username . ' is now a Scribbler!');
        } else {
            return redirect(route('admin.scribbler'))->with('success', $user->username . ' is no longer a Scribbler.');
        }
    }

    public function alts(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $associatedUsers = User::where('register_ip', $user->register_ip)
            ->orWhere('last_ip', $user->last_ip)
            ->orderBy('joined', 'ASC')
            ->paginate(10);

        return view('admin.alts')->with(['user' => $user, 'associatedUsers' => $associatedUsers]);
    }

    public function gamejoins(Request $request)
    {
        $gamejoins = GameToken::query();

        return view('admin.gamejoins')->with('gamejoins', $gamejoins->orderBy('created_at', 'DESC')->paginate(10)->appends($request->all()));
    }

    public function renderasset(request $request)
    {
        if ($request->isMethod('post'))
        {
            $request->validate([
                'assetid' => ['required'],
                'type' => ['required']
            ]);

            $asset = null;
            $type = null;

            switch($request->type)
            {
                case "Item":
                    $asset = Item::findOrFail((int)$request->assetid);
                    $type = "xml";
                    break;
                case "Place":
                    $asset = Server::where('uuid', $request->assetid)->first();
                    $type = "serverplace";
                    break;
                case "User":                    
                    $asset = User::findOrFail((int)$request->assetid);
                    $type = "user";
                    break;
                default:
                    return redirect('/admin/renderasset')->with('error', 'Invalid asset type.');
            }
            
            if($asset)
            {                
                $this->dispatch(new RenderJob($type, $asset->id));
                return redirect('/admin/renderasset')->with('success', sprintf('A Render Job has been dispatched for Asset ID %s.', (string)$request->assetid));
            }
            else
            {
                return redirect('/admin/renderasset')->with('error', 'Invalid asset.');
            }            
        }
        else 
        {
            return view('admin.renderasset');
        }
    }

    public function forceunlinkdiscord(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'username' => ['required', 'string']
            ]);

            $user = User::where('username', $request['username'])->first();

            if (!$user) {
                return redirect(route('admin.forceunlinkdiscord'))->with('error', 'That user does not exist. Name: ' . $request['username']);
            }

            if ($user->isAdmin()) {
                return redirect(route('admin.forceunlinkdiscord'))->with('error', 'You cannot unlink an admin\'s Discord account');
            }

            $user->discord_id = null;
            $user->save();

            return redirect(route('admin.forceunlinkdiscord'))->with('success', 'Successfully unlinked Discord account.');
        } else {
            return view('admin.forceunlinkdiscord');
        }
    }
}
