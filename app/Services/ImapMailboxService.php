<?php

namespace App\Services;

use App\Models\InboxEmail;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ImapMailboxService
{
    public function syncMailbox(int $limit = 40): array
    {
        $host = env('IMAP_HOST', 'mail.qmstraders.com');
        $port = env('IMAP_PORT', 993);
        $encryption = env('IMAP_ENCRYPTION', 'ssl');
        $username = env('IMAP_USERNAME', 'noreply@qmstraders.com');
        $password = env('IMAP_PASSWORD', 'Nope1seem@1');

        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'error' => 'IMAP credentials not configured in .env',
                'new_count' => 0,
            ];
        }

        $mailbox = "{" . "{$host}:{$port}/imap/{$encryption}/novalidate-cert}INBOX";

        // Open IMAP stream
        $inbox = @imap_open($mailbox, $username, $password);

        if (!$inbox) {
            $err = imap_last_error();
            Log::error("ImapMailboxService: Connection failure: {$err}");
            return [
                'success' => false,
                'error' => "Failed to connect to GoDaddy IMAP server: {$err}",
                'new_count' => 0,
            ];
        }

        $totalMsgs = imap_num_msg($inbox);
        if ($totalMsgs === 0) {
            imap_close($inbox);
            return [
                'success' => true,
                'message' => 'Mailbox is empty.',
                'new_count' => 0,
                'total' => 0,
            ];
        }

        $startMsg = max(1, $totalMsgs - $limit + 1);
        $newCount = 0;

        // Loop from newest to oldest
        for ($mailId = $totalMsgs; $mailId >= $startMsg; $mailId--) {
            $header = @imap_headerinfo($inbox, $mailId);
            if (!$header) continue;

            $msgUid = (string) imap_uid($inbox, $mailId);
            $rawMsgId = $header->message_id ?? null;
            $cleanMsgId = $rawMsgId ? trim($rawMsgId, "<> \t\n\r\0\x0B") : null;

            $subject = isset($header->subject) ? mb_decode_mimeheader($header->subject) : '(No Subject)';
            $fromEmail = (isset($header->from[0]->mailbox, $header->from[0]->host))
                ? strtolower($header->from[0]->mailbox . '@' . $header->from[0]->host)
                : (isset($header->from[0]->mailbox) ? strtolower($header->from[0]->mailbox) : 'unknown@bank.com');
            $fromName = isset($header->from[0]->personal) ? mb_decode_mimeheader($header->from[0]->personal) : $fromEmail;
            $toEmail = (isset($header->to[0]->mailbox, $header->to[0]->host))
                ? strtolower($header->to[0]->mailbox . '@' . $header->to[0]->host)
                : (isset($header->to[0]->mailbox) ? strtolower($header->to[0]->mailbox) : $username);
            $emailDate = isset($header->udate) ? Carbon::createFromTimestamp($header->udate) : Carbon::now();
            $isRead = ($header->Unseen !== 'U' && $header->Recent !== 'R');

            // Extract CC recipients
            $ccAddresses = [];
            if (!empty($header->cc) && is_array($header->cc)) {
                foreach ($header->cc as $ccItem) {
                    if (isset($ccItem->mailbox) && isset($ccItem->host)) {
                        $addr = strtolower(trim($ccItem->mailbox . '@' . $ccItem->host));
                        if ($addr !== strtolower($username)) {
                            $ccAddresses[] = $addr;
                        }
                    }
                }
            }
            $ccString = !empty($ccAddresses) ? implode(', ', array_unique($ccAddresses)) : null;

            // Check if already stored in database
            $existing = null;
            if ($cleanMsgId) {
                $existing = InboxEmail::where('message_id', $cleanMsgId)->first();
            }
            if (!$existing && $msgUid) {
                $existing = InboxEmail::where('uid', $msgUid)->first();
            }

            if ($existing) {
                // Backfill cc_emails if empty
                if (empty($existing->cc_emails) && !empty($ccString)) {
                    $existing->update(['cc_emails' => $ccString]);
                }
                // Update read status or ticket link if not set
                if (empty($existing->ticket_id)) {
                    $matchedTicket = Ticket::where('incoming_message_id', 'like', "%{$cleanMsgId}%")->first();
                    if ($matchedTicket) {
                        $existing->update(['ticket_id' => $matchedTicket->id]);
                    }
                }
                continue;
            }

            // Extract body parts (both text and html)
            $bodyText = $this->getMailBodyText($inbox, $mailId);
            $bodyHtml = $this->getMailBodyHtml($inbox, $mailId);

            if (empty($bodyText) && !empty($bodyHtml)) {
                $bodyText = trim(strip_tags($bodyHtml));
            }

            // Check if any Ticket was already created for this email
            $matchedTicket = null;
            if ($cleanMsgId) {
                $matchedTicket = Ticket::where('incoming_message_id', 'like', "%{$cleanMsgId}%")->first();
            }
            if (!$matchedTicket && !empty($subject)) {
                // Thread reply match: exact subject match with same sender
                $cleanSubject = preg_replace('/^(?:re|fwd|fw):\s*/i', '', trim($subject));
                if (!empty($cleanSubject)) {
                    $matchedTicket = Ticket::where('customer_email', $fromEmail)
                        ->where(function($q) use ($cleanSubject) {
                            $q->where('email_subject', 'like', "%{$cleanSubject}%")
                              ->orWhere('ticket_no', $cleanSubject);
                        })
                        ->first();
                }
            }

            InboxEmail::create([
                'message_id' => $cleanMsgId,
                'uid' => $msgUid,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'to_email' => $toEmail,
                'cc_emails' => $ccString,
                'subject' => $subject,
                'body_text' => $bodyText,
                'body_html' => $bodyHtml,
                'email_date' => $emailDate,
                'is_read' => $isRead,
                'is_sent' => false,
                'ticket_id' => $matchedTicket?->id,
            ]);

            $newCount++;
        }

        imap_close($inbox);

        return [
            'success' => true,
            'new_count' => $newCount,
            'total' => $totalMsgs,
        ];
    }

    private function getMailBodyText($inbox, $mailId): string
    {
        $structure = @imap_fetchstructure($inbox, $mailId);
        if (!$structure) return '';

        return $this->extractBodyPart($inbox, $mailId, $structure, '', 'text/plain');
    }

    private function getMailBodyHtml($inbox, $mailId): string
    {
        $structure = @imap_fetchstructure($inbox, $mailId);
        if (!$structure) return '';

        $html = $this->extractBodyPart($inbox, $mailId, $structure, '', 'text/html');
        if (empty($html)) {
            $plain = $this->extractBodyPart($inbox, $mailId, $structure, '', 'text/plain');
            return nl2br(htmlspecialchars($plain));
        }
        return $html;
    }

    private function extractBodyPart($inbox, $mailId, $structure, $partPrefix, $targetMime): string
    {
        if (isset($structure->parts) && count($structure->parts) > 0) {
            foreach ($structure->parts as $index => $subPart) {
                $prefix = $partPrefix === '' ? (string)($index + 1) : "{$partPrefix}." . ($index + 1);
                $found = $this->extractBodyPart($inbox, $mailId, $subPart, $prefix, $targetMime);
                if (!empty($found)) {
                    return $found;
                }
            }
        }

        $primaryType = match ((int)($structure->type ?? 0)) {
            0 => 'text',
            1 => 'multipart',
            2 => 'message',
            3 => 'application',
            4 => 'audio',
            5 => 'image',
            6 => 'video',
            default => 'other',
        };

        $subType = strtolower($structure->subtype ?? 'plain');
        $mime = "{$primaryType}/{$subType}";

        if ($mime === $targetMime) {
            $partNum = $partPrefix === '' ? '1' : $partPrefix;
            $rawContent = @imap_fetchbody($inbox, $mailId, $partNum);

            // Handle encoding
            return match ((int)($structure->encoding ?? 0)) {
                3 => base64_decode($rawContent),
                4 => quoted_printable_decode($rawContent),
                default => $rawContent,
            };
        }

        return '';
    }
}
