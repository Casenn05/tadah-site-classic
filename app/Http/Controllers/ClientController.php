<?php

namespace App\Http\Controllers;

use App\Helpers\ScriptSigner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Server;
use App\Models\GameToken;
use App\Models\User;
use App\Models\BodyColors;
use App\Models\OwnedItems;
use App\Models\Item;
use App\Models\Ban;
use App\Http\Cdn\Thumbnail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ClientController extends Controller
{
    const ADJECTIVES = ["MLG", "Dope", "Mrs.", "Gay", "Mr.", "Squandalous", "Big", "Obtuse", "Dumb", "Large", "Quintavious", "Deaf", "Pondering", "Punished", "Zapped", "Hilarious", "Big", "Frowning", "Fishy", "Premium", "Pristine", "Tubular", "Geeky", "Tweezy", "Condescending", "Repenting", "Quindarious", "Unnerving", "Reverberating", "Squashnabbing", "Guilty", "Nationwide", "Scrumptious", "Absurd", "Atrocious", "Flabbergasted", "Red", "Green", "Yellow", "Blue", "Purple", "Orange", "White", "Black", "Helen"];
    const NOUNS = ["Buford", "Pie", "Furry", "Duncan", "Peter scully", "Omar", "Cheese", "Blox", "Connor", "Pacifist", "Gooch", "Ears", "Kyle", "Hearing", "Taxes", "Gas", "Clownfish", "Snake", "Cake", "Checkers", "McDonalds", "Chess", "Bowler", "Andy", "Jason", "Yeat", "Gerald", "Pimp", "Emmanuel", "Scrotum", "Boosie", "Tupac", "Feet", "Shakur", "Tupacalypse", "Topaz yates", "Boomer", "Tank", "Jockey", "Hunter", "Spitter", "Witch", "Smoker", "Bill", "Zoey", "Louis", "Francis", "Rochelle", "Nick", "Ellis", "Coach", "Eminem", "Cole", "Keller", "Geico", "Nation", "Scallywag", "Scoundrel", "Squat", "Hercule", "Poppy", "Rose", "Straw", "Kendrick", "Tesla", "Baljeet", "Demarcus", "Rye", "Low"];

    public function client(Request $request, $client)
    {
        $clients = config('app.clients');

        if (!array_key_exists($client, $clients)) {
            abort(404);
        }

        $path = 'clients/' . $client . '.zip';
        $hash = 'none';
        if (Storage::disk('public')->exists($path)) {
            $hash = hash('sha256', Storage::disk('public')->get($path));
        }

        $data = [
            'version' => $clients[$client],
            'url' => route('client.downloadversion', $client),
            'sha256' => $hash
        ];

        $response = Response::make(json_encode($data), 200, ["Content-Type" => "application/json"]);
        return $response;
    }

    public function download(Request $request, $client)
    {
        $clients = config('app.clients');

        if (!array_key_exists($client, $clients)) {
            return 'badversion';
        }

        $path = 'clients/' . $client . '.zip';
        if (!Storage::disk('public')->exists($path)) {
            return 'nofiles';
        }

        return Storage::disk('public')->download($path);
    }

    public function generate(Request $request, $uuid)
    {
        $server = Server::where('uuid', $uuid)->firstOrFail();

        if (!$request->user()) {
            abort(404);


            if (!$server->allow_guests) {
                abort(404);
            }
        }

        if ($request->user()) {
            if ($request->user()->banned) {
                abort(403);
            }
        }

        if (!Cache::has('server_online' . $server->id, 0)) {
            abort(400);
        }

        $tokenString = Str::random(20);
        $token = new GameToken;
        if ($request->user()) {
            $token->user_id = $request->user()->id;
        }
        $token->server_id = $server->id;
        $token->token = $tokenString;
        $token->save();

        return $tokenString;
    }

    public function verifyuser(Request $request, $requestToken)
    {
        $token = GameToken::where('token', $requestToken)->first();

        if (is_null($token)) {
            return "invalid";
        }

        // already validated, so invalidate if an already validated token is used
        if ($token->validated) {
            return "invalid";
        }

        // deny users who do not have verified discord
        if ($token->user) {
            if (config('app.discord_verification_required') && !$token->user->discord_id) {
                return "invalid";
            }

            if ($token->user->banned) {
                return "invalid";
            }

            if ($token->user == $token->server->user) {
                $token->validated = true;
                $token->save();
                return "valid";
            }
        }

        if ($token) {
            if ($token->user) {
                if ($request->username == $token->user->username) {
                    $token->validated = true;
                    $token->save();

                    $token->server->update(['visits' => $token->server->visits + 1]);
                    return "valid";
                }
            } else {
                $token->validated = true;
                $token->save();

                $token->server->update(['visits' => $token->server->visits + 1]);
                return "valid";
            }
        }

        return "invalid";
    }

    public function host(Request $request, $secret)
    {
        $server = Server::where('secret', $secret)->first();

        if (!$server) {
            return 'print("Invalid server.")';
        }

        $script = file_get_contents(resource_path('lua/host.lua'));
        $script = strtr(
            $script,
            [
                '%SERVERPORT%' => $server->port,
                '%SERVERSECRET%' => $server->secret,
                '%SERVERUUID%' => $server->uuid,
                '%SITE%' => request()->getHttpHost(),
                '%PLACEID%' => $server->id,
                '%MAXPLAYERS%' => $server->maxplayers
            ]
        );

        return $script;
    }

    public function admin(Request $request, $secret)
    {
        $server = Server::where('secret', $secret)->first();

        if (!$server) {
            return 'print("Invalid server.")';
        }

        $admins = User::where('admin', true)->get();

        return view('client.admin')->with(['server' => $server, 'admins' => $admins]);
    }

    public function body_colors(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $colors = BodyColors::where('user_id', $user->id)->firstOrFail();
        $document = simplexml_load_file(resource_path('xml/BodyColors.xml'));

        $document->xpath('//int[@name="HeadColor"]')[0][0] = $colors->head_color;
        $document->xpath('//int[@name="TorsoColor"]')[0][0] = $colors->torso_color;
        $document->xpath('//int[@name="LeftArmColor"]')[0][0] = $colors->left_arm_color;
        $document->xpath('//int[@name="LeftLegColor"]')[0][0] = $colors->left_leg_color;
        $document->xpath('//int[@name="RightArmColor"]')[0][0] = $colors->right_arm_color;
        $document->xpath('//int[@name="RightLegColor"]')[0][0] = $colors->right_leg_color;

        return response($document->asXML())->header('Content-Type', 'text/xml');
    }

    public function body_colors_asset(Request $request)
    {
        $user = User::find($request->userId);
        $colors = BodyColors::where('user_id', $user->id)->firstOrFail();
        $document = simplexml_load_file(resource_path('xml/BodyColors.xml'));

        $document->xpath('//int[@name="HeadColor"]')[0][0] = $colors->head_color;
        $document->xpath('//int[@name="TorsoColor"]')[0][0] = $colors->torso_color;
        $document->xpath('//int[@name="LeftArmColor"]')[0][0] = $colors->left_arm_color;
        $document->xpath('//int[@name="LeftLegColor"]')[0][0] = $colors->left_leg_color;
        $document->xpath('//int[@name="RightArmColor"]')[0][0] = $colors->right_arm_color;
        $document->xpath('//int[@name="RightLegColor"]')[0][0] = $colors->right_leg_color;

        return response($document->asXML())->header('Content-Type', 'text/xml');
    }


    public function charapp_asset(Request $request)
    {
        // DO NOT CHANGE THIS TO USE url()!!! IT WILL BREAK 2010 DUE TO HTTPS!!!

        $user = User::find($request->userId);

        if(!$user)
        {
            return 'nil';
        };

        $appearance = array();
        $appearance[] = 'http://' . request()->getHost() . '/Asset/BodyColors.ashx?userId=' . $user->id . '&tick=' . time();

        $wearingItems = OwnedItems::where(['user_id' => $user->id, 'wearing' => true])->get();

        foreach ($wearingItems as $wearingItem) {
            $item = Item::find($wearingItem->item_id);

            if ($item->approved == 1) {
                if ($item->isXmlAsset()) {
                    $appearance[] = 'http://' . request()->getHost() . '/asset?id=' . $item->id;
                } else {
                    $appearance[] = 'http://' . request()->getHost() . '/xmlasset?id=' . $item->id;
                }
            }
        }

        return join(';', $appearance);
    }

    public function charapp(Request $request, $id)
    {
        // DO NOT CHANGE THIS TO USE url()!!! IT WILL BREAK 2010 DUE TO HTTPS!!!

        $user = User::find($id);

        if(!$user)
        {
            return 'nil';
        };

        $appearance = array();
        $appearance[] = 'http://' . request()->getHost() . '/users/' . $user->id . '/bodycolors?tick=' . time();

        $wearingItems = OwnedItems::where(['user_id' => $user->id, 'wearing' => true])->get();

        foreach ($wearingItems as $wearingItem) {
            $item = Item::find($wearingItem->item_id);

            if ($item->approved == 1) {
                if ($item->isXmlAsset()) {
                    $appearance[] = 'http://' . request()->getHost() . '/asset?id=' . $item->id;
                } else {
                    $appearance[] = 'http://' . request()->getHost() . '/xmlasset?id=' . $item->id;
                }
            }
        }

        return join(';', $appearance);
    }

    public function ping(Request $request, $secret)
    {
        $server = Server::where('secret', $secret)->first();
        $playercount = 0;

        if (!$server) {
            abort(404);
        }

        if (!is_numeric($request->playercount)) {
            abort(400);
        }
        $playercount = abs(intval($request->playercount));

        if ($playercount > $server->maxplayers + 5) {
            abort(400);
        }

        Cache::put('server_online' . $server->id, $playercount, Carbon::now()->addMinutes(1));
        $server->touch();

        return 'OK';
    }

    public function getuserthumbnail(Request $request)
    {
        if (!$request->has('userId'))
        {
            return abort(404);
        }

        User::findOrFail($request->userId);
        $thumbnail = Thumbnail::resolve('user', $request->userId);

        $url = match ($thumbnail['status'])
        {
            -1, 1, 3 => Thumbnail::static_image('blank.png'),
            2 => Thumbnail::static_image('disapproved.png'),
            0 => $thumbnail['result']['body']
        };

        return redirect($url);
    }

    public function getitemthumbnail(Request $request)
    {
        if (!$request->has('itemId') && !$request->has('assetid'))
        {
            return abort(404);
        }

        $id = $request->has('itemId') ? $request->itemId : $request->assetid;
        Item::findOrFail($id);
        $thumbnail = Thumbnail::resolve('item', $id);

        $url = match ($thumbnail['status'])
        {
            -1, 1, 3 => Thumbnail::static_image('blank.png'),
            2 => Thumbnail::static_image('disapproved.png'),
            0 => $thumbnail['result']['url']
        };

        return redirect($url);
    }

    public function negotiate(Request $request)
    {
        if (!$request->headers->has('roblox_session-id') || !$request->headers->has('roblox-game-id'))
        {
            abort(403);
        }

        $ticket = sprintf('%s;%s;%s', $request->headers('roblox_session-id'), $request->headers('roblox_game-id'), date('n/j/Y g:i:s A'));
        $result = '<Value Type="string">%s</Value>';

        return sprintf($result, ScriptSigner::instance()->sign($ticket, false));
    }

    public function playsolo(Request $request)
    {
        $script = file_get_contents(resource_path('lua/playsolo.lua'));

        $playername = "Player";
        $playerid = "1";

        if ($request->user()) {
            $playername = $request->user()->username;
            $playerid = $request->user()->id;
        }

        $script = strtr(
            $script,
            [
                '%PLAYERNAME%' => $playername,
                '%PLAYERID%' => $playerid,
                '%PLAYERCHARAPP%' => route('users.getcharacter', $playerid),
                '%SITE%' => request()->getHttpHost()
            ]
        );

        $signedscript = ScriptSigner::instance()->sign($script, 'old');

        $response = Response::make($signedscript);
        $response->header('Content-Type', 'text/plain');
        return $response;
    }

    public function newplaysolo(Request $request)
    {
        $script = file_get_contents(resource_path('lua/playsolo.lua'));

        $playerid = "0";

        if ($request->user()) {
            $playerid = $request->user()->id;
        }

        $script = strtr(
            $script,
            [
                '%PLAYERID%' => $playerid,
                '%PLAYERCHARAPP%' => route('users.getcharacter', $playerid),
                '%SITE%' => request()->getHttpHost()
            ]
        );

        $signedscript = ScriptSigner::instance()->sign($script, 'new');

        $response = Response::make($signedscript);
        $response->header('Content-Type', 'text/plain');
        return $response;
    }

    public function studioscript(Request $request)
    {
        $script = file_get_contents(resource_path('lua/studio.lua'));
        $signedscript = ScriptSigner::instance()->sign($script, 'old');

        $response = Response::make($signedscript);
        $response->header('Content-Type', 'text/plain');
        return $response;
    }

    public function newstudioscript(Request $request)
    {
        $script = file_get_contents(resource_path('lua/newstudio.lua'));
        $signedscript = ScriptSigner::instance()->sign($script, 'new');

        $response = Response::make($signedscript);
        $response->header('Content-Type', 'text/plain');
        return $response;
    }

    public function joinlegacy(Request $request, $requestToken) // 2010 and below
    {
        // DO NOT CHANGE THIS TO USE url()!!! IT WILL BREAK 2010 DUE TO HTTPS!!!

        $token = GameToken::where('token', $requestToken)->first();

        if (!$token) {
            return 'game:SetMessage("Invalid join token. If this error persists, contact us.")';
        }

        if (!$token->server->allow_guests) {
            if (config('app.discord_verification_required') && !$token->user->discord_id) {
                return ScriptSigner::instance()->sign('game:SetMessage("Link your Discord account to play!")', 'new');
            }
        }

        if ($token->generated) {
            return 'game:SetMessage("This token was already used.")';
        }

        if (!Cache::has('server_online' . $token->server->id, 0)) {
            return 'game:SetMessage("This server is offline.")';
        }

        if (Cache::get('server_online' . $token->server->id, 0) >= $token->server->maxplayers) {
            return 'game:SetMessage("This server is currently full.")';
        }

        $ip = $token->server->ip;
        if ($token->user) {
            if ($token->user == $token->server->user && $token->server->loopback_ip) {
                $ip = $token->server->loopback_ip;
            }
        }

        $script = file_get_contents(resource_path('lua/join.lua'));
        if (!$token->user) {
            $script = strtr(
                $script,
                [
                    '%PLAYERNAME%' => $token->user->username,
                    '%PLAYERID%' => $token->user->id,
                    '%PLAYERCHARAPP%' => 'http://' . request()->getHttpHost() . '/users/' . $token->user->id . '/character',
                    '%TOKEN%' => $token->token,
                    '%SERVERIP%' => $ip,
                    '%SERVERPORT%' => $token->server->port,
                    '%SITE%' => request()->getHttpHost(),
                    '%GUEST%' => 'false'
                ]
            );
        } else {
            $script = strtr(
                $script,
                [
                    '%PLAYERNAME%' => self::ADJECTIVES[array_rand(self::ADJECTIVES)] . ' ' . self::NOUNS[array_rand(self::NOUNS)] . ' ' . strval(rand(0, 99)),
                    '%PLAYERID%' => rand(1, 100000) * -1,
                    '%PLAYERCHARAPP%' => 'http://' . request()->getHttpHost() . '/users/1583/character',
                    '%TOKEN%' => $token->token,
                    '%SERVERIP%' => $ip,
                    '%SERVERPORT%' => $token->server->port,
                    '%SITE%' => request()->getHttpHost(),
                    '%GUEST%' => 'true'
                ]
            );
        }

        $token->generated = true;
        $token->save();

        $response = Response::make($script);
        $response->header('Content-Type', 'text/plain');
        return $response;
    }

    public function joinold(Request $request, $requestToken) // 2012
    {
        $token = GameToken::where('token', $requestToken)->first();

        if (!$token) {
            return ScriptSigner::instance()->sign('game:SetMessage("Invalid join token. If this error persists, contact us.")', 'old');
        }

        if (config('app.discord_verification_required') && !$token->user->discord_id) {
            return ScriptSigner::instance()->sign('game:SetMessage("Link your Discord account to play!")', 'old');
        }

        if ($token->generated) {
            return ScriptSigner::instance()->sign('game:SetMessage("This token was already used.")', 'old');
        }

        if (!Cache::has('server_online' . $token->server->id, 0)) {
            return ScriptSigner::instance()->sign('game:SetMessage("This server is offline.")', 'old');
        }

        if (Cache::get('server_online' . $token->server->id, 0) >= $token->server->maxplayers) {
            return ScriptSigner::instance()->sign('game:SetMessage("This server is currently full.")', 'old');
        }

        $ip = $token->server->ip;
        if ($token->user == $token->server->user && $token->server->loopback_ip) {
            $ip = $token->server->loopback_ip;
        }

        $script = file_get_contents(resource_path('lua/join.lua'));
        $script = strtr(
            $script,
            [
                '%PLAYERNAME%' => $token->user->username,
                '%PLAYERID%' => $token->user->id,
                '%PLAYERCHARAPP%' => 'http://' . request()->getHttpHost() . '/users/' . $token->user->id . '/character',
                '%TOKEN%' => $token->token,
                '%SERVERIP%' => $ip,
                '%SERVERPORT%' => $token->server->port,
                '%SITE%' => request()->getHttpHost()
            ]
        );

        $token->generated = true;
        $token->save();

        $response = Response::make($script);
        $response->header('Content-Type', 'text/plain');
        return $response;
    }

    public function joinnew(Request $request, $requestToken) // 2013-2016(?)
    {
        $token = GameToken::where('token', $requestToken)->first();

        if (!$token) {
            return 'game:SetMessage("Invalid join token. If this error persists, contact us.")';
        }

        if (Cache::get('server_online' . $token->server->id, 0) >= $token->server->maxplayers) {
            return 'game:SetMessage("This server is currently full.")';
        }

        $ip = $token->server->ip;
        if ($token->user == $token->server->user && $token->server->loopback_ip) {
            $ip = $token->server->loopback_ip;
        }

        $script = file_get_contents(resource_path('lua/join.lua'));
        $script = strtr(
            $script,
            [
                '%PLAYERNAME%' => $token->user->username,
                '%PLAYERID%' => $token->user->id,
                '%PLAYERCHARAPP%' => route('users.getcharacter', $token->user->id),
                '%TOKEN%' => $token->token,
                '%SERVERIP%' => $ip,
                '%SERVERPORT%' => $token->server->port,
                '%SITE%' => request()->getHttpHost()
            ]
        );

        $response = Response::make($script);
        $response->header('Content-Type', 'text/plain');
        return $response;
    }

    public function newjoin(Request $request, $requestToken) // for 2013 and above
    {
        $token = GameToken::where('token', $requestToken)->first();

        if (!$token) {
            return ScriptSigner::instance()->sign('game:SetMessage("Invalid join token. If this error persists, contact us.")', 'new');
        }

        if (!$token->server->allow_guests) {
            if (config('app.discord_verification_required') && !$token->user->discord_id) {
                return ScriptSigner::instance()->sign('game:SetMessage("Link your Discord account to play!")', 'new');
            }
        }

        if ($token->generated) {
            return ScriptSigner::instance()->sign('game:SetMessage("This token was already used.")', 'new');
        }

        $ip = $token->server->ip;
        if ($token->user) {
            if ($token->user == $token->server->user && $token->server->loopback_ip) {
                $ip = $token->server->loopback_ip;
            }
        }

        if (!Cache::has('server_online' . $token->server->id, 0)) {
            return ScriptSigner::instance()->sign('game:SetMessage("This server is offline.")', 'new');
        }

        if (Cache::get('server_online' . $token->server->id, 0) >= $token->server->maxplayers) {
            return ScriptSigner::instance()->sign('game:SetMessage("This server is currently full.")', 'new');
        }

        $script = file_get_contents(resource_path('lua/newjoin.lua'));
        if ($token->user) {
            $script = strtr(
                $script,
                [
                    '%PLAYERNAME%' => $token->user->username,
                    '%PLAYERID%' => $token->user->id,
                    '%TOKEN%' => $token->token,
                    '%SERVERIP%' => $ip,
                    '%SERVERPORT%' => $token->server->port,
                    "%MEMBERSHIP%" => (int)$token->user->booster,
                    "%CHATTYPE%" => $token->server->chat_type,
                    "%PLACEID%" => $token->server->id,
                    '%CREATOR%' => $token->server->user->id,
                    '%CORESCRIPT_PREFRENCE%' => ($token->user->old_cores ? 1320 : 2006),
                    '%SITE%' => request()->getHttpHost(),
                    '%GUEST%' => 'false'
                ]
            );
        } else {
            $script = strtr(
                $script,
                [
                    '%PLAYERNAME%' => self::ADJECTIVES[array_rand(self::ADJECTIVES)] . ' ' . self::NOUNS[array_rand(self::NOUNS)] . ' ' . strval(rand(0, 99)),
                    '%PLAYERID%' => rand(1, 100000) * -1,
                    '%TOKEN%' => $token->token,
                    '%SERVERIP%' => $ip,
                    '%SERVERPORT%' => $token->server->port,
                    "%MEMBERSHIP%" => 0,
                    "%CHATTYPE%" => $token->server->chat_type,
                    "%PLACEID%" => $token->server->id,
                    '%CREATOR%' => $token->server->user->id,
                    '%CORESCRIPT_PREFRENCE%' => 2006,
                    '%SITE%' => request()->getHttpHost(),
                    '%GUEST%' => 'true'
                ]
            );
        }

        $token->generated = true;
        $token->save();

        $response = Response::make(ScriptSigner::instance()->sign($script, 'new'));
        $response->header('Content-Type', 'text/plain');
        return $response;
    }

    public function newhost(Request $request, $secret)
    {
        $server = Server::where('secret', $secret)->first();

        if (!$server) {
            return ScriptSigner::instance()->sign('game:SetMessage("Invalid server.")', "new");
        }

        $script = file_get_contents(resource_path('lua/newhost.lua'));
        $script = strtr(
            $script,
            [
                '%SERVERPORT%' => $server->port,
                '%SERVERSECRET%' => $server->secret,
                '%SERVERUUID%' => $server->uuid,
                '%SITE%' => request()->getHttpHost(),
                '%MAXPLAYERS%' => $server->maxplayers,
                '%PLACEID%' => $server->id,
                '%CREATOR%' => $server->user->id,
            ]
        );

        $script = ScriptSigner::instance()->sign($script, "new");

        $response = Response::make($script);
        $response->header('Content-Type', 'text/plain');
        return $response;
    }

    public function newhosttest(Request $request)
    {
        if ($request->has('key')) {
            if ($request->key == config('app.rcc_key')) {
                $version = $request->has('old') ? "old" : "new";
                $script = file_get_contents(resource_path('lua/newhosttest.lua'));
                $script = strtr(
                    $script,
                    [
                        '%SITE%' => request()->getHttpHost()
                    ]
                );

                $script = ScriptSigner::instance()->sign($script, $version);

                $response = Response::make($script);
                $response->header('Content-Type', 'text/plain');
                return $response;
            } else {
                abort(404);
            }
        } else {
            abort(404);
        }
    }

    public function newjointest(Request $request)
    {
        if ($request->has('key')) {
            if ($request->key == config('app.rcc_key')) {
                $version = $request->has('old') ? "old" : "new";
                $script = file_get_contents(resource_path('lua/newjointest.lua'));
                $script = strtr(
                    $script,
                    [
                        '%PLAYERNAME%' => $request->username,
                        '%PLAYERID%' => $request->id,
                        '%SERVERIP%' => $request->ip,
                        '%SERVERPORT%' => $request->port,
                        '%SITE%' => request()->getHttpHost()
                    ]
                );

                // Carrot will be found dead on November 5th 2021 @ exactly 11:59 PM EST
                $script = ScriptSigner::instance()->sign($script, $version);

                $response = Response::make($script);
                $response->header('Content-Type', 'text/plain');
                return $response;
            } else {
                abort(404);
            }
        } else {
            abort(404);
        }
    }

    public function thumbnailasset(Request $request) {
        if (!$request['aid']) {
            abort(404);
        }

        $response = Http::withUserAgent('Roblox/WinInet')->get('https://assetgame.roblox.com/Thumbs/Asset.asmx/RequestThumbnail_v2?width=420&height=420&imageFormat=Png&thumbnailFormatId=296&overrideModeration=false&assetId=' . $request['aid']);
        $response = json_decode($response, true);
        return redirect($response['d']['url']);
    }

    public function fastflags(Request $request, $type)
    {
        $version = "2012";

        if ($type == "CometAppSettings") {
            $version = "2016"; // Fuck yuo im lazy right now. 2016 is in testing phase...... not anything big
        }

        if ($type == "ClientAppSettings") {
            $version = "2014";
        }

        $flags = Storage::disk('local')->get((sprintf('fastflags/%s.json', $version)));

        return response()->json(json_decode($flags));
    }

    public function placelauncher(Request $request)
    {
        $id = $request->placeId;
        if(!$id) {
            abort(404);
        };

        $test = '{"jobId":"asdadasd","status":2,"joinScriptUrl":"http://tadah.local/game/join.ashx?ip=127.0.0.1&port=53640&username=Iago&id=45","authenticationUrl":"http://google.com","authenticationTicket":"atest","message":"null"}';
        return $test;
    }

    public function gameserver(Request $request)
    {
        $script = file_get_contents(resource_path('lua/gameserver.lua'));
        $script = strtr(
            $script,
            [
                '%SERVERPORT%' => 53640,
                '%SITE%' => request()->getHttpHost(),
            ]
        );

        $script = ScriptSigner::instance()->sign($script, "new");

        $response = Response::make($script);
        $response->header('Content-Type', 'text/plain');
        return $response;
    }

    public function studiojoin(Request $request)
    {
        $script = file_get_contents(resource_path('lua/studiojoin.lua'));
        $script = strtr(
            $script,
            [
                '%PLAYERID%' => $request->UserID,
                '%SERVERPORT%' => $request->serverPort,
                '%SITE%' => request()->getHttpHost(),
            ]
        );

        $script = ScriptSigner::instance()->sign($script, "new");

        $response = Response::make($script);
        $response->header('Content-Type', 'text/plain');
        return $response;
    }

    public function awardpoints(Request $request)
    {
        // literally no point in ACTUALLY implementing player points, we just want it to show up ingame
        if(!User::find($request->get('userId'))) {
            abort(404);
        }

        return response()->json([
            'success' => true,
            'userGameBalance' => $request->get('amount'),
            'userBalance' => $request->get('amount'),
            'pointsAwarded' => $request->get('amount')
        ]);
    }

    public function currency(Request $request)
    {
        // roblonium type beat
        // idk how else to do this so oh boy
        // this will absolutely not work with alts LOL
        $ip = $request->ip();
        $user = User::where(['last_ip' => $ip])
                    ->orderBy('last_online', 'DESC')
                    ->first();
        $result = json_encode(
            array(
                "robux" => (int)$user->money
            )
        );
        return $result;
    }

    public function getproductdetails(Request $request)
    {
        $item = Item::findOrFail($request->productId);
        $result = json_encode(
            array(
                "TargetId" => $item->id,
                "ProductType" => $item->type,
                "AssetId" => $item->id,
                "ProductId" => null,
                "Name" => $item->name,
                "Description" => $item->description,
                "AssetTypeId" => 2,
                "Creator" => array(
                    "Id" => $item->user->id,
                    "Name" => $item->user->username,
                    "CreatorType" => "User",
                    "CreatorTargetId" => $item->user->id,
                ),
                "IconImageAssetId" => $item->id,
                "Created" => $item->created_at,
                "Updated" => $item->updated_at,
                "PriceInRobux" => (int)$item->price,
                "PriceInTickets" => null,
                "IsNew" => false,
                "IsForSale" => (bool)$item->onsale,
                "IsPublicDomain" => false,
                "IsLimited" => false,
                "IsLimitedUnique" => false,
                "Remaining" => null,
                "MinimumMembershipLevel" => 0
            )
        );
        return $result;
    }

    function toolbox(Request $request)
    {
        $models = Item::where(['type' => 'Model'])->get();
        if (request('search')) {
            $models = Item::where(['type' => 'Model'])->where('name', 'LIKE', '%' . request('search') . '%')->get();
        }
        return view('client.toolbox', ['models' => $models]);
    }
}
