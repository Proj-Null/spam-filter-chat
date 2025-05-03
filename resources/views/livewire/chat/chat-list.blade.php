<div>
    <div
    x-init="
 
    setTimeout(()=>{
 
     conversationElement = document.getElementById('conversation-'+query);
 
 
     //scroll to the element
 
     if(conversationElement)
     {
 
         conversationElement.scrollIntoView({'behavior':'smooth'});
 
     }
 
     },200);
    "
  class="flex flex-col transition-all h-full overflow-hidden">
 
     <header class="px-3 z-10 bg-white sticky top-0 w-full py-2">
 
         <div class="border-b justify-between flex items-center pb-2">
 
             <div class="flex items-center gap-2">
                  <h5 class="font-extrabold text-2xl">Chats</h5>
             </div>
 
         </div>
     </header>
 
 
     <main class=" overflow-y-scroll overflow-hidden grow  h-full relative " style="contain:content">
         <ul class="p-2 grid w-full spacey-y-2">
 
             @if ($conversations)
                 
             @foreach ($conversations as $key=> $conversation)
                 
            
             <li
               id="conversation-{{$conversation->id}}" wire:key="{{$conversation->id}}"
              class="py-3 hover:bg-gray-50 rounded-2xl dark:hover:bg-gray-200/70 transition-colors duration-150 flex gap-4 relative w-full cursor-pointer px-2 {{$conversation->id==$selectedConversation?->id ? 'bg-gray-100/70':''}}">
 
                 <aside class="grid grid-cols-12 w-full">
 
                     <a href="{{route('chat',$conversation->id)}}" class="col-span-11 border-b pb-2 border-gray-200 relative overflow-hidden truncate leading-5 w-full flex-nowrap p-1">
                         <div class="flex justify-between w-full items-center">
 
                             <h6 class="truncate font-medium tracking-wider text-gray-900">
                                 {{$conversation->getReceiver()->name}}
                             </h6>
 
                             <small class="text-gray-700">{{$conversation->messages?->last()?->created_at?->shortAbsoluteDiffForHumans()}} </small>
 
                         </div>
 
                         <div class="flex gap-x-2 items-center">
                              <p class="grow truncate text-sm font-[100]">
                                {{$conversation->messages?->last()?->body??' '}}
                             </p>
                              @if ($conversation->unreadMessagesCount()>0)
                              <span class="font-bold p-px px-2 text-xs shrink-0 rounded-full bg-blue-500 text-white">
                                 {{$conversation->unreadMessagesCount()}}
                              </span>
                                  
                              @endif
                         </div>
                     </a>
                     <div class="col-span-1 flex flex-col text-center my-auto">
                         <div class="w-6 h-6 opacity-0">.</div>
                     </div>
                 </aside>
             </li>
             @endforeach
             @else
             @endif
         </ul>
 
     </main>
 </div>
 
</div>