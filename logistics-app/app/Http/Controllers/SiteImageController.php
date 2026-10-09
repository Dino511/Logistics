<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\SiteImage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SiteImageController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-site-images');

        return view('site-images.index', [
            'slots' => SiteImage::SLOTS,
            'images' => SiteImage::whereNull('user_id')->get()->keyBy('name'),
            'users' => User::with('avatar')->orderBy('name')->get(),
        ]);
    }

    public function storeAvatar(Request $request, User $user)
    {
        Gate::authorize('manage-site-images');

        $request->validate(['avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);

        SiteImage::storeAvatar($user, $request->file('avatar'));
        ActivityLog::record('avatar_updated', "Updated {$user->name}'s profile picture", $user);

        return back()->with('status', "{$user->name}'s profile picture updated.");
    }

    public function destroyAvatar(User $user)
    {
        Gate::authorize('manage-site-images');

        SiteImage::removeAvatar($user);
        ActivityLog::record('avatar_removed', "Removed {$user->name}'s profile picture", $user);

        return back()->with('status', "{$user->name}'s profile picture removed.");
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-site-images');

        $data = $request->validate([
            'name' => ['required', Rule::in(array_keys(SiteImage::SLOTS))],
            // Raster types only: SVG can carry scripts.
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=400,min_height=400'],
        ]);

        // Laravel picks a random file name, so uploads can't overwrite or guess other files.
        $path = $request->file('image')->store('site-images', 'public');

        $image = SiteImage::firstOrNew(['name' => $data['name']]);
        $oldPath = $image->exists ? $image->path : null;
        $oldDisk = $image->disk;

        $image->fill(['path' => $path, 'disk' => 'public'])->save();

        if ($oldPath && $oldPath !== $path) {
            Storage::disk($oldDisk)->delete($oldPath);
        }

        ActivityLog::record('site_image_updated', 'Updated site image "'.SiteImage::SLOTS[$data['name']]['label'].'"', $image);

        return back()->with('status', 'Image updated.');
    }
}
