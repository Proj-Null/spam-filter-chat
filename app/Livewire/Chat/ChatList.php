<?php

namespace App\Livewire\Chat;

use App\Models\Conversation;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Events\MessageSendEvent;

class ChatList extends Component
{
    public $selectedConversation;
    public $query;
    public $authId;
    public $conversations;

    public function loadConversations()
    {
        $user = auth()->user();
        $this->conversations = $user->conversations()->latest('updated_at')->get();
    }

    #[On('echo-private:chat-channel.{authId},MessageSendEvent')]
    public function listenForMessage($event)
    {
        // \Log::info('MessageSendEvent received in ChatList:', $event);
        $this->loadConversations();
        $this->dispatch('new-message-received');
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