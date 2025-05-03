<?php

namespace App\Livewire\Chat;

use App\Models\Message;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Events\MessageSendEvent;
use App\Events\MessageReadEvent;
use Illuminate\Support\Collection;

class ChatBox extends Component
{
    public $selectedConversation;
    public $body;
    public $loadedMessages;
    public $authId;

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

        // Mark unread messages as read in bulk
        $unreadMessages = $this->loadedMessages
            ->where('receiver_id', $userId)
            ->whereNull('read_at');

        if ($unreadMessages->isNotEmpty()) {
            $unreadMessageIds = $unreadMessages->pluck('id')->toArray();
            Message::whereIn('id', $unreadMessageIds)
                ->update(['read_at' => now()]);

            // Update loadedMessages to reflect read_at changes
            $this->loadedMessages = $this->loadedMessages->map(function ($message) use ($unreadMessageIds) {
                if (in_array($message->id, $unreadMessageIds)) {
                    $message->read_at = now();
                }
                return $message;
            });

            // Broadcast a single event with updated message IDs
            broadcast(new MessageReadEvent($unreadMessages))->toOthers();
        }

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
        $this->loadedMessages->push($createdMessage);
        $this->selectedConversation->updated_at = now();
        $this->selectedConversation->save();
    }

    #[On('echo-private:chat-channel.{selectedConversation.id},MessageSendEvent')]
    public function listenForMessage($event)
    {
        \Log::info('MessageSendEvent received:', $event);
        $message = Message::find($event['message']['id']);
        if ($message && $message->conversation_id === $this->selectedConversation->id) {
            $this->loadedMessages->push($message);
            $this->js(<<<'JS'
                window.dispatchEvent(new CustomEvent('scroll-bottom'));
            JS);
        }
    }

    #[On('echo-private:chat-channel.{selectedConversation.id},MessageReadEvent')]
    public function listenForReadReceipt($event)
    {
        \Log::info('MessageReadEvent received:', $event);
        $messageIds = $event['message_ids'] ?? [];
        if (!empty($messageIds)) {
            $this->loadedMessages = $this->loadedMessages->map(function ($message) use ($messageIds) {
                if (in_array($message->id, $messageIds)) {
                    $message->read_at = now();
                }
                return $message;
            });
        }
    }

    public function mount()
    {
        $this->authId = auth()->id();
        $this->loadedMessages = collect();
        $this->loadMessages();
    }

    public function render()
    {
        return view('livewire.chat.chat-box');
    }
}