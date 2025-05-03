<div>
    <div class="max-w-6xl mx-auto my-16">

        <h5 class="text-center text-5xl font-bold py-3">Users</h5>
    
    
    
        <div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 p-3 ">
    
            {{-- child --}}
            @foreach($users as $user)
            <div class="w-full bg-white border border-gray-200 rounded-lg p-5 pb-10 shadow">

                <div class="flex flex-col items-center pb-6">
                    <h5 class="mb-1 text-xl font-medium text-gray-900">
                        {{$user->name}}
                    </h5>
                    <span class="text-sm text-gray-500">{{$user->email}}</span>
            
                    <div class="flex mt-4 space-x-4 md:mt-6">
                        <x-primary-button class="mb-2" wire:click="message({{$user->id}})">
                            Message
                        </x-primary-button>
                    </div>
                </div>
            
            </div>
            @endforeach
        </div>
    </div>
</div>
