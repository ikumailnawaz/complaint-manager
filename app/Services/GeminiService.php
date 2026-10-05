<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected static bool $apiTemporarilyDisabled = false;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', config('services.gemini.key', ''));
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent';
    }

    /**
     * Classify an incoming bank email and extract structured ticket data.
     */
    public function classifyAndExtractEmail(string $subject, string $body, string $fromEmail = ''): array
    {
        // If API key is not configured or API was unavailable in this request, fallback immediately to regex rule extractor
        if (empty($this->apiKey) || $this->apiKey === 'your_gemini_api_key_here' || static::$apiTemporarilyDisabled) {
            return $this->fallbackEmailExtraction($subject, $body, $fromEmail);
        }

        $systemPrompt = <<<EOT
You are an expert AI triage parser for a bank hardware maintenance ERP in Pakistan.
Analyze this incoming email and extract structured data in strict JSON.

FOLLOW THESE STRICT RULES:
1. "type": STRICT INTENT CLASSIFICATION:
   - "complaint": ONLY when the email explicitly reports a bank machine fault, breakdown, jam, error, malfunction, or repair service request.
   - "inquiry": If the email is asking questions, pricing inquiries, general queries, or follow-ups.
   - "conversation": Casual chatting, greetings, or test messages (e.g. "checking ai", "is this complain or spam", "hello", "test").
   - "spam": Marketing, promotional, junk, or irrelevant messages.
   - "other": System bounce notices, delivery failure messages.
2. "is_complaint": true ONLY if "type" is "complaint", otherwise false.
3. "bank_name": Recognized Pakistani bank (e.g. "United Bank Limited (UBL)", "Habib Bank Limited (HBL)", "MCB Bank", "Meezan Bank", "Bank Alfalah", "Allied Bank", "Faysal Bank", "Askari Bank") or organization. If unknown, null.
4. "branch_name": Specific branch name and branch code if present (e.g. "RAILWAY ROAD FAISALABAD (Branch Code: 472)"). If unknown, null.
5. "branch_location": City name in Pakistan (e.g. "Faisalabad", "Lahore", "Karachi", "Islamabad", "Multan", "Rawalpindi"). If unknown, null.
6. "branch_address": Full branch address if mentioned. If unknown, null.
7. "customer_name": Name and title of bank officer who logged or manages the complaint (e.g. "Mr. Shoaib (BOM)" or "Zubair"). Do NOT take the vendor or recipient name. If unknown, null.
8. "customer_mobile": Branch contact phone number for the engineer to call. Do NOT take vendor contact. If unknown, null.
9. "machine_type": Can be "Cash Sorting Machine", "Counting Machine", "Binding Machine", "ATM", "CDM", "POS", "Kiosk", or null if not mentioned or unknown.
10. "machine_model": Model name/number if mentioned (e.g. "cm30mm", "Glory GFS-120", "Magner 150", "NCR 6634", "Diebold 280"). If unknown, null.
11. "machine_serial_no": Serial number or terminal ID if explicitly present (e.g. "cms316827"). If unknown, null.
12. "warranty_hint": ONLY "in_warranty" if explicitly stated as under warranty/SLA contract. ONLY "out_of_warranty" if explicitly stated as expired or billable. If warranty is NOT mentioned in the email, it MUST BE "unknown".
13. "customer_ref_no": If the email contains a ticket/complaint reference number (e.g. "118929"). If unknown, null.
14. "urgency": "high" (machine totally down, urgent), "medium" (minor error, maintenance), "low" (minor query, cosmetic).
15. "sla_tat": TAT / SLA expectation mentioned in email (e.g. "1 day", "2 days", "3 days", "4 days", "4 hours", "8 hours", or null).
16. "issue_summary": A concise one-line professional summary of the fault.

Return ONLY a valid JSON object with these keys. No markdown backticks, no markdown formatting.
EOT;

        $userContent = "Email Subject: {$subject}\nSender Email: {$fromEmail}\nEmail Body:\n{$body}";

        try {
            $response = Http::withoutVerifying()->withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(6)->post("{$this->apiUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => "{$systemPrompt}\n\n{$userContent}"]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'response_mime_type' => 'application/json',
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                $cleanText = trim(str_replace(['```json', '```'], '', $text));
                $parsed = json_decode($cleanText, true);

                if (is_array($parsed)) {
                    return $parsed;
                }
            }

            if (in_array($response->status(), [429, 503])) {
                static::$apiTemporarilyDisabled = true;
            }

            Log::warning('Gemini API call returned non-200 or unparseable JSON', ['response' => $response->body()]);
        } catch (\Throwable $e) {
            static::$apiTemporarilyDisabled = true;
            Log::error('Gemini API exception: ' . $e->getMessage());
        }

        return $this->fallbackEmailExtraction($subject, $body, $fromEmail);
    }

    /**
     * Ask Gemini AI for tentative road distance between Origin (engineer home coordinates/address) and Destination (branch address/city).
     */
    public function estimateDistance(string $origin, string $destination, string $tripType = 'round_trip'): array
    {
        $oneWayKm = null;
        $oneWayHours = null;

        if ($this->hasApiKey()) {
            $prompt = "Calculate the realistic ONE-WAY driving road distance in kilometers and estimated driving duration between Origin: \"{$origin}\" and Destination: \"{$destination}\" in Pakistan. Return strictly JSON: {\"one_way_km\": number, \"one_way_hours\": number}";

            try {
                $response = Http::withoutVerifying()->withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(10)
                    ->post("{$this->apiUrl}?key={$this->apiKey}", [
                        'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                        'generationConfig' => ['temperature' => 0.1, 'response_mime_type' => 'application/json']
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                    $parsed = json_decode(trim(str_replace(['```json', '```'], '', $text)), true);
                    if (isset($parsed['one_way_km']) && is_numeric($parsed['one_way_km'])) {
                        $oneWayKm = (float) $parsed['one_way_km'];
                        $oneWayHours = (float) ($parsed['one_way_hours'] ?? round($oneWayKm / 65, 1));
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Gemini Distance exception: ' . $e->getMessage());
            }
        }

        if ($oneWayKm === null) {
            $fallback = $this->calculateRoadDistance($origin, $destination);
            $oneWayKm = $fallback['one_way_km'];
            $oneWayHours = $fallback['one_way_hours'];
        }

        $isRoundTrip = ($tripType === 'round_trip');
        $finalDistance = $isRoundTrip ? round($oneWayKm * 2, 1) : round($oneWayKm, 1);
        $finalHours = $isRoundTrip ? round($oneWayHours * 2, 1) : round($oneWayHours, 1);

        return [
            'one_way_km'      => round($oneWayKm, 1),
            'distance_km'     => $finalDistance,
            'estimated_hours' => $finalHours,
            'source'          => ($this->hasApiKey() && $oneWayKm !== null) ? 'gemini_ai' : 'haversine_matrix',
        ];
    }

    public function hasApiKey(): bool
    {
        return !empty($this->apiKey) && $this->apiKey !== 'your_gemini_api_key_here';
    }

    /**
     * Rule-based fallback extractor with strict intent classification & truthful extraction.
     */
    public function fallbackEmailExtraction(string $subject, string $body, string $fromEmail = ''): array
    {
        // 1. Detect system bounce / mailer-daemon / automated notices
        $systemPatterns = ['mailer-daemon', 'postmaster', 'mail delivery failed', 'failure notice', 'undeliverable', 'delivery status notification', 'auto-submitted', 'auto-reply', 'bounce'];
        foreach ($systemPatterns as $pat) {
            if (stripos($fromEmail, $pat) !== false || stripos($subject, $pat) !== false) {
                return [
                    'type' => 'spam',
                    'is_complaint' => false,
                    'issue_summary' => 'System mailer daemon / bounce message (skipped)',
                ];
            }
        }

        $text = $subject . ' ' . $body;

        // 2. Check for actual equipment defect / complaint keywords across subject AND body
        $complaintKeywords = [
            'fault', 'error', 'jam', 'shutter', 'breakdown', 'not working', 'stopped working',
            'malfunction', 'repair', 'broken', 'defect', 'damaged', 'cannot dispense', 'cannot deposit',
            'cannot withdraw', 'card swallowed', 'reversing', 'cash stuck', 'burnt', 'heating element',
            'counterfeit alarm', 'motor failure', 'maintenance required', 'urgent attendance', 'ticket',
            'complain', 'complaint', 're-align', 'down', 'offline', 'power failure', 'blank screen',
            'dispenser', 'bill validator', 'currency sorter', 'sorter error', 'sensor fault',
            'sorting machine', 'counting machine', 'binding machine', 'atm', 'cdm', 'pos machine'
        ];

        $hasComplaintKeyword = false;
        foreach ($complaintKeywords as $kw) {
            if (stripos($text, $kw) !== false) {
                $hasComplaintKeyword = true;
                break;
            }
        }

        // Check if email is a meta testing phrase without a genuine machine breakdown
        $isMetaTest = (stripos($text, 'is this complain') !== false || stripos($text, 'is this spam') !== false || stripos($text, 'checking ai') !== false);

        // If no equipment/fault keyword was found, or if it is just a meta-test ("is this complain or spam"), treat as conversation
        if (!$hasComplaintKeyword || $isMetaTest) {
            return [
                'type' => 'conversation',
                'is_complaint' => false,
                'issue_summary' => 'Conversational email / inquiry (no equipment failure reported)',
            ];
        }

        // Clean text for template and pattern matching
        $cleanBody = strip_tags(str_replace(['*', '#', "\r"], ['', '', ''], $body));

        // 4. Detect Complaint / Ticket Reference Number (Checks both body and subject)
        // Must contain numbers (never words like 'erence', 'captioned')
        $refNo = null;
        if (preg_match('/(?:Ticket|Complaint|Call\s*Log|Ref)\s*(?:Number|No\.?|#)?\s*[:\s#-]+([0-9]{4,10}|[A-Z]{2,5}-[0-9]{3,10})/i', $cleanBody, $refMatches)) {
            $refNo = trim($refMatches[1]);
        } elseif (preg_match('/(?:Ticket|Complaint|Call\s*Log|Ref)\s*(?:Number|No\.?|#)?\s*[:\s#-]+([0-9]{4,10}|[A-Z]{2,5}-[0-9]{3,10})/i', $subject, $refMatches)) {
            $refNo = trim($refMatches[1]);
        }
        if ($refNo && !preg_match('/[0-9]/', $refNo)) {
            $refNo = null;
        }

        // 5. Detect Bank Name (Check sender email domain first, then full names, then acronyms with word boundaries)
        $bankName = null;
        if (stripos($fromEmail, 'bankalfalah') !== false || stripos($fromEmail, 'alfalah') !== false) {
            $bankName = 'Bank Alfalah';
        } elseif (stripos($fromEmail, 'ubl') !== false) {
            $bankName = 'United Bank Limited (UBL)';
        } elseif (stripos($fromEmail, 'hbl') !== false) {
            $bankName = 'Habib Bank Limited (HBL)';
        } elseif (stripos($fromEmail, 'mcb') !== false) {
            $bankName = 'MCB Bank';
        } elseif (stripos($fromEmail, 'meezan') !== false) {
            $bankName = 'Meezan Bank';
        } elseif (stripos($fromEmail, 'allied') !== false || stripos($fromEmail, '@abl.com') !== false) {
            $bankName = 'Allied Bank';
        } elseif (stripos($fromEmail, 'faysal') !== false) {
            $bankName = 'Faysal Bank';
        } elseif (stripos($fromEmail, 'askari') !== false) {
            $bankName = 'Askari Bank';
        }

        if (!$bankName) {
            $fullBankPatterns = [
                'Bank Alfalah' => 'Bank Alfalah',
                'Alfalah' => 'Bank Alfalah',
                'United Bank' => 'United Bank Limited (UBL)',
                'Habib Bank' => 'Habib Bank Limited (HBL)',
                'MCB Bank' => 'MCB Bank',
                'Meezan Bank' => 'Meezan Bank',
                'Meezan' => 'Meezan Bank',
                'Allied Bank' => 'Allied Bank',
                'Faysal Bank' => 'Faysal Bank',
                'Askari Bank' => 'Askari Bank',
                'Soneri Bank' => 'Soneri Bank',
                'Standard Chartered' => 'Standard Chartered',
                'National Bank' => 'National Bank of Pakistan (NBP)',
                'Bank of Punjab' => 'Bank of Punjab (BOP)',
            ];
            foreach ($fullBankPatterns as $kw => $name) {
                if (stripos($text, $kw) !== false) {
                    $bankName = $name;
                    break;
                }
            }
        }

        if (!$bankName) {
            $acronyms = [
                '/\bUBL\b/' => 'United Bank Limited (UBL)',
                '/\bHBL\b/' => 'Habib Bank Limited (HBL)',
                '/\bMCB\b/' => 'MCB Bank',
                '/\bABL\b/' => 'Allied Bank',
                '/\bNBP\b/' => 'National Bank of Pakistan (NBP)',
                '/\bBOP\b/' => 'Bank of Punjab (BOP)',
                '/\bBOK\b/' => 'Bank of Khyber',
            ];
            foreach ($acronyms as $pattern => $name) {
                if (preg_match($pattern, $text)) {
                    $bankName = $name;
                    break;
                }
            }
        }

        // 6. Detect Branch Name & Code
        $branchName = null;
        if (preg_match('/Branch\s+Name\s*[:\s]+([^\n]+)/i', $cleanBody, $bMatches)) {
            $branchName = trim($bMatches[1]);
        }
        if (preg_match('/Branch\s+Code\s*[:\s]+([0-9A-Za-z]+)/i', $cleanBody, $bcMatches)) {
            $code = trim($bcMatches[1]);
            if ($branchName && stripos($branchName, $code) === false) {
                $branchName .= " (Branch Code: {$code})";
            }
        }

        // 7. Detect City / Location
        $location = null;
        $cities = [
            'Abbottabad', 'Lahore', 'Karachi', 'Islamabad', 'Rawalpindi', 'Faisalabad', 
            'Multan', 'Peshawar', 'Quetta', 'Sahiwal', 'Sialkot', 'Gujranwala', 
            'Hyderabad', 'Sukkur', 'Bahawalpur', 'Sargodha', 'Rahim Yar Khan', 'Gujrat',
            'Wah Cantt', 'Mardan', 'Jhelum', 'Kasur', 'Okara', 'Dera Ghazi Khan', 
            'Muzaffarabad', 'Mirpur', 'Jhang', 'Daska', 'Kohat', 'Chiniot', 'Attock', 
            'Swat', 'Mingora', 'Haripur', 'Nowshera', 'Mansehra', 'Sheikhupura'
        ];
        foreach ($cities as $c) {
            if (stripos($branchName ?? '', $c) !== false || stripos($text, $c) !== false) {
                $location = $c;
                break;
            }
        }
        if (!$location) {
            $location = 'Lahore';
        }

        // 8. Detect Branch Physical Street Address
        $branchAddress = null;
        if (preg_match('/(?:Branch\s+Address|Street\s+Address|Physical\s+Address|Address|Location)\s*[:\s]+([^\n]+)/i', $cleanBody, $addrMatches)) {
            $candidateAddr = trim($addrMatches[1]);
            if (strlen($candidateAddr) > 8 && !preg_match('/^(?:unknown|na|n\/a)$/i', $candidateAddr)) {
                $branchAddress = $candidateAddr;
            }
        }

        // Signature address fallback (e.g. "BA Building, Mansehra Road, Near SNGPL Office, Abbottabad, Pakistan")
        if (!$branchAddress) {
            if (preg_match('/([^\n\r]+(?:Building|Bldg|Road|Street|Near|Plaza|Floor|Chowk|Bazar|Tehsil|Sector)[^\n\r]+(?:Pakistan|[0-9]{5}|Abbottabad|Lahore|Karachi|Rawalpindi|Islamabad|Peshawar))/i', $cleanBody, $sigAddrMatches)) {
                $branchAddress = trim($sigAddrMatches[1]);
            }
        }

        // 9. Detect Machine Type
        $machineType = null;
        if (stripos($text, 'binding') !== false || stripos($text, 'strapping') !== false || stripos($text, 'bundling') !== false) {
            $machineType = 'Binding Machine';
        } elseif (stripos($text, 'cash sort') !== false || stripos($text, 'sorting machine') !== false || stripos($text, 'sorter') !== false) {
            $machineType = 'Cash Sorting Machine';
        } elseif (stripos($text, 'counting machine') !== false || stripos($text, 'note counter') !== false || stripos($text, 'currency counter') !== false || stripos($text, 'cash counter') !== false) {
            $machineType = 'Counting Machine';
        } elseif (stripos($text, 'ATM') !== false || stripos($text, 'cash dispenser') !== false) {
            $machineType = 'ATM';
        } elseif (stripos($text, 'CDM') !== false || stripos($text, 'cash deposit') !== false) {
            $machineType = 'CDM';
        } elseif (stripos($text, 'POS') !== false || stripos($text, 'terminal') !== false) {
            $machineType = 'POS';
        } elseif (stripos($text, 'kiosk') !== false) {
            $machineType = 'Kiosk';
        }

        // 10. Detect Machine Serial Number (supports 'serial no: cms316827', 'SN:', 'S/N:', etc.)
        $serialNo = null;
        if (preg_match('/(?:serial\s*(?:no\.?|number|#)?|s\/n|sn)[:\s#]*([A-Za-z0-9\-]{4,20})/i', $cleanBody, $sMatches)) {
            $serialNo = trim($sMatches[1]);
        } elseif (preg_match('/(?:TID|Terminal)[:\s#]*([A-Za-z0-9\-]{4,20})/i', $cleanBody, $sMatches)) {
            $serialNo = trim($sMatches[1]);
        }

        // 11. Detect Machine Model (handles 'model no: cm30mm', 'Model:', etc. without stopping on 'no')
        $machineModel = null;
        if (preg_match('/(?:model\s*(?:no\.?|number|#)?|machine\s*model)[:\s#]*([A-Za-z0-9\-\s]{3,25})/i', $cleanBody, $modMatches)) {
            $candidateModel = trim($modMatches[1]);
            if (strtolower($candidateModel) !== 'no') {
                $candidateModel = preg_split('/(?:dop|issue|serial|status|date|warranty)/i', $candidateModel)[0];
                $machineModel = trim($candidateModel);
            }
        }

        // 12. Detect SLA in days or hours
        $slaTat = null;
        if (preg_match('/(\b[1-9]\b|\b1[0-4]\b)\s*(?:day|days|d\b)/i', $text, $dMatches)) {
            $slaTat = (int) $dMatches[1] . ' ' . ((int) $dMatches[1] === 1 ? 'day' : 'days');
        } elseif (preg_match('/(\b[1-9]\b|\b1[0-9]\b|\b2[0-4]\b)\s*(?:hour|hours|hrs|hr\b)/i', $text, $hMatches)) {
            $slaTat = (int) $hMatches[1] . ' ' . ((int) $hMatches[1] === 1 ? 'hour' : 'hours');
        }

        // 13. Detect Warranty Status
        $warrantyStatus = 'unknown';
        if (stripos($text, 'in warranty') !== false || stripos($text, 'under warranty') !== false || stripos($text, 'amc contract') !== false || stripos($text, 'sla contract') !== false) {
            $warrantyStatus = 'in_warranty';
        } elseif (stripos($text, 'out of warranty') !== false || stripos($text, 'expired') !== false || stripos($text, 'billable') !== false) {
            $warrantyStatus = 'out_of_warranty';
        }

        // 14. Contact Mobile: Prioritize Branch Contact Number over Vendor Contact!
        $customerMobile = null;
        if (preg_match('/(?:Branch\s*Contact(?:\s*Number)?|Contact\s*(?:Number|Mobile|Phone))[:\s]*([0-9\s,\-\+]{10,30})/i', $cleanBody, $pMatches)) {
            $phones = preg_split('/[,\s\/]+/', trim($pMatches[1]));
            foreach ($phones as $p) {
                $cleanP = preg_replace('/[^0-9\+]/', '', $p);
                if (strlen($cleanP) >= 10 && strlen($cleanP) <= 13) {
                    $customerMobile = trim($p);
                    break;
                }
            }
        }
        if (!$customerMobile && preg_match('/(?:03\d{2}[-\s]?\d{7}|\+92[-\s]?3\d{2}[-\s]?\d{7})/i', $cleanBody, $pMatches)) {
            $customerMobile = trim($pMatches[0]);
        }

        // 15. Contact Person: Prioritize Bank Officer (BOM / Logged By / Bank Sign-off) over Vendor Sign-off!
        $customerName = null;
        if (preg_match('/(?:BOM|Branch\s*Manager)[:\s]+([^\n]+)/i', $cleanBody, $nMatches)) {
            $customerName = trim($nMatches[1]) . ' (BOM)';
        } elseif (preg_match('/(?:Logged\s*By)[:\s]+([^\n]+)/i', $cleanBody, $nMatches)) {
            $customerName = trim($nMatches[1]);
        } elseif (preg_match('/Regards,?\s*\n\s*([A-Za-z\s]+)\s*\n\s*(?:United Bank|HBL|MCB|Allied|Meezan|Bank)/i', $cleanBody, $nMatches)) {
            $customerName = trim($nMatches[1]);
        } elseif (preg_match('/(?:Best regards|Regards|Sincerely|Thanks & regards|From)[:,\s]*\r?\n\s*([A-Za-z]+(?:\s+[A-Za-z]+){1,2})/i', $body, $nameMatches)) {
            $customerName = trim(explode("\n", trim($nameMatches[1]))[0]);
        }

        // 16. Detect Urgency
        $urgency = 'medium';
        if (stripos($text, 'urgent') !== false || stripos($text, 'critical') !== false || stripos($text, 'emergency') !== false || stripos($text, 'jam') !== false || stripos($text, 'offline') !== false) {
            $urgency = 'high';
        }

        // 17. Issue Summary: Pull from 'Issue Details:' if present
        $issueSummary = null;
        if (preg_match('/Issue\s*Details\s*[:\s]+([^\n]+)/i', $cleanBody, $sumMatches)) {
            $issueSummary = trim($sumMatches[1]);
        } elseif (!empty($subject) && !in_array(strtolower(trim($subject)), ['hello', 'hi', 'hey', 'urgent', 'unkown', 'unknown', 'query', 'test', 're:', 'fwd:']) && strlen(trim($subject)) >= 6) {
            $issueSummary = substr(trim($subject), 0, 100);
        } else {
            $issueSummary = trim(($machineType ?? 'Equipment') . ' fault reported at ' . ($bankName ?? 'Bank Branch') . ($branchName ? ' ' . $branchName : ''));
        }

        return [
            'type' => 'complaint',
            'is_complaint' => true,
            'customer_ref_no' => $refNo,
            'bank_name' => $bankName ?: 'Bank Branch',
            'branch_name' => $branchName ?: ($location ? "{$location} Main Branch" : null),
            'branch_location' => $location,
            'branch_address' => $branchAddress,
            'customer_name' => $customerName,
            'customer_mobile' => $customerMobile,
            'customer_email' => $fromEmail ?: null,
            'machine_type' => $machineType,
            'machine_model' => $machineModel,
            'machine_serial_no' => $serialNo,
            'warranty_hint' => $warrantyStatus,
            'urgency' => $urgency,
            'sla_tat' => $slaTat,
            'issue_summary' => $issueSummary,
        ];
    }

    public function calculateRoadDistance(string $origin, string $destination): array
    {
        // 1. Direct Pakistani Highway Matrix (One-way kilometers)
        $highwayMatrix = [
            'lahore-vehari'       => 331.0,
            'lahore-sahiwal'      => 180.0,
            'lahore-multan'       => 345.0,
            'lahore-karachi'      => 1210.0,
            'lahore-islamabad'    => 375.0,
            'lahore-rawalpindi'   => 370.0,
            'lahore-faisalabad'   => 135.0,
            'lahore-gujranwala'   => 70.0,
            'lahore-sialkot'      => 130.0,
            'lahore-gujrat'       => 120.0,
            'lahore-sheikhupura'  => 45.0,
            'lahore-kasur'        => 55.0,
            'lahore-okara'        => 125.0,
            'lahore-sargodha'     => 190.0,
            'lahore-bahawalpur'   => 430.0,
            'lahore-peshawar'     => 515.0,
            'multan-vehari'       => 105.0,
            'sahiwal-vehari'      => 150.0,
            'karachi-islamabad'   => 1410.0,
            'karachi-multan'      => 930.0,
            'karachi-hyderabad'   => 165.0,
            'karachi-sukkur'      => 480.0,
            'islamabad-peshawar'  => 185.0,
            'islamabad-multan'    => 540.0,
            'multan-faisalabad'   => 240.0,
            'multan-bahawalpur'   => 90.0,
        ];

        // 2. City Geocoding Map for Pakistani Cities
        $cityCoordinates = [
            'lahore'         => [31.5204, 74.3587],
            'vehari'         => [30.0452, 72.3489],
            'multan'         => [30.1575, 71.5249],
            'sahiwal'        => [30.6682, 73.1114],
            'faisalabad'     => [31.4504, 73.1350],
            'karachi'        => [24.8607, 67.0011],
            'islamabad'      => [33.6844, 73.0479],
            'rawalpindi'     => [33.5651, 73.0169],
            'gujranwala'     => [32.1877, 74.1945],
            'sialkot'        => [32.4945, 74.5229],
            'sargodha'       => [32.0836, 72.6711],
            'bahawalpur'     => [29.3544, 71.6911],
            'rahim yar khan' => [28.4212, 70.2989],
            'sukkur'         => [27.7131, 68.8486],
            'hyderabad'      => [25.3960, 68.3578],
            'peshawar'       => [34.0151, 71.5249],
            'quetta'         => [30.1798, 66.9750],
            'abbottabad'     => [34.1688, 73.2215],
            'kasur'          => [31.1179, 74.4461],
            'okara'          => [30.8081, 73.4458],
            'sheikhupura'    => [31.7131, 73.9783],
            'jhang'          => [31.2781, 72.3317],
            'gujrat'         => [32.5742, 74.0754],
            'muzaffargarh'   => [30.0703, 71.1933],
            'burewala'       => [30.1667, 72.6833],
            'mailsi'         => [29.8006, 72.1764],
        ];

        // Try extracting GPS coordinates from string (e.g. "31.5204, 74.3587")
        $originCoords = $this->extractCoordinates($origin, $cityCoordinates);
        $destCoords   = $this->extractCoordinates($destination, $cityCoordinates);

        // Check if matching city pair in matrix
        $originCity = $this->detectCityName($origin, array_keys($cityCoordinates));
        $destCity   = $this->detectCityName($destination, array_keys($cityCoordinates));

        if ($originCity && $destCity) {
            if ($originCity === $destCity) {
                return ['one_way_km' => 20.0, 'one_way_hours' => 0.5];
            }
            $key1 = "{$originCity}-{$destCity}";
            $key2 = "{$destCity}-{$originCity}";
            if (isset($highwayMatrix[$key1])) {
                $km = $highwayMatrix[$key1];
                return ['one_way_km' => $km, 'one_way_hours' => round($km / 65, 1)];
            }
            if (isset($highwayMatrix[$key2])) {
                $km = $highwayMatrix[$key2];
                return ['one_way_km' => $km, 'one_way_hours' => round($km / 65, 1)];
            }
        }

        // If coordinates found, compute Haversine with road circuity
        if ($originCoords && $destCoords) {
            $lat1 = $originCoords[0];
            $lon1 = $originCoords[1];
            $lat2 = $destCoords[0];
            $lon2 = $destCoords[1];

            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);
            $a = sin($dLat / 2) * sin($dLat / 2) +
                 cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
                 sin($dLon / 2) * sin($dLon / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $crowKm = 6371 * $c;

            if ($crowKm < 5.0) {
                return ['one_way_km' => 15.0, 'one_way_hours' => 0.5];
            }

            // Road routing factor for Pakistan road network is ~1.30
            $roadKm = round($crowKm * 1.30, 1);
            return [
                'one_way_km' => $roadKm,
                'one_way_hours' => round($roadKm / 65, 1),
            ];
        }

        return ['one_way_km' => 160.0, 'one_way_hours' => 2.5];
    }

    private function extractCoordinates(string $input, array $cityCoordinates): ?array
    {
        // 1. Look for explicit "lat, lon" numbers (e.g. 31.5204, 74.3587)
        if (preg_match('/([23]\d\.\d+)\s*,\s*([67]\d\.\d+)/', $input, $matches)) {
            return [(float) $matches[1], (float) $matches[2]];
        }

        // 2. Look for city name inside the input
        $lower = strtolower($input);
        foreach ($cityCoordinates as $city => $coords) {
            if (str_contains($lower, $city)) {
                return $coords;
            }
        }

        return null;
    }

    private function detectCityName(string $input, array $cities): ?string
    {
        $lower = strtolower($input);
        foreach ($cities as $city) {
            if (str_contains($lower, $city)) {
                return $city;
            }
        }
        return null;
    }
}
