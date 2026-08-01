<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New GEDCOM Contribution</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
            margin: 0;
            padding: 24px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #1e293b;
            border-radius: 16px;
            border: 1px solid #334155;
            padding: 32px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }
        .header {
            border-bottom: 1px solid #334155;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .title {
            color: #818cf8;
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 6px 0;
        }
        .subtitle {
            color: #94a3b8;
            font-size: 14px;
            margin: 0;
        }
        .meta-box {
            background-color: #0f172a;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .meta-item {
            margin-bottom: 8px;
            font-size: 14px;
        }
        .meta-item:last-child {
            margin-bottom: 0;
        }
        .meta-label {
            color: #94a3b8;
            font-weight: 600;
        }
        .meta-value {
            color: #ffffff;
            font-weight: 500;
        }
        .section-title {
            color: #cbd5e1;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0 0 10px 0;
        }
        .note-content {
            background-color: #0f172a;
            border-left: 4px solid #6366f1;
            border-radius: 6px;
            padding: 16px;
            font-size: 14px;
            line-height: 1.6;
            color: #f1f5f9;
            white-space: pre-wrap;
            margin-bottom: 24px;
        }
        .btn-download {
            display: inline-block;
            background-color: #4f46e5;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 8px;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);
        }
        .footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #334155;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">New Family Tree Contribution</h1>
            <p class="subtitle">A user submitted a note or media file for a person in the GEDCOM viewer.</p>
        </div>

        <div class="meta-box">
            <div class="meta-item">
                <span class="meta-label">Submitted By:</span>
                <span class="meta-value">{{ $sender->name }} ({{ $sender->email }})</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Person Target:</span>
                <span class="meta-value">{{ $person['name'] ?? $person['id'] }} (ID: {{ $person['id'] }})</span>
            </div>
            @if(!empty($person['birth_date']) || !empty($person['death_date']))
            <div class="meta-item">
                <span class="meta-label">Lifespan:</span>
                <span class="meta-value">{{ $person['birth_date'] ?? '?' }} – {{ $person['death_date'] ?? 'Present/Unknown' }}</span>
            </div>
            @endif
        </div>

        @if(!empty($note))
        <h3 class="section-title">Submitted Note / Information</h3>
        <div class="note-content">{{ $note }}</div>
        @endif

        @if(!empty($mediaUrl))
        <h3 class="section-title">Uploaded Media Attachment</h3>
        <p style="font-size: 14px; color: #94a3b8; margin-top: 0;">
            File: <strong style="color: #ffffff;">{{ $mediaOriginalName ?? 'Attachment' }}</strong>
        </p>
        <a href="{{ $mediaUrl }}" class="btn-download" target="_blank" rel="noopener noreferrer">
            ⬇️ Download Attachment
        </a>
        <p style="font-size: 12px; color: #64748b; margin-top: 12px; word-break: break-all;">
            Direct URL: <a href="{{ $mediaUrl }}" style="color: #818cf8;">{{ $mediaUrl }}</a>
        </p>
        @endif

        <div class="footer">
            Topland & Mikaelsen GEDCOM Viewer &bull; Superuser Notification System
        </div>
    </div>
</body>
</html>
