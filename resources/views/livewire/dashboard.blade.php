<?php

use App\Jobs\ProcessPodcastUrl;
use App\Models\Episode;
use App\Models\ListeningParty;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component {

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required')]
    public $startTime;

    #[Validate('url')]
    public string $mediaUrl = '';

    public function createListeningParty()
    {
        $this->validate();

        $episode = Episode::create([
            'media_Url' => $this->mediaUrl,
        ]);
        $listeningParty = ListeningParty::create([
            'episode_id' => $episode->id,
            'name' => $this->name,
            'start_time' => $this->startTime,
        ]);

        ProcessPodcastUrl::dispatch($this->mediaUrl, $listeningParty, $episode);

        return redirect()->route('parties.show', $listeningParty);
    }

    public function getIsLiveAttribute()
    {
        $now = now();
        return $this->start_time <= $now && (!$this->end_time || $this->end_time > $now);
    }

    public function with()
    {
        return [
            'listeningParties' => ListeningParty::where('is_active', true)
                ->whereNotNull('end_time')->orderBy('start_time', 'asc')->with('episode.podcast')->get(),
        ];
    }
}; ?>

<div class="min-h-screen bg-emerald-50 flex flex-col pt-8">
    <div class="items-center justify-center p-4 block">
        <div class="flex items-center justify-center">
            <x-card shadow="lg" rounded="lg">
                <h2 class="text-x1 font-bold font-serif text-center">
                    Let's listen together.
                </h2>
                <form wire:submit="createListeningParty" class="space-y-6 mt-6">
                    <x-input wire:model='name' placeholder="Listening Party Name"/>
                    <x-input wire:model="mediaUrl" placeholder="Podcast RSS Feed URL"
                             description="Entering the RSS Feed URL will grab the latest episode"/>
                    <x-datetime-picker wire:model="startTime" placeholder="Listening PartyStart Time"/>
                    <x-button primary type="submit" class="w-full">Create Listening Party</x-button>
                </form>
            </x-card>
        </div>
        <div class="max-w-lg mx-auto mt-20">
            <h3 class="lg font-serif mb-4 text-[0.9rem]">
                Ongoing Listening Parties
            </h3>
            <div class="bg-white rounded-lg shadow-lg">
                @if($listeningParties->isEmpty())
                    <div class="flex items-center justify-center font-serif text-sm p-6">
                        No audio listening parties started yet...
                    </div>
                @else
                    @foreach($listeningParties as $listeningParty)
                        <div wire:key="{{ $listeningParty->id }}"x-data="{
                                    isLive: {{ $listeningParty->is_live ? 'true' : 'false' }},
                                    countdownText: '',
                                    init() {
                                        const startTime = new Date('{{ $listeningParty->start_time }}');
                                        const updateCountdown = () => {
                                            const now = new Date();
                                            const diff = startTime - now;

                                            if (diff <= 0) {
                                                this.isLive = true;
                                                this.countdownText = 'Live Now';
                                            } else {
                                                this.isLive = false;
                                                const hours = Math.floor(diff / 1000 / 60 / 60);
                                                const minutes = Math.floor((diff / 1000 / 60) % 60);
                                                const seconds = Math.floor((diff / 1000) % 60);
                                                this.countdownText = `${hours}h ${minutes}m ${seconds}s`;
                                            }
                                        };
                                        updateCountdown();
                                        setInterval(updateCountdown, 1000); // Update every second
                                    }
                                }"
                            >




                            <a href="{{ route('parties.show', $listeningParty) }}" class="block">
                                <div
                                    class="flex items-center justify-between p-4 transition-all border-b border-gray-200 hover:bg-gray-50 duration-150 ease-in-out">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-shrink-0">
                                            <x-avatar src="{{ $listeningParty->episode->podcast->artwork_url }}"
                                                      size="xl"
                                                      rounded="sm" alt="Podcast Artwork"/>
                                        </div>
                                        <div class="items-center min-w-0">
                                            <p class="text-[0.9rem] font-semibold truncate text-slate-900">{{ $listeningParty->name }}</p>
                                            <div class="mt-0.8">
                                                <p class="text-[0.7rem] truncate text-slate-500 max-w-sm">{{ $listeningParty->episode->title }}</p>
                                                <p class="text-slate-400 uppercase tracking-tighter text-xs">{{ $listeningParty->podcast->title }}</p>
                                                @if($listeningParty->is_live)
                                                    <span class="text-red-500 font-bold">Live</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="text-xs text-slate-600 mt-1">
                                             <span x-show="!isLive" class="text-gray-500">
                                                    Starts in: <span x-text="countdownText"></span>
                                             </span>
                                        </div>
                                    </div>
                                    <x-button flat xs class="w-20">Join</x-button>
                                </div>
                            </a>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
