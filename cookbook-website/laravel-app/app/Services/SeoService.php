<?php

namespace App\Services;

use App\Models\Rack;
use App\Models\User;
use Illuminate\Support\Str;

class SeoService
{
    public function getMetaTags(array $data = []): array
    {
        $defaults = [
            'title' => config('app.name', 'Ableton Cookbook'),
            'description' => 'Share and discover Ableton Live racks, instrument racks, audio effect racks, and MIDI racks for music production workflows.',
            'keywords' => 'ableton racks, ableton live, instrument racks, audio effect racks, midi racks, music production, workflows, ableton devices',
            'og_type' => 'website',
            'og_url' => request()->url(),
            'og_site_name' => config('app.name'),
            'twitter_card' => 'summary_large_image',
            'canonical_url' => request()->url(),
            'robots' => 'index, follow',
        ];

        return array_merge($defaults, $data);
    }

    public function getRackMetaTags(Rack $rack): array
    {
        $title = $rack->title . ' - ' . ucfirst($rack->rack_type) . ' Rack';
        $description = $this->generateRackDescription($rack);
        $keywords = $this->generateRackKeywords($rack);
        $imageUrl = $rack->preview_image_path ? asset('storage/' . $rack->preview_image_path) : asset('images/default-rack-og.jpg');

        return $this->getMetaTags([
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'og_type' => 'article',
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $imageUrl,
            'og_url' => route('racks.show', $rack),
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => $imageUrl,
            'canonical_url' => route('racks.show', $rack),
            'article_author' => $rack->user->name,
            'article_published_time' => $rack->published_at?->toISOString(),
            'article_modified_time' => $rack->updated_at->toISOString(),
        ]);
    }

    public function getUserMetaTags(User $user): array
    {
        $title = $user->name . ' - Music Producer Profile';
        $description = $this->generateUserDescription($user);
        $keywords = $this->generateUserKeywords($user);
        $imageUrl = $user->profile_photo_url ?? asset('images/default-user-og.jpg');

        return $this->getMetaTags([
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'og_type' => 'profile',
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $imageUrl,
            'og_url' => route('users.show', $user),
            'twitter_card' => 'summary',
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => $imageUrl,
            'canonical_url' => route('users.show', $user),
            'profile_first_name' => explode(' ', $user->name)[0] ?? '',
            'profile_last_name' => explode(' ', $user->name, 2)[1] ?? '',
            'profile_username' => $user->email,
        ]);
    }

    public function getHomeMetaTags(): array
    {
        return $this->getMetaTags([
            'title' => 'Ableton Cookbook - Share & Discover Ableton Live Racks',
            'description' => 'The ultimate community for sharing and discovering Ableton Live racks. Find instrument racks, audio effect racks, MIDI racks, and complete music production workflows from talented producers worldwide.',
            'keywords' => 'ableton racks, ableton live, instrument racks, audio effect racks, midi racks, music production workflows, ableton devices, electronic music, music production community',
            'og_title' => 'Ableton Cookbook - Share & Discover Ableton Live Racks',
            'og_description' => 'The ultimate community for sharing and discovering Ableton Live racks. Find instrument racks, audio effect racks, MIDI racks, and complete music production workflows.',
            'og_image' => asset('images/home-og.jpg'),
            'twitter_title' => 'Ableton Cookbook - Share & Discover Ableton Live Racks',
            'twitter_description' => 'The ultimate community for sharing and discovering Ableton Live racks. Find instrument racks, audio effect racks, MIDI racks, and complete music production workflows.',
            'twitter_image' => asset('images/home-og.jpg'),
        ]);
    }

    public function getUploadMetaTags(): array
    {
        return $this->getMetaTags([
            'title' => 'Upload Your Ableton Rack - Share with the Community',
            'description' => 'Share your Ableton Live racks with music producers worldwide. Upload instrument racks, audio effect racks, MIDI racks and help grow the community.',
            'keywords' => 'upload ableton rack, share music production, ableton live upload, contribute racks, music production community',
            'robots' => 'noindex, nofollow',
        ]);
    }

    public function getStructuredData(string $type, array $data = []): array
    {
        switch ($type) {
            case 'rack':
                return $this->getRackStructuredData($data['rack']);
            case 'user':
                return $this->getUserStructuredData($data['user']);
            case 'website':
                return $this->getWebsiteStructuredData();
            case 'breadcrumb':
                return $this->getBreadcrumbStructuredData($data['items']);
            default:
                return [];
        }
    }

