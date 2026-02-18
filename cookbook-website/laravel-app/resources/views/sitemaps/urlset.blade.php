<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
        <lastmod>{{ $url['lastmod'] }}</lastmod>
        <changefreq>{{ $url['changefreq'] ?? 'weekly' }}</changefreq>
        <priority>{{ $url['priority'] ?? '0.5' }}</priority>
        @if(isset($url['image']) && $url['image'])
        <image:image>
            <image:loc>{{ $url['image']['loc'] }}</image:loc>
            <image:title>{{ $url['image']['title'] }}</image:title>
            <image:caption>{{ $url['image']['caption'] }}</image:caption>
        </image:image>
        @endif
    </url>
@endforeach
</urlset>