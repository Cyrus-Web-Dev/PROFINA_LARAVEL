<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background-color:#f8fafc;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;">
          <tr>
            <td style="background:#0f172a;padding:24px 32px;">
              <span style="color:#ffffff;font-size:18px;font-weight:bold;">PROFINA</span>
            </td>
          </tr>
          <tr>
            <td style="padding:32px;">
              <p style="font-size:15px;color:#334155;margin:0 0 8px;">Halo{{ $name ? ' ' . $name : '' }},</p>
              <h2 style="font-size:20px;color:{{ $color }};margin:8px 0 16px;">{{ $heading }}</h2>

              @foreach ($paragraphs as $p)
                <p style="font-size:15px;line-height:1.6;color:#334155;margin:0 0 14px;">{!! nl2br(e($p)) !!}</p>
              @endforeach

              @if (! empty($details))
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:10px;margin:18px 0;">
                  @foreach ($details as $label => $value)
                    <tr>
                      <td style="padding:8px 14px;font-size:13px;color:#64748b;width:130px;vertical-align:top;">{{ $label }}</td>
                      <td style="padding:8px 14px;font-size:14px;color:#0f172a;font-weight:bold;">{{ $value }}</td>
                    </tr>
                  @endforeach
                </table>
              @endif

              @if (! empty($button))
                <div style="text-align:center;margin:24px 0 8px;">
                  <a href="{{ $button['url'] }}" style="display:inline-block;background:#059669;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:12px 28px;border-radius:10px;">{{ $button['text'] }}</a>
                </div>
              @endif

              <p style="font-size:12px;color:#94a3b8;margin:24px 0 0;">Email ini dikirim otomatis oleh sistem PROFINA.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
