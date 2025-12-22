<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Models\SpaceParticipant;
use Illuminate\Http\Request;

class SpaceController extends Controller
{
    public function index(Request $request)
    {
        $spaces = Space::live()
            ->public()
            ->with(['host:id,name,username,avatar', 'participants.user:id,name,username,avatar'])
            ->withCount('activeParticipants')
            ->orderBy('current_participants', 'desc')
            ->paginate(20);

        return response()->json($spaces);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'privacy' => 'required|in:public,followers,invited',
            'max_participants' => 'nullable|integer|min:2|max:50',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $space = Space::create([
            'host_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'privacy' => $request->privacy,
            'max_participants' => $request->max_participants ?? 10,
            'status' => $request->scheduled_at ? 'scheduled' : 'live',
            'scheduled_at' => $request->scheduled_at,
            'started_at' => $request->scheduled_at ? null : now(),
            'settings' => [
                'recording_enabled' => $request->boolean('recording_enabled', false),
                'chat_enabled' => $request->boolean('chat_enabled', true),
            ],
        ]);

        // Add host as participant
        SpaceParticipant::create([
            'space_id' => $space->id,
            'user_id' => $request->user()->id,
            'role' => 'host',
            'status' => 'joined',
            'joined_at' => now(),
        ]);

        $space->increment('current_participants');
        $space->load('host:id,name,username,avatar');

        return response()->json($space, 201);
    }

    public function show(Space $space)
    {
        $space->load([
            'host:id,name,username,avatar',
            'participants.user:id,name,username,avatar',
        ])->loadCount('activeParticipants');

        return response()->json($space);
    }

    public function join(Request $request, Space $space)
    {
        $userId = $request->user()->id;

        if (! $space->canJoin($userId)) {
            return response()->json(['message' => 'Cannot join this space'], 403);
        }

        if ($space->current_participants >= $space->max_participants) {
            return response()->json(['message' => 'Space is full'], 403);
        }

        $participant = SpaceParticipant::updateOrCreate(
            ['space_id' => $space->id, 'user_id' => $userId],
            [
                'status' => 'joined',
                'joined_at' => now(),
                'left_at' => null,
            ]
        );

        if ($participant->wasRecentlyCreated) {
            $space->increment('current_participants');
        }

        broadcast(new \App\Events\SpaceParticipantJoined($space, $request->user()));

        return response()->json(['message' => 'Joined space successfully']);
    }

    public function leave(Request $request, Space $space)
    {
        $participant = SpaceParticipant::where('space_id', $space->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $participant) {
            return response()->json(['message' => 'Not in this space'], 404);
        }

        $participant->update([
            'status' => 'left',
            'left_at' => now(),
        ]);

        $space->decrement('current_participants');

        broadcast(new \App\Events\SpaceParticipantLeft($space, $request->user()));

        return response()->json(['message' => 'Left space successfully']);
    }

    public function updateRole(Request $request, Space $space, SpaceParticipant $participant)
    {
        $this->authorize('update', $space);

        $request->validate([
            'role' => 'required|in:co_host,speaker,listener',
        ]);

        $participant->update(['role' => $request->role]);

        broadcast(new \App\Events\SpaceParticipantRoleChanged($space, $participant));

        return response()->json(['message' => 'Role updated successfully']);
    }

    public function end(Request $request, Space $space)
    {
        $this->authorize('update', $space);

        $space->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        broadcast(new \App\Events\SpaceEnded($space));

        return response()->json(['message' => 'Space ended successfully']);
    }
}
