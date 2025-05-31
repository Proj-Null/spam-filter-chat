<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Conversation;
use App\Models\User;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;

class Users extends Component
{
    public $authId;
    public $usersList;
    public function mount(){
        $this->authId=auth()->id();
        $this->loadUsers();
    }
    public function loadUsers(){
        $users = User::where('id', '!=', auth()->id())->get();
        $users->each(function ($user) {
            $convo = Conversation::where(function ($q) use ($user) {
                $q->where('sender_id', auth()->id())
                ->where('receiver_id', $user->id);
            })->orWhere(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                ->where('receiver_id', auth()->id());
            })->first();

            if ($convo) {
                $unreadCount = $convo->getOrMarkUnreadMessages(false);
            } else {
                $unreadCount = 0;
            }

            $user->unreadCount = $unreadCount;
        });

        $this->users = $users;
    }
    #[On('echo-private:chat-channel.{authId},MessageSendEvent')]
    public function listenForUpdate($event)
    {
        Log::info("message is actually received and userslist found out");
        $this->loadUsers();
    }
    public function render()
    {
        return view('livewire.users');

    }
    public function message($userId){
        $authenticatedUserId=auth()->id();
        $existingConversation = Conversation::where(function ($query) use ($authenticatedUserId, $userId) {
            $query->where('sender_id', $authenticatedUserId)
                ->where('receiver_id', $userId);
            })
        ->orWhere(function ($query) use ($authenticatedUserId, $userId) {
            $query->where('sender_id', $userId)
                ->where('receiver_id', $authenticatedUserId);
        })->first();
        if ($existingConversation) {
            return redirect()->route('chat', ['query' => $existingConversation->id]);
        }
            $createdConversation = Conversation::create([
            'sender_id' => $authenticatedUserId,
            'receiver_id' => $userId,
        ]);
   
          return redirect()->route('chat', ['query' => $createdConversation->id]);  
    }
    
}
 