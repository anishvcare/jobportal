<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Document</title>
    <style>
        @page { size: A4; margin: 24px; }

        html, body {
            margin: 0;
            padding: 0;
        }

        .frame {
            text-align: center;
        }

        .frame img {
            max-width: 100%;
            max-height: 780px;
        }
    </style>
</head>
<body>
    <div class="frame">
        <img src="{{ $imageDataUri }}" alt="Document page">
    </div>
</body>
</html>
