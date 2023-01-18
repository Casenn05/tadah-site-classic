<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemComment;
use App\Models\OwnedItems;
use Illuminate\Support\Str;
use App\Models\RenderQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use claviska\SimpleImage;
use Illuminate\Support\Facades\Response;
use App\Rules\AssetTypesRule;
use App\Rules\MeshValidator;
use App\Rules\ModelValidator;
use App\Jobs\RenderJob;
use App\Http\Cdn\Render;
use App\Http\Cdn\Thumbnail;
use Illuminate\Support\Facades\File;

class CatalogController extends Controller
{
    const CATEGORIES = ['hats', 'shirts', 'pants', 'tshirts', 'images', 'faces', 'gears', 'heads', 'packages', 'audio', 'meshes', 'models'];
    const BASE_CATEGORIES = ['hats', 'shirts', 'pants', 'tshirts', 'images', 'faces', 'gears', 'heads', 'packages', 'audio', 'meshes', 'models'];
    const UPLOAD_CATEGORIES = ['t-shirt', 'shirt', 'pants', 'image', 'mesh', 'face', 'audio', 'model']; // For the asset upload rule, which allows uploading of specific types.
    const TEMPLATE_CATEGORIES = ['shirt', 'pants']; // items that have a template

    public function __construct()
    {
        // $this->middleware('auth');
    }

    private function is_valid_category($category) : bool
    {
        if (strtolower($category) == 'meshes')
        {
            return true;
        }

        return (in_array(strtolower($category), self::CATEGORIES) || in_array(strtolower("{$category}s"), self::CATEGORIES));
    }

