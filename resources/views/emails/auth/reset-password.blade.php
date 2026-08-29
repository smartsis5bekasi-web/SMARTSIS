{{--
    Branded password reset email for SMARTSIS.

    Rendered by the ResetPassword::toMailUsing() callback registered in
    FortifyServiceProvider. Email clients strip <style> and ignore Tailwind, so
    every rule here is inline and the layout is table-based on purpose.

    @var \App\Models\User $user
    @var string $resetUrl
    @var int $expiresInMinutes
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ __('Atur Ulang Kata Sandi') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f1fd; font-family:'Segoe UI',Helvetica,Arial,sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f1fd; padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">

                    {{-- Brand header --}}
                    <tr>
                        <td align="center" style="background-color:#441daa; padding:28px 24px;">
                            <div style="font-size:22px; font-weight:700; letter-spacing:0.5px; color:#ffffff;">
                                {{ config('app.name', 'SMARTSIS') }}
                            </div>
                            <div style="margin-top:4px; font-size:13px; color:#f5a800; font-weight:600;">
                                SMAN 5 Bekasi
                            </div>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px 32px 8px 32px;">
                            <h1 style="margin:0 0 16px 0; font-size:20px; font-weight:700; color:#28135e;">
                                Atur Ulang Kata Sandi
                            </h1>

                            <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6; color:#374151;">
                                Halo <strong>{{ $user->name }}</strong>,
                            </p>

                            <p style="margin:0 0 24px 0; font-size:15px; line-height:1.6; color:#374151;">
                                Kami menerima permintaan untuk mengatur ulang kata sandi akun
                                {{ config('app.name', 'SMARTSIS') }} yang terhubung dengan alamat email ini.
                                Klik tombol di bawah untuk membuat kata sandi baru.
                            </p>
                        </td>
                    </tr>

                    {{-- Call to action --}}
                    <tr>
                        <td align="center" style="padding:0 32px 24px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" bgcolor="#441daa" style="border-radius:8px;">
                                        <a href="{{ $resetUrl }}"
                                           target="_blank"
                                           rel="noopener"
                                           style="display:inline-block; padding:14px 32px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:8px;">
                                            Atur Ulang Kata Sandi
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 24px 32px;">
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:1.6; color:#6b7280;">
                                Tautan ini hanya berlaku selama <strong>{{ $expiresInMinutes }} menit</strong>.
                                Setelah itu Anda perlu mengajukan permintaan baru dari halaman
                                <em>Forgot password</em>.
                            </p>

                            <p style="margin:0 0 16px 0; font-size:14px; line-height:1.6; color:#6b7280;">
                                Jika Anda tidak merasa meminta pengaturan ulang kata sandi, abaikan email ini.
                                Kata sandi Anda tidak akan berubah.
                            </p>

                            <hr style="border:none; border-top:1px solid #e5e7eb; margin:24px 0;">

                            <p style="margin:0 0 8px 0; font-size:12px; line-height:1.6; color:#9ca3af;">
                                Tombol tidak berfungsi? Salin dan tempel tautan berikut ke peramban Anda:
                            </p>
                            <p style="margin:0; font-size:12px; line-height:1.6; word-break:break-all;">
                                <a href="{{ $resetUrl }}" target="_blank" rel="noopener" style="color:#441daa;">{{ $resetUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="background-color:#f9fafb; padding:20px 32px; border-top:1px solid #e5e7eb;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#9ca3af;">
                                Email ini dikirim otomatis oleh {{ config('app.name', 'SMARTSIS') }} &mdash; mohon tidak membalas.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
