<?php

namespace App\Livewire\Chat;

use App\Models\Message;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Events\MessageSendEvent;

class ChatBox extends Component
{
    public $selectedConversation;
    public $body;
    public $loadedMessages;
    public $authId;
    public function loadMessages()
    {

        $userId = auth()->id();
        $this->loadedMessages = Message::where('conversation_id', $this->selectedConversation->id)
        ->where(function ($query) use ($userId) {
            $query->where('sender_id', $userId)
                  ->orWhere('receiver_id', $userId);
        })
        ->get();
        return $this->loadedMessages;
    }
    public function sendMessage()
    {
        $this->validate(['body' => 'required|string']);
        $createdMessage = Message::create([
            'conversation_id' => $this->selectedConversation->id,
            'sender_id' => auth()->id(),
            'receiver_id' => $this->selectedConversation->getReceiver()->id,
            'body' => $this->body

        ]);
        broadcast(new MessageSendEvent($createdMessage))->toOthers();
        $this->reset('body');
        $this->js(<<<'JS'
    window.dispatchEvent(new CustomEvent('scroll-bottom'));
JS);
        #push the message
        $this->loadedMessages->push($createdMessage);
        #update conversation model
        $this->selectedConversation->updated_at = now();
        $this->selectedConversation->save();
    }
    #[On('echo-private:chat-channel.{authId},MessageSendEvent')]
    public function listenForMessage($event)
    {
        // \Log::info('Event received:', $event); // Debug the event
        $message = Message::find($event['message']['id']);
        if ($message && $message->conversation_id === $this->selectedConversation->id) {
            $this->loadedMessages->push($message);
            $this->js(<<<'JS'
                window.dispatchEvent(new CustomEvent('scroll-bottom'));
            JS);
        }
    }


    public function mount()
    {
        $this->loadMessages();
        $this->authId=auth()->id();
 
    }
    public function render()
    {
        return view('livewire.chat.chat-box');
    }
}
