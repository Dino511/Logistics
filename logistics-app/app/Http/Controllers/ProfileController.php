<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        if ($request->hasFile('avatar')) {
            $this->deleteAvatar($user->avatar_path);
            // Stored under a generated name on the public disk; only the path is saved in the DB.
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar')) {
            $this->deleteAvatar($user->avatar_path);
            $user->avatar_path = null;
        }

        $user->save();
        ActivityLog::record('profile_updated', 'Updated their own profile'.($request->hasFile('avatar') ? ' and photo' : ''));

        return back()->with('status', 'Profile updated.');
    }

    private function deleteAvatar(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
