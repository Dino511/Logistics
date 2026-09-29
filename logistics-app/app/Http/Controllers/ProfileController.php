<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\SiteImage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            // mimes checks the real file content, not just the client-supplied extension.
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];

        $user->save();

        if ($request->hasFile('avatar')) {
            // Stored under a generated name on the public disk; only the path is saved in site_images.
            SiteImage::storeAvatar($user, $request->file('avatar'));
        } elseif ($request->boolean('remove_avatar')) {
            SiteImage::removeAvatar($user);
        }

        ActivityLog::record('profile_updated', 'Updated their own profile'.($request->hasFile('avatar') ? ' and photo' : ''));

        return back()->with('status', 'Profile updated.');
    }
}
