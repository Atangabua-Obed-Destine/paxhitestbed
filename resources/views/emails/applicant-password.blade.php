<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>{{ $setting->title ?? config('app.name') }} - Password Reset</title>
    <style>
      @media only screen and (max-width: 620px) {
        table.body h1 {
          font-size: 28px !important;
          margin-bottom: 10px !important;
        }
        table.body p,
        table.body ul,
        table.body ol,
        table.body td,
        table.body span,
        table.body a {
          font-size: 16px !important;
        }
        table.body .wrapper,
        table.body .article {
          padding: 10px !important;
        }
        table.body .content {
          padding: 0 !important;
        }
        table.body .container {
          padding: 0 !important;
          width: 100% !important;
        }
        table.body .main {
          border-left-width: 0 !important;
          border-radius: 0 !important;
          border-right-width: 0 !important;
        }
        table.body .btn table {
          width: 100% !important;
        }
        table.body .btn a {
          width: 100% !important;
        }
        table.body .img-responsive {
          height: auto !important;
          max-width: 100% !important;
          width: auto !important;
        }
      }
      @media all {
        .ExternalClass {
          width: 100%;
        }
        .ExternalClass,
        .ExternalClass p,
        .ExternalClass span,
        .ExternalClass font,
        .ExternalClass td,
        .ExternalClass div {
          line-height: 100%;
        }
        .apple-link a {
          color: inherit !important;
          font-family: inherit !important;
          font-size: inherit !important;
          font-weight: inherit !important;
          line-height: inherit !important;
          text-decoration: none !important;
        }
        #MessageViewBody a {
          color: inherit;
          text-decoration: none;
          font-size: inherit;
          font-family: inherit;
          font-weight: inherit;
          line-height: inherit;
        }
        .btn-primary table td:hover {
          background-color: #5a6fd6 !important;
        }
        .btn-primary a:hover {
          background-color: #5a6fd6 !important;
          border-color: #5a6fd6 !important;
        }
      }
    </style>
  </head>

  <body style="background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; -webkit-font-smoothing: antialiased; font-size: 14px; line-height: 1.6; margin: 0; padding: 0; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%;">

    <span class="preheader" style="color: transparent; display: none; height: 0; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; mso-hide: all; visibility: hidden; width: 0;">Reset your Application Portal password</span>

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #f4f6f9; width: 100%;" width="100%" bgcolor="#f4f6f9">
      <tr>
        <td style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
        <td class="container" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; vertical-align: top; display: block; max-width: 580px; padding: 10px; width: 580px; margin: 0 auto;" width="580" valign="top">
          <div class="content" style="box-sizing: border-box; display: block; margin: 0 auto; max-width: 580px; padding: 10px;">

            <!-- Header with Logo/Branding -->
            <table role="presentation" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px 8px 0 0; width: 100%;" width="100%">
              <tr>
                <td style="padding: 30px 20px; text-align: center;">
                  <h1 style="color: #ffffff; font-size: 24px; font-weight: 600; margin: 0;">
                    🔐 Password Reset Request
                  </h1>
                  <p style="color: rgba(255,255,255,0.9); font-size: 14px; margin: 10px 0 0 0;">
                    Application Portal
                  </p>
                </td>
              </tr>
            </table>

            <!-- Main Content -->
            <table role="presentation" class="main" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background: #ffffff; border-radius: 0 0 8px 8px; width: 100%; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);" width="100%">
              <tr>
                <td class="wrapper" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; vertical-align: top; box-sizing: border-box; padding: 30px;" valign="top">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%;" width="100%">
                    <tr>
                      <td style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; vertical-align: top;" valign="top">

                        <p style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 16px; font-weight: normal; margin: 0; margin-bottom: 20px; color: #333;">
                          Hello <strong>{{ $data['first_name'] }} {{ $data['last_name'] }}</strong>,
                        </p>

                        <p style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 20px; color: #555;">
                          We received a request to reset the password for your Application Portal account associated with this email address.
                        </p>

                        <p style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 25px; color: #555;">
                          Click the button below to create a new password:
                        </p>

                        <!-- Reset Button -->
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="btn btn-primary" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; box-sizing: border-box; width: 100%;" width="100%">
                          <tbody>
                            <tr>
                              <td align="center" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; vertical-align: top; padding-bottom: 25px;" valign="top">
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: auto;">
                                  <tbody>
                                    <tr>
                                      <td style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; vertical-align: top; border-radius: 8px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);" valign="top" align="center">
                                        <a href="{{ $data['reset_url'] }}" target="_blank" style="border: solid 2px transparent; border-radius: 8px; box-sizing: border-box; cursor: pointer; display: inline-block; font-size: 16px; font-weight: 600; margin: 0; padding: 14px 40px; text-decoration: none; text-transform: uppercase; letter-spacing: 0.5px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff;">
                                          🔓 Reset My Password
                                        </a>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </td>
                            </tr>
                          </tbody>
                        </table>

                        <!-- Security Info Box -->
                        <table role="presentation" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background: #fef9e7; border: 1px solid #f1c40f; border-radius: 6px; width: 100%; margin-bottom: 20px;" width="100%">
                          <tr>
                            <td style="padding: 15px;">
                              <p style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; font-weight: 600; margin: 0 0 8px 0; color: #9a7b0a;">
                                ⏰ Important Security Information:
                              </p>
                              <ul style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; color: #666; margin: 0; padding-left: 20px;">
                                <li style="margin-bottom: 5px;">This link will expire in <strong>60 minutes</strong></li>
                                <li style="margin-bottom: 5px;">This link can only be used once</li>
                                <li>If you didn't request this, please ignore this email</li>
                              </ul>
                            </td>
                          </tr>
                        </table>

                        <p style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; font-weight: normal; margin: 0; margin-bottom: 15px; color: #888;">
                          If the button above doesn't work, copy and paste this link into your browser:
                        </p>
                        
                        <p style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; font-weight: normal; margin: 0; margin-bottom: 20px; color: #667eea; word-break: break-all;">
                          {{ $data['reset_url'] }}
                        </p>

                        <hr style="border: none; border-top: 1px solid #eee; margin: 25px 0;">

                        <p style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; font-weight: normal; margin: 0; color: #888;">
                          If you did not request a password reset, your account is safe and you can ignore this email. Someone may have typed your email address by mistake.
                        </p>

                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <!-- Footer -->
            <table role="presentation" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%; margin-top: 20px;" width="100%">
              <tr>
                <td style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; vertical-align: top; text-align: center; color: #888; padding: 10px 0;" valign="top" align="center">
                  <p style="margin: 0 0 5px 0;">
                    © {{ date('Y') }} {{ $setting->title ?? config('app.name') }}. All rights reserved.
                  </p>
                  <p style="margin: 0; color: #aaa;">
                    This is an automated message. Please do not reply to this email.
                  </p>
                </td>
              </tr>
            </table>

          </div>
        </td>
        <td style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
      </tr>
    </table>
  </body>
</html>