    public function item_thumbnail(Request $request, $id)
    {
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

    public function list(Request $request, string $category)
    {
        if (!$this->is_valid_category($category))
        {
            abort(404);
        }

        $category = ucfirst($category);
        if ($category == 'Pants' || $category == 'Tshirts')
        {
            if ($category == 'Tshirts')
            {
                $category = 'T-Shirt';
            }
        }
        elseif($category == 'Audio')
        {
            $category = 'Audio';
        }
        else
        {
            $category = substr($category, 0, ($category == 'Meshes' ? -2 : -1));
        }
        
        $items = Item::where(['type' => $category, 'approved' => 1, 'onsale' => true])->orderBy('created_at', 'desc');

        if (request('search')) {
            $items->where('name', 'LIKE', '%' . request('search') . '%');
        }

        return view('catalog.list')->with(['items' => $items->paginate(18)->appends($request->all()), 'type' => $category, 'search' => request('search')]);
    }

    public function json(Request $request, string $category)
    {
        if (!$this->is_valid_category($category))
        {
            abort(404);
        }

        $category = ucfirst($category);
        if ($category == 'Pants' || $category == 'Tshirts')
        {
            if ($category == 'Tshirts')
            {
                $category = 'T-Shirt';
            }
        }
        elseif($category == 'Audio')
        {
            $category = 'Audio';
        }
        else
        {
            $category = substr($category, 0, ($category == 'Meshes' ? -2 : -1));
        }
        
        $items = Item::where(['type' => $category, 'approved' => 1, 'onsale' => true])->orderBy('created_at', 'desc');

        if (request('search')) {
            $items->where('name', 'LIKE', '%' . request('search') . '%');
        }

        return $items->paginate(18);
    }

    public function item(Request $request, $id)
    {
        $item = Item::findOrFail($id);
        $recommended = Item::where(['type' => $item->type, 'onsale' => true])->inRandomOrder()->limit(6)->get();
        $comments = ItemComment::where(['item_id' => $item->id])->orderBy('created_at', 'desc')->get();
        $ownedItem = OwnedItems::where(['user_id' => $request->user()->id, 'item_id' => $item->id])->first();

        return view('item.view')->with(['item' => $item, 'ownedItem' => $ownedItem, 'recommended' => $recommended, 'comments' => $comments]);
    }

    public function comment(Request $request, $id)
    {
        $item = Item::find($id);
        $user = $request->user();

        if(!$item)
        {
            abort(404);
        }

        if(!$user)
        {
            abort(404);
        }

        $request->validate([
            'comment' => ['required', 'max:200', 'string', 'not_regex:/[\xCC\xCD]/'],            
        ]);

        $comment = new ItemComment;
        $comment->item_id = $item->id;
        $comment->user_id = $user->id;
        $comment->body = $request->comment;
        $comment->save();

        return redirect(route('item.view', $item->id))->with('message', 'Comment successfully posted.');
    }

    public function upload(Request $request)
    {
        if ($request->isMethod('post'))
        {
            if (!config('app.item_creation_enabled'))
            {
                abort(404);
            }

            switch ($request['type'])
            {
                case "Model":
                    $asset_validator = ['required', 'max:51200', new ModelValidator()];
                    break;

                case 'Mesh':
                    $asset_validator = ['required', 'max:51200', new MeshValidator()];
                    break;
                
                case 'Audio':
                    if (!$request->user()->isStaff())
                    {
                        abort(404);
                    }

                    $asset_validator = ['required', 'mimes:ogg,mp3,wav,midi'];
                    break;
                
                default:
                    $asset_validator = ['required', 'max:51200', 'image'];
            }

            $request->validate([
                'name' => ['required', 'string', 'max:100', 'not_regex:/[\xCC\xCD]/'],
                'description' => ['string', 'max:2000', 'not_regex:/[\xCC\xCD]/'],
                'price' => ['required', 'integer', 'min:0', 'max:999999'],
                'type' => ['required', new AssetTypesRule()],
                'asset' => $asset_validator
            ]);

            $user = $request->user();

            if ($request['type'] == 'Face' && !$user->isStaff() || $request['type'] == 'Audio' && !$user->isStaff() || $request['type'] == 'Hat') {
                return abort(404);
            }
    
            if ($user->money < config('app.asset_upload_cost')) {
                return redirect(route('catalog.upload'))->with('error', 'You do not have enough money to upload an asset.');
            }
    
            $user->money = $user->money - config('app.asset_upload_cost');
            $user->save();
    
            $item = Item::create([
                'name' => $request['name'],
                'description' => $request['description'],
                'creator' => $user->id,
                'price' => $request['price'],
                'type' => $request['type'],
                'sales' => 0,
                'onsale' => true,
                'approved' => (config('app.assets_approved_by_default') ? 1 : ($user->isAdmin() ? 1 : 0))
            ]);
            
            if ($request->type != 'Audio')
            {
                try
                {
                    $img = new SimpleImage($request->file('asset'));
                    $tshirt = new SimpleImage(resource_path('png/tshirt.png'));
        
                    switch ($request->type) {
                        case 'Face':                        
                            $img->resize(250, 250);
                            break;
                        case 'T-Shirt':
                            $img->resize(250, 250);
                            $img = $tshirt->overlay($img, 'center');
                            break;
                        default:
                            $img->resize(250, 250);
                            break;
                        case 'Image':
                            $img->resize(250, 250);
                            break;
                        case 'Shirt':
                            $img->crop(165, 201, 424, 74);
                            break;
                        case 'Pants':
                            $img->crop(217, 482, 371, 355);
                            break;
                    }
                    
                    Render::save(sprintf('items/%d.png', $item->id), $img->toString());
                }
                catch (\Exception $exception)
                {
                    // this sucks fucking ass omfg
                    File::copy(resource_path('png/asset/disapproved.png'), Storage::disk('local')->path(sprintf('renders/items/%d.png', $item->id)));
                }
            }
            else
            {
                Render::save(sprintf('items/%d.png', $item->id), File::get(resource_path('png/asset/audio.png')));
            }


            $request->file('asset')->storeAs("public/items", $item->id);
    
            OwnedItems::create([
                'user_id' => $user->id,
                'item_id' => $item->id,
                'wearing' => false
            ]);
    
            if ($item->type == 'Shirt' || $item->type == 'Pants') {
                $this->dispatch(new RenderJob("clothing", $item->id));
            }

            if ($item->type == 'Mesh') {
                $this->dispatch(new RenderJob('mesh', $item->id));
            }

            if ($item->type == 'Model') {
                $this->dispatch(new RenderJob('xml', $item->id));
            }

            if($item->type == 'Head') {
                $this->dispatch(new RenderJob('head', $item->id));
            }
            
            return redirect(route('item.view', $item->id))->with('message', 'Item successfully uploaded.');
        }

        return view('catalog.upload');
    }

    public function delete(Request $request, $id)
    {
        $item = Item::findOrFail($id);        
        $user = $request->user();

        if (!$item)
        {
            abort(404);
        }

        $itemName = $item->name;

        if (!$user->isAdmin())
        {
            abort(404);
        }

        // delete the item
        // but first, delete it from owned items
        foreach(OwnedItems::where('item_id', $item->id)->get() as $ownedItem)
        {
            $ownedItem->delete();
        }

        // delete all of it's data
        if($item->isXmlAsset())
        {
            Storage::disk('public')->delete('items/' . $item->id);
        }

        $item->delete();

        return redirect(route('catalog.category', 'hats'))->with('message', 'Item ' . $itemName . ' deleted successfully.');
    }

    public function configure(Request $request, $id)
    {
        if ($request->isMethod('post'))
        {
            $item = Item::findOrFail($id);
            $user = $request->user();
            
            if (!$item)
            {
                abort(404);
            }
    
            if ($user->id != $item->creator && !$user->isAdmin())
            {
                abort(404);
            }
    
            $request->validate([
                'name' => ['required', 'string', 'max:100', 'not_regex:/[\xCC\xCD]/'],
                'description' => ['string', 'max:2000', 'not_regex:/[\xCC\xCD]/'],
                'price' => ['required', 'integer', 'min:0', 'max:999999'],
                'xml' => ['string']
            ]);
    
            $item->name = $request['name'];
            $item->description = $request['description'];
            $item->price = $request['price'];
            $item->onsale = $request->has('onsale');
    
            if ($item->isXmlAsset() && $user->isAdmin())
            {
                $item->thumbnail_url = $request['thumbnailurl'];
                $this->dispatch(new RenderJob($item->type, $item->id));
            }
    
            if ($item->isXmlAsset() || $item->type == "Lua")
            {
                if ($user->isAdmin())
                {
                    Storage::disk('public')->put('items/' . $item->id, $request['xml']);
                }
            }
    
            if ($item->type == "Lua")
            {
                $item->new_signature = $request->has('newsignature');
            }
    
            $item->save();
    
            return redirect(route('item.view', $item->id))->with('message', 'Changes saved successfully.');
        }

        $item = Item::findOrFail($id);
        $user = $request->user();

        if (!$item) {
            abort(404);
        }

        if ($user->id != $item->creator && !$user->isAdmin()) {
            abort(403);
        }

        return view('item.configure')->with('item', $item);
    }

    public function buy(Request $request, $id)
    {
        $item = Item::findOrFail($id);
        $ownedItem = OwnedItems::where(['user_id' => $request->user()->id, 'item_id' => $item->id])->first();

        if (!$request->user()->isAdmin()) {
            if (!$item->onsale)
            {
                abort(403);
            }
    
            if ($item->approved != 1)
            {
                abort(403);
            }
        }

        if ($ownedItem)
        {
            abort(403);
        }

        // TODO: Skip if user is admin?
        if ($request->user()->money < $item->price)
        {
            abort(403);
        }

        OwnedItems::create([
            'user_id' => $request->user()->id,
            'item_id' => $item->id,
            'wearing' => false
        ]);

        $request->user()->money = $request->user()->money - $item->price;
        $request->user()->save();

        $item->user->money = $item->user->money + $item->price;
        $item->user->save();

        $item->sales = $item->sales + 1;
        $item->save();

        return redirect(route('item.view', $item->id))->with('message', 'Item purchased successfully.');
    }

    public function template(Request $request, $id)
    {
        $item = Item::findOrFail($id);
        if(!in_array(Str::lower($item->type), self::TEMPLATE_CATEGORIES))
        {
            abort(403);
        }

        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        $path = 'items/asset-error.png';
        if(Storage::disk('public')->exists(sprintf('items/%d', $id))) {
            $path = sprintf('items/%d', $id);
        }
        
        $response = Response::make(Storage::disk('public')
        ->get($path, 200))
        ->header('Content-Type', 'image/png');
        return $response;
    }

    public function thumbnail(Request $request, $id)
    {
        $item = Item::findOrFail($id);

        $path = 'items/asset-error.png'; // default image (not found)
        if ($item->type == "Audio") {
            $path = 'items/asset-audio.png';
        } else {
            if (Storage::disk('public')->exists('items/' . $item->id . '_thumbnail.png')) {
                $path = ($item->approved == 1) ?
                    ('items/' . $id . '_thumbnail.png') :   // item thumbnail if it's approved
                    'items/asset-notapproved.png';          // not approved image otherwise
            } elseif ($item->thumbnail_url) {
                return redirect($item->thumbnail_url);
            }
        }
        
        $response = Response::make(Storage::disk('public')
        ->get($path, 200))
        ->header('Content-Type', 'image/png');
        return $response;
    }
}
