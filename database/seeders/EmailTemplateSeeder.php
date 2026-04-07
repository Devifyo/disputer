<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        EmailTemplate::updateOrCreate(
            ['slug' => 'inbound-email-received'],
            [
                'subject' => 'New Reply Received on Your Case [CASE_REFERENCE]',
                'is_active' => true,
                'body' => '
                    <tr>
                        <td style="padding: 48px 40px 24px; text-align: center;">
                            <div style="display: inline-block; padding: 14px; background-color: #2563eb; border-radius: 14px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);">
                                <img src="https://img.icons8.com/ios-filled/50/ffffff/email-open.png" width="28" height="28" alt="Email" style="display: block;">
                            </div>
                            <h1 style="margin: 0; font-size: 24px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px;">You have a new reply</h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 40px; font-size: 16px; line-height: 26px; color: #475569; text-align: center;">
                            <p style="margin: 0 0 16px;">Hello <strong style="color: #0f172a;">[USER_NAME]</strong>,</p>
                            <p style="margin: 0 0 8px;">A new email has been received on your case:</p>
                            <p style="margin: 0 0 8px;"><strong style="color: #0f172a;">Case Reference:</strong> [CASE_REFERENCE]</p>
                            <p style="margin: 0 0 8px;"><strong style="color: #0f172a;">From:</strong> [SENDER_EMAIL]</p>
                            <p style="margin: 0 0 32px;"><strong style="color: #0f172a;">Subject:</strong> [EMAIL_SUBJECT]</p>

                            <a href="[CASE_URL]" style="display: inline-block; background-color: #0f172a; color: #ffffff; font-weight: 700; font-size: 15px; text-decoration: none; padding: 16px 36px; border-radius: 10px;">View Case</a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 24px 40px; background-color: #f8fafc; border-top: 1px solid #f1f5f9; font-size: 13px; line-height: 22px; color: #94a3b8; text-align: left;">
                            <p style="margin: 0;">You are receiving this notification because you have an active case on ' . config('app.name') . '.</p>
                        </td>
                    </tr>
                '
            ]
        );

        EmailTemplate::updateOrCreate(
            ['slug' => 'forgot-password'],
            [
                'subject' => 'Reset Your Password - ' . config('app.name'),
                'is_active' => true,
                'body' => '
                    <tr>
                        <td style="padding: 48px 40px 24px; text-align: center;">
                            <div style="display: inline-block; padding: 14px; background-color: #2563eb; border-radius: 14px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);">
                                <img src="https://img.icons8.com/ios-filled/50/ffffff/lock.png" width="28" height="28" alt="Lock" style="display: block;">
                            </div>
                            <h1 style="margin: 0; font-size: 24px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px;">Reset your password</h1>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="padding: 0 40px 40px; font-size: 16px; line-height: 26px; color: #475569; text-align: center;">
                            <p style="margin: 0 0 16px;">Hello <strong style="color: #0f172a;">[USER_NAME]</strong>,</p>
                            <p style="margin: 0 0 32px;">We received a request to reset the password for your account. If you didn\'t make this request, you can safely ignore this email.</p>
                            
                            <a href="[RESET_LINK]" style="display: inline-block; background-color: #0f172a; color: #ffffff; font-weight: 700; font-size: 15px; text-decoration: none; padding: 16px 36px; border-radius: 10px;">Create New Password</a>
                            
                            <p style="margin: 32px 0 0; font-size: 14px; color: #64748b; font-weight: 500;">This link will securely expire in 60 minutes.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="padding: 24px 40px; background-color: #f8fafc; border-top: 1px solid #f1f5f9; font-size: 13px; line-height: 22px; color: #94a3b8; text-align: left;">
                            <p style="margin: 0 0 8px; font-weight: 600;">Having trouble clicking the button?</p>
                            <p style="margin: 0; word-break: break-all;"><a href="[RESET_LINK]" style="color: #2563eb; text-decoration: underline;">[RESET_LINK]</a></p>
                        </td>
                    </tr>
                '
            ]
        );
        EmailTemplate::updateOrCreate(
            ['slug' => 'escalation-deadline-alert'],
            [
                'subject' => 'Action Required: Case [CASE_REFERENCE] Deadline Passed',
                'is_active' => true,
                'body' => '
                    <tr>
                        <td style="padding: 48px 40px 24px; text-align: center;">
                            <div style="display: inline-block; padding: 14px; background-color: #dc2626; border-radius: 14px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);">
                                <img src="https://img.icons8.com/ios-filled/50/ffffff/warning-shield.png" width="28" height="28" alt="Alert" style="display: block;">
                            </div>
                            <h1 style="margin: 0; font-size: 24px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px;">Response Deadline Passed</h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 32px; font-size: 16px; line-height: 26px; color: #475569; text-align: center;">
                            <p style="margin: 0 0 16px;">Hello <strong style="color: #0f172a;">[USER_NAME]</strong>,</p>
                            <p style="margin: 0 0 24px;">Your case against <strong style="color: #0f172a;">[INSTITUTION]</strong> has passed its response deadline and requires your attention.</p>

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse; margin: 0 0 28px; text-align: left;">
                                <tr>
                                    <td style="padding: 10px 14px; background:#f8fafc; border: 1px solid #e2e8f0; font-weight: 600; color: #0f172a; width: 40%;">Current Step</td>
                                    <td style="padding: 10px 14px; border: 1px solid #e2e8f0; color: #475569;">[STEP_NAME]</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 14px; background:#f8fafc; border: 1px solid #e2e8f0; font-weight: 600; color: #0f172a;">Waiting For</td>
                                    <td style="padding: 10px 14px; border: 1px solid #e2e8f0; color: #475569;">[WAITING_FOR]</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 14px; background:#f8fafc; border: 1px solid #e2e8f0; font-weight: 600; color: #0f172a;">Allowed Days</td>
                                    <td style="padding: 10px 14px; border: 1px solid #e2e8f0; color: #475569;">[MAX_DAYS] days</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 14px; background:#fff1f2; border: 1px solid #fecdd3; font-weight: 600; color: #be123c;">Days Overdue</td>
                                    <td style="padding: 10px 14px; background:#fff1f2; border: 1px solid #fecdd3; font-weight: 700; color: #be123c;">[DAYS_OVER] day(s)</td>
                                </tr>
                            </table>

                            <a href="[CASE_URL]" style="display: inline-block; background-color: #7c3aed; color: #ffffff; font-weight: 700; font-size: 15px; text-decoration: none; padding: 16px 36px; border-radius: 10px;">Escalate Now &rarr;</a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 24px 40px; background-color: #f8fafc; border-top: 1px solid #f1f5f9; font-size: 13px; line-height: 22px; color: #94a3b8; text-align: left;">
                            <p style="margin: 0;">You are receiving this notification because your case on ' . config('app.name') . ' has passed its response deadline.</p>
                        </td>
                    </tr>
                '
            ]
        );
    }
}