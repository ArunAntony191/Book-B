<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Message;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $user1 = (int) $request->query('user1');
        $user2 = (int) $request->query('user2');

        if (!$user1 || !$user2) {
            return response()->json(['error' => 'Missing user parameters'], 400);
        }

        // Mark messages FROM user2 TO user1 as read
        Message::where('sender_id', $user2)
               ->where('receiver_id', $user1)
               ->where('is_read', 0)
               ->update(['is_read' => 1]);

        $messages = Message::where(function($q) use ($user1, $user2) {
            $q->where('sender_id', $user1)->where('receiver_id', $user2);
        })->orWhere(function($q) use ($user1, $user2) {
            $q->where('sender_id', $user2)->where('receiver_id', $user1);
        })->orderBy('created_at', 'asc')->get();

        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sender_id' => 'required|integer',
            'receiver_id' => 'required|integer',
            'message' => 'required|string',
        ]);

        return Message::create($validated);
    }

    public function contacts($userId)
    {
        // Get unique users who have chatted with this user
        $senders = Message::where('receiver_id', $userId)->pluck('sender_id')->toArray();
        $receivers = Message::where('sender_id', $userId)->pluck('receiver_id')->toArray();
        
        $contacts = array_unique(array_merge($senders, $receivers));
        
        return response()->json(array_values($contacts));
    }

    public function unreadCount($userId)
    {
        $count = Message::where('receiver_id', $userId)->where('is_read', 0)->count();
        return response()->json(['count' => $count]);
    }

    public function unreadBySender($userId)
    {
        // Get unread message counts grouped by sender
        $unread = Message::where('receiver_id', $userId)
            ->where('is_read', 0)
            ->select('sender_id', \DB::raw('count(*) as count'))
            ->groupBy('sender_id')
            ->get();
        
        $result = [];
        foreach ($unread as $item) {
            $result[$item->sender_id] = $item->count;
        }
        
        return response()->json($result);
    }
}
