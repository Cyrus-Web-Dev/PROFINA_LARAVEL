<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background-color:#f8fafc;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;">
          <tr>
            <td style="background:#0f172a;padding:24px 32px;">
              <span style="color:#ffffff;font-size:18px;font-weight:bold;">MKP &middot; Manajemen Keuangan</span>
            </td>
          </tr>
          <tr>
            <td style="padding:32px;">
              <p style="font-size:15px;color:#334155;margin:0 0 8px;">Halo{{ $name ? ' ' . $name : '' }},</p>
              <p style="font-size:15px;color:#334155;margin:0 0 24px;">{{ $intro }}</p>

              <div style="text-align:center;margin:24px 0;">
                <span style="display:inline-block;background:#ecfdf5;color:#047857;font-size:32px;font-weight:bold;letter-spacing:8px;padding:16px 32px;border-radius:12px;">{{ $code }}</span>
              </div>

              <p style="font-size:13px;color:#64748b;margin:0 0 4px;">Kode ini berlaku selama {{ $ttlMinutes }} menit.</p>
              <p style="font-size:13px;color:#64748b;margin:0 0 24px;">Jangan bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku dari tim MKP.</p>

              <p style="font-size:12px;color:#94a3b8;margin:0;">Kalau kamu tidak merasa melakukan permintaan ini, abaikan saja email ini - tidak ada tindakan lebih lanjut yang diperlukan.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
