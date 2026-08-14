<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0; padding:0; background:#eef4f6; font-family: Tahoma, Arial, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4f6; padding:24px 0;">
<tr><td align="center">
<table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden;">
    <tr>
        <td style="background:#0f2d4d; padding:18px 24px; text-align:right;">
            <span style="color:#ffffff; font-size:15px; font-weight:bold;">{{ config('branding.company.name') }}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:26px 24px; text-align:right; color:#0b2b3f; font-size:14px; line-height:1.9;">
            <p style="margin:0 0 14px;">{{ __('mail.survey_greeting') }}</p>
            <p style="margin:0 0 22px;">
                {{ __('mail.survey_body', ['number' => $ticket->number, 'subject' => $ticket->subject]) }}
            </p>
            <div style="text-align:center; margin:0 0 22px; font-size:26px; letter-spacing:3px; color:#f5b301;">
                ★★★★★
            </div>
            <div style="text-align:center;">
                <a href="{{ $surveyUrl }}"
                   style="display:inline-block; background:#14b8a6; color:#ffffff; text-decoration:none;
                          padding:10px 24px; border-radius:8px; font-size:13.5px;">
                    {{ __('mail.survey_cta') }}
                </a>
            </div>
        </td>
    </tr>
    <tr>
        <td style="padding:16px 24px; border-top:1px solid #dde8ec; text-align:right; color:#5f7d8c; font-size:11.5px;">
            {{ __('mail.footer_note', ['company' => config('branding.company.name')]) }}<br>
            <a href="{{ config('branding.company.website') }}" style="color:#0f766e; text-decoration:none;">{{ config('branding.company.website_label') }}</a>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
