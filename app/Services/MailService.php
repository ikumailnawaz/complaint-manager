<?php

namespace App\Services;

use App\Models\Ticket;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Illuminate\Support\Facades\Log;

class MailService
{
    /**
     * Send professional assignment confirmation email directly to the bank via GoDaddy SMTP.
     */
    public function sendBankAssignmentReply(Ticket $ticket, ?string $customNotes = null): array
    {
        if (app()->environment('testing')) {
            $chainHtml = $this->buildThreadChainHtml($ticket);
            $chainText = $this->buildThreadChainText($ticket);
            return [
                'success' => true,
                'message_id' => '<test-msg.' . uniqid() . '@cmscompany.biz>',
                'recipient' => $ticket->customer_email ?: 'operations@bank.com.pk',
                'thread_chain_html' => $chainHtml,
                'thread_chain_text' => $chainText,
            ];
        }

        $mail = new PHPMailer(true);

        try {
            $this->configureSmtp($mail);
            
            $toEmail = trim($ticket->customer_email);
            if (empty($toEmail)) {
                return [
                    'success' => false,
                    'error' => 'No bank customer email is associated with this ticket.',
                ];
            }
            $mail->addAddress($toEmail, $ticket->customer_name ?? $ticket->bank_name);

            // Cc recipients: Original bank email thread CCs alongside default operations CC
            $ccList = [];
            if (!empty($ticket->customer_cc)) {
                $exploded = preg_split('/[,;\s]+/', $ticket->customer_cc);
                foreach ($exploded as $c) {
                    $clean = trim($c, " <>\"'");
                    if (filter_var($clean, FILTER_VALIDATE_EMAIL) && strtolower($clean) !== strtolower($toEmail)) {
                        $ccList[] = strtolower($clean);
                    }
                }
            }

            $defaultCc = env('MAIL_CC_ADDRESS', null);
            if (!empty($defaultCc) && filter_var($defaultCc, FILTER_VALIDATE_EMAIL) && !in_array(strtolower($defaultCc), $ccList) && strtolower($defaultCc) !== strtolower($toEmail)) {
                $ccList[] = strtolower($defaultCc);
            }

            foreach (array_unique($ccList) as $validCc) {
                $mail->addCC($validCc);
            }

            // Resolve incoming Message-ID and Subject (with fallback to linked InboxEmail if ticket field is empty)
            $incomingMsgId = $ticket->incoming_message_id;
            $emailSubject = $ticket->email_subject;

            if (empty($incomingMsgId) || empty($emailSubject)) {
                $linkedEmail = \App\Models\InboxEmail::where('ticket_id', $ticket->id)
                    ->where('is_sent', false)
                    ->latest('id')
                    ->first();
                if ($linkedEmail) {
                    $incomingMsgId = $incomingMsgId ?: $linkedEmail->message_id;
                    $emailSubject = $emailSubject ?: $linkedEmail->subject;
                }
            }

            // Email Threading: Exact Conversation Reply Headers (RFC 2822 / RFC 5322)
            if (!empty($incomingMsgId)) {
                $cleanMsgId = '<' . trim($incomingMsgId, "<> \t\n\r\0\x0B") . '>';
                $mail->addCustomHeader('In-Reply-To', $cleanMsgId);
                $mail->addCustomHeader('References', $cleanMsgId);
            }

            // Microsoft Outlook / Exchange Threading: Thread-Topic header
            $fallbackSubject = "Ticket #{$ticket->ticket_no}" . ($ticket->customer_ref_no ? " (Bank Ref: {$ticket->customer_ref_no})" : '') . " - Assigned to Field Engineer [{$ticket->bank_name}]";
            $cleanSubject = !empty($emailSubject) ? trim($emailSubject) : $fallbackSubject;
            $threadTopic = preg_replace('/^(re|fwd?|fw):\s*/i', '', $cleanSubject);
            if (!empty($threadTopic)) {
                $mail->addCustomHeader('Thread-Topic', $threadTopic);
            }

            // Subject: Preserve exact conversation subject so email clients thread into the existing conversation
            $mail->Subject = $this->buildThreadSubject($cleanSubject, "RE: " . $fallbackSubject);

            $threadChainHtml = $this->buildThreadChainHtml($ticket);
            $threadChainText = $this->buildThreadChainText($ticket);

            // Format HTML Body
            $engineerName   = $ticket->engineer ? $ticket->engineer->name : 'Senior Field Engineer';
            $engineerPhone  = $ticket->engineer ? ($ticket->engineer->phone_whatsapp ?? 'N/A') : 'N/A';
            $engineerCity   = $ticket->engineer ? ($ticket->engineer->base_city ?? 'Regional Office') : 'Regional Office';
            $slaDeadlineFormatted = $ticket->sla_deadline ? $ticket->sla_deadline->format('d M Y, h:i A') : 'Standard SLA';
            $assignedAtFormatted = $ticket->assigned_at ? $ticket->assigned_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A');
            $branchAddressText = $ticket->branch_address ?? 'Branch on record';
            $machineInfo = ($ticket->machine_type ?? 'Cash Handling Equipment') . 
                ($ticket->machine_model ? " - Model: {$ticket->machine_model}" : '') .
                ($ticket->machine_serial_no ? " (S/N: {$ticket->machine_serial_no})" : '');

            $notesBlock = '';
            if (!empty($customNotes)) {
                $safeNotes = nl2br(htmlspecialchars($customNotes));
                $notesBlock = "<div style=\"background:#fffbeb; border-left:4px solid #f59e0b; padding:12px 16px; margin:16px 0; border-radius:0 6px 6px 0; font-size:13px; color:#92400e;\"><strong>Additional Operational Notes / Instructions:</strong><br>{$safeNotes}</div>";
            }

            $bankName = htmlspecialchars($ticket->bank_name ?? 'Bank');
            $ticketRefNo = htmlspecialchars($ticket->customer_ref_no ?: $ticket->ticket_no);
            $ticketNo = htmlspecialchars($ticket->ticket_no);
            $urgencyBadge = 'HIGH PRIORITY';
            $urgencyColor = '#9a5b00';
            $urgencyBg = '#fff4e5';
            $urgencyBorder = '#f2d19b';
            $branchDetails = htmlspecialchars($ticket->branch_name ? ($ticket->branch_name . ($ticket->branch_code ? " — Branch Code: {$ticket->branch_code}" : '') . ($ticket->branch_location ? "<br>{$ticket->branch_location}" : '')) : ($ticket->branch_location ?? 'Branch on record'));
            $contactInfo = htmlspecialchars(($ticket->customer_name ?: 'Operations') . ($ticket->customer_mobile ? " | {$ticket->customer_mobile}" : ''));

            $notesRow = '';
            if (!empty($customNotes)) {
                $safeNotes = nl2br(htmlspecialchars($customNotes));
                $notesRow = <<<NOTE
                <!-- CUSTOM OPERATIONAL REMARKS -->
                <tr>
                    <td style="padding:0 30px 20px 30px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="border-left:4px solid #f59e0b;background:#fffbeb;border-radius:4px;">
                            <tr>
                                <td style="padding:12px 16px;">
                                    <div style="font-size:11px;font-weight:bold;color:#92400e;text-transform:uppercase;letter-spacing:.5px;">Additional Operational Notes</div>
                                    <div style="margin-top:4px;font-size:13px;line-height:1.6;color:#78350f;">{$safeNotes}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
NOTE;
            }

            $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS Company Help Desk - Ticket #{$ticketNo}</title>
</head>

<body style="
    margin:0;
    padding:0;
    background-color:#f3f5f7;
    font-family:Arial, Helvetica, sans-serif;
    color:#1f2933;
">

<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background-color:#f3f5f7; padding:35px 15px;">
    <tr>
        <td align="center">

            <!-- MAIN CONTAINER -->
            <table width="680" cellpadding="0" cellspacing="0" border="0"
                   style="
                       max-width:680px;
                       width:100%;
                       background:#ffffff;
                       border:1px solid #dfe4e8;
                       border-radius:8px;
                       overflow:hidden;
                   ">

                <!-- HEADER -->
                <tr>
                    <td style="
                        background:#17212b;
                        padding:24px 30px;
                    ">

                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>

                                <td valign="middle">

                                    <div style="
                                        font-size:21px;
                                        font-weight:bold;
                                        color:#ffffff;
                                        letter-spacing:.3px;
                                    ">
                                        CMS COMPANY
                                    </div>

                                    <div style="
                                        margin-top:5px;
                                        font-size:12px;
                                        color:#b9c3cc;
                                        letter-spacing:.7px;
                                        text-transform:uppercase;
                                    ">
                                        Help Desk &amp; Technical Support
                                    </div>

                                </td>

                                <td align="right" valign="middle">

                                    <div style="
                                        font-size:11px;
                                        color:#aeb8c2;
                                        text-transform:uppercase;
                                        letter-spacing:.6px;
                                    ">
                                        Service Ticket
                                    </div>

                                    <div style="
                                        margin-top:3px;
                                        font-size:20px;
                                        font-weight:bold;
                                        color:#ffffff;
                                    ">
                                        #{$ticketNo}
                                    </div>

                                </td>

                            </tr>
                        </table>

                    </td>
                </tr>


                <!-- STATUS BAR -->
                <tr>
                    <td style="
                        padding:18px 30px;
                        border-bottom:1px solid #e5e7eb;
                        background:#fafbfc;
                    ">

                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>

                                <td valign="middle">

                                    <div style="
                                        font-size:11px;
                                        font-weight:bold;
                                        color:#66737f;
                                        letter-spacing:.7px;
                                        text-transform:uppercase;
                                    ">
                                        Engineer Assignment Notification
                                    </div>

                                    <div style="
                                        margin-top:5px;
                                        font-size:15px;
                                        font-weight:bold;
                                        color:#17212b;
                                    ">
                                        Field Support Task Assigned
                                    </div>

                                </td>

                                <td align="right" valign="middle">

                                    <span style="
                                        display:inline-block;
                                        background:{$urgencyBg};
                                        color:{$urgencyColor};
                                        border:1px solid {$urgencyBorder};
                                        border-radius:4px;
                                        padding:7px 11px;
                                        font-size:11px;
                                        font-weight:bold;
                                    ">
                                        HIGH PRIORITY
                                    </span>

                                </td>

                            </tr>
                        </table>

                    </td>
                </tr>


                <!-- INTRODUCTION -->
                <tr>
                    <td style="padding:28px 30px 18px 30px;">

                        <div style="
                            font-size:15px;
                            line-height:1.7;
                            color:#26333d;
                        ">
                            Dear <strong>{$bankName} Operations Team</strong>,
                        </div>

                        <div style="
                            margin-top:15px;
                            font-size:14px;
                            line-height:1.7;
                            color:#4b5965;
                        ">
                            this is to inform you complain has been registered with cms company help desk and assigned to a certified field support engineer.
                        </div>

                    </td>
                </tr>


                <!-- ASSIGNMENT DETAILS -->
                <tr>
                    <td style="padding:0 30px 25px 30px;">

                        <table width="100%" cellpadding="0" cellspacing="0"
                               style="
                                   border:1px solid #dfe4e8;
                                   border-radius:6px;
                                   background:#ffffff;
                               ">

                            <tr>
                                <td colspan="2"
                                    style="
                                        padding:14px 16px;
                                        background:#f5f7f9;
                                        border-bottom:1px solid #dfe4e8;
                                        font-size:12px;
                                        font-weight:bold;
                                        color:#374151;
                                        text-transform:uppercase;
                                        letter-spacing:.6px;
                                    ">
                                    Assignment Details
                                </td>
                            </tr>

                            <tr>

                                <td width="38%"
                                    style="
                                        padding:12px 16px;
                                        font-size:12px;
                                        color:#687681;
                                        border-bottom:1px solid #edf0f2;
                                    ">
                                    Assigned Field Engineer
                                </td>

                                <td
                                    style="
                                        padding:12px 16px;
                                        font-size:13px;
                                        font-weight:bold;
                                        color:#17212b;
                                        border-bottom:1px solid #edf0f2;
                                    ">
                                    {$engineerName}
                                    <span style="
                                        font-weight:normal;
                                        color:#6b7280;
                                    ">
                                        — {$engineerCity}
                                    </span>
                                </td>

                            </tr>

                            <tr>

                                <td
                                    style="
                                        padding:12px 16px;
                                        font-size:12px;
                                        color:#687681;
                                        border-bottom:1px solid #edf0f2;
                                    ">
                                    Task Status
                                </td>

                                <td
                                    style="
                                        padding:12px 16px;
                                        font-size:13px;
                                        font-weight:bold;
                                        color:#23633b;
                                        border-bottom:1px solid #edf0f2;
                                    ">
                                    ASSIGNED
                                </td>

                            </tr>

                            <tr>

                                <td
                                    style="
                                        padding:12px 16px;
                                        font-size:12px;
                                        color:#687681;
                                    ">
                                    Expected Resolution
                                </td>

                                <td
                                    style="
                                        padding:12px 16px;
                                        font-size:13px;
                                        font-weight:bold;
                                        color:#17212b;
                                    ">
                                    Within Defined TAT ({$slaDeadlineFormatted})
                                </td>

                            </tr>

                        </table>

                    </td>
                </tr>


                <!-- COMPLAINT & SITE INFORMATION -->
                <tr>
                    <td style="padding:0 30px 25px 30px;">

                        <div style="
                            margin-bottom:10px;
                            font-size:12px;
                            font-weight:bold;
                            color:#374151;
                            text-transform:uppercase;
                            letter-spacing:.6px;
                        ">
                            Complaint &amp; Site Information
                        </div>

                        <table width="100%" cellpadding="0" cellspacing="0"
                               style="
                                   border:1px solid #dfe4e8;
                                   border-radius:6px;
                               ">

                            <!-- Complaint -->
                            <tr>

                                <td width="38%"
                                    style="
                                        padding:11px 14px;
                                        background:#fafbfc;
                                        border-bottom:1px solid #e8ecef;
                                        font-size:12px;
                                        color:#687681;
                                    ">
                                    Complaint Reference
                                </td>

                                <td
                                    style="
                                        padding:11px 14px;
                                        border-bottom:1px solid #e8ecef;
                                        font-size:13px;
                                        font-weight:bold;
                                        color:#17212b;
                                    ">
                                    Complaint #{$ticketRefNo}
                                </td>

                            </tr>


                            <!-- Bank / Branch -->
                            <tr>

                                <td
                                    style="
                                        padding:11px 14px;
                                        background:#fafbfc;
                                        border-bottom:1px solid #e8ecef;
                                        font-size:12px;
                                        color:#687681;
                                    ">
                                    Bank / Branch
                                </td>

                                <td
                                    style="
                                        padding:11px 14px;
                                        border-bottom:1px solid #e8ecef;
                                        font-size:13px;
                                        color:#17212b;
                                    ">
                                    <strong>{$bankName}</strong><br>
                                    {$branchDetails}
                                </td>

                            </tr>


                            <!-- Address -->
                            <tr>

                                <td
                                    style="
                                        padding:11px 14px;
                                        background:#fafbfc;
                                        border-bottom:1px solid #e8ecef;
                                        font-size:12px;
                                        color:#687681;
                                    ">
                                    Branch Address
                                </td>

                                <td
                                    style="
                                        padding:11px 14px;
                                        border-bottom:1px solid #e8ecef;
                                        font-size:13px;
                                        line-height:1.5;
                                        color:#17212b;
                                    ">
                                    {$branchAddressText}
                                </td>

                            </tr>


                            <!-- Machine -->
                            <tr>

                                <td
                                    style="
                                        padding:11px 14px;
                                        background:#fafbfc;
                                        border-bottom:1px solid #e8ecef;
                                        font-size:12px;
                                        color:#687681;
                                    ">
                                    Machine
                                </td>

                                <td
                                    style="
                                        padding:11px 14px;
                                        border-bottom:1px solid #e8ecef;
                                        font-size:13px;
                                        color:#17212b;
                                    ">
                                    {$machineInfo}
                                </td>

                            </tr>


                            <!-- Contact -->
                            <tr>

                                <td
                                    style="
                                        padding:11px 14px;
                                        background:#fafbfc;
                                        font-size:12px;
                                        color:#687681;
                                    ">
                                    Branch Contact
                                </td>

                                <td
                                    style="
                                        padding:11px 14px;
                                        font-size:13px;
                                        color:#17212b;
                                    ">
                                    {$contactInfo}
                                </td>

                            </tr>

                        </table>

                    </td>
                </tr>

                {$notesRow}

                <!-- ACTION REQUIRED -->
                <tr>
                    <td style="padding:0 30px 28px 30px;">

                        <table width="100%" cellpadding="0" cellspacing="0"
                               style="
                                   border-left:4px solid #263746;
                                   background:#f5f7f9;
                               ">

                            <tr>

                                <td style="padding:15px 17px;">

                                    <div style="
                                        font-size:12px;
                                        font-weight:bold;
                                        color:#263746;
                                        text-transform:uppercase;
                                        letter-spacing:.5px;
                                    ">
                                        Branch Coordination Required
                                    </div>

                                    <div style="
                                        margin-top:6px;
                                        font-size:13px;
                                        line-height:1.6;
                                        color:#4b5965;
                                    ">
                                        Please facilitate the necessary branch
                                        security and premises access for the
                                        designated CMS field engineer ({$engineerName}) to carry
                                        out the assigned service task.
                                    </div>

                                </td>

                            </tr>

                        </table>

                    </td>
                </tr>


                <!-- CLOSING -->
                <tr>

                    <td style="
                        padding:0 30px 28px 30px;
                        font-size:13px;
                        line-height:1.7;
                        color:#4b5965;
                    ">

                        Our technical team will coordinate with the branch
                        representative and proceed with the required service
                        activity in accordance with the applicable service
                        turnaround time.

                        <br><br>

                        Regards,<br>

                        <strong style="color:#17212b;">
                            CMS Company Help Desk
                        </strong><br>

                        <span style="
                            font-size:12px;
                            color:#7b8790;
                        ">
                            Technical Support &amp; Field Operations
                        </span>

                    </td>

                </tr>


                <!-- FOOTER -->
                <tr>

                    <td style="
                        background:#17212b;
                        padding:18px 30px;
                    ">

                        <table width="100%" cellpadding="0" cellspacing="0">

                            <tr>

                                <td style="
                                    font-size:11px;
                                    line-height:1.6;
                                    color:#aeb8c2;
                                ">
                                    This is an automated notification generated
                                    by the <strong style="color:#d7dde2;">
                                    CMS Company Help Desk</strong>.
                                    <br>
                                    Please do not reply to this automated
                                    notification.
                                </td>

                                <td align="right"
                                    valign="middle"
                                    style="
                                        font-size:11px;
                                        color:#8996a1;
                                        white-space:nowrap;
                                    ">
                                    Ticket #{$ticketNo}
                                </td>

                            </tr>

                        </table>

                    </td>

                </tr>

            </table>

            <!-- THREAD CONVERSATION HISTORY & EMAIL CHAIN -->
            <div style="max-width:680px; width:100%; margin:18px auto 0 auto; text-align:left;">
                {$threadChainHtml}
            </div>

            <!-- OUTSIDE FOOTER -->

            <div style="
                max-width:680px;
                margin-top:14px;
                font-size:10px;
                line-height:1.5;
                color:#8a959e;
                text-align:center;
            ">
                CMS Company Help Desk
                &nbsp;•&nbsp;
                Technical Support
                &nbsp;•&nbsp;
                Automated Service Notification
            </div>

        </td>
    </tr>
</table>

</body>
</html>
HTML;

            $mail->isHTML(true);
            $mail->Body    = $html;
            $mail->AltBody = "Dear {$bankName} Operations Team,\n\nYour complaint has been assigned to Field Engineer: {$engineerName} ({$engineerCity}).\nTicket No: {$ticketNo}\nPriority: {$urgencyBadge}\nExpected Resolution: Within Defined TAT\nCMS Company Support." . $threadChainText;

            $mail->send();
            $messageId = $mail->getLastMessageID() ?: ('<' . uniqid('msg.') . '@' . env('MAIL_HOST_DOMAIN', 'cmscompany.biz') . '>');

            // Save sent copy to IMAP Sent folder so Outlook / Webmail displays the reply in Sent Items & Conversation threads
            $this->saveToImapSentFolder($mail);

            Log::info("PHPMailer: Assignment email dispatched for ticket {$ticket->ticket_no} to {$toEmail} with ID {$messageId}");

            return [
                'success' => true,
                'message_id' => $messageId,
                'recipient' => $toEmail,
                'thread_chain_html' => $threadChainHtml,
                'thread_chain_text' => $threadChainText,
            ];
        } catch (PHPMailerException $e) {
            Log::error("PHPMailer Exception for ticket {$ticket->ticket_no}: " . $mail->ErrorInfo);
            return [
                'success' => false,
                'error' => $mail->ErrorInfo ?: $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            Log::error("MailService Assignment Email Exception: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send official resolution email to bank, CC'ing the same addresses, threaded as a reply,
     * and attaching the supporting document / photo uploaded by the field engineer.
     */
    public function sendResolutionEmailReply(Ticket $ticket, ?string $customNotes = null): array
    {
        if (app()->environment('testing')) {
            $chainHtml = $this->buildThreadChainHtml($ticket);
            $chainText = $this->buildThreadChainText($ticket);
            return [
                'success' => true,
                'message_id' => '<test-res.' . uniqid() . '@cmscompany.biz>',
                'recipient' => $ticket->customer_email ?: 'operations@bank.com.pk',
                'thread_chain_html' => $chainHtml,
                'thread_chain_text' => $chainText,
            ];
        }

        $mail = new PHPMailer(true);

        try {
            $this->configureSmtp($mail);

            $toEmail = trim($ticket->customer_email ?: 'operations@bank.com.pk');
            $mail->addAddress($toEmail, $ticket->customer_name ?: ($ticket->bank_name . ' Representative'));

            // Cc recipients: Original bank email thread CCs alongside default operations CC
            $ccList = [];
            if (!empty($ticket->customer_cc)) {
                $exploded = preg_split('/[,;\s]+/', $ticket->customer_cc);
                foreach ($exploded as $c) {
                    $clean = trim($c, " <>\"'");
                    if (filter_var($clean, FILTER_VALIDATE_EMAIL) && strtolower($clean) !== strtolower($toEmail)) {
                        $ccList[] = strtolower($clean);
                    }
                }
            }

            $defaultCc = env('MAIL_CC_ADDRESS', null);
            if (!empty($defaultCc) && filter_var($defaultCc, FILTER_VALIDATE_EMAIL) && !in_array(strtolower($defaultCc), $ccList) && strtolower($defaultCc) !== strtolower($toEmail)) {
                $ccList[] = strtolower($defaultCc);
            }

            foreach (array_unique($ccList) as $validCc) {
                $mail->addCC($validCc);
            }

            // Resolve incoming Message-ID and Subject (with fallback to linked InboxEmail if ticket field is empty)
            $incomingMsgId = $ticket->incoming_message_id;
            $emailSubject = $ticket->email_subject;

            if (empty($incomingMsgId) || empty($emailSubject)) {
                $linkedEmail = \App\Models\InboxEmail::where('ticket_id', $ticket->id)
                    ->where('is_sent', false)
                    ->latest('id')
                    ->first();
                if ($linkedEmail) {
                    $incomingMsgId = $incomingMsgId ?: $linkedEmail->message_id;
                    $emailSubject = $emailSubject ?: $linkedEmail->subject;
                }
            }

            // RFC Threading: Keep reply in the exact same conversation thread
            // References chain includes incoming message and assignment confirmation reply
            $refIds = [];
            if (!empty($incomingMsgId)) {
                $cleanIncoming = '<' . trim($incomingMsgId, "<> \t\n\r\0\x0B") . '>';
                $refIds[] = $cleanIncoming;
            }
            if (!empty($ticket->email_reply_message_id)) {
                $replyId = '<' . trim($ticket->email_reply_message_id, "<> \t\n\r\0\x0B") . '>';
                $refIds[] = $replyId;
            }

            if (!empty($refIds)) {
                $lastMsgId = end($refIds);
                $mail->addCustomHeader('In-Reply-To', $lastMsgId);
                $mail->addCustomHeader('References', implode(' ', array_unique($refIds)));
            }

            // Microsoft Outlook / Exchange Threading: Thread-Topic header
            $fallbackSubject = "Ticket #{$ticket->ticket_no} - RESOLVED - {$ticket->bank_name} {$ticket->branch_name}";
            $cleanSubject = !empty($emailSubject) ? trim($emailSubject) : $fallbackSubject;
            $threadTopic = preg_replace('/^(re|fwd?|fw):\s*/i', '', $cleanSubject);
            if (!empty($threadTopic)) {
                $mail->addCustomHeader('Thread-Topic', $threadTopic);
            }

            // Subject: Preserve exact thread subject so mail clients thread together
            $mail->Subject = $this->buildThreadSubject($cleanSubject, "RE: " . $fallbackSubject);

            $threadChainHtml = $this->buildThreadChainHtml($ticket);
            $threadChainText = $this->buildThreadChainText($ticket);

            // Attach supporting document / picture if uploaded by engineer
            if (!empty($ticket->supporting_document)) {
                $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($ticket->supporting_document);
                if (file_exists($fullPath)) {
                    $attachmentName = $ticket->resolution_document_name ?: basename($fullPath);
                    $mail->addAttachment($fullPath, $attachmentName);
                }
            }

            $engineerName = $ticket->engineer ? $ticket->engineer->name : 'Field Engineer';
            $resolutionSummaryText = $ticket->resolution_summary ?: 'Maintenance and corrective service successfully completed on-site. Machine calibrated and verified operational.';
            $machineInfo = ($ticket->machine_type ?? 'Banking Device') . 
                ($ticket->machine_model ? " - Model: {$ticket->machine_model}" : '') .
                ($ticket->machine_serial_no ? " (S/N: {$ticket->machine_serial_no})" : '');

            $attachmentNotice = '';
            if (!empty($ticket->supporting_document)) {
                $attachmentNotice = "<div style=\"background:#eff6ff; border:1px solid #bfdbfe; padding:10px 14px; margin:14px 0; border-radius:6px; font-size:12px; color:#1e40af;\">📎 <strong>Attached Supporting Proof:</strong> " . htmlspecialchars($ticket->resolution_document_name ?: 'Service Slip / Completion Photo') . "</div>";
            }

            $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; color: #333; }
  .card { max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
  .header { background: #065f46; color: #ffffff; padding: 24px 30px; }
  .header h2 { margin: 0 0 6px 0; font-size: 20px; font-weight: 600; color: #f8fafc; }
  .header p { margin: 0; font-size: 13px; color: #a7f3d0; }
  .badge { display: inline-block; background: #059669; color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-top: 8px; }
  .body { padding: 30px; line-height: 1.6; font-size: 14px; }
  .table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
  .table th, .table td { padding: 10px 14px; border-bottom: 1px solid #e2e8f0; text-align: left; }
  .table th { background: #f8fafc; color: #475569; width: 35%; font-weight: 600; }
  .table td { color: #1e293b; }
  .highlight-box { background: #ecfdf5; border-left: 4px solid #10b981; padding: 14px; margin: 18px 0; border-radius: 0 6px 6px 0; color: #065f46; }
  .footer { background: #f8fafc; padding: 18px 30px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: center; }
</style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h2>CMS Technical Operations Desk</h2>
      <p>Official Service Resolution &amp; Handover Notification</p>
      <div class="badge">Ticket #{$ticket->ticket_no} &bull; RESOLVED</div>
    </div>
    <div class="body">
      <p>Dear <strong>{$ticket->bank_name} Operations Team</strong>,</p>
      <p>We are pleased to inform you that Complaint Ticket #<strong>{$ticket->ticket_no}</strong> has been successfully <strong>RESOLVED</strong> by our designated field engineer.</p>
      
      <div class="highlight-box">
        <strong>Service Resolution Summary:</strong><br>
        {$resolutionSummaryText}
      </div>

      <table class="table">
        <tr>
          <th>Bank &amp; Branch</th>
          <td>{$ticket->bank_name} - {$ticket->branch_name} ({$ticket->branch_location})</td>
        </tr>
        <tr>
          <th>Machine Hardware</th>
          <td>{$machineInfo}</td>
        </tr>
        <tr>
          <th>Servicing Engineer</th>
          <td>{$engineerName}</td>
        </tr>
        <tr>
          <th>Current Status</th>
          <td><strong style="color: #059669; text-transform: uppercase;">COMPLETED &amp; RESOLVED</strong></td>
        </tr>
      </table>

      {$attachmentNotice}

      <p style="font-size: 13px; color: #475569; margin-top: 20px;">
        The machine has been returned to operational status and signed off with branch personnel. Please find the attached FSR report / service resolution slip. If you require further support send email to <strong><a href="mailto:support@cmscompany.biz" style="color:#0284c7; text-decoration:none;">support@cmscompany.biz</a></strong>.
      </p>

      <p style="margin-top: 24px;">
        Warm regards,<br>
        <strong>CMS Customer Service &amp; Field Engineering Operations</strong><br>
        CMS Company Pakistan
      </p>

      <!-- THREAD CONVERSATION HISTORY & EMAIL CHAIN -->
      {$threadChainHtml}
    </div>
    <div class="footer">
      <strong>Notice:</strong> This is an automated, system-generated service resolution notification from CMS Bank Complaint Manager Help Desk.<br>
      Support: support@cmscompany.biz | UAN: 0333-3453664
    </div>
  </div>
</body>
</html>
HTML;

            $mail->isHTML(true);
            $mail->Body    = $html;
            $mail->AltBody = "Dear {$ticket->bank_name} Operations Team,\n\nComplaint Ticket #{$ticket->ticket_no} has been RESOLVED.\nWork Summary: {$resolutionSummaryText}\nResolved By: {$engineerName}\n\nIf you require further support send email to support@cmscompany.biz\n\nCMS Technical Operations Support." . $threadChainText;

            $mail->send();
            $messageId = $mail->getLastMessageID() ?: ('<' . uniqid('res.') . '@' . env('MAIL_HOST_DOMAIN', 'cmscompany.biz') . '>');

            // Save sent copy to IMAP Sent folder so Outlook / Webmail displays the reply in Sent Items & Conversation threads
            $this->saveToImapSentFolder($mail);

            Log::info("PHPMailer: Resolution email dispatched for ticket {$ticket->ticket_no} to {$toEmail} with ID {$messageId}");

            return [
                'success' => true,
                'message_id' => $messageId,
                'recipient' => $toEmail,
                'thread_chain_html' => $threadChainHtml,
                'thread_chain_text' => $threadChainText,
            ];
        } catch (PHPMailerException $e) {
            Log::error("PHPMailer Resolution Email Exception for ticket {$ticket->ticket_no}: " . $mail->ErrorInfo);
            return [
                'success' => false,
                'error' => $mail->ErrorInfo ?: $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            Log::error("MailService Resolution Email Exception: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Collect all conversation messages for a ticket in chronological order.
     * Ensures any recipient (especially newly added CCs) has complete visibility into the full email chain.
     */
    public function collectThreadChainEntries(Ticket $ticket): array
    {
        $entries = [];

        // 1. Gather messages from InboxEmail
        $inboxEmails = \App\Models\InboxEmail::where('ticket_id', $ticket->id)
            ->orderBy('email_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $hasIncoming = false;

        foreach ($inboxEmails as $msg) {
            $isIncoming = !$msg->is_sent;
            if ($isIncoming) {
                $hasIncoming = true;
            }

            $fromDisplay = $msg->from_name
                ? "{$msg->from_name} <{$msg->from_email}>"
                : ($msg->from_email ?: 'Technical Desk');

            $dateDisplay = $msg->email_date
                ? $msg->email_date->format('l, d F Y, h:i A')
                : ($msg->created_at ? $msg->created_at->format('l, d F Y, h:i A') : 'N/A');

            $entries[] = [
                'from'        => $fromDisplay,
                'date'        => $dateDisplay,
                'to'          => $msg->to_email ?: 'CMS Technical Operations <support@cmscompany.biz>',
                'cc'          => $msg->cc_emails,
                'subject'     => $msg->subject ?: ($ticket->email_subject ?: "Ticket #{$ticket->ticket_no}"),
                'body_html'   => !empty($msg->body_html) ? $msg->body_html : nl2br(htmlspecialchars($msg->body_text ?: '')),
                'body_text'   => $msg->body_text ?: strip_tags($msg->body_html ?? ''),
                'is_incoming' => $isIncoming,
            ];
        }

        // 2. If no incoming email was found in InboxEmail (e.g. ticket entered directly or fallback),
        // add the ticket's original logged complaint as the root email in the chain
        if (!$hasIncoming) {
            $senderEmail = $ticket->customer_email ?: 'operations@bank.com.pk';
            $senderName = $ticket->customer_name ?: ($ticket->bank_name . ' Representative');
            $senderDisplay = "{$senderName} <{$senderEmail}>";

            $dateDisplay = $ticket->created_at
                ? $ticket->created_at->format('l, d F Y, h:i A')
                : now()->format('l, d F Y, h:i A');

            $complaintBody = $ticket->issue_description
                ?: ($ticket->issue_summary ?: "Complaint reported for {$ticket->machine_type} at {$ticket->branch_name}.");

            $subject = $ticket->email_subject
                ?: "Complaint: {$ticket->machine_type} - {$ticket->bank_name} {$ticket->branch_name} (Ref: " . ($ticket->customer_ref_no ?: $ticket->ticket_no) . ")";

            // Prepend original bank email to the very top of the historical chain
            array_unshift($entries, [
                'from'        => $senderDisplay,
                'date'        => $dateDisplay,
                'to'          => 'CMS Company Help Desk <support@cmscompany.biz>',
                'cc'          => $ticket->customer_cc,
                'subject'     => $subject,
                'body_html'   => '<p style="margin:0 0 8px 0;">' . nl2br(htmlspecialchars($complaintBody)) . '</p>' .
                                 '<div style="font-size:11px; color:#64748b; margin-top:6px;"><strong>Terminal / Branch:</strong> ' . htmlspecialchars($ticket->branch_name . ' (' . $ticket->branch_location . ')') . ' &bull; <strong>Machine:</strong> ' . htmlspecialchars($ticket->machine_type . ' ' . $ticket->machine_model . ' S/N: ' . $ticket->machine_serial_no) . '</div>',
                'body_text'   => $complaintBody . "\nTerminal: {$ticket->branch_name} ({$ticket->branch_location}) | Machine: {$ticket->machine_type} {$ticket->machine_model} (S/N: {$ticket->machine_serial_no})",
                'is_incoming' => true,
            ]);
        }

        return $entries;
    }

    /**
     * Build the chronological conversation thread chain HTML for embedding in email replies.
     * Ensures any recipient (especially newly added CCs) has complete visibility into the full email chain.
     */
    public function buildThreadChainHtml(Ticket $ticket): string
    {
        $chainEntries = $this->collectThreadChainEntries($ticket);
        if (empty($chainEntries)) {
            return '';
        }

        $html = '<div style="margin-top:28px; padding-top:20px; border-top:2px dashed #94a3b8; font-family: Segoe UI, Arial, sans-serif;">';
        $html .= '<div style="font-size:12px; font-weight:700; color:#334155; margin-bottom:14px; text-transform:uppercase; letter-spacing:0.5px;">';
        $html .= '<span>──── Previous Conversation History &amp; Message Chain (' . count($chainEntries) . ' Messages) ────</span>';
        $html .= '</div>';

        // Loop entries in reverse order (most recent first, down to original message) as standard corporate email replies format
        $reversed = array_reverse($chainEntries);
        foreach ($reversed as $entry) {
            $fromStr = htmlspecialchars($entry['from']);
            $sentStr = htmlspecialchars($entry['date']);
            $toStr = htmlspecialchars($entry['to']);
            $ccStr = !empty($entry['cc']) ? htmlspecialchars($entry['cc']) : '';
            $subjectStr = htmlspecialchars($entry['subject']);
            $bodyHtml = $entry['body_html'];

            $isIncoming = $entry['is_incoming'] ?? true;
            $borderColor = $isIncoming ? '#0284c7' : '#059669';
            $badgeColor = $isIncoming ? '#0369a1' : '#047857';
            $badgeBg = $isIncoming ? '#e0f2fe' : '#d1fae5';
            $badgeText = $isIncoming ? 'Bank Complaint Message' : 'CMS Operations Dispatch';

            $html .= '<div style="margin-bottom:18px; border-left:4px solid ' . $borderColor . '; padding-left:14px; background:#f8fafc; border-radius:0 8px 8px 0; padding-top:12px; padding-bottom:12px; border-top:1px solid #e2e8f0; border-right:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0;">';
            $html .= '<div style="margin-bottom:8px;">';
            $html .= '<span style="display:inline-block; font-size:10px; font-weight:bold; text-transform:uppercase; padding:2px 8px; border-radius:4px; color:' . $badgeColor . '; background:' . $badgeBg . ';">' . $badgeText . '</span>';
            $html .= '</div>';
            $html .= '<div style="font-size:12px; line-height:1.6; color:#475569; border-bottom:1px solid #e2e8f0; padding-bottom:8px; margin-bottom:10px;">';
            $html .= '<strong>From:</strong> ' . $fromStr . '<br>';
            $html .= '<strong>Sent:</strong> ' . $sentStr . '<br>';
            $html .= '<strong>To:</strong> ' . $toStr . '<br>';
            if ($ccStr) {
                $html .= '<strong>Cc:</strong> <span style="color:#0284c7;">' . $ccStr . '</span><br>';
            }
            $html .= '<strong>Subject:</strong> ' . $subjectStr . '';
            $html .= '</div>';
            $html .= '<div style="font-size:13px; line-height:1.6; color:#1e293b;">' . $bodyHtml . '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Build the plain-text chronological thread chain for AltBody.
     */
    public function buildThreadChainText(Ticket $ticket): string
    {
        $chainEntries = $this->collectThreadChainEntries($ticket);
        if (empty($chainEntries)) {
            return '';
        }

        $text = "\n\n=======================================================\n";
        $text .= "PREVIOUS CONVERSATION HISTORY & MESSAGE CHAIN (" . count($chainEntries) . " Messages)\n";
        $text .= "=======================================================\n";

        $reversed = array_reverse($chainEntries);
        foreach ($reversed as $entry) {
            $text .= "\n----- Original Message -----\n";
            $text .= "From: {$entry['from']}\n";
            $text .= "Sent: {$entry['date']}\n";
            $text .= "To: {$entry['to']}\n";
            if (!empty($entry['cc'])) {
                $text .= "Cc: {$entry['cc']}\n";
            }
            $text .= "Subject: {$entry['subject']}\n\n";
            $text .= strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $entry['body_text'] ?: $entry['body_html'])) . "\n";
            $text .= "-------------------------------------------------------\n";
        }

        return $text;
    }

    /**
     * Send direct email (new compose or reply to thread) via GoDaddy SMTP.
     */
    public function sendDirectMail(string $toEmail, string $subject, string $bodyText, ?string $inReplyTo = null, ?string $references = null): array
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = env('MAIL_HOST', 'mail.cmscompany.biz');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('MAIL_USERNAME', 'support@cmscompany.biz');
            $mail->Password   = env('MAIL_PASSWORD', '');
            
            $encryption = strtolower(env('MAIL_ENCRYPTION', 'ssl'));
            if ($encryption === 'ssl' || (int) env('MAIL_PORT', 465) === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port = (int) env('MAIL_PORT', 465);

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            $fromAddress = env('MAIL_FROM_ADDRESS', 'support@cmscompany.biz');
            $fromName    = env('MAIL_FROM_NAME', 'CMS Technical Operations Desk');
            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress(trim($toEmail));

            // Threading headers
            if (!empty($inReplyTo)) {
                $cleanMsgId = '<' . trim($inReplyTo, "<> \t\n\r\0\x0B") . '>';
                $mail->addCustomHeader('In-Reply-To', $cleanMsgId);
                
                $cleanRefs = !empty($references) ? trim($references) : $cleanMsgId;
                if (!str_starts_with($cleanRefs, '<')) {
                    $cleanRefs = '<' . $cleanRefs . '>';
                }
                $mail->addCustomHeader('References', $cleanRefs);
            }

            // Microsoft Outlook / Exchange Threading: Thread-Topic header
            $threadTopic = preg_replace('/^(re|fwd?|fw):\s*/i', '', trim($subject));
            if (!empty($threadTopic)) {
                $mail->addCustomHeader('Thread-Topic', $threadTopic);
            }

            $mail->Hostname = env('MAIL_HOST_DOMAIN', 'cmscompany.biz');

            $mail->Subject = $subject;
            $mail->isHTML(true);

            $htmlFormatted = nl2br(htmlspecialchars($bodyText));
            $mail->Body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; font-size: 14px; color: #1e293b; line-height: 1.6; padding: 20px;">
    {$htmlFormatted}
    <br><br>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">
    <p style="font-size: 12px; color: #64748b;">
        <strong>CMS Technical Operations Desk</strong><br>
        Support: support@cmscompany.biz | UAN: 0333-3453664
    </p>
</body>
</html>
HTML;
            $mail->AltBody = $bodyText;

            $mail->send();
            $messageId = $mail->getLastMessageID() ?: ('<' . uniqid('msg.') . '@' . env('MAIL_HOST_DOMAIN', 'cmscompany.biz') . '>');

            // Save sent copy to IMAP Sent folder so Outlook / Webmail displays the reply in Sent Items & Conversation threads
            $this->saveToImapSentFolder($mail);

            Log::info("PHPMailer: Direct email sent to {$toEmail} with Subject: '{$subject}', Message ID: {$messageId}");

            return [
                'success' => true,
                'message_id' => $messageId,
            ];
        } catch (PHPMailerException $e) {
            Log::error("PHPMailer Exception in sendDirectMail: " . $mail->ErrorInfo);
            return [
                'success' => false,
                'error' => $mail->ErrorInfo ?: $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            Log::error("sendDirectMail General Exception: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Common SMTP Configuration Helper for PHPMailer
     */
    protected function configureSmtp(PHPMailer $mail): void
    {
        $mail->isSMTP();
        $mail->Host       = env('MAIL_HOST', 'mail.cmscompany.biz');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('MAIL_USERNAME', 'support@cmscompany.biz');
        $mail->Password   = env('MAIL_PASSWORD', '');
        
        $encryption = strtolower(env('MAIL_ENCRYPTION', 'ssl'));
        if ($encryption === 'ssl' || (int) env('MAIL_PORT', 465) === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port = (int) env('MAIL_PORT', 465);

        // SSL verification bypass for local environments / self-signed certs
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        // Set Hostname for Message-ID and HELO so Message-ID domain is valid and not @127.0.0.1
        $mail->Hostname = env('MAIL_HOST_DOMAIN', 'cmscompany.biz');

        // Default Sender
        $fromAddress = env('MAIL_FROM_ADDRESS', 'support@cmscompany.biz');
        $fromName    = env('MAIL_FROM_NAME', 'CMS Technical Operations Desk');
        $mail->setFrom($fromAddress, $fromName);

        // Optional Auto-BCC sender so mailbox receives a direct copy in INBOX for instantaneous Outlook thread linking
        if (filter_var(env('MAIL_AUTO_BCC_SENDER', true), FILTER_VALIDATE_BOOLEAN)) {
            if (filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
                $mail->addBCC($fromAddress);
            }
        }
    }

    /**
     * Standardize thread subject preserving exact conversation title with RE: prefix.
     */
    public function buildThreadSubject(?string $rawSubject, string $fallback): string
    {
        if (empty($rawSubject)) {
            return $fallback;
        }

        $clean = trim($rawSubject);
        if (preg_match('/^re:\s*/i', $clean)) {
            return $clean;
        }

        return 'RE: ' . $clean;
    }

    /**
     * Appends the sent MIME message to the sender's IMAP Sent folder (INBOX.Sent)
     * so that Microsoft Outlook and other IMAP clients see the sent reply in their
     * Sent Items folder and automatically link it into the conversation thread.
     */
    public function saveToImapSentFolder(PHPMailer $mail): bool
    {
        if (!extension_loaded('imap')) {
            Log::warning("MailService: PHP imap extension is not loaded; cannot append sent email to IMAP Sent folder.");
            return false;
        }

        $host = env('IMAP_HOST', env('MAIL_HOST', 'mail.cmscompany.biz'));
        $port = (int) env('IMAP_PORT', 993);
        $encryption = strtolower(env('IMAP_ENCRYPTION', 'ssl'));
        $username = env('IMAP_USERNAME', env('MAIL_USERNAME', 'support@cmscompany.biz'));
        $password = env('IMAP_PASSWORD', env('MAIL_PASSWORD', ''));

        if (empty($username) || empty($password)) {
            Log::warning("MailService: Missing IMAP credentials; cannot append to IMAP Sent.");
            return false;
        }

        try {
            if (function_exists('imap_timeout')) {
                @imap_timeout(IMAP_OPENTIMEOUT, 5);
            }

            $baseMailbox = "{" . "{$host}:{$port}/imap/{$encryption}/novalidate-cert}";

            // Connect to IMAP
            $stream = @imap_open($baseMailbox . "INBOX", $username, $password, 0, 1);
            if (!$stream) {
                $stream = @imap_open($baseMailbox, $username, $password, 0, 1);
            }

            if (!$stream) {
                Log::warning("MailService: Could not connect to IMAP server to save sent email: " . imap_last_error());
                return false;
            }

            // Determine Sent folder name (INBOX.Sent on cPanel/Dovecot, or Sent/Sent Items)
            $targetFolder = $baseMailbox . "INBOX.Sent";
            $folders = @imap_list($stream, $baseMailbox, "*");
            if (is_array($folders)) {
                $candidates = ['INBOX.Sent', 'Sent', 'Sent Items', 'INBOX.Sent Items', 'Sent Messages'];
                foreach ($candidates as $cand) {
                    $fullCand = $baseMailbox . $cand;
                    if (in_array($fullCand, $folders, true)) {
                        $targetFolder = $fullCand;
                        break;
                    }
                }
            }

            $mime = $mail->getSentMIMEMessage();
            if (empty($mime)) {
                Log::warning("MailService: getSentMIMEMessage() returned empty string; skipping IMAP append.");
                @imap_close($stream);
                return false;
            }

            $appended = @imap_append($stream, $targetFolder, $mime, "\\Seen");
            @imap_close($stream);

            if ($appended) {
                Log::info("MailService: Sent email copy successfully appended to IMAP folder: {$targetFolder}");
                return true;
            } else {
                Log::warning("MailService: imap_append failed for folder {$targetFolder}: " . imap_last_error());
                return false;
            }
        } catch (\Throwable $e) {
            Log::warning("MailService: Exception while saving sent email to IMAP: " . $e->getMessage());
            return false;
        }
    }
}
