<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:32px 16px;background:#F5F7FA;font-family:Arial,Helvetica,sans-serif;color:#1D2433;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:480px;background:#ffffff;border:1px solid #DDE2EA;border-radius:12px;" cellspacing="0" cellpadding="0">
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 24px;font-size:16px;font-weight:bold;color:#0F1F3D;">{{ config('app.name') }}</p>
                            <p style="margin:0 0 8px;font-size:15px;">Use this code to finish signing in:</p>
                            <p style="margin:0 0 24px;font-size:32px;font-weight:bold;letter-spacing:8px;color:#0F1F3D;">{{ $code }}</p>
                            <p style="margin:0 0 8px;font-size:14px;color:#5B6475;">The code expires in {{ $expiresInMinutes }} minutes. Do not share it with anyone.</p>
                            <p style="margin:0;font-size:14px;color:#5B6475;">If you did not try to sign in, contact your account manager.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>