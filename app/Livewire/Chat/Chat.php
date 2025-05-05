<?php

namespace App\Livewire\Chat;

use App\Models\Conversation;
use App\Models\Message;
use Livewire\Component;
use App\Events\MessageReadEvent;


class Chat extends Component
{

    public $query;
    public $selectedConversation;
    public $lastmessage;

    public function mount()
    {

        $this->selectedConversation= Conversation::findOrFail($this->query);
       Message::where('conversation_id',$this->selectedConversation->id)
                ->where('receiver_id',auth()->id())
                ->whereNull('read_at')
                ->update(['read_at'=>now()]);
        $this->lastmessage = Message::where('conversation_id', $this->selectedConversation->id)
                ->where('receiver_id', auth()->id())
                ->orderByDesc('id') // Sort by ID in descending order
                ->first(); // Get the message with the largest ID                       
        broadcast(new MessageReadEvent($this->lastmessage))->toOthers();
    }
    public function render()
    {
        return view('livewire.chat.chat');
    }
}
