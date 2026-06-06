<!DOCTYPE html>
<html>
<head>
    <title>IIJmio Usage Checker Config</title>
    <style>
        body { font-family: sans-serif; margin: 2em; }
        textarea { width: 100%; height: 500px; font-family: monospace; }
        .footer { margin-top: 2em; font-size: 0.8em; color: #666; }
        .message { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <h1>IIJmio Usage Checker Config</h1>
    @if($message)
        <p class="message">{{ $message }}</p>
    @endif
    <p>Collection: {{ $collectionName }}</p>
    <form method="POST">
        <textarea name="config">{{ $configJson }}</textarea>
        <br><br>
        <button type="submit">Save Config</button>
    </form>
    <div class="footer">
        Environment: {{ $appEnv }}
    </div>
</body>
</html>
