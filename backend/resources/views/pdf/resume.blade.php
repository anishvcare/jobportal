<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Resume - {{ $fullName }}</title>
    <style>
        @page { size: A4; margin: 28px 36px; }

        * { box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2933;
            line-height: 1.45;
            margin: 0;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 2px 0;
            color: #102a43;
        }

        .contact {
            font-size: 10px;
            color: #486581;
            margin-bottom: 12px;
        }

        .contact span { white-space: nowrap; }

        .section {
            margin-bottom: 14px;
        }

        .section h2 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #334e68;
            border-bottom: 1px solid #bcccdc;
            padding-bottom: 3px;
            margin: 0 0 6px 0;
        }

        .entry {
            margin-bottom: 8px;
        }

        .entry .title {
            font-weight: bold;
            color: #243b53;
        }

        .entry .meta {
            color: #627d98;
            font-size: 10px;
        }

        .entry .desc {
            margin-top: 2px;
        }

        .summary {
            white-space: pre-line;
        }

        .tags span {
            display: inline-block;
            background: #f0f4f8;
            border: 1px solid #d9e2ec;
            border-radius: 3px;
            padding: 2px 6px;
            margin: 0 4px 4px 0;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <h1>{{ $fullName }}</h1>

    <div class="contact">
        @foreach ($contactLines as $line)
            <span>{{ $line }}</span>@if (! $loop->last) &nbsp;&bull;&nbsp; @endif
        @endforeach
    </div>

    @if ($summary !== '')
        <div class="section">
            <h2>Summary</h2>
            <div class="summary">{{ $summary }}</div>
        </div>
    @endif

    @if (count($experiences) > 0)
        <div class="section">
            <h2>Experience</h2>
            @foreach ($experiences as $experience)
                <div class="entry">
                    <div class="title">{{ $experience['title'] }}</div>
                    @if ($experience['meta'] !== '')
                        <div class="meta">{{ $experience['meta'] }}</div>
                    @endif
                    @if ($experience['description'] !== '')
                        <div class="desc">{{ $experience['description'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if (count($educations) > 0)
        <div class="section">
            <h2>Education</h2>
            @foreach ($educations as $education)
                <div class="entry">
                    <div class="title">{{ $education['title'] }}</div>
                    @if ($education['meta'] !== '')
                        <div class="meta">{{ $education['meta'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if (count($skills) > 0)
        <div class="section">
            <h2>Skills</h2>
            <div class="tags">
                @foreach ($skills as $skill)
                    <span>{{ $skill }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @if (count($languages) > 0)
        <div class="section">
            <h2>Languages</h2>
            <div class="tags">
                @foreach ($languages as $language)
                    <span>{{ $language }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @if (count($preferredCategories) > 0)
        <div class="section">
            <h2>Preferred Trades</h2>
            <div class="tags">
                @foreach ($preferredCategories as $category)
                    <span>{{ $category }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @if (count($preferredCountries) > 0)
        <div class="section">
            <h2>Preferred Countries</h2>
            <div class="tags">
                @foreach ($preferredCountries as $country)
                    <span>{{ $country }}</span>
                @endforeach
            </div>
        </div>
    @endif
</body>
</html>
