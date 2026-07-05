<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Unjamm') }}</title>
    <style>
        /* Fallback web fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        body, table, td, a {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        
        /* Client-specific resets */
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; }
        
        /* Mobile responsive adjustments */
        @media screen and (max-width: 600px) {
            .container { width: 100% !important; padding: 0 16px !important; }
            .content-pad { padding: 32px 24px !important; }
            .header-pad { padding: 24px !important; }
        }
    </style>
</head>
<body style="background-color: #f8fafc; padding: 40px 0; margin: 0; -webkit-font-smoothing: antialiased; text-size-adjust: 100%;">
    
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #f8fafc;">
        <tr>
            <td align="center">
                
                {{-- Main Card Container --}}
                <table class="container" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 600px; margin: 0 auto;">
                    <tr>
                        <td align="center">
                            
                            {{-- White Box --}}
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; border-top: 4px solid #0B6B4C; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);">
                                
                                {{-- Header with Logo Mark --}}
                                <tr>
                                    <td class="header-pad" style="padding: 32px 40px 24px 40px; border-bottom: 1px solid #f1f5f9;">
                                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                            <tr>
                                                {{-- Logo Icon Box — the landing-page peak mark baked into a single
                                                     opaque PNG: Outlook renders neither SVG nor CSS gradients, so
                                                     the whole mark ships as one email-safe image. --}}
                                                <td width="38" valign="middle" style="width: 38px;">
                                                    <img src="{{ asset('email-logo.png') }}" alt="{{ config('app.name', 'Unjamm') }}" width="38" height="38" style="display: block; width: 38px; height: 38px; border: 0; border-radius: 10px;" />
                                                </td>
                                                
                                                {{-- Spacer --}}
                                                <td width="12" style="width: 12px;">&nbsp;</td>
                                                
                                                {{-- App Name & Tagline --}}
                                                <td align="left" valign="middle">
                                                    <div style="font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; line-height: 1;">
                                                        {{ config('app.name', 'Unjamm') }}
                                                    </div>
                                                    <div style="font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.15em; margin-top: 4px; line-height: 1;">
                                                        Get the money airlines owe you
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Dynamic Content Block --}}
                                <tr>
                                    <td class="content-pad" style="padding: 40px;">
                                        @yield('content')
                                    </td>
                                </tr>
                                
                            </table>
                            
                            {{-- Footer --}}
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 600px;">
                                <tr>
                                    <td style="padding: 32px 40px; text-align: center; font-size: 13px; color: #64748b; line-height: 1.6;">
                                        &copy; {{ date('Y') }} {{ config('app.name', 'Unjamm') }}. All rights reserved.<br>
                                        <span style="color: #94a3b8; font-weight: 500; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em;">Get the money airlines owe you</span>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>
                </table>
                
            </td>
        </tr>
    </table>
    
</body>
</html>