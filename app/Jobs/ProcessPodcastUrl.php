<?php

namespace App\Jobs;

use App\Models\Podcast;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPodcastUrl implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $rssUrl;
    public $listeningParty;
    public $episode;

    /**
     * Create a new job instance.
     */
    public function __construct($rssUrl, $listeningParty, $episode)
    {
        $this->rssUrl = $rssUrl;
        $this->listeningParty = $listeningParty;
        $this->episode = $episode;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // RSS feed-ийг ачаалах
            $xml = simplexml_load_file($this->rssUrl);
            if ($xml === false) {
                Log::error("Failed to load RSS feed from URL: {$this->rssUrl}");
                throw new \Exception("Failed to load RSS feed from URL: {$this->rssUrl}");
            }

            // Podcast үндсэн мэдээлэл авах
            $podcastTitle = $xml->channel->title ?? 'Unknown Podcast';
            $podcastArtworkUrl = $xml->channel->image->url ?? null;

            // Хамгийн сүүлийн үеийн episode-ийг авах
            $latestEpisode = $xml->channel->item[0] ?? null;
            if (!$latestEpisode) {
                throw new \Exception("No episodes found in the RSS feed.");
            }

            $episodeTitle = $latestEpisode->title ?? 'Untitled Episode';
            $episodeMediaUrl = (string) ($latestEpisode->enclosure['url'] ?? '');

            // iTunes-н үргэлжлэх хугацааг авах
            $namespaces = $xml->getNamespaces(true);
            $itunesNamespace = $namespaces['itunes'] ?? null;
            $episodeLength = $itunesNamespace ? $latestEpisode->children($itunesNamespace)->duration : '00:00:00';

            try {
                $interval = CarbonInterval::createFromFormat('H:i:s', $episodeLength);
            } catch (\Exception $e) {
                Log::warning("Failed to parse episode duration: {$episodeLength}. Using default 00:00:00");
                $interval = CarbonInterval::seconds(0);
            }

            // `end_time`-ийг тооцоолох
            $endTime = $this->listeningParty->start_time->copy()->add($interval);

            // Podcast мэдээллийг update эсвэл create хийх
            $podcast = Podcast::updateOrCreate(
                ['rss' => $this->rssUrl],
                [
                    'title' => $podcastTitle,
                    'artwork_url' => $podcastArtworkUrl,
                ]
            );

            Log::info('Podcast saved:', [
                'id' => $podcast->id,
                'title' => $podcast->title,
                'artwork_url' => $podcast->artwork_url,
            ]);

            // Episode-ийг podcast-той холбох
            $this->episode->podcast()->associate($podcast);

            // Episode-г update хийх
            $this->episode->update([
                'title' => $episodeTitle,
                'media_url' => $episodeMediaUrl,
            ]);

            Log::info('Episode updated:', [
                'id' => $this->episode->id,
                'title' => $this->episode->title,
                'media_url' => $this->episode->media_url,
            ]);

            // Listening Party-г update хийх
            $this->listeningParty->update([
                'end_time' => $endTime,
            ]);

            Log::info('Listening Party updated:', [
                'id' => $this->listeningParty->id,
                'end_time' => $this->listeningParty->end_time,
            ]);

        } catch (\Exception $e) {
            // Log алдааг бүртгэх
            Log::error('Failed to process podcast URL: ' . $e->getMessage());
        }
    }
}
