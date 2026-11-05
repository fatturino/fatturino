<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailSubject }}</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 15px; color: #222; background: #f9f9f9; margin: 0; padding: 32px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 40px; border: 1px solid #e5e7eb;">
        @if (! empty($companyLogoContents) && ! empty($companyLogoName))
            <div style="margin-bottom: 28px; text-align: left;">
                <img
                    src="{{ $message->embedData($companyLogoContents, $companyLogoName, $companyLogoMime ?? 'image/png') }}"
                    alt="{{ $companyLogoAlt }}"
                    style="display: block; max-height: 56px; max-width: 220px; height: auto; width: auto;"
                >
            </div>
        @endif

        <div style="line-height: 1.7;">
            {!! nl2br(e($emailBody)) !!}
        </div>

        <div style="margin-top: 32px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; line-height: 1.5; color: #6b7280; text-align: center;">
            {{ __('app.email.branding_footer_prefix') }}
            <a href="{{ __('app.email.branding_footer_url') }}" style="color: #6b7280; text-decoration: underline;">{{ __('app.email.branding_footer_brand') }}</a>
        </div>
    </div>
</body>
</html>
