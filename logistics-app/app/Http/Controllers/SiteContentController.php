<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The guide, guidelines and company profile on the drivers' dashboard, edited by Super Admins. */
class SiteContentController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-site-contents');

        return view('site-contents.index', ['sections' => SiteContent::sections()]);
    }

    public function update(Request $request, SiteContent $content)
    {
        Gate::authorize('manage-site-contents');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $content->update($data + ['updated_by' => $request->user()->id]);
        ActivityLog::record('updated', "Updated the drivers' dashboard section \"{$content->title}\"", $content);

        return redirect(route('site-contents.index').'#section-'.$content->key)->with('status', "\"{$content->title}\" saved.");
    }
}
