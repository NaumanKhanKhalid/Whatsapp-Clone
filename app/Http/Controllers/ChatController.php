<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Message;
use App\Events\MessageRead;

use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Events\MessageDelivered;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{

    public function getAllUsers()
{
    $users = User::where('id', '!=', Auth::user()->id)->get();

    $users->map(function ($user) {
        $lastMessage = $user->lastMessage();
        
        // Set the message content
        $user->last_message = $lastMessage ? $lastMessage->message : null;
        
        // Set the last message time with custom formatting
        $user->last_message_time = $lastMessage ? 
            ($lastMessage->created_at->isToday() ? $lastMessage->created_at->format('h:i A') : 
            ($lastMessage->created_at->isYesterday() ? 'Yesterday ' . $lastMessage->created_at->format('h:i A') : 
            $lastMessage->created_at->format('Y-m-d'))) 
            : null;
        
        return $user;
    });

    return response()->json($users);
}

public function chat(Request $request, $id = null)
{
    $selectedUser = null;
    $messages = [];

    if ($id) {
        $selectedUser = User::find($id);
        if ($selectedUser) {
            $messages = $this->getMessages($selectedUser, $request->query('page', 1), 10)->map(function ($message) {
                // Format the 'created_at' timestamp based on custom logic
                $formattedCreatedAt = $message->created_at ?
                    ($message->created_at->isToday() ? $message->created_at->format('h:i A') :
                    ($message->created_at->isYesterday() ? 'Yesterday ' . $message->created_at->format('h:i A') :
                    $message->created_at->format('Y-m-d'))) : null;

                return [
                    'sender_id' => $message->sender_id,
                    'receiver_id' => $message->receiver_id,
                    'message' => $message->message,
                    'message_id' => $message->id,
                    'read_at' => $message->read_at,
                    'delivered_at' => $message->delivered_at,
                    'created_at' => $formattedCreatedAt, // Use formatted timestamp here
                ];
            });
        }
    }

    if ($request->ajax()) {
        return response()->json([
            'selectedUser' => $selectedUser,
            'messages' => $messages,
        ]);
    }

    return view('chat', compact('selectedUser', 'messages'));
}


    protected function getMessages($selectedUser, $page = 1, $perPage = 10)
    {
        if ($selectedUser) {
            return Message::where(function ($query) use ($selectedUser) {
                $query->where('sender_id', Auth::id())
                    ->where('receiver_id', $selectedUser->id);
            })
                ->orWhere(function ($query) use ($selectedUser) {
                    $query->where('sender_id', $selectedUser->id)
                        ->where('receiver_id', Auth::id());
                })
                ->orderBy('created_at', 'desc') // Order by most recent first
                ->paginate($perPage, ['*'], 'page', $page);
        }

        return collect();
    }


    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'receiver_id' => 'required|exists:users,id',
        ]);
        $message = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
        ]);

        event(new MessageSent($message));


        return response()->json([
            'success' => true,
            'message' => [
                'message_id' => $message->id,
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'message' => $message->message,
                'created_at' => $message->created_at->diffForHumans(),
            ],
        ]);
    }

    public function updateLastSeen($id)
    {
        $user = User::find($id);
        if ($user) {
            $user->last_seen = Carbon::now();
            $user->save();
            return response()->json(['message' => 'Last seen updated successfully.']);
        }
        return response()->json(['error' => 'User not found.'], 404);
    }

    public function markAsDelivered(Request $request)
    {

        $message_id = $request->messageId;
        $message = Message::findOrFail($message_id);
        $message->delivered_at = now();
        $message->save();

        event(new MessageDelivered($message));
        return response()->json(['status' => 'delivered']);
    }


    public function markAsRead(Request $request)
    {
        $message_id = $request->messageId;
        $message = Message::findOrFail($message_id);
        $message->read_at = now();
        $message->save();

        event(new MessageRead($message));
        return response()->json(['status' => 'read']);
    }
}
