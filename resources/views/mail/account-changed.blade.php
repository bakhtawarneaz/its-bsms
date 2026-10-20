<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:32px 16px;background:#F5F7FA;font-family:Arial,Helvetica,sans-serif;color:#1D2433;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:560px;background:#ffffff;border:1px solid #DDE2EA;border-radius:12px;" cellspacing="0" cellpadding="0">
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 24px;font-size:16px;font-weight:bold;color:#0F1F3D;">{{ config('app.name') }}</p>
                            <p style="margin:0 0 16px;font-size:15px;">The account <strong>{{ $username }}</strong> was updated on {{ $changedAt }}.</p>

                            @if ($changes)
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="10" style="border-collapse:collapse;font-size:14px;margin:0 0 16px;">
                                    <tr style="background:#F5F7FA;">
                                        <td style="border:1px solid #DDE2EA;font-weight:bold;">Field</td>
                                        <td style="border:1px solid #DDE2EA;font-weight:bold;">Previous</td>
                                        <td style="border:1px solid #DDE2EA;font-weight:bold;">New</td>
                                    </tr>
                                    @foreach ($changes as $label => [$previous, $new])
                                        <tr>
                                            <td style="border:1px solid #DDE2EA;">{{ $label }}</td>
                                            <td style="border:1px solid #DDE2EA;">{!! nl2br(e(str_replace(',', "\n", $previous))) !!}</td>
                                            <td style="border:1px solid #DDE2EA;">{!! nl2br(e(str_replace(',', "\n", $new))) !!}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if ($passwordChanged)
                                <p style="margin:0 0 16px;font-size:15px;">The account password was changed.</p>
                            @endif

                            <p style="margin:0;font-size:14px;color:#5B6475;">If you did not make this change, contact your account manager immediately.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>