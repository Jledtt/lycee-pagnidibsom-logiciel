<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mailSubject }}</title>
</head>
<body style="margin:0;background:#f5f5f5;color:#202020;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f5f5;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #dedede;">
                    <tr>
                        <td style="background:#8b1e2d;color:#ffffff;padding:20px 28px;border-bottom:4px solid #e6a817;">
                            <strong style="font-size:18px;">Lycée Privé Pagnidibsom</strong>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;font-size:15px;line-height:1.65;">
                            {!! nl2br(e($mailBody)) !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;background:#f7f7f7;color:#666666;font-size:12px;">
                            Message envoyé automatiquement par la plateforme de gestion du lycée.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
