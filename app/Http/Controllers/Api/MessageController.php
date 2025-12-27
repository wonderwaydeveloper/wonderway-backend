<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function conversations(Request $request)
    {
        $userId = $request->user()->id;

        $conversations = Conversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with(['userOne:id,name,username,avatar', 'userTwo:id,name,username,avatar', 'lastMessage'])
            ->orderBy('last_message_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => ConversationResource::collection($conversations)
        ]);
    }

    public function messages(Request $request, User $user)
    {
        $currentUser = $request->user();

        $conversation = Conversation::between($currentUser->id, $user->id);

        if (! $conversation) {
            return response()->json(['messages' => []]);
        }

        $messages = $conversation->messages()
            ->with('sender:id,name,username,avatar')
            ->latest()
            ->paginate(50);

        $conversation->messages()
            ->where('sender_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);

        return MessageResource::collection($messages);
    }

    public function send(SendMessageRequest $request, User $user)
    {
        try {
            $validated = $request->validated();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 400);
        }
        
        $currentUser = $request->user();

        if ($currentUser->id === $user->id) {
            return response()->json(['message' => 'Cannot send message to yourself'], 400);
        }

        $conversation = Conversation::between($currentUser->id, $user->id);

        if (! $conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $currentUser->id,
                'user_two_id' => $user->id,
                'last_message_at' => now(),
            ]);
        }

        $data = [
            'conversation_id' => $conversation->id,
            'sender_id' => $currentUser->id,
            'content' => $validated['content'] ?? null,
        ];

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $extension = $file->getClientOriginalExtension();
            $mediaType = in_array($extension, ['mp4', 'mov']) ? 'video' : 'image';

            $data['media_path'] = $file->store('messages', 'public');
            $data['media_type'] = $mediaType;
        }

        $message = Message::create($data);
        $conversation->update(['last_message_at' => now()]);
        $message->load('sender:id,name,username,avatar');

        broadcast(new MessageSent($message));

        return new MessageResource($message);
    }

    public function typing(Request $request, User $user)
    {
        $request->validate([
            'is_typing' => 'required|boolean',
        ]);

        $currentUser = $request->user();
        $conversation = Conversation::between($currentUser->id, $user->id);

        if ($conversation) {
            broadcast(new UserTyping(
                $conversation->id,
                $currentUser->id,
                $currentUser->name,
                $request->is_typing
            ));
        }

        return response()->json(['status' => 'sent']);
    }

    public function markAsRead(Message $message)
    {
        if ($message->sender_id === auth()->id()) {
            return response()->json(['message' => 'This message is from you'], 400);
        }

        $message->markAsRead();

        return response()->json(['message' => 'Marked as read']);
    }

    public function unreadCount(Request $request)
    {
        $userId = $request->user()->id;

        $count = Message::whereHas('conversation', function ($query) use ($userId) {
            $query->where('user_one_id', $userId)
                  ->orWhere('user_two_id', $userId);
        })
        ->where('sender_id', '!=', $userId)
        ->unread()
        ->count();

        return response()->json(['count' => $count]);
    }
}
