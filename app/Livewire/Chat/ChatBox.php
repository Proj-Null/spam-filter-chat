<?php

namespace App\Livewire\Chat;

use App\Models\Message;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Events\MessageSendEvent;
use App\Events\MessageReadEvent;
use Illuminate\Support\Collection;
use App\Http\Controllers\ChatController;


class ChatBox extends Component
{
    public $selectedConversation;
    public $body;
    public $loadedMessages;
    public $authId;
    protected $listeners = ['refreshComponent' => '$refresh'];


    public function loadMessages()
    {
        if (!$this->selectedConversation) {
            $this->loadedMessages = collect();
            return $this->loadedMessages;
        }
    
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
        $isSpam = ChatController::predictSpam($this->body);
        $createdMessage = Message::create([
            'conversation_id' => $this->selectedConversation->id,
            'sender_id' => auth()->id(),
            'receiver_id' => $this->selectedConversation->getReceiver()->id,
            'body' => $this->body,
            'is_spam'=>$isSpam
        ]);

            broadcast(new MessageSendEvent($createdMessage))->toOthers();
            $this->reset('body');
            $this->js(<<<'JS'
            window.dispatchEvent(new CustomEvent('scroll-bottom'));
            JS);
            $this->loadedMessages->push($createdMessage);
            $this->selectedConversation->updated_at = now();
            $this->selectedConversation->save();
    }

    #[On('echo-private:chat-channel.{authId},MessageSendEvent')]
    public function listenForMessage($event)
    {
        $message = Message::find($event['message']['id']);
        if ($message && $message->conversation_id === $this->selectedConversation->id) {
            $this->loadedMessages->push($message);
            $this->js(<<<'JS'
                window.dispatchEvent(new CustomEvent('scroll-bottom'));
            JS);
            $message->read_at=now();
            $message->save();
            broadcast(new MessageReadEvent($message))->toOthers();
        }
    }

    #[On('echo-private:chat-channel.{authId},MessageReadEvent')]
    public function listenRead($event)
    {
        // dd($event);
        // $this->emit('refreshComponent');
        $this->js(<<<'JS'
    window.dispatchEvent(new CustomEvent('mark-read'));
JS);

    }
    

    public function mount()
    {
        $this->loadMessages();
        $this->authId = auth()->id();
    }
    

    public function render()
    {
        return view('livewire.chat.chat-box');
    }
}
