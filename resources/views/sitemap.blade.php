{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ url('/') }}</loc><changefreq>weekly</changefreq><priority>1.0</priority></url>
    <url><loc>{{ route('register') }}</loc><changefreq>monthly</changefreq><priority>0.6</priority></url>
    <url><loc>{{ route('privacy') }}</loc><changefreq>yearly</changefreq><priority>0.2</priority></url>
    <url><loc>{{ route('login') }}</loc><changefreq>monthly</changefreq><priority>0.3</priority></url>
    <url><loc>{{ route('lunar.today') }}</loc><changefreq>daily</changefreq><priority>0.9</priority></url>
    {{-- Lịch âm năm nay, năm trước và năm sau: trang năm, trang tháng; trang ngày cho năm nay và năm sau --}}
    @foreach ([$year - 1, $year, $year + 1] as $sitemapYear)
        <url><loc>{{ route('lunar.year', $sitemapYear) }}</loc><changefreq>monthly</changefreq><priority>0.7</priority></url>
        @foreach (range(1, 12) as $sitemapMonth)
            <url><loc>{{ route('lunar.month', [$sitemapYear, $sitemapMonth]) }}</loc><changefreq>monthly</changefreq><priority>0.6</priority></url>
        @endforeach
    @endforeach
    @for ($sitemapDay = new DateTimeImmutable($year.'-01-01'); (int) $sitemapDay->format('Y') <= $year + 1; $sitemapDay = $sitemapDay->modify('+1 day'))
        <url><loc>{{ route('lunar.day', $sitemapDay->format('Y-m-d')) }}</loc><changefreq>yearly</changefreq><priority>0.4</priority></url>
    @endfor
</urlset>