    private function generateRackDescription(Rack $rack): string
    {
        $description = "Download {$rack->title}, a high-quality " . ucfirst($rack->rack_type) . " rack for Ableton Live";
        
        if ($rack->description) {
            $description .= ". " . Str::limit(strip_tags($rack->description), 120);
        }

        if ($rack->device_count > 0) {
            $description .= " Features {$rack->device_count} devices";
        }

        if ($rack->chain_count > 0) {
            $description .= " across {$rack->chain_count} chains";
        }

        $description .= ". Perfect for music production workflows.";

        return Str::limit($description, 160);
    }

    private function generateRackKeywords(Rack $rack): string
    {
        $keywords = [
            'ableton ' . $rack->rack_type . ' rack',
            $rack->title,
            'ableton live',
            'music production',
            ucfirst($rack->rack_type) . ' rack',
        ];

        if ($rack->category) {
            $keywords[] = $rack->category . ' rack';
            $keywords[] = $rack->category . ' ableton';
        }

        if ($rack->tags && $rack->tags->count() > 0) {
            foreach ($rack->tags->take(5) as $tag) {
                $keywords[] = $tag->name;
            }
        }

        if ($rack->ableton_version) {
            $keywords[] = 'ableton live ' . $rack->ableton_version;
        }

        return implode(', ', array_unique($keywords));
    }

    private function generateUserDescription(User $user): string
    {
        $description = "{$user->name} is a music producer sharing Ableton Live racks on Ableton Cookbook";
        
        $rackCount = $user->racks()->published()->count();
        if ($rackCount > 0) {
            $description .= ". {$rackCount} published racks";
        }

        if ($user->bio) {
            $description .= ". " . Str::limit(strip_tags($user->bio), 100);
        }

        $description .= ". Discover their instrument racks, audio effect racks, and MIDI racks for your music production workflow.";

        return Str::limit($description, 160);
    }

    private function generateUserKeywords(User $user): string
    {
        $keywords = [
            $user->name,
            'music producer',
            'ableton live producer',
            'ableton racks',
            'music production',
        ];

        if ($user->location) {
            $keywords[] = $user->location . ' producer';
        }

        return implode(', ', $keywords);
    }

    private function getRackStructuredData(Rack $rack): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $rack->title,
            'description' => $this->generateRackDescription($rack),
            'url' => route('racks.show', $rack),
            'image' => $rack->preview_image_path ? asset('storage/' . $rack->preview_image_path) : null,
            'author' => [
                '@type' => 'Person',
                'name' => $rack->user->name,
                'url' => route('users.show', $rack->user),
            ],
            'datePublished' => $rack->published_at?->toISOString(),
            'dateModified' => $rack->updated_at->toISOString(),
            'applicationCategory' => 'Music Production Software',
            'applicationSubCategory' => ucfirst($rack->rack_type) . ' Rack',
            'operatingSystem' => 'Windows, macOS',
            'softwareRequirements' => 'Ableton Live ' . ($rack->ableton_version ?? '9+'),
            'fileSize' => $rack->file_size ? number_format($rack->file_size / 1024, 2) . ' KB' : null,
            'downloadUrl' => route('racks.show', $rack),
            'aggregateRating' => $rack->ratings_count > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => $rack->average_rating,
                'ratingCount' => $rack->ratings_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ] : null,
            'interactionStatistic' => [
                [
                    '@type' => 'InteractionCounter',
                    'interactionType' => 'https://schema.org/DownloadAction',
                    'userInteractionCount' => $rack->downloads_count ?? 0,
                ],
                [
                    '@type' => 'InteractionCounter',
                    'interactionType' => 'https://schema.org/ViewAction',
                    'userInteractionCount' => $rack->views_count ?? 0,
                ],
            ],
        ];
    }

    private function getUserStructuredData(User $user): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $user->name,
            'url' => route('users.show', $user),
            'image' => $user->profile_photo_url,
            'description' => $this->generateUserDescription($user),
            'jobTitle' => 'Music Producer',
            'knows' => [
                '@type' => 'Thing',
                'name' => 'Ableton Live',
            ],
            'memberOf' => [
                '@type' => 'Organization',
                'name' => 'Ableton Cookbook Community',
                'url' => url('/'),
            ],
        ];
    }

    private function getWebsiteStructuredData(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => url('/'),
            'description' => 'Share and discover Ableton Live racks, instrument racks, audio effect racks, and MIDI racks for music production workflows.',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => url('/') . '?search={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('app.name'),
                'url' => url('/'),
            ],
        ];
    }

    private function getBreadcrumbStructuredData(array $items): array
    {
        $listItems = [];
        foreach ($items as $index => $item) {
            $listItems[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $listItems,
        ];
    }
}