<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Cdn\Render;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use App\Models\Server;
use App\Models\Item;
use App\Models\OwnedItems;
use App\Models\BodyColors;
use App\Models\RenderQueue;
use App\Models\Ban;
use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use App\Http\Controllers\RenderController;
use App\Jobs\RenderJob;
use App\Http\Cdn\Thumbnail;
use Laravel\Socialite\Facades\Socialite;
use App\Models\InviteKey;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    const WEARABLE_CATEGORIES = ['hat', 'shirt', 'pants', 't-shirt', 'face', 'gear', 'head', 'package'];

    public function __construct()
    {
        // $this->middleware('auth');
    }

    private function is_wearable_type($type) : bool
    {
        return (in_array(strtolower($type), self::WEARABLE_CATEGORIES));
    }

    public function user_thumbnail(Request $request, $id)
    {
        User::findOrFail($id);
        $thumbnail = Thumbnail::resolve('user', $id);

        $url = match ($thumbnail['status'])
        {
            -1, 1, 3 => Thumbnail::static_image('blank.png'),
            2 => Thumbnail::static_image('disapproved.png'),
            0 => $thumbnail['result']['body']
        };

        return redirect($url);
    }

    public function list(Request $request)
    {
        $users = User::query();

        if (request('search'))
        {
            $users->where('username', 'LIKE', '%' . request('search') . '%')->orderBy('last_online', 'desc');
        }
        else
        {
            $users->orderBy('last_online', 'desc');
        }

        return view('users.index')->with('users', $users->paginate(10)->appends($request->all()));
    }

    public function ping(Request $request)
    {
        return Response::make(json_encode(["success" => true]))->header("Content-Type", "application/json");
    }

    public function profile(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $ban = Ban::where(['user_id' => $user->id, 'banned' => true])->first();

        // we can cache most of this for some extra page speed
        // cache every 10 mins?
        $wornItems = Cache::remember(sprintf('%d_wornitems', $user->id), (60 * 5), fn() => DB::table('owned_items')
			->join('items', 'owned_items.item_id', '=', 'items.id')
			->where('owned_items.user_id', $user->id)
            ->where('owned_items.wearing', true)
			->where('items.approved', 1)
			->select('items.id', 'items.thumbnail_url', 'items.name', 'items.type', 'owned_items.wearing', 'owned_items.unequippable')
			->orderBy('owned_items.created_at', 'desc')
			->get());

        // this is a nightmare
        $friends = Cache::remember(sprintf('%d_friends', $user->id), (60 * 10), fn() =>
            Friendship::where(function($query) use ($user) {
                $query->where(['receiver_id' => $user->id]);
                $query->orWhere(['requester_id' => $user->id]);
            })
            ->where('status', '1')
            ->limit(8)
            ->get()
            ->transform(function($friend) use ($user) {
                $friend['online'] = Cache::has('last_online' . ($friend->requester_id == $user->id ? $friend->receiver_id : $friend->requester_id));
                return $friend;
            })
        );

        $sort = collect($friends->sortByDesc('online')->values()->all());

        $servers = Server::where(['creator' => $user->id, 'unlisted' => false])->get();

        return view('users.profile', compact('user', 'servers', 'ban', 'wornItems'))->with('friends', $sort);
    }

    public function settings(Request $request)
    {
        if ($request->isMethod('post'))
        {
            if (isset($request->blurb))
            {
                $request->validate([
                    'blurb' => ['required', 'max:700', 'not_regex:/[\xCC\xCD]/']
                ]);

                $user = $request->user();
                $user->blurb = $request->blurb;
                $user->save();
            }
            elseif (isset($request->old_cores))
            {
                $request->validate([
                    'old_cores' => ['required', Rule::in(['old_cores', 'new_cores'])]
                ]);

                $user = $request->user();
                $user->old_cores = ($request->old_cores != "new_cores" ? false : true);
                $user->save();
            }
            elseif (isset($request->theme))
            {
                $request->validate([
                    'theme' => ['required', Rule::in(config('app.themes'))]
                ]);

                $theme = $request->theme;
                return redirect(route('my.settings'))->withCookie(cookie()->forever('theme', $theme))->with('message', 'Theme changed successfully.'); // frfr
            }

            return redirect(route('my.settings'))->with('message', 'Settings saved successfully.');
        }

        return view('my.settings');
    }

    public function characterItems(Request $request, $requestType)
    {
        $type = "Hat";
		switch ($requestType) {
			case "hats":
			default:
				$type = "Hat";
				break;
			case "shirts":
				$type = "Shirt";
				break;
			case "pants":
				$type = "Pants";
				break;
            case "tshirts":
                $type = "T-Shirt";
                break;
            case "images":
                $type = "Image";
                break;
			case "faces":
				$type = "Face";
				break;
			case "gears":
				$type = "Gear";
				break;
            case "heads":
                $type = "Head";
                break;
            case "packages":
                $type = "Package";
                break;
		}

		$items = DB::table('owned_items')
			->join('items', 'owned_items.item_id', '=', 'items.id')
			->where('owned_items.user_id', $request->user()->id)
			->where('items.type', $type)
			->where('items.approved', 1)
			->select('items.id', 'items.thumbnail_url', 'items.name', 'items.type', 'owned_items.wearing', 'owned_items.unequippable')
			->orderBy('owned_items.created_at', 'desc')
			->get();

		return view('users.characteritems', ['items' => $items, 'type' => $type]);
    }

    public function jsonCharacterItems(Request $request)
    {
        $type = "Hat";
		switch ($request->type) {
			case "hats":
				$type = "Hat";
				break;
			case "shirts":
				$type = "Shirt";
				break;
			case "pants":
				$type = "Pants";
				break;
            case "tshirts":
                $type = "T-Shirt";
                break;
			case "faces":
				$type = "Face";
				break;
			case "gears":
				$type = "Gear";
				break;
            case "heads":
                $type = "Head";
                break;
            case "packages":
                $type = "Package";
                break;
            default:
                $type = null;
                break;
		}

		$items = DB::table('owned_items')
			->join('items', 'owned_items.item_id', '=', 'items.id')
			->where('owned_items.user_id', $request->user()->id)
			->where('items.approved', 1)
			->select('items.id', 'items.thumbnail_url', 'items.name', 'items.type', 'owned_items.wearing', 'owned_items.unequippable')
			->orderBy('owned_items.created_at', 'desc');

        $items = Response::json(View::make('ajax.items', ['items' => ($type ? $items->where('type', $type)->paginate(12) : $items = $items->where('wearing', true)->get())])->render());
        return $items;
    }

    public function toggleWearing(Request $request, $id)
	{
        $item = Item::findOrFail($id);
		$ownedItem = OwnedItems::where(['item_id' => $id, 'user_id' => $request->user()->id])->firstOrFail();

		$wearingItems = DB::table('owned_items')
			->join('items', 'owned_items.item_id', '=', 'items.id')
			->where('owned_items.user_id', $request->user()->id)
			->where('owned_items.wearing', true)
			->where('items.type', $item->type)
			->select('owned_items.id', 'owned_items.item_id', 'owned_items.wearing')
			->get();

		if (!$ownedItem->wearing) {
            if ($this->is_wearable_type(strtolower($item->type))) {
                if ($item->type == "Hat") {
                    if (count($wearingItems) >= 5 && !$request->user()->isAdmin()) {
                        $wearingItem = OwnedItems::where(['id' => $wearingItems[0]->id, 'wearing' => true])->first();

                        if ($wearingItem) {
                            $wearingItem->wearing = false;
                            $wearingItem->save();
                        }
                    }
                } elseif ($item->type == "Gear") {
                    if (count($wearingItems) >= 2 && !$request->user()->isAdmin()) {
                        $wearingItem = OwnedItems::where(['id' => $wearingItems[0]->id, 'wearing' => true])->first();

                        if ($wearingItem) {
                            $wearingItem->wearing = false;
                            $wearingItem->save();
                        }
                    }
                } else {
                    foreach ($wearingItems as $wearingItem) {
                        $wearingItem = OwnedItems::where(['id' => $wearingItem->id, 'user_id' => $request->user()->id])->first();
                        if(!$wearingItem->unequippable) {
                            $wearingItem->wearing = false;
                            $wearingItem->save();
                        }
                    }
                }

                $ownedItem->wearing = true;
                $ownedItem->save();
            } else {
                abort(400);
            }
		} else {
            if (!$ownedItem->unequippable) {
                $ownedItem->wearing = false;
			    $ownedItem->save();
            }
		}

		return back();
	}

    public function regenThumbnail(Request $request)
    {
        if (!config('app.character_regeneration'))
        {
            return abort(404);
        }

        $user = $request->user();

        $this->dispatch(new RenderJob('user', $user->id));
        return response()->api(Render::resolve('user', $user->id));
    }

    public function download(Request $request)
    {
        return view('client.download');
    }

    public function banned(Request $request)
    {
        if ($request->isMethod('post'))
        {
            $user = $request->user();
            $ban = Ban::where(['user_id' => $user->id, 'banned' => true])->first();
            if ($ban->banned_until->isPast())
            {
                // we can unban
                $ban->banned = false;
                $ban->pardon_user_id = 1; // default to Tadah account
                $ban->save();

                return redirect(route('my.dashboard'))->with('success', 'Welcome back to ' . config('app.name') . '. Don\'t mess up again - we may not be so merciful next time.');
            }
            else
            {
                abort(403);
            }
        }

        $ban = Ban::where(['user_id' => $request->user()->id, 'banned' => true])->first();
        if (!$ban)
        {
            return redirect(route('home'));
        }

        return view('users.banned')->with(['ban' => $ban]);
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();

        $friends = $user->friends()->take(9);
        $friends->sort(function ($a, $b) {
            return Cache::get('last_online' . $a->id) - Cache::get('last_online' . $b->id);
        });

        $servers = Server::orderBy('updated_at', 'DESC')->where('unlisted', false)->get();

        $servers = $servers->sortBy(function ($a, $b) {
            if (!($a instanceof Server) || !($b instanceof Server))
            {
                return 0;
            }

            $a = Cache::get('server_online' . $a->id) ?? 0;
            $b = Cache::get('server_online' . $b->id) ?? 0;

            return $a - $b;
        });
        
        $servers = $servers->take(6);
        
        return view('my.dashboard', compact('servers', 'friends'));
    }

    public function friendStatus(Request $request, $targetId)
    {
        $requester = $request->user();
        $receiver = User::findOrFail($targetId);

        $data = [
            'requesting' => false,
            'friends' => false,
            'best_friends' => false
        ];

        $friendship = Friendship::where(['requester_id' => $requester->id, 'receiver_id' => $receiver->id])->first() ?: Friendship::where(['requester_id' => $receiver->id, 'receiver_id' => $requester->id])->first();

        if ($friendship) {
            if (!$friendship->areFriends()) {
                $data['requesting'] = true;
            }

            if ($friendship->areFriends()) {
                $data['friends'] = true;
            }

            if ($friendship->areBestFriends()) {
                $data['best_friends'] = true;
            }
        } else {
            abort(404);
        }

        return $data;
    }

    public function friendList(Request $request, $userId)
    {
        $perPage = 18;
        if ($request->has('perPage')) {
            $perPage = $request['perPage'];
        }

        $user = User::findOrFail($userId);

        if (!((is_int($perPage) || ctype_digit($perPage)) && (int)$perPage > 0 && (int)$perPage <= 50)) {
            abort(400);
        }

        $friends = Friendship::where(function($query) use ($user, $perPage, $request) {
            $query->where(['receiver_id' => $user->id]);
            if($request->get('type') == 1) {
                $query = $query->orWhere(['requester_id' => $user->id]);
            };
        })->where('status', ($request->has('type') ? $request->get('type') : '0'))
            ->get();

        // this deeply saddens me
        $friends->transform(function($friend) use ($user) {
            $friend['username'] = User::findOrFail(($friend->requester_id == $user->id ? $friend->receiver_id : $friend->requester_id))->username;
            $friend['online'] = Cache::has('last_online' . ($friend->requester_id == $user->id ? $friend->receiver_id : $friend->requester_id));
            return $friend;
        });

        $sort = $friends->sortByDesc('online')->values()->all();
        return $sort;
    }

    public function profileFriends(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $friends = Friendship::where(function($query) use ($user) {
            $query->where(['receiver_id' => $user->id]);
            $query->orWhere(['requester_id' => $user->id]);
        })->where('status', '1')
            ->get();

        // this deeply saddens me
        $friends->transform(function($friend) use ($user) {
            $friend['online'] = Cache::has('last_online' . ($friend->requester_id == $user->id ? $friend->receiver_id : $friend->requester_id));
            return $friend;
        });

        $sort = collect($friends->sortByDesc('online')->values()->all());
        return view('users.friends')->with(['user' => $user, 'friends' => $sort]);
    }


    public function friendListView(Request $request)
    {
        return view('my.friends');
    }

    public function addFriend(Request $request, $targetId)
    {
        $requester = $request->user();
        $receiver = User::findOrFail($targetId);

        if ($requester == $receiver) {
            abort(400);
        }

        $friendship = Friendship::where(['requester_id' => $requester->id, 'receiver_id' => $receiver->id])->first() ?: Friendship::where(['requester_id' => $receiver->id, 'receiver_id' => $requester->id])->first();

        if ($friendship) {
            abort(400);
        }

        Friendship::create([
            'receiver_id' => $receiver->id,
            'requester_id' => $requester->id,
            'status' => 0
        ]);

        return ['success' => true];
    }

    public function removeFriend(Request $request, $targetId)
    {
        $requester = $request->user();
        $receiver = User::findOrFail($targetId);

        $friendship = Friendship::where(['requester_id' => $requester->id, 'receiver_id' => $receiver->id])->first() ?: Friendship::where(['requester_id' => $receiver->id, 'receiver_id' => $requester->id])->first();

        if (!$friendship) {
            abort(400);
        }

        if (!$friendship->areFriends()) {
            abort(400);
        }

        $friendship->delete();

        return ['success' => true];
    }

    public function friendAccept(Request $request, $targetId)
    {
        $requester = User::findOrFail($targetId);
        $receiver = $request->user();

        $friendship = Friendship::where(['requester_id' => $requester->id, 'receiver_id' => $receiver->id])->first();

        if (!$friendship) {
            abort(404);
        }

        if ($friendship->areFriends()) {
            abort(400);
        }

        $friendship->status = 1;
        $friendship->save();

        return ['success' => true];
    }

    public function friendDeny(Request $request, $targetId)
    {
        $requester = User::findOrFail($targetId);
        $receiver = $request->user();

        $friendship = Friendship::where(['requester_id' => $requester->id, 'receiver_id' => $receiver->id])->first();

        if (!$friendship) {
            abort(404);
        }

        if ($friendship->areFriends()) {
            abort(400);
        }

        $friendship->delete();

        return ['success' => true];
    }

    public function toggleBestFriend(Request $request, $targetId)
    {
        $requester = User::findOrFail($targetId);
        $receiver = $request->user();

        $friendship = Friendship::where(['requester_id' => $requester->id, 'receiver_id' => $receiver->id])->first() ?: Friendship::where(['requester_id' => $receiver->id, 'receiver_id' => $requester->id])->first();

        if (!$friendship) {
            abort(404);
        }

        if (!$friendship->areFriends()) {
            abort(400);
        }

        $friendship->best_friends = !$friendship->best_friends;

        return ['success' => true, 'best_friends' => $friendship->best_friends];
    }

    public function discordlink(Request $request)
    {
        return Socialite::driver('discord')->redirect();
    }

    public function discordcallback(Request $request)
    {
        $discordUser = Socialite::driver('discord')->user();
        $user = $request->user();

        $user->discord_id = $discordUser->id;
        $user->save();

        return redirect(route('my.settings'));
    }

    public function invitekeys(Request $request)
    {
        $inviteKeys = InviteKey::where('creator', $request->user()->id)
            ->orderBy('created_at', 'DESC')
            ->paginate(15);

        return view('my.keys')->with(['inviteKeys' => $inviteKeys]);
    }

    public function purchaseinvitekey(Request $request)
    {
        $user = $request->user();

        if ($user->money < config('app.user_invite_key_cost')) {
            return redirect(route('my.keys'))->with('error', 'You cannot afford this.');
        }

        $inviteKeys = InviteKey::where('creator', $user->id)
            ->where('created_at', '>', now()->subDays(config('app.user_invite_key_cooldown'))->endOfDay())
            ->get();

        if (count($inviteKeys) >= config('app.user_maximum_keys_in_window')) {
            return redirect(route('my.keys'))->with('error', 'You\'ve already made ' . config('app.user_maximum_keys_in_window') . ' invite keys in the past ' . config('app.user_invite_key_cooldown') .' days.');
        }

        if (!$user->discord_id) {
            return redirect(route('my.keys'))->with('error', 'Please link your Discord account to create new keys.');
        }

        $user->money = $user->money - config('app.user_invite_key_cost');
        $user->save();

        $newKey = InviteKey::create([
            'creator' => $user->id,
            'token' => sprintf('%sKey-%s-%s', config('app.name'), $user->id, Str::random(25)),
            'uses' => 1
        ]);

        return redirect(route('my.keys'))->with('success', 'New invite key created.');
    }
}
