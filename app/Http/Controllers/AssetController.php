<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Server;
use App\Helpers\Gzip;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use App\Helpers\ScriptSigner;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\AcceptHeader;

class AssetController extends Controller
{
    public function getasset(Request $request)
    {
        if (!$request->id) {
            abort(404);
        }

        $item = Item::find($request->id);

        if (!$item) {
            $response = Http::withUserAgent('Roblox/WinInet')->get('https://assetdelivery.roblox.com/v1/asset?id=' . $request->id);
            $response = str_replace('www.roblox.com/asset', request()->getHttpHost() . '/asset', $response);
            return $response;
        }

        if ($item->type == "Lua") {
            $script = Storage::disk('public')->get('items/' . $item->id, 200);
            
            $response = Response::make(ScriptSigner::instance()->sign($script, $item->new_signature ? 'new' : 'old', $item->id));
            $response->header('Content-Type', 'text/plain');
            return $response;
        }
        
        if (Storage::disk('public')->exists('items/' . $item->id)) {
            $response = Response::make(Storage::disk('public')->get('items/' . $item->id), 200);
            $response->header('Content-Type', 'application/octet-stream');
            return $response;
        } else {
            abort(404);
        }
    }

    function getserverplace(Request $request, $id)
    {        
        $access = false;
        $server = Server::where('id', $id)->firstOrFail();

        if ($request->user) {
            if ($request->user->admin) {
                $access = true;
            }

            if ($request->user == $server->user) {
                $access = true;
            }
        }
        
        if ($request->has('secret')) {
            if ($request->secret == $server->secret) {
                $access = true;
            }
        }

        if ($request->has('key')) {
            if ($request->key == config('app.rcc_key')) {
                $access = true;
            }
        }

        if (!$access) {
            abort(403);
        }

        if (Storage::disk('public')->exists('serverplaces/' . $server->id))
        {
            return response(Storage::disk('public')->get(sprintf('serverplaces/%d', $server->id)))->header('Content-Encoding', 'gzip');
        }
        else
        {
            abort(404);
        }
    }

    public function getxmlasset(Request $request)
    {
        if (!$request->id) {
            abort(404);
        }

        $item = Item::findOrFail($request->id);

        if ($item->isXmlAsset()) {
            if (Storage::disk('public')->exists('items/' . $request->id)) {
                $response = Response::make(Storage::disk('public')->get('items/' . $request->id), 200);
                $response->header('Content-Type', 'application/octet-stream');
                return $response;
            } else {
                return abort(404);
            }
        }

        return view('client.xmlasset')->with('item', $item);
    }

    public function clothingCharApp(Request $request, $id)
    {
        return 'http://' . request()->getHost() . '/xmlasset?id=' . $id;
    }

    public function robloxredirect(Request $request)
    {
        if ($request->id) {
            if ($request->id == "humHealth") {
                return view('client.humanoidHealth');
            }

            $response = Http::withUserAgent('Roblox/WinInet')->get('https://assetdelivery.roblox.com/v1/asset?id=' . $request->id);
            return $response;
        }

        if ($request->assetversionid) {
            $response = Http::withUserAgent('Roblox/WinInet')->get('https://assetdelivery.roblox.com/v1/asset?id=' . $request->assetversionid);
            return $response;
        }
        
        abort(404);
    }

    public function insertasset(Request $request) {
        $nsets = 20;
        $type = "user";

        if ($request->has('nsets')) {
            $nsets = $request->nsets;
        }

        if ($request->has('type')) {
            $type = $request->type;
        }

        if ($request->has('userid')) {
            //http://www.tadah.rocks/Game/Tools/InsertAsset.ashx?nsets=20&type=user&userid=11744447

            //$response = Http::withUserAgent('Roblox/WinInet')->get('http://www.roblox.com/Game/Tools/InsertAsset.ashx?nsets=' . $nsets . '&type=' . $type . '&userid=' . $request->userid);
            $response = redirect()->away('http://polygon.pizzaboxer.xyz/Game/Tools/InsertAsset.ashx?nsets=' . $nsets . '&type=' . $type . '&userid=' . $request->userid);
            
            return $response;
        }

        if ($request->has('sid')) {
            //http://www.tadah.rocks/Game/Tools/InsertAsset.ashx?nsets=20&type=user&userid=11744447

            //$response = Http::withUserAgent('Roblox/WinInet')->get('http://www.roblox.com/Game/Tools/InsertAsset.ashx?sid=' . $request->sid);
            $response = redirect()->away('http://polygon.pizzaboxer.xyz/Game/Tools/InsertAsset.ashx?sid=' . $request->sid);

            return $response;
        }

        abort(404);
    }
}
