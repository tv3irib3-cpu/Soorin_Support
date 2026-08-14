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
            <p style="margin:0 0 14px;">
                {{ __('mail.invoice_issued_greeting', ['name' => $invoice->customer->name]) }}
            </p>
            <p style="margin:0 0 8px;">
                {{ __('mail.invoice_issued_body', ['number' => $invoice->number]) }}
            </p>
            <p style="margin:0 0 20px; font-size:16px; font-weight:bold; color:#0f766e;">
                {{ __('mail.invoice_issued_payable', ['amount' => \App\Support\Jalali::money($invoice->payable_amount)]) }}
            </p>
            <a href="{{ $viewUrl }}"
               style="display:inline-block; background:#14b8a6; color:#ffffff; text-decoration:none;
                      padding:10px 22px; border-radius:8px; font-size:13.5px;">
                {{ __('mail.invoice_issued_cta') }}
            </a>
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
