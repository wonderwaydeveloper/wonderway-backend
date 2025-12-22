<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserList;
use Illuminate\Http\Request;

class ListController extends Controller
{
    public function index(Request $request)
    {
        $lists = UserList::where('user_id', $request->user()->id)
            ->withCount(['members', 'subscribers'])
            ->latest()
            ->paginate(20);

        return response()->json($lists);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'privacy' => 'required|in:public,private',
        ]);

        $list = UserList::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'description' => $request->description,
            'privacy' => $request->privacy,
        ]);

        return response()->json($list, 201);
    }

    public function show(UserList $list)
    {
        if ($list->privacy === 'private' && $list->user_id !== auth()->id()) {
            return response()->json(['message' => 'List not found'], 404);
        }

        $list->load(['owner:id,name,username,avatar', 'members:id,name,username,avatar'])
             ->loadCount(['members', 'subscribers']);

        return response()->json($list);
    }

    public function update(Request $request, UserList $list)
    {
        $this->authorize('update', $list);

        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'privacy' => 'required|in:public,private',
        ]);

        $list->update($request->only(['name', 'description', 'privacy']));

        return response()->json($list);
    }

    public function destroy(UserList $list)
    {
        $this->authorize('delete', $list);

        $list->delete();

        return response()->json(['message' => 'List deleted successfully']);
    }

    public function addMember(Request $request, UserList $list)
    {
        $this->authorize('update', $list);

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        if ($list->hasMember($request->user_id)) {
            return response()->json(['message' => 'User already in list'], 409);
        }

        $list->members()->attach($request->user_id);
        $list->increment('members_count');

        return response()->json(['message' => 'Member added successfully']);
    }

    public function removeMember(Request $request, UserList $list, User $user)
    {
        $this->authorize('update', $list);

        if (! $list->hasMember($user->id)) {
            return response()->json(['message' => 'User not in list'], 404);
        }

        $list->members()->detach($user->id);
        $list->decrement('members_count');

        return response()->json(['message' => 'Member removed successfully']);
    }

    public function subscribe(Request $request, UserList $list)
    {
        if ($list->privacy === 'private') {
            return response()->json(['message' => 'Cannot subscribe to private list'], 403);
        }

        $userId = $request->user()->id;

        if ($list->isSubscribedBy($userId)) {
            return response()->json(['message' => 'Already subscribed'], 409);
        }

        $list->subscribers()->attach($userId);
        $list->increment('subscribers_count');

        return response()->json(['message' => 'Subscribed successfully']);
    }

    public function unsubscribe(Request $request, UserList $list)
    {
        $userId = $request->user()->id;

        if (! $list->isSubscribedBy($userId)) {
            return response()->json(['message' => 'Not subscribed'], 404);
        }

        $list->subscribers()->detach($userId);
        $list->decrement('subscribers_count');

        return response()->json(['message' => 'Unsubscribed successfully']);
    }

    public function posts(UserList $list)
    {
        if ($list->privacy === 'private' && $list->user_id !== auth()->id()) {
            return response()->json(['message' => 'List not found'], 404);
        }

        $posts = $list->posts()
            ->with(['user:id,name,username,avatar', 'hashtags:id,name,slug'])
            ->withCount(['likes', 'comments', 'quotes'])
            ->paginate(20);

        return response()->json($posts);
    }

    public function discover(Request $request)
    {
        $lists = UserList::public()
            ->where('user_id', '!=', $request->user()->id)
            ->withCount(['members', 'subscribers'])
            ->orderBy('subscribers_count', 'desc')
            ->paginate(20);

        return response()->json($lists);
    }
}
