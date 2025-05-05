<?php

namespace App\Http\Middleware;

use App\Models\Conversation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeConversation
{
    public function handle(Request $request, Closure $next): Response
    {
        // Get the conversation from the route parameter
        $conv = $request->route('query');
        // dd($conversation);

        // If the parameter is an ID, fetch the Conversation model
        $conversation = Conversation::findOrFail($conv);
        // dd($conversation);

        // Check if the authenticated user is the sender or receiver
        // dd($conversation->receiver_id );
        if (auth()->id() != $conversation->sender_id && auth()->id() != $conversation->receiver_id) {
            return redirect()->route('chat.index');
        }

        // Attach the conversation to the request for use in the Livewire component
        $request->merge(['query' => $conversation]);

        return $next($request);
    }
}