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
    public $editingMessageId = null;



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
    
        if ($this->editingMessageId) {
            $message = Message::findOrFail($this->editingMessageId);
    
            if ($message->sender_id !== auth()->id()) {
                abort(403);
            }
    
            $message->update(['body' => $this->body]);
            return redirect(request()->header('Referer'));//reload after editing, which was the easiest way to update
            $this->editingMessageId = null;
        } else {
            $isSpam = ChatController::predictSpam($this->body,0.5);
    
            $createdMessage = Message::create([
                'conversation_id' => $this->selectedConversation->id,
                'sender_id' => auth()->id(),
                'receiver_id' => $this->selectedConversation->getReceiver()->id,
                'body' => $this->body,
                'is_spam' => $isSpam
            ]);
    
            broadcast(new MessageSendEvent($createdMessage))->toOthers();
            $this->loadedMessages->push($createdMessage);
            $this->selectedConversation->updated_at = now();
            $this->selectedConversation->save();
        }
    
        $this->reset('body', 'editingMessageId');
    
        $this->js(<<<'JS'
            window.dispatchEvent(new CustomEvent('scroll-bottom'));
        JS);
    }
    
    public function deleteMessage($messageId){
        Message::findOrFail($messageId)->delete();
        //reload the page so the message is no longer visible
        return redirect(request()->header('Referer'));
    }

    public function editMessage($messageId)
    {
        $message = Message::findOrFail($messageId);
        $this->body = $message->body;
        $this->editingMessageId = $message->id;
    }
    public function reportMessage($messageId){
        $checkAgain=Message::findOrFail($messageId);

        // dd($checkAgain->body);
        $isSpam = ChatController::predictSpam($checkAgain->body,0.35);//check again with a lowered threshold
        if($isSpam){
            ChatController::addSpamMessage($checkAgain->body);
        }
        return redirect(request()->header('Referer'));

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
