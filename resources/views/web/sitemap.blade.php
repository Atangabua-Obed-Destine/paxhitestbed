<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach($pages as $page)
    <url>
        <loc>{{ $page['url'] }}</loc>
        <changefreq>{{ $page['changefreq'] }}</changefreq>
        <priority>{{ $page['priority'] }}</priority>
        <lastmod>{{ now()->toAtomString() }}</lastmod>
    </url>
    @endforeach

    @foreach($programs as $program)
    <url>
        <loc>{{ route('program.single', $program->slug) }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
        <lastmod>{{ $program->updated_at->toAtomString() }}</lastmod>
    </url>
    @endforeach

    @foreach($faculties as $faculty)
    <url>
        <loc>{{ route('faculty.single', $faculty->slug) }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
        <lastmod>{{ $faculty->updated_at->toAtomString() }}</lastmod>
    </url>
    @endforeach

    @foreach($projects as $project)
    <url>
        <loc>{{ route('project.detail', $project->id) }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
        <lastmod>{{ $project->updated_at->toAtomString() }}</lastmod>
    </url>
    @endforeach
</urlset>
