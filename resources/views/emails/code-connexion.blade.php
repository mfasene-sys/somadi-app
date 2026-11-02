<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Code de connexion SOMADI</title></head>
<body style="margin:0;padding:24px 12px;background:#f5f5f4;font-family:Arial,Helvetica,sans-serif;color:#1c1917">
    <div style="max-width:440px;margin:0 auto;background:#ffffff;border-radius:12px;padding:28px 24px">
        <p style="margin:0 0 4px;font-size:18px;font-weight:bold;color:#1f4a31">SOMADI Farming</p>
        <p style="margin:0 0 20px;font-size:14px;color:#57534e">Bonjour {{ $user->prenom ?: $user->nom }},</p>
        <p style="margin:0 0 12px;font-size:14px">Voici votre code de connexion :</p>
        <p style="margin:0 0 20px;font-size:32px;font-weight:bold;letter-spacing:8px;color:#1f4a31;font-family:'Courier New',monospace">{{ $code }}</p>
        <p style="margin:0 0 8px;font-size:14px">Il est valable {{ $minutes }} minutes et ne peut servir qu'une fois.</p>
        <p style="margin:0 0 20px;font-size:13px;color:#57534e">Demande faite le {{ $heure }} (heure de Dakar).</p>
        <p style="margin:0;padding-top:16px;border-top:1px solid #e7e5e4;font-size:12px;color:#78716c">
            Si vous n'êtes pas à l'origine de cette demande, quelqu'un connaît votre mot de passe :
            changez-le dès que possible et prévenez un administrateur. Ne communiquez jamais ce code.
        </p>
    </div>
</body>
</html>
