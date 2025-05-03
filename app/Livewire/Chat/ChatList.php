<?php

namespace App\Livewire\Chat;

use App\Models\Conversation;
use Livewire\Component;

class ChatList extends Component
{
    public $selectedConversation;
    public $query;
    public function render()
    {

        $user= auth()->user();
                return view('livewire.chat.chat-list',[
            'conversations'=>$user->conversations()->latest('updated_at')->get()
        ]);
    }
}
