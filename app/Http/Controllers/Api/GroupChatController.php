<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupConversation;
use App\Models\GroupMessage;
use Illuminate\Http\Request;

class GroupChatController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'member_ids' => 'required|array|min:1|max:49',
            'member_ids.*' => 'exists:users,id',
        ]);

        $group = GroupConversation::create([
            'name' => $request->name,
            'created_by' => $request->user()->id,
        ]);

        $members = array_unique(array_merge([$request->user()->id], $request->member_ids));

        foreach ($members as $memberId) {
            $group->members()->attach($memberId, [
                'is_admin' => $memberId === $request->user()->id,
            ]);
        }

        $group->load('members');

        return response()->json($group, 201);
    }

    public function addMember(Request $request, GroupConversation $group)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        if (! $group->members()->where('user_id', $request->user()->id)->wherePivot('is_admin', true)->exists()) {
            return response()->json(['message' => 'Only admins can add members'], 403);
        }

        if ($group->members()->count() >= 50) {
            return response()->json(['message' => 'Group is full (max 50 members)'], 400);
        }

        $group->members()->attach($request->user_id);

        return response()->json(['message' => 'Member added successfully']);
    }

    public function removeMember(Request $request, GroupConversation $group, $userId)
    {
        if (! $group->members()->where('user_id', $request->user()->id)->wherePivot('is_admin', true)->exists()) {
            return response()->json(['message' => 'Only admins can remove members'], 403);
        }

        $group->members()->detach($userId);

        return response()->json(['message' => 'Member removed successfully']);
    }

    public function update(Request $request, GroupConversation $group)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'avatar' => 'sometimes|image|max:2048',
        ]);

        if (! $group->members()->where('user_id', $request->user()->id)->wherePivot('is_admin', true)->exists()) {
            return response()->json(['message' => 'Only admins can update group'], 403);
        }

        $data = $request->only('name');

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('groups', 'public');
        }

        $group->update($data);

        return response()->json($group);
    }

    public function sendMessage(Request $request, GroupConversation $group)
    {
        $request->validate([
            'content' => 'nullable|string|max:1000',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,gif,mp4,mov|max:10240',
        ]);

        if (! $group->members()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'You are not a member of this group'], 403);
        }

        if (! $request->content && ! $request->hasFile('media')) {
            return response()->json(['message' => 'Content or media is required'], 400);
        }

        $data = [
            'group_conversation_id' => $group->id,
            'sender_id' => $request->user()->id,
            'content' => $request->content,
        ];

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $extension = $file->getClientOriginalExtension();
            $mediaType = in_array($extension, ['mp4', 'mov']) ? 'video' : 'image';

            $data['media_path'] = $file->store('group_messages', 'public');
            $data['media_type'] = $mediaType;
        }

        $message = GroupMessage::create($data);
        $group->update(['last_message_at' => now()]);

        $message->load('sender:id,name,username,avatar');

        return response()->json($message, 201);
    }

    public function messages(GroupConversation $group)
    {
        if (! $group->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'You are not a member of this group'], 403);
        }

        $messages = $group->messages()
            ->with('sender:id,name,username,avatar')
            ->latest()
            ->paginate(50);

        return response()->json($messages);
    }

    public function myGroups(Request $request)
    {
        $groups = GroupConversation::whereHas('members', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })
        ->withCount('members')
        ->orderBy('last_message_at', 'desc')
        ->paginate(20);

        return response()->json($groups);
    }
}
