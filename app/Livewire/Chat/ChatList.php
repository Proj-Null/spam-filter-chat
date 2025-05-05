<?php

namespace App\Livewire\Chat;

use App\Models\Conversation;
use App\Models\Message;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Events\MessageSendEvent;

class ChatList extends Component
{
    public $selectedConversation;
    public $query;
    public $authId;
    public $conversations;
    public $mark=false;
    public function loadConversations()
    {
        $user = auth()->user();
        $this->conversations = $user->conversations()->latest('updated_at')->get();
    }
    #[On('echo-private:chat-channel.{authId},MessageSendEvent')]
    public function listenForMessage($event)
    {
        $this->loadConversations();
        $message = Message::find($event['message']['id']);
        if ($message && $message->conversation_id === $this->selectedConversation->id) {
            $this->mark=true;
        }
    }
    #[On('echo-private:chat-channel.{authId},MessageReadEvent')]
    public function listenRead($event)
    {


    }
    public function mount()
    {
        $this->authId = auth()->id();
        $this->loadConversations();
    }

    public function render()
    {
        return view('livewire.chat.chat-list', [
            'conversations' => $this->conversations
        ]);
    }
}