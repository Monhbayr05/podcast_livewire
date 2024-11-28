<?php

use App\Models\ListeningParty;
use Livewire\Volt\Component;

new class extends Component {
    public ListeningParty $listeningParty;
    public $isFinished = false;

    public function mount(ListeningParty $listeningParty)
    {
        $this->listeningParty = $listeningParty->load('episode.podcast');
        $this->isFinished = !$listeningParty->is_active;
    }
};
?>

<div x-data="{
    audio: null,
    isLoading: true,
    isLive: false,
    isPlaying: false,
    isReady: false,
    currentTime: 0,
    countdownText: '',
    startTimestamp: {{ $listeningParty->start_time->timestamp }},
    endTimestamp: {{ $listeningParty->end_time ? $listeningParty->end_time->timestamp : 'null' }},
    copyNotification: false,

    init() {
        this.startCountdown();
        if (this.$refs.audioPlayer && !this.isFinished) {
            this.initializeAudioPlayer();
        }
    },

    initializeAudioPlayer() {
        this.audio = this.$refs.audioPlayer;

        this.audio.addEventListener('loadedmetadata', () => {
            this.isLoading = false;
        });

        this.audio.addEventListener('timeupdate', () => {
            this.currentTime = this.audio.currentTime;
            if (this.endTimestamp && this.currentTime >= (this.endTimestamp - this.startTimestamp)) {
                this.finishListeningParty();
            }
        });

        this.audio.addEventListener('play', () => this.isPlaying = true);
        this.audio.addEventListener('pause', () => this.isPlaying = false);
        this.audio.addEventListener('ended', () => this.isPlaying = false);

        setInterval(() => this.checkAndUpdate(), 1000);
    },

    finishListeningParty() {
        $wire.isFinished = true;
        $wire.$refresh();
        this.isPlaying = false;
        if (this.audio) {
            this.audio.pause();
        }
    },

    startCountdown() {
        this.checkAndUpdate();
        setInterval(() => this.checkAndUpdate(), 1000);
    },

    checkAndUpdate() {
        const now = Math.floor(Date.now() / 1000);
        const timeUntilStart = this.startTimestamp - now;

        if (timeUntilStart <= 0) {
            this.isLive = true;
            if (this.audio && !this.isPlaying && !this.isFinished) {
                this.playAudio();
            }
        } else {
            const days = Math.floor(timeUntilStart / 86400);
            const hours = Math.floor((timeUntilStart % 86400) / 3600);
            const minutes = Math.floor((timeUntilStart % 3600) / 60);
            const seconds = timeUntilStart % 60;
            this.countdownText = `${days}d ${hours}h ${minutes}m ${seconds}s`;
        }
    },

    playAudio() {
        if (!this.audio) return;

        const now = Math.floor(Date.now() / 1000);
        const elapsedTime = Math.max(0, now - this.startTimestamp);
        this.audio.currentTime = elapsedTime;

        this.audio.play().catch(error => {
            console.error('Playback failed:', error);
        });
    },

    joinAndBeReady() {
        this.isReady = true;
        if (this.isLive && this.audio && !this.isFinished) {
            this.playAudio();
        }
    },

    formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    },

    copyToClipboard() {
        navigator.clipboard.writeText(window.location.href);
        this.copyNotification = true;
        setTimeout(() => this.copyNotification = false, 3000);
    }
}" x-init="init()">

    @if($listeningParty->end_time === null)
        <div class="flex items-center justify-center min-h-screen bg-emerald-50">
            <div class="w-full max-w-2xl p-8 text-center bg-white rounded-lg shadow-lg">
                <h2>Creating your <span class="font-bold">{{ $listeningParty->name }}</span> listening party...</h2>
            </div>
        </div>
    @elseif($isFinished)
        <div class="flex items-center justify-center min-h-screen bg-emerald-50">
            <div class="w-full max-w-2xl p-8 text-center bg-white rounded-lg shadow-lg">
                <h2 class="text-2xl font-bold">This listening party has finished</h2>
                <p>Thank you for joining the {{ $listeningParty->name }} Listening Party.</p>
            </div>
        </div>
    @else
        <audio x-ref="audioPlayer" src="{{ $listeningParty->episode->media_url }}" preload="auto"></audio>

        <div x-show="!isLive" class="flex items-center justify-center min-h-screen bg-emerald-50">
            <div class="w-full max-w-2xl p-8 bg-white rounded-lg shadow-lg">
                <div class="flex items-center space-x-4">
                    <img src="{{ $listeningParty->episode->podcast->artwork_url }}" alt="Podcast Artwork" class="w-20 h-20 rounded-sm">
                    <div>
                        <h3 class="font-semibold">{{ $listeningParty->name }}</h3>
                        <p>{{ $listeningParty->episode->title }}</p>
                        <p class="text-sm text-gray-500">{{ $listeningParty->episode->podcast->title }}</p>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <p>Starts in: <span x-text="countdownText"></span></p>
                    <button @click="joinAndBeReady" class="px-4 py-2 mt-4 text-white bg-green-500 rounded">Join</button>
                </div>
            </div>
        </div>

        <div x-show="isLive" class="flex items-center justify-center min-h-screen bg-emerald-50">
            <div class="w-full max-w-2xl p-8 bg-white rounded-lg shadow-lg">
                <div class="flex items-center space-x-4">
                    <img src="{{ $listeningParty->episode->podcast->artwork_url }}" alt="Podcast Artwork" class="w-20 h-20 rounded-sm">
                    <div>
                        <h3 class="font-semibold">{{ $listeningParty->name }}</h3>
                        <p>{{ $listeningParty->episode->title }}</p>
                        <p class="text-sm text-gray-500">{{ $listeningParty->episode->podcast->title }}</p>
                    </div>
                </div>
                <div class="mt-4">
                    <p x-text="formatTime(currentTime)"></p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full" :style="`width: ${(currentTime / audio.duration) * 100}%`"></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
