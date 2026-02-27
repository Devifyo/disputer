<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dispute Report - Case #{{ $case->case_reference_id }}</title>
    <style>
        /* -------------------------------------------------------------
           DOMPDF PAGE SETUP & RESET
        ------------------------------------------------------------- */
        @page {
            margin: 120px 40px 70px 40px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b; /* Slate 800 */
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        
        /* -------------------------------------------------------------
           FIXED HEADER (Repeats on every page)
        ------------------------------------------------------------- */
        header {
            position: fixed;
            top: -90px;
            left: 0;
            right: 0;
            height: 70px;
            border-bottom: 2px solid #0f172a; /* Slate 900 */
        }
        .header-brand { font-size: 24px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; margin-bottom: 2px; }
        .header-subtitle { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        
        .header-meta { text-align: right; }
        .case-number { font-size: 18px; font-weight: bold; color: #0f172a; }
        .status-pill {
            display: inline-block;
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 5px;
        }

        /* -------------------------------------------------------------
           FIXED FOOTER (Repeats on every page)
        ------------------------------------------------------------- */
        footer {
            position: fixed;
            bottom: -40px;
            left: 0;
            right: 0;
            height: 30px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #64748b;
            padding-top: 8px;
        }
        .page-number:after { content: counter(page); }

        /* -------------------------------------------------------------
           TYPOGRAPHY & SECTIONS
        ------------------------------------------------------------- */
        .section-header {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding-bottom: 4px;
            border-bottom: 1px solid #cbd5e1;
            margin-top: 25px;
            margin-bottom: 15px;
        }

        /* -------------------------------------------------------------
           CASE DETAILS GRID
        ------------------------------------------------------------- */
        .details-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            margin-bottom: 30px;
        }
        .details-box td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            width: 25%;
        }
        .details-box td:nth-child(even) { border-right: none; }
        .details-box tr:last-child td { border-bottom: none; }
        
        .label { font-size: 9px; color: #64748b; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px; margin-bottom: 3px; display: block; }
        .value { font-size: 12px; font-weight: bold; color: #0f172a; }
        .value-sub { font-size: 10px; color: #475569; font-weight: normal; margin-top: 2px; }
        .value-highlight { color: #059669; font-size: 14px; } /* Emerald green for money */

        /* -------------------------------------------------------------
           TIMELINE & EMAILS
        ------------------------------------------------------------- */
        .timeline-item {
            margin-bottom: 20px;
            page-break-inside: avoid; /* PREVENTS PAGE CUTTING ITEMS IN HALF */
        }
        
        .log-header {
            margin-bottom: 8px;
        }
        .log-date {
            display: inline-block;
            font-size: 9px;
            color: #64748b;
            font-weight: bold;
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 3px;
            margin-right: 8px;
            text-transform: uppercase;
        }
        .log-title {
            display: inline-block;
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }
        .log-desc {
            font-size: 11px;
            color: #475569;
            margin-top: 3px;
            margin-left: 2px;
        }

        /* The Premium Email Thread Container */
        .email-thread {
            margin-top: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background-color: #ffffff;
            overflow: hidden;
        }
        .email-meta {
            background-color: #f8fafc;
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .email-meta table { width: 100%; }
        .email-meta td { padding: 2px 0; font-size: 10px; color: #334155; }
        .em-label { width: 60px; font-weight: bold; color: #64748b; text-transform: uppercase; font-size: 8px; letter-spacing: 0.5px;}
        .em-subject { font-size: 12px; font-weight: bold; color: #0f172a; margin-bottom: 6px; display: block; }
        
        .email-body {
            padding: 15px;
            font-size: 11px;
            color: #334155;
            line-height: 1.6;
        }
        .email-body p { margin-top: 0; margin-bottom: 10px; }
        .email-body blockquote { 
            margin: 10px 0 0 0; 
            padding-left: 10px; 
            border-left: 3px solid #cbd5e1; 
            color: #64748b; 
            font-style: italic; 
        }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <header>
        <table>
            <tr>
                <td style="width: 50%;">
                    <div class="header-brand">{{ config('app.name', 'Dispute Resolution') }}</div>
                    <div class="header-subtitle">Official Case Report</div>
                </td>
                <td class="header-meta" style="width: 50%;">
                    <div class="case-number">CASE #{{ $case->case_reference_id }}</div>
                    <div class="status-pill">{{ strtoupper($case->status?->value ?? $case->status) }}</div>
                </td>
            </tr>
        </table>
    </header>

    {{-- FOOTER --}}
    <footer>
        <table>
            <tr>
                <td style="width: 33%; text-align: left;">Generated on {{ now()->format('M d, Y \a\t H:i A') }}</td>
                <td style="width: 34%; text-align: center;">Strictly Confidential &bull; Official Record</td>
                <td style="width: 33%; text-align: right;">Page <span class="page-number"></span></td>
            </tr>
        </table>
    </footer>

    {{-- MAIN CONTENT --}}
    <main>
        
        <div class="section-header">Dispute Overview</div>
        
        <table class="details-box">
            <tr>
                <td>
                    <span class="label">Institution</span>
                    <span class="value">{{ $case->institution_name }}</span>
                    <div class="value-sub">{{ $case->institution->category->name ?? 'Financial' }}</div>
                </td>
                <td>
                    <span class="label">Disputed Amount</span>
                    <span class="value value-highlight">${{ number_format((float)($metadata['amount'] ?? 0), 2) }}</span>
                </td>
                <td>
                    <span class="label">Reference Number</span>
                    <span class="value" style="font-family: monospace;">{{ $metadata['ref_num'] ?? 'N/A' }}</span>
                </td>
                <td>
                    <span class="label">Transaction Date</span>
                    <span class="value">{{ $metadata['txn_date'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Current Workflow Stage</span>
                    <span class="value">{{ ucwords(str_replace('_', ' ', $case->current_workflow_step)) }}</span>
                </td>
                <td colspan="2">
                    <span class="label">Escalation Status</span>
                    <span class="value">Level {{ $case->escalation_level }}</span>
                    @if($case->last_escalated_at)
                        <div class="value-sub">Last Action: {{ $case->last_escalated_at->format('M d, Y') }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <div class="section-header">Activity & Communication Log</div>
        
        <div>
            @php
                // DESIGNER'S SECRET: This function cleanly converts complex HTML emails into perfect print formatting.
                // It prevents sentences from mashing together when strip_tags is applied.
                $formatEmailBody = function($rawHtml) {
                    if (empty($rawHtml)) return '';
                    // 1. Convert block tags into physical line breaks
                    $html = str_ireplace(['<br>', '<br/>', '<br />', '</div>', '</p>', '</tr>', '</table>'], "\n", $rawHtml);
                    // 2. Strip all remaining HTML tags to prevent DOMPDF crashes
                    $text = strip_tags($html);
                    // 3. Remove excessive empty lines (more than 2)
                    $text = preg_replace("/[\r\n]{3,}/", "\n\n", $text);
                    // 4. Safely escape for PDF and convert \n back to <br>
                    return nl2br(e(trim($text)));
                };
            @endphp

            @forelse($publicTimeline as $log)
                <div class="timeline-item">
                    
                    <div class="log-header">
                        <span class="log-date">{{ $log->occurred_at ? $log->occurred_at->format('M d, Y H:i') : 'Unknown Date' }}</span>
                        <span class="log-title">{{ $log->readable_type ?? ucfirst(str_replace('_', ' ', $log->type)) }}</span>
                    </div>
                    <div class="log-desc">{{ $log->description }}</div>

                    @php
                        // Initialize variables
                        $hasEmail = false;
                        $eSubj = ''; $eFrom = ''; $eTo = ''; $eBody = '';

                        // 1. Try fetching from actual Email Model first
                        if ($log->email) {
                            $hasEmail = true;
                            $eSubj = $log->email->subject;
                            $eFrom = $log->email->sender_email;
                            $eTo = $log->email->recipient_email;
                            $eBody = $log->email->body_text ?? $log->email->body_html;
                        } 
                        // 2. Fallback to Metadata if no Model exists
                        elseif (isset($log->metadata['subject']) || isset($log->metadata['full_body']) || isset($log->metadata['body'])) {
                            
                            $rawSubj = $log->metadata['subject'] ?? null;
                            $eSubj = is_array($rawSubj) ? ($rawSubj[0] ?? '') : $rawSubj;

                            $rawFrom = $log->metadata['sender_email'] ?? null;
                            $eFrom = is_array($rawFrom) ? ($rawFrom[0] ?? '') : $rawFrom;

                            $rawTo = $log->metadata['recipient'] ?? $log->metadata['recipient_email'] ?? null;
                            $eTo = is_array($rawTo) ? ($rawTo[0] ?? '') : $rawTo;

                            $rawBody = $log->metadata['full_body'] ?? $log->metadata['body'] ?? null;
                            $eBody = is_array($rawBody) ? ($rawBody[0] ?? '') : $rawBody;

                            if ($eSubj || $eFrom || $eTo || $eBody) {
                                $hasEmail = true;
                            }
                        }
                    @endphp

                    {{-- Render the Email Thread if data exists --}}
                    @if($hasEmail)
                        <div class="email-thread">
                            <div class="email-meta">
                                @if($eSubj)
                                    <div class="em-subject">{{ $eSubj }}</div>
                                @endif
                                <table>
                                    @if($eFrom)
                                    <tr>
                                        <td class="em-label">From:</td>
                                        <td><strong>{{ $eFrom }}</strong></td>
                                    </tr>
                                    @endif
                                    @if($eTo)
                                    <tr>
                                        <td class="em-label">To:</td>
                                        <td>{{ $eTo }}</td>
                                    </tr>
                                    @endif
                                </table>
                            </div>
                            
                            @if($eBody)
                            <div class="email-body">
                                {!! $formatEmailBody($eBody) !!}
                            </div>
                            @endif
                        </div>
                    @endif

                </div>
            @empty
                <div style="text-align: center; color: #94a3b8; padding: 40px 0; border: 1px dashed #cbd5e1; border-radius: 6px; margin-top: 20px;">
                    <span style="font-size: 14px; font-weight: bold; color: #64748b; display: block; margin-bottom: 5px;">No Activity Recorded</span>
                    <span style="font-size: 11px;">Public timeline events and emails will appear here when available.</span>
                </div>
            @endforelse
        </div>
        
    </main>

</body>
</html>