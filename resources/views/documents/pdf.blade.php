<!DOCTYPE html>
<html lang="en-GB">
    <head>
        <meta charset="utf-8">
        <title>{{ $document->title }}</title>
        <style>
            body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; color: #1a1a1a; line-height: 1.45; }
            header { border-bottom: 1px solid #999; margin-bottom: 18pt; padding-bottom: 6pt; }
            header strong { font-size: 13pt; }
            h2 { font-size: 15pt; margin: 12pt 0 8pt; }
            p { margin: 0 0 8pt; }
            .signature { margin-top: 24pt; border-top: 1px solid #999; padding-top: 8pt; }
            .signature img { height: 60pt; }
            footer { margin-top: 24pt; font-size: 8pt; color: #666; }
        </style>
    </head>
    <body>
        <header><strong>{{ $organisationName }}</strong></header>

        {!! $document->body_html !!}

        <div class="signature">
            @if ($signature)
                <img src="data:image/png;base64,{{ $signature }}" alt="Signature">
                <p>Signed by {{ $document->signed_by_name }} on {{ $signedOn }}</p>
            @else
                <p>Signature: ______________________________ Date: ______________</p>
            @endif
        </div>

        <footer>Document {{ $document->id }} · generated {{ $generatedOn }}</footer>
    </body>
</html>
