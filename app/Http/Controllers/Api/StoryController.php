<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $followingIds = $user->following()->pluck('users.id')->push($user->id);

        $stories = Story::active()
            ->whereIn('user_id', $followingIds)
            ->with('user:id,name,username,avatar')
            ->latest()
            ->get()
            ->groupBy('user_id');

        return response()->json($stories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'media' => 'required|file|mimes:jpeg,png,jpg,mp4|max:10240',
            'caption' => 'nullable|string|max:280',
        ]);

        $mediaType = $request->file('media')->getMimeType();
        $mediaType = str_starts_with($mediaType, 'video') ? 'video' : 'image';

        $mediaUrl = $request->file('media')->store('stories', 'public');

        $story = Story::create([
            'user_id' => $request->user()->id,
            'media_type' => $mediaType,
            'media_url' => $mediaUrl,
            'caption' => $request->caption,
            'expires_at' => now()->addHours(24),
        ]);

        return response()->json($story, 201);
    }

    public function destroy(Story $story)
    {
        if ($story->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        Storage::disk('public')->delete($story->media_url);
        $story->delete();

        return response()->json(['message' => 'استوری حذف شد']);
    }

    public function view(Story $story)
    {
        $story->increment('views_count');

        return response()->json(['message' => 'مشاهده شد']);
    }
}
