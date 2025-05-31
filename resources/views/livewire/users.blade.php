<div>
    <div class="max-w-6xl mx-auto my-16">

        <h5 class="text-center text-5xl font-bold py-3">Users</h5>
    
    
    
        <div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 p-3 ">
    
            {{-- child --}}
            @foreach($this->users as $user)
            <div class="relative w-full bg-white border border-gray-200 rounded-lg p-5 pb-10 shadow">
                @if($user->unreadCount>0)
                <span class="absolute top-2 right-2 font-bold p-px px-2 text-xs shrink-0 rounded-full bg-blue-500 text-white">
                    {{$user->unreadCount}}
                </span>
                @endif
                <div class="flex flex-col items-center pb-6">
                    <h5 class="mb-1 text-xl font-medium text-gray-900">
                        {{$user->name}}
                    </h5>
                    <span class="text-sm text-gray-500">{{$user->email}}</span>

                    <div class="flex mt-4 space-x-4 md:mt-6">
                        <x-primary-button class="mb-2" wire:click="message({{$user->id}})" >
                            Message
                        </x-primary-button>
                    </div>
                </div>

            </div>

            @endforeach
        </div>
    </div>
</div>
