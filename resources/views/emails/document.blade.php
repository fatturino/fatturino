<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailSubject }}</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 15px; color: #222; background: #f9f9f9; margin: 0; padding: 32px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 40px; border: 1px solid #e5e7eb;">
        <div style="line-height: 1.7;">
            {!! nl2br(e($emailBody)) !!}
        </div>
    </div>
</body>
</html>
