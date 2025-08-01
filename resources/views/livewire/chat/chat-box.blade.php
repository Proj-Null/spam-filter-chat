<div 
    x-data="{
        height: 0,
        conversationElement: document.getElementById('conversation'),
        markAsRead: null
    }"
    x-init="
        height = conversationElement.scrollHeight;
        $nextTick(() => conversationElement.scrollTop = height);
    "
    @scroll-bottom.window="
        $nextTick(() => conversationElement.scrollTop = conversationElement.scrollHeight);
    "
    class="w-full overflow-hidden"
>
    <div class="border-b flex flex-col overflow-y-scroll grow h-full">
        <header class="w-full sticky inset-x-0 flex pb-[5px] pt-[5px] top-0 z-10 bg-white border-b">
            <div class="flex w-full items-center px-2 lg:px-4 gap-2 md:gap-5">
                <a class="relative inline-block shrink-0 lg:hidden" href="{{route('chat.index')}}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-list text-gray-700" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
                    </svg>
                    @if($this->hasUnreadMessages)
                    <span class="absolute -top-1 -right-1 bg-blue-600 text-white rounded-full w-4 h-4 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.553.553 0 0 1-1.1 0z"/>
                        </svg>
                    </span>
                    @endif
                </a>
                <div class="shrink-0">
                    <x-avatar class="h-9 w-9 lg:w-11 lg:h-11" />
                </div>
                <h6 class="font-bold truncate">{{ $this->selectedConversation->getReceiver()->name }}</h6>
            </div>
        </header>
        <main id="conversation" class="flex flex-col gap-3 p-2.5 overflow-y-auto flex-grow overscroll-contain overflow-x-hidden w-full my-auto">
            @if ($loadedMessages)
                @php
                    $previousMessage = null;
                @endphp
                @foreach ($loadedMessages as $key => $message)
                    @if ($key > 0)
                        @php
                            $previousMessage = $loadedMessages->get($key - 1);
                        @endphp
                    @endif
                    <div 
                        wire:key="message-{{ $message->id }}"
                        @class([
                            'max-w-[85%] md:max-w-[78%] flex w-auto gap-2 relative mt-2',
                            'ml-auto' => $message->sender_id === auth()->id(),
                        ])
                    >
                        <div @class([
                            'shrink-0',
                            'invisible' => $previousMessage?->sender_id == $message->sender_id,
                            'hidden' => $message->sender_id === auth()->id(),
                        ])>
                            <x-avatar />
                        </div>
                        <div @class([
                            'flex text-[15px] rounded-xl p-2.5 flex-col text-black bg-[#f6f6f8fb]',
                            'rounded-bl-none border border-gray-200/40' => $message->sender_id !== auth()->id(),
                            'rounded-br-none bg-blue-500/80 text-white' => $message->sender_id === auth()->id(),
                        ])>
                            <div class="flex items-start gap-2">
                                @if ($message->sender_id === auth()->id())
                                    <!-- Three dots dropdown for authenticated user (left side) -->
                                    <div class="relative" x-data="{ open: false }">
                                        <button @click="open = !open" class="p-1 rounded-full hover:bg-gray-200/50" aria-label="Message options">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-three-dots-vertical" viewBox="0 0 16 16">
                                                <path d="M9.5 13a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/>
                                            </svg>
                                        </button>
                                        <div x-show="open" 
                                             @click.away="open = false"
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="transform opacity-0 scale-95"
                                             x-transition:enter-end="transform opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="transform opacity-100 scale-100"
                                             x-transition:leave-end="transform opacity-0 scale-95"
                                             class="absolute z-10 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 left-0"
                                        >
                                            <div class="py-1" role="menu" aria-orientation="vertical">
                                                <button wire:click="deleteMessage({{ $message->id }})" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left" role="menuitem" aria-label="Delete this message">
                                                    Delete
                                                </button>
                                                <button wire:click="editMessage({{ $message->id }})" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left" role="menuitem" aria-label="Edit this message">
                                                    Edit
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <p class="whitespace-normal truncate text-sm md:text-base tracking-wide lg:tracking-normal flex-grow">
                                    {{ $message->body }}
                                </p>
                                @if ($message->sender_id !== auth()->id())
                                    <!-- Three dots dropdown for other users (right side) -->
                                    <div class="relative" x-data="{ open: false }">
                                        <button @click="open = !open" class="p-1 rounded-full hover:bg-gray-200/50" aria-label="Message options">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-three-dots-vertical" viewBox="0 0 16 16">
                                                <path d="M9.5 13a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/>
                                            </svg>
                                        </button>
                                        <div x-show="open" 
                                             @click.away="open = false"
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="transform opacity-0 scale-95"
                                             x-transition:enter-end="transform opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="transform opacity-100 scale-100"
                                             x-transition:leave-end="transform opacity-0 scale-95"
                                             class="absolute z-10 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 right-0"
                                        >
                                            <div class="py-1" role="menu" aria-orientation="vertical">
                                                <button wire:click="reportMessage({{ $message->id }})" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left" role="menuitem" aria-label="{{ $message->is_spam ? 'Unmark as spam' : 'Report as spam' }}">
                                                    Report spam
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            @if ($message->sender_id != auth()->id() && $message->is_spam)
                                <div class="flex items-center mt-1 text-xs italic">
                                    <small class="text-gray-900">
                                        This message has been flagged as potential spam
                                    </small>
                                </div>
                            @endif
                            <div class="ml-auto flex gap-2">
                                <p @class([
                                    'text-xs',
                                    'text-gray-500' => $message->sender_id !== auth()->id(),
                                    'text-white' => $message->sender_id === auth()->id(),
                                ])>
                                    {{ $message->created_at->format('g:i a') }}
                                </p>
                                @if ($message->sender_id === auth()->id())
                                    <span x-data="{ read: @js($message->read_at !== null) }"
                                          x-init="window.addEventListener('mark-read', () => { read = true })">
                                        <template x-if="read">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check2-all" viewBox="0 0 16 16">
                                                <path d="M12.354 4.354a.5.5 0 0 0-.708-.708L5 10.293 1.854 7.146a.5.5 0 1 0-.708.708l3.5 3.5a.5.5 0 0 0 .708 0l7-7zm-4.208 7-.896-.897.707-.707.543.543 6.646-6.647a.5.5 0 0 1 .708.708l-7 7a.5.5 0 0 1-.708 0z"/>
                                                <path d="m5.354 7.146.896.897-.707.707-.897-.896a.5.5 0 1 1 .708-.708z"/>
                                            </svg>
                                        </template>
                                        <template x-if="!read">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check2" viewBox="0 0 16 16">
                                                <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
                                            </svg>
                                        </template>
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <p class="text-center text-gray-500">No messages yet.</p>
            @endif
        </main>
        <footer class="shrink-0 z-10 bg-white inset-x-0">
            <div class="p-2 border-t">
                <form
                    x-data="{ body: @entangle('body') }"
                    @submit.prevent="$wire.sendMessage().then(() => body = '')"
                    method="POST"
                    autocapitalize="off"
                >
                    @csrf
                    <input type="hidden" autocomplete="false" style="display:none">
                    <div class="grid grid-cols-12">
                        <input 
                            x-model="body"
                            type="text"
                            autocomplete="off"
                            autofocus
                            placeholder="write your message here"
                            maxlength="1700"
                            class="col-span-11 bg-gray-100 border-0 outline-0 focus:border-0 focus:ring-0 hover:ring-0 rounded-lg focus:outline-none"
                        >
                        <button class="col-span-1" type="submit" aria-label="Send message">
                            <span class="flex justify-center items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-send" viewBox="0 0 16 16">
                                    <path d="M15.854.146a.5.5 0 0 1 .11.54l-5.819 14.547a.75.75 0 0 1-1.329.124l-3.178-4.995L.643 7.184a.75.75 0 0 1 .124-1.33L15.314.037a.5.5 0 0 1 .54.11ZM6.636 10.07l2.761 4.338L14.13 2.576zm6.787-8.201L1.591 6.602l4.339 2.76z"/>
                                </svg>
                            </span>
                        </button>
                    </div>
                </form>
                @error('body')
                    <p>{{ $message }}</p>
                @enderror
            </div>
        </footer>
    </div>
</div>
