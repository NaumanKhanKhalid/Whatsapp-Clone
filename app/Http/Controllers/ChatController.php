<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Message;
use App\Events\MessageSent;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function chat(Request $request, $id = null)
    {

        $selectedUser = null;

        $messages = [];

        if ($id) {
            $selectedUser = User::find($id);
            if ($selectedUser) {
                $messages = $this->getMessages($selectedUser)->map(function ($message) {
                    return [
                        'sender_id' => $message->sender_id,
                        'receiver_id' => $message->receiver_id,
                        'message' => $message->message,
                        'created_at' => $message->created_at->diffForHumans(),
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

    protected function getMessages($selectedUser)
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
                ->get();
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
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'message' => $message->message,
                'created_at' => $message->created_at->diffForHumans(),
            ],
        ]);
    }
}
