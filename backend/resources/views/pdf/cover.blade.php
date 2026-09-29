<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Candidate Pack - {{ $fullName }}</title>
    <style>
        @page { size: A4; margin: 40px 44px; }

        * { box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2933;
            line-height: 1.5;
            margin: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 24px;
        }

        .header .kicker {
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #627d98;
        }

        h1 {
            font-size: 26px;
            margin: 6px 0 0 0;
            color: #102a43;
        }

        .photo {
            text-align: center;
            margin: 18px 0 24px 0;
        }

        .photo img {
            width: 150px;
            height: 190px;
            object-fit: cover;
            border: 1px solid #bcccdc;
        }

        .details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        .details th {
            text-align: left;
            width: 38%;
            padding: 5px 8px;
            color: #486581;
            font-weight: normal;
            vertical-align: top;
        }

        .details td {
            padding: 5px 8px;
            color: #243b53;
            font-weight: bold;
            vertical-align: top;
        }

        .details tr {
            border-bottom: 1px solid #e4e9f0;
        }

        .missing {
            border: 1px solid #f0b429;
            background: #fffbea;
            border-radius: 4px;
            padding: 12px 16px;
        }

        .missing h2 {
            font-size: 13px;
            margin: 0 0 6px 0;
            color: #8d2b0b;
        }

        .missing ul {
            margin: 0;
            padding-left: 18px;
        }

        .missing li {
            color: #7b341e;
        }

        .complete {
            border: 1px solid #7bc47f;
            background: #f0fff4;
            border-radius: 4px;
            padding: 12px 16px;
            color: #0b6b3a;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="kicker">Candidate Pack</div>
        <h1>{{ $fullName }}</h1>
    </div>

    @if ($photoDataUri !== null)
        <div class="photo">
            <img src="{{ $photoDataUri }}" alt="Candidate photo">
        </div>
    @endif

    <table class="details">
        @foreach ($details as $label => $value)
            <tr>
                <th>{{ $label }}</th>
                <td>{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    @if (count($missing) > 0)
        <div class="missing">
            <h2>Missing documents</h2>
            <ul>
                @foreach ($missing as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="complete">All required documents are present.</div>
    @endif
</body>
</html>
