<?php

namespace App\Services;

use App\Models\InboxEmail;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ImapMailboxService
{
    public function syncMailbox(int $limit = 100, ?string $serverSearch = null): array
    {
        $host = config('mail.imap.host') ?: env('IMAP_HOST', config('mail.mailers.smtp.host', 'mail.cmscompany.biz'));
        $port = (int) (config('mail.imap.port') ?: env('IMAP_PORT', 993));
        $encryption = strtolower(config('mail.imap.encryption') ?: env('IMAP_ENCRYPTION', 'ssl'));
        $username = config('mail.imap.username') ?: env('IMAP_USERNAME', config('mail.mailers.smtp.username', 'support@cmscompany.biz'));
        $password = config('mail.imap.password') ?: env('IMAP_PASSWORD', config('mail.mailers.smtp.password', ''));

        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'error' => 'IMAP credentials not configured in .env (username: ' . ($username ?: 'empty') . ', password: ' . (empty($password) ? 'empty' : 'set') . ')',
                'new_count' => 0,
            ];
        }

        if (!function_exists('imap_open')) {
            return [
                'success' => false,
                'error' => 'The PHP IMAP extension (php-imap) is not installed or enabled on this server. Please enable it in cPanel -> Select PHP Version -> Extensions -> imap.',
                'new_count' => 0,
            ];
        }

        @ini_set('max_execution_time', '300');
        $mailbox = "{" . "{$host}:{$port}/imap/{$encryption}/novalidate-cert}INBOX";

        try {
            // Open IMAP stream
            $inbox = @imap_open($mailbox, $username, $password);

            if (!$inbox) {
                $err = imap_last_error();
                Log::error("ImapMailboxService: Connection failure: {$err}");
                return [
                    'success' => false,
                    'error' => "Failed to connect to IMAP server: {$err}",
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

        // Determine message IDs to inspect
        $mailIds = [];
        if (!empty($serverSearch)) {
            // Search remote IMAP directly (e.g. SUBJECT or FROM)
            $searchClean = addslashes(trim($serverSearch));
            $found = @imap_search($inbox, "OR FROM \"{$searchClean}\" SUBJECT \"{$searchClean}\"");
            if (is_array($found)) {
                rsort($found);
                $mailIds = array_slice($found, 0, $limit);
            }
        }

        if (empty($mailIds)) {
            $startMsg = max(1, $totalMsgs - $limit + 1);
            for ($m = $totalMsgs; $m >= $startMsg; $m--) {
                $mailIds[] = $m;
            }
        }

        // Preload existing UIDs in one quick query to avoid redundant IMAP body fetches
        $existingUids = InboxEmail::pluck('uid')->filter()->flip()->all();
        $existingMsgIds = InboxEmail::pluck('message_id')->filter()->flip()->all();

        $newCount = 0;

        foreach ($mailIds as $mailId) {
            $msgUid = (string) @imap_uid($inbox, $mailId);

            // Fast skip if UID already recorded in database
            if ($msgUid && isset($existingUids[$msgUid])) {
                continue;
            }

            $header = @imap_headerinfo($inbox, $mailId);
            if (!$header) continue;

            $rawMsgId = $header->message_id ?? null;
            $cleanMsgId = $rawMsgId ? trim($rawMsgId, "<> \t\n\r\0\x0B") : null;

            if ($cleanMsgId && isset($existingMsgIds[$cleanMsgId])) {
                continue;
            }

            $subject = isset($header->subject) ? mb_decode_mimeheader($header->subject) : '(No Subject)';
            $subject = $this->toUtf8($subject);

            $fromEmail = (isset($header->from[0]->mailbox, $header->from[0]->host))
                ? strtolower($header->from[0]->mailbox . '@' . $header->from[0]->host)
                : (isset($header->from[0]->mailbox) ? strtolower($header->from[0]->mailbox) : 'unknown@bank.com');
            $fromEmail = $this->toUtf8($fromEmail);

            $fromName = isset($header->from[0]->personal) ? mb_decode_mimeheader($header->from[0]->personal) : $fromEmail;
            $fromName = $this->toUtf8($fromName);

            $toEmail = (isset($header->to[0]->mailbox, $header->to[0]->host))
                ? strtolower($header->to[0]->mailbox . '@' . $header->to[0]->host)
                : (isset($header->to[0]->mailbox) ? strtolower($header->to[0]->mailbox) : $username);
            $toEmail = $this->toUtf8($toEmail);

            $emailDate = isset($header->udate) ? Carbon::createFromTimestamp($header->udate) : Carbon::now();
            $isRead = ($header->Unseen !== 'U' && $header->Recent !== 'R');

            // Extract CC recipients
            $ccAddresses = [];
            if (!empty($header->cc) && is_array($header->cc)) {
                foreach ($header->cc as $ccItem) {
                    if (isset($ccItem->mailbox) && isset($ccItem->host)) {
                        $addr = strtolower(trim($ccItem->mailbox . '@' . $ccItem->host));
                        if ($addr !== strtolower($username)) {
                            $ccAddresses[] = $this->toUtf8($addr);
                        }
                    }
                }
            }
            $ccString = !empty($ccAddresses) ? implode(', ', array_unique($ccAddresses)) : null;

            // Extract body parts (text & HTML)
            $bodyText = $this->toUtf8($this->getMailBodyText($inbox, $mailId));
            $bodyHtml = $this->toUtf8($this->getMailBodyHtml($inbox, $mailId));

            if (empty($bodyText) && !empty($bodyHtml)) {
                $bodyText = trim(strip_tags($bodyHtml));
            }

            // Check if any Ticket was already created for this email
            $matchedTicket = null;
            if ($cleanMsgId) {
                $matchedTicket = Ticket::where('incoming_message_id', 'like', "%{$cleanMsgId}%")->first();
            }
            if (!$matchedTicket && !empty($subject)) {
                // Thread reply match: subject match with same sender
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

            try {
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

                $existingUids[$msgUid] = true;
                if ($cleanMsgId) $existingMsgIds[$cleanMsgId] = true;
                $newCount++;
            } catch (\Throwable $rowEx) {
                Log::warning("ImapMailboxService: Row insert retry with scrub for UID {$msgUid}: " . $rowEx->getMessage());
                try {
                    InboxEmail::create([
                        'message_id' => $cleanMsgId,
                        'uid' => $msgUid,
                        'from_email' => mb_scrub($fromEmail, 'UTF-8'),
                        'from_name' => mb_scrub($fromName, 'UTF-8'),
                        'to_email' => mb_scrub($toEmail, 'UTF-8'),
                        'cc_emails' => $ccString ? mb_scrub($ccString, 'UTF-8') : null,
                        'subject' => mb_scrub($subject, 'UTF-8'),
                        'body_text' => mb_scrub(iconv('UTF-8', 'UTF-8//IGNORE', $bodyText) ?: $bodyText, 'UTF-8'),
                        'body_html' => mb_scrub(iconv('UTF-8', 'UTF-8//IGNORE', $bodyHtml) ?: $bodyHtml, 'UTF-8'),
                        'email_date' => $emailDate,
                        'is_read' => $isRead,
                        'is_sent' => false,
                        'ticket_id' => $matchedTicket?->id,
                    ]);
                    $existingUids[$msgUid] = true;
                    if ($cleanMsgId) $existingMsgIds[$cleanMsgId] = true;
                    $newCount++;
                } catch (\Throwable $permEx) {
                    Log::error("ImapMailboxService: Permanent skip for UID {$msgUid}: " . $permEx->getMessage());
                }
            }
        }

        imap_close($inbox);

        return [
            'success' => true,
            'new_count' => $newCount,
            'total' => $totalMsgs,
        ];
        } catch (\Throwable $e) {
            Log::error("ImapMailboxService: Exception during sync: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'IMAP sync exception: ' . $e->getMessage(),
                'new_count' => 0,
            ];
        }
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

            // Handle transfer encoding
            $data = match ((int)($structure->encoding ?? 0)) {
                3 => base64_decode($rawContent),
                4 => quoted_printable_decode($rawContent),
                default => $rawContent,
            };

            // Detect declared charset from parameters if available
            $declaredCharset = null;
            if (!empty($structure->parameters)) {
                foreach ($structure->parameters as $param) {
                    if (isset($param->attribute) && strtolower($param->attribute) === 'charset') {
                        $declaredCharset = $param->value ?? null;
                        break;
                    }
                }
            }

            return $this->toUtf8($data, $declaredCharset);
        }

        return '';
    }

    /**
     * Convert any string with arbitrary or corrupted encoding into clean, valid UTF-8 for MySQL.
     */
    public function toUtf8(?string $str, ?string $declaredCharset = null): string
    {
        if ($str === null || $str === '') {
            return '';
        }

        // 1. If declared charset is provided and not UTF-8, convert from it
        if (!empty($declaredCharset)) {
            $charsetUpper = strtoupper(trim($declaredCharset, " '\"\t\n\r\0\x0B"));
            if (!in_array($charsetUpper, ['UTF-8', 'UTF8'])) {
                try {
                    $converted = @mb_convert_encoding($str, 'UTF-8', $charsetUpper);
                    if ($converted !== false && $converted !== '') {
                        $str = $converted;
                    }
                } catch (\Throwable) {}
            }
        }

        // 2. Check if string is already valid UTF-8; if not, try Windows-1252 / ISO-8859-1
        if (!mb_check_encoding($str, 'UTF-8')) {
            $converted = @mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                $str = $converted;
            } else {
                $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
                if ($converted !== false) {
                    $str = $converted;
                } else {
                    $str = @mb_convert_encoding($str, 'UTF-8', 'UTF-8');
                }
            }
        }

        // 3. Scrub any remaining malformed bytes
        if (function_exists('mb_scrub')) {
            $str = mb_scrub($str, 'UTF-8');
        }

        return $str;
    }
}
