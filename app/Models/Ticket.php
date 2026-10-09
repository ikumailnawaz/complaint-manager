<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class Ticket extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Ticket $ticket) {
            TicketCycle::firstOrCreate(
                ['ticket_id' => $ticket->id, 'cycle_no' => 1],
                [
                    'status' => 'open',
                    'opened_at' => $ticket->created_at ?? now(),
                    'sla_deadline' => $ticket->sla_deadline,
                ]
            );
        });

        static::saved(function (Ticket $ticket) {
            if (!$ticket->wasChanged('assigned_engineer_id') || !$ticket->assigned_engineer_id) {
                return;
            }

            $leadId = (int) $ticket->assigned_engineer_id;

            TicketEngineer::where('ticket_id', $ticket->id)
                ->whereNull('released_at')
                ->where('role', 'lead')
                ->where('engineer_id', '!=', $leadId)
                ->update(['released_at' => now()]);

            $row = TicketEngineer::where('ticket_id', $ticket->id)
                ->whereNull('released_at')
                ->where('engineer_id', $leadId)
                ->first();

            if ($row) {
                if ($row->role !== 'lead') {
                    $row->update(['role' => 'lead']);
                }
            } else {
                TicketEngineer::create([
                    'ticket_id' => $ticket->id,
                    'engineer_id' => $leadId,
                    'role' => 'lead',
                    'assigned_by_id' => $ticket->assigned_by_id,
                    'assigned_at' => now(),
                    'cycle_id' => TicketCycle::where('ticket_id', $ticket->id)->orderByDesc('cycle_no')->value('id'),
                ]);
            }
        });
    }

    protected $fillable = [
        'ticket_no',
        'ticket_no_source',
        'customer_ref_no',
        'bank_name',
        'branch_name',
        'branch_location',
        'branch_address',
        'customer_name',
        'customer_mobile',
        'customer_email',
        'customer_cc',
        'machine_type',
        'machine_model',
        'machine_serial_no',
        'warranty_status',
        'warranty_expiry',
        'urgency',
        'status',
        'issue_summary',
        'issue_description',
        'assigned_engineer_id',
        'assigned_by_id',
        'assigned_at',
        'whatsapp_notified',
        'whatsapp_notified_at',
        'whatsapp_notified_by_id',
        'whatsapp_message_id',
        'email_assignment_sent',
        'email_assignment_sent_at',
        'email_assignment_sent_by_id',
        'email_reply_message_id',
        'resolution_email_sent',
        'resolution_email_sent_at',
        'resolution_email_sent_by_id',
        'resolution_email_message_id',
        'resolution_email_to',
        'sla_deadline',
        'escalated_at',
        'escalated_to_id',
        'workshop_location',
        'original_field_engineer_id',
        'workshop_engineer_id',
        'workshop_dispatch_courier',
        'workshop_dispatch_tracking',
        'workshop_dispatched_at',
        'workshop_dispatch_notes',
        'workshop_received_at',
        'workshop_received_by_id',
        'workshop_intake_remarks',
        'workshop_repaired_at',
        'workshop_repair_summary',
        'return_courier',
        'return_tracking_number',
        'return_dispatched_at',
        'return_dispatched_by_id',
        'return_dispatch_notes',
        'bank_received_at',
        'bank_received_confirmed_by_id',
        'ai_classified',
        'incoming_message_id',
        'email_subject',
        'is_human_verified',
        'verified_by_id',
        'verified_at',
        'resolution_summary',
        'supporting_document',
        'resolution_document_name',
        'resolved_at',
        'closed_at',
        'created_at',
        'approval_requested_at',
        'approval_requested_by_id',
        'approval_source',
        'approval_request_reason',
        'sla_paused_at',
        'approval_paused_seconds',
        'approval_arrived_at',
        'approval_arrived_by_id',
        'approval_arrived_remarks',
        'current_cycle_no',
        'reopen_count',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'whatsapp_notified' => 'boolean',
        'whatsapp_notified_at' => 'datetime',
        'email_assignment_sent' => 'boolean',
        'email_assignment_sent_at' => 'datetime',
        'resolution_email_sent' => 'boolean',
        'resolution_email_sent_at' => 'datetime',
        'sla_deadline' => 'datetime',
        'escalated_at' => 'datetime',
        'warranty_expiry' => 'date',
        'ai_classified' => 'boolean',
        'is_human_verified' => 'boolean',
        'verified_at' => 'datetime',
        'workshop_dispatched_at' => 'datetime',
        'workshop_received_at' => 'datetime',
        'workshop_repaired_at' => 'datetime',
        'return_dispatched_at' => 'datetime',
        'bank_received_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'approval_requested_at' => 'datetime',
        'sla_paused_at' => 'datetime',
        'approval_arrived_at' => 'datetime',
    ];

    /**
     * Get recognized uppercase standard bank abbreviation for ticket prefixes.
     */
    public static function getBankCode(?string $bankName): string
    {
        if (empty($bankName)) return 'BANK';
        $b = strtoupper(trim($bankName));

        if (str_contains($b, 'ALFALAH') || str_contains($b, 'BAF')) return 'BAF';
        if (str_contains($b, 'UNITED') || str_contains($b, 'UBL')) return 'UBL';
        if (str_contains($b, 'HABIB') || str_contains($b, 'HBL')) return 'HBL';
        if (str_contains($b, 'MCB')) return 'MCB';
        if (str_contains($b, 'MEEZAN')) return 'MEEZAN';
        if (str_contains($b, 'ALLIED') || str_contains($b, 'ABL')) return 'ABL';
        if (str_contains($b, 'FAYSAL') || str_contains($b, 'FBL')) return 'FBL';
        if (str_contains($b, 'ASKARI') || str_contains($b, 'AKBL')) return 'AKBL';
        if (str_contains($b, 'NATIONAL BANK') || str_contains($b, 'NBP')) return 'NBP';
        if (str_contains($b, 'PUNJAB') || str_contains($b, 'BOP')) return 'BOP';
        if (str_contains($b, 'KHYBER') || str_contains($b, 'BOK')) return 'BOK';
        if (str_contains($b, 'SONERI') || str_contains($b, 'SNBL')) return 'SNBL';
        if (str_contains($b, 'STANDARD CHARTERED') || str_contains($b, 'SCB')) return 'SCB';
        if (str_contains($b, 'DUBAI ISLAMIC') || str_contains($b, 'DIB')) return 'DIB';
        if (str_contains($b, 'BANKISLAMI') || str_contains($b, 'BIPL')) return 'BIPL';
        if (str_contains($b, 'JS BANK') || str_contains($b, 'JSBL')) return 'JSBL';
        if (str_contains($b, 'SINDH BANK')) return 'SINDH';
        if (str_contains($b, 'AL BARAKA') || str_contains($b, 'ABPL')) return 'ABPL';

        if (preg_match('/\(([A-Z0-9]+)\)/', $b, $m)) {
            return $m[1];
        }

        $clean = preg_replace('/[^A-Z0-9]/', '', $b);
        return substr($clean, 0, 6) ?: 'BANK';
    }

    /**
     * Format DB ticket_no with bank reference prefix so multiple banks can share identical ticket numbers.
     */
    public static function formatTicketNo(string $bankName, string $rawTicketNo): string
    {
        $raw = strtoupper(trim($rawTicketNo));
        if (str_starts_with($raw, 'CMP-')) {
            return $raw;
        }

        $bankCode = self::getBankCode($bankName);
        if (str_starts_with($raw, $bankCode . '-') || str_starts_with($raw, $bankCode . '/')) {
            return $raw;
        }

        return "{$bankCode}-{$raw}";
    }

    /**
     * Check whether a ticket with this ticket number and bank already exists.
     */
    public static function ticketExistsForBank(string $bankName, string $rawTicketNo, ?int $ignoreTicketId = null): ?self
    {
        $raw = strtoupper(trim($rawTicketNo));
        $formatted = self::formatTicketNo($bankName, $raw);
        $bankCode = self::getBankCode($bankName);

        $query = self::query();
        if ($ignoreTicketId) {
            $query->where('id', '!=', $ignoreTicketId);
        }

        return $query->where(function ($q) use ($bankName, $bankCode) {
                $q->where('bank_name', 'like', "%{$bankName}%")
                  ->orWhere('bank_name', 'like', "%{$bankCode}%")
                  ->orWhere('ticket_no', 'like', "{$bankCode}-%")
                  ->orWhere('ticket_no', 'like', "{$bankCode}/%");
            })
            ->where(function ($q) use ($raw, $formatted) {
                $q->where('ticket_no', $raw)
                  ->orWhere('ticket_no', $formatted)
                  ->orWhere('customer_ref_no', $raw);
            })
            ->first();
    }

    public function approvalRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_requested_by_id');
    }

    public function approvalArrivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_arrived_by_id');
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === 'awaiting_approval';
    }

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_engineer_id');
    }

    public function assignedEngineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_engineer_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function whatsappNotifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'whatsapp_notified_by_id');
    }

    public function emailSentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email_assignment_sent_by_id');
    }

    public function resolutionEmailSentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolution_email_sent_by_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to_id');
    }

    public function originalFieldEngineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_field_engineer_id');
    }

    public function workshopEngineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'workshop_engineer_id');
    }

    public function workshopReceivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'workshop_received_by_id');
    }

    public function returnDispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_dispatched_by_id');
    }

    public function bankReceivedConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bank_received_confirmed_by_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(TicketFeedback::class)->orderBy('day_number', 'asc');
    }

    // ---- Multi-engineer alignment ------------------------------------------------

    public function ticketEngineers(): HasMany
    {
        return $this->hasMany(TicketEngineer::class);
    }

    public function activeTicketEngineers(): HasMany
    {
        return $this->hasMany(TicketEngineer::class)->whereNull('released_at');
    }

    /** All engineers currently aligned (lead + support). */
    public function activeEngineers()
    {
        return User::whereIn(
            'id',
            $this->activeTicketEngineers()->pluck('engineer_id')
        )->get();
    }

    public function activeEngineerIds(): array
    {
        $ids = $this->activeTicketEngineers()->pluck('engineer_id')->all();
        if ($this->assigned_engineer_id) {
            $ids[] = $this->assigned_engineer_id;
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** True if the user is the lead or an active support engineer on this ticket. */
    public function hasEngineer($user): bool
    {
        $id = is_object($user) ? $user->id : (int) $user;
        return in_array((int) $id, $this->activeEngineerIds(), true);
    }

    public function isLeadEngineer($user): bool
    {
        $id = is_object($user) ? $user->id : (int) $user;
        return (int) $this->assigned_engineer_id === (int) $id;
    }

    /** Tickets visible to an engineer: lead, support, or legacy assigned id. */
    public function scopeForEngineer($query, int $engineerId)
    {
        return $query->where(function ($q) use ($engineerId) {
            $q->where('assigned_engineer_id', $engineerId)
              ->orWhereHas('activeTicketEngineers', fn ($e) => $e->where('engineer_id', $engineerId));
        });
    }

    // ---- Reopen cycles -----------------------------------------------------------

    public function cycles(): HasMany
    {
        return $this->hasMany(TicketCycle::class)->orderBy('cycle_no');
    }

    public function currentCycle(): ?TicketCycle
    {
        return $this->cycles()->where('cycle_no', $this->current_cycle_no ?: 1)->first();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TicketDocument::class)->orderBy('uploaded_at');
    }

    public const REOPEN_WINDOW_DAYS = 15;

    public function resolutionOrCloseDate(): ?Carbon
    {
        if ($this->closed_at) {
            return Carbon::parse($this->closed_at);
        }
        if ($this->resolved_at) {
            return Carbon::parse($this->resolved_at);
        }
        if (in_array($this->status, ['resolved', 'closed'], true) && $this->updated_at) {
            return Carbon::parse($this->updated_at);
        }
        return null;
    }

    public function isReopenWindowExpired(): bool
    {
        $resolvedDate = $this->resolutionOrCloseDate();
        if (!$resolvedDate) {
            return false;
        }
        return $resolvedDate->diffInDays(now()) > self::REOPEN_WINDOW_DAYS;
    }

    public function reopenDaysRemaining(): int
    {
        $resolvedDate = $this->resolutionOrCloseDate();
        if (!$resolvedDate) {
            return self::REOPEN_WINDOW_DAYS;
        }
        $expiry = $resolvedDate->copy()->addDays(self::REOPEN_WINDOW_DAYS);
        if (now() >= $expiry) {
            return 0;
        }
        return max(0, (int) ceil(now()->diffInHours($expiry, false) / 24));
    }

    public function canBeReopened(): bool
    {
        if (!in_array($this->status, ['resolved', 'closed'], true)) {
            return false;
        }
        return !$this->isReopenWindowExpired();
    }

    /** Claims belonging to the current tour only; earlier tours stay visible via expenseClaims(). */
    public function currentCycleClaims(): HasMany
    {
        return $this->hasMany(ExpenseClaim::class)
            ->where('cycle_id', TicketCycle::where('ticket_id', $this->id)->orderByDesc('cycle_no')->value('id'));
    }

    public function expenseClaims(): HasMany
    {
        return $this->hasMany(ExpenseClaim::class);
    }

    public function latestExpenseClaim(): HasOne
    {
        return $this->hasOne(ExpenseClaim::class)->latestOfMany();
    }

    public function partRequests(): HasMany
    {
        return $this->hasMany(PartRequest::class)->latest();
    }

    public function latestPartRequest(): HasOne
    {
        return $this->hasOne(PartRequest::class)->latestOfMany();
    }

    public function advanceTransactions(): HasMany
    {
        return $this->hasMany(EngineerAdvanceTransaction::class)->latest();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TicketLog::class)->latest();
    }

    public function emails(): HasMany
    {
        return $this->hasMany(InboxEmail::class);
    }

    public function incomingEmail(): HasOne
    {
        return $this->hasOne(InboxEmail::class)->where('is_sent', false)->orderBy('id', 'desc');
    }

    public function getIncomingEmailAttribute()
    {
        if ($this->relationLoaded('incomingEmail')) {
            $rel = $this->getRelation('incomingEmail');
            if ($rel) {
                return $rel;
            }
        }

        return $this->emails()->where('is_sent', false)->latest('id')->first();
    }

    public function expectedResponseHours(): int
    {
        return match ($this->urgency) {
            'high' => 4,
            'medium' => 8,
            'low' => 24,
            default => 8,
        };
    }

    public function canNotifyWhatsApp(): bool
    {
        return !empty($this->assigned_engineer_id) && !$this->whatsapp_notified;
    }

    public function canSendAssignmentEmail(): bool
    {
        // Strictly unlocked only after WhatsApp is notified
        return $this->whatsapp_notified && !$this->email_assignment_sent && !empty($this->customer_email);
    }

    public function isSlaBreached(): bool
    {
        if ($this->status === 'closed' || $this->status === 'resolved') {
            return false;
        }

        if ($this->sla_deadline && now()->greaterThan($this->sla_deadline)) {
            return true;
        }

        return false;
    }

    /**
     * Generate HTML for the email body with AI-extracted entities visually highlighted.
     */
    public function getHighlightedBodyHtml(): string
    {
        $raw = $this->issue_description ?? $this->issue_summary ?? '';
        if (empty(trim($raw))) {
            return '<span class="text-slate-500 italic">No email body content recorded.</span>';
        }

        $escaped = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');

        // Target entities to highlight in order of priority (most specific first)
        $targets = [
            [
                'value' => $this->branch_address,
                'label' => 'Street Address',
                'class' => 'bg-rose-500/25 text-rose-200 border border-rose-500/50 px-1 py-0.5 rounded font-semibold inline-block my-0.5 shadow-sm',
                'badge' => 'bg-rose-600 text-white text-[9px] font-bold px-1 py-0.5 rounded ml-1 uppercase tracking-wider',
            ],
            [
                'value' => $this->customer_ref_no,
                'label' => 'Ticket #',
                'class' => 'bg-amber-500/25 text-amber-200 border border-amber-500/50 px-1 py-0.5 rounded font-semibold inline-block my-0.5 shadow-sm',
                'badge' => 'bg-amber-600 text-white text-[9px] font-bold px-1 py-0.5 rounded ml-1 uppercase tracking-wider',
            ],
            [
                'value' => $this->bank_name,
                'label' => 'Bank',
                'class' => 'bg-blue-500/25 text-blue-200 border border-blue-500/50 px-1 py-0.5 rounded font-semibold inline-block my-0.5 shadow-sm',
                'badge' => 'bg-blue-600 text-white text-[9px] font-bold px-1 py-0.5 rounded ml-1 uppercase tracking-wider',
            ],
            [
                'value' => $this->branch_name,
                'label' => 'Branch',
                'class' => 'bg-purple-500/25 text-purple-200 border border-purple-500/50 px-1 py-0.5 rounded font-semibold inline-block my-0.5 shadow-sm',
                'badge' => 'bg-purple-600 text-white text-[9px] font-bold px-1 py-0.5 rounded ml-1 uppercase tracking-wider',
            ],
            [
                'value' => $this->machine_serial_no,
                'label' => 'Serial #',
                'class' => 'bg-emerald-500/25 text-emerald-200 border border-emerald-500/50 px-1 py-0.5 rounded font-semibold inline-block my-0.5 shadow-sm',
                'badge' => 'bg-emerald-600 text-white text-[9px] font-bold px-1 py-0.5 rounded ml-1 uppercase tracking-wider',
            ],
            [
                'value' => $this->machine_model,
                'label' => 'Model',
                'class' => 'bg-teal-500/25 text-teal-200 border border-teal-500/50 px-1 py-0.5 rounded font-semibold inline-block my-0.5 shadow-sm',
                'badge' => 'bg-teal-600 text-white text-[9px] font-bold px-1 py-0.5 rounded ml-1 uppercase tracking-wider',
            ],
            [
                'value' => $this->machine_type,
                'label' => 'Machine',
                'class' => 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-1 py-0.5 rounded font-semibold inline-block my-0.5',
                'badge' => 'bg-emerald-700 text-white text-[9px] font-bold px-1 py-0.5 rounded ml-1 uppercase tracking-wider',
            ],
            [
                'value' => $this->branch_location,
                'label' => 'City',
                'class' => 'bg-indigo-500/25 text-indigo-200 border border-indigo-500/50 px-1 py-0.5 rounded font-semibold inline-block my-0.5 shadow-sm',
                'badge' => 'bg-indigo-600 text-white text-[9px] font-bold px-1 py-0.5 rounded ml-1 uppercase tracking-wider',
            ],
            [
                'value' => $this->customer_mobile,
                'label' => 'Phone',
                'class' => 'bg-cyan-500/25 text-cyan-200 border border-cyan-500/50 px-1 py-0.5 rounded font-semibold inline-block my-0.5 shadow-sm',
                'badge' => 'bg-cyan-600 text-white text-[9px] font-bold px-1 py-0.5 rounded ml-1 uppercase tracking-wider',
            ],
        ];

        // If branch_address was not filled on the ticket, try detecting street address in text to highlight
        if (empty($this->branch_address)) {
            if (preg_match('/([^\n\r]+(?:Building|Bldg|Road|Street|Near|Plaza|Floor|Chowk|Bazar|Tehsil|Sector)[^\n\r]+(?:Pakistan|[0-9]{5}|Abbottabad|Lahore|Karachi|Rawalpindi|Islamabad|Peshawar))/i', $raw, $detectedAddr)) {
                $targets[] = [
                    'value' => trim($detectedAddr[1]),
                    'label' => 'Detected Address',
                    'class' => 'bg-rose-500/25 text-rose-200 border border-dashed border-rose-500 px-1 py-0.5 rounded font-semibold inline-block my-0.5 shadow-sm',
                    'badge' => 'bg-rose-600 text-white text-[9px] font-bold px-1 py-0.5 rounded ml-1 uppercase tracking-wider',
                ];
            }
        }

        foreach ($targets as $target) {
            $val = trim($target['value'] ?? '');
            if (empty($val) || strlen($val) < 2) {
                continue;
            }

            // Split into words to handle whitespace/newlines in raw text
            $words = preg_split('/[\s,]+/', $val, -1, PREG_SPLIT_NO_EMPTY);
            if (empty($words)) {
                continue;
            }

            $escapedWords = array_map(function ($w) {
                return preg_quote($w, '/');
            }, $words);

            $pattern = implode('[\s,]+', $escapedWords);

            $result = @preg_replace_callback('/(?<!\w)' . $pattern . '(?!\w)/iu', function ($m) use ($target) {
                return '<mark class="' . $target['class'] . '">' . $m[0] . '<span class="' . $target['badge'] . '">' . $target['label'] . '</span></mark>';
            }, (string) $escaped, 1);

            if ($result !== null) {
                $escaped = $result;
            }
        }

        return $escaped;
    }

    public function getSlaHealth(): array
    {
        if (!$this->sla_deadline) {
            return [
                'percent' => 100,
                'status' => 'healthy',
                'color' => 'emerald',
                'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                'bar' => 'bg-emerald-500',
                'label' => 'Standard SLA',
                'breached' => false,
            ];
        }

        if (in_array($this->status, ['resolved', 'closed'])) {
            return [
                'percent' => 100,
                'status' => 'resolved',
                'color' => 'slate',
                'badge' => 'bg-slate-100 text-slate-700 border-slate-300',
                'bar' => 'bg-slate-400',
                'label' => ucfirst($this->status),
                'breached' => false,
            ];
        }

        if ($this->status === 'awaiting_approval') {
            $pauseTime = $this->sla_paused_at ?? Carbon::now();
            $createdAt = $this->assigned_at ?? $this->created_at ?? Carbon::now();
            $totalMinutes = max(1, abs($createdAt->diffInMinutes($this->sla_deadline, false)));
            $remainingMinutes = $pauseTime->diffInMinutes($this->sla_deadline, false);

            if ($remainingMinutes <= 0) {
                return [
                    'percent' => 0,
                    'status' => 'paused',
                    'color' => 'amber',
                    'badge' => 'bg-amber-100 text-amber-900 border-amber-300 ring-1 ring-amber-400 font-bold',
                    'bar' => 'bg-amber-500',
                    'label' => 'SLA Paused (0m remaining)',
                    'paused_label' => 'Awaiting Approval',
                    'breached' => false,
                    'is_paused' => true,
                ];
            }

            $percentRemaining = min(100, max(0, round(($remainingMinutes / $totalMinutes) * 100)));
            $frozenLeft = $pauseTime->diffForHumans($this->sla_deadline, [
                'parts' => 2,
                'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
            ]);

            return [
                'percent' => $percentRemaining,
                'status' => 'paused',
                'color' => 'amber',
                'badge' => 'bg-amber-100 text-amber-900 border-amber-300 ring-1 ring-amber-400 font-bold',
                'bar' => 'bg-amber-500',
                'label' => 'SLA Paused (' . $frozenLeft . ' frozen)',
                'paused_label' => 'Awaiting Approval',
                'breached' => false,
                'is_paused' => true,
            ];
        }

        $createdAt = $this->assigned_at ?? $this->created_at ?? Carbon::now();
        $totalMinutes = max(1, abs($createdAt->diffInMinutes($this->sla_deadline, false)));
        $remainingMinutes = Carbon::now()->diffInMinutes($this->sla_deadline, false);

        if ($remainingMinutes <= 0) {
            $overdueFormatted = Carbon::now()->diffForHumans($this->sla_deadline, [
                'parts' => 2,
                'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
            ]);
            return [
                'percent' => 0,
                'status' => 'breached',
                'color' => 'rose',
                'badge' => 'bg-rose-100 text-rose-800 border-rose-300 animate-pulse',
                'bar' => 'bg-rose-600 animate-pulse',
                'label' => 'Breached (' . $overdueFormatted . ' overdue)',
                'breached' => true,
            ];
        }

        $percentRemaining = min(100, max(0, round(($remainingMinutes / $totalMinutes) * 100)));

        if ($percentRemaining > 50) {
            $color = 'emerald';
            $badge = 'bg-emerald-100 text-emerald-800 border-emerald-300';
            $bar = 'bg-emerald-500';
            $status = 'healthy';
        } elseif ($percentRemaining >= 20) {
            $color = 'amber';
            $badge = 'bg-amber-100 text-amber-800 border-amber-300';
            $bar = 'bg-amber-500';
            $status = 'warning';
        } else {
            $color = 'rose';
            $badge = 'bg-rose-100 text-rose-800 border-rose-300';
            $bar = 'bg-rose-500';
            $status = 'critical';
        }

        // Clean time string without awkward "from now"
        $timeRemainingLabel = Carbon::now()->diffForHumans($this->sla_deadline, [
            'parts' => 2,
            'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
        ]);

        return [
            'percent' => $percentRemaining,
            'status' => $status,
            'color' => $color,
            'badge' => $badge,
            'bar' => $bar,
            'label' => $timeRemainingLabel,
            'breached' => false,
        ];
    }

    /**
     * Compute comprehensive lifecycle audit trail metrics:
     * - Gross elapsed time (Email received to resolution)
     * - Weekend pauses (Saturday and Sunday do not count against SLA)
     * - Workshop transit time (Transit In + Return Delivery)
     * - Management approval waiting pauses
     * - Net business resolution time (Gross minus non-working pauses)
     */
    public function calculateAuditTrailMetrics(): array
    {
        $start = $this->created_at ?? Carbon::now();
        $isResolved = in_array($this->status, ['resolved', 'closed']);
        $end = $this->resolved_at ? Carbon::parse($this->resolved_at) : ($isResolved ? ($this->updated_at ?? Carbon::now()) : Carbon::now());

        if ($start > $end) {
            $end = $start->copy();
        }

        $grossMinutes = (int) $start->diffInMinutes($end);

        // 1. Weekend Slices (Saturday and Sunday non-working hours)
        $weekendIntervals = [];
        $cursor = $start->copy();
        while ($cursor < $end) {
            $dayEnd = $cursor->copy()->endOfDay();
            $sliceEnd = $dayEnd < $end ? $dayEnd : $end->copy();

            if ($cursor->isWeekend()) {
                $weekendIntervals[] = [
                    'start' => $cursor->copy(),
                    'end' => $sliceEnd->copy(),
                ];
            }
            $cursor = $sliceEnd->copy()->addSecond();
        }

        $weekendMinutes = 0;
        foreach ($weekendIntervals as $wi) {
            $weekendMinutes += (int) $wi['start']->diffInMinutes($wi['end']);
        }

        // 2. Workshop Transit intervals
        $transitIntervals = [];
        $transitInMinutes = 0;
        if ($this->workshop_dispatched_at && $this->workshop_received_at && $this->workshop_dispatched_at < $this->workshop_received_at) {
            $tInStart = max($start, $this->workshop_dispatched_at);
            $tInEnd = min($end, $this->workshop_received_at);
            if ($tInStart < $tInEnd) {
                $transitIntervals[] = [
                    'start' => $tInStart,
                    'end' => $tInEnd,
                    'type' => 'transit_in',
                ];
                $transitInMinutes = (int) $tInStart->diffInMinutes($tInEnd);
            }
        }

        $transitOutMinutes = 0;
        if ($this->return_dispatched_at && $this->bank_received_at && $this->return_dispatched_at < $this->bank_received_at) {
            $tOutStart = max($start, $this->return_dispatched_at);
            $tOutEnd = min($end, $this->bank_received_at);
            if ($tOutStart < $tOutEnd) {
                $transitIntervals[] = [
                    'start' => $tOutStart,
                    'end' => $tOutEnd,
                    'type' => 'transit_out',
                ];
                $transitOutMinutes = (int) $tOutStart->diffInMinutes($tOutEnd);
            }
        }

        $transitMinutes = $transitInMinutes + $transitOutMinutes;

        // 3. Approval Paused intervals
        $approvalIntervals = [];
        $approvalMinutes = 0;
        if ($this->approval_requested_at && $this->approval_arrived_at && $this->approval_requested_at < $this->approval_arrived_at) {
            $appStart = max($start, $this->approval_requested_at);
            $appEnd = min($end, $this->approval_arrived_at);
            if ($appStart < $appEnd) {
                $approvalIntervals[] = [
                    'start' => $appStart,
                    'end' => $appEnd,
                ];
                $approvalMinutes = (int) $appStart->diffInMinutes($appEnd);
            }
        } elseif ($this->approval_paused_seconds > 0) {
            $approvalMinutes = (int) round($this->approval_paused_seconds / 60);
        }

        // 4. Calculate Total Permitted Deductions
        // Gross TAT − Weekend Deductions − Workshop Transit − Approval Pauses = Net Business SLA Time
        $totalDeductedMinutes = min($grossMinutes, $weekendMinutes + $transitMinutes + $approvalMinutes);
        $netMinutes = max(0, $grossMinutes - $totalDeductedMinutes);

        $formatHhMm = function (int $minutes): string {
            $h = floor($minutes / 60);
            $m = $minutes % 60;
            return "{$h}h {$m}m";
        };

        // Target SLA hours from urgency or deadline
        $targetHours = $this->sla_deadline
            ? round(abs($start->diffInMinutes($this->sla_deadline)) / 60, 1)
            : $this->expectedResponseHours();

        $isInTat = ($netMinutes / 60) <= ($targetHours + 0.05);

        return [
            'is_resolved' => $isResolved,
            'start_at' => $start,
            'end_at' => $end,
            'gross_minutes' => $grossMinutes,
            'gross_hours' => round($grossMinutes / 60, 1),
            'gross_formatted' => $formatHhMm($grossMinutes),

            'weekend_minutes' => $weekendMinutes,
            'weekend_hours' => round($weekendMinutes / 60, 1),
            'weekend_formatted' => $formatHhMm($weekendMinutes),

            'transit_in_minutes' => $transitInMinutes,
            'transit_in_hours' => round($transitInMinutes / 60, 1),
            'transit_out_minutes' => $transitOutMinutes,
            'transit_out_hours' => round($transitOutMinutes / 60, 1),
            'transit_minutes' => $transitMinutes,
            'transit_hours' => round($transitMinutes / 60, 1),
            'transit_formatted' => $formatHhMm($transitMinutes),

            'approval_minutes' => $approvalMinutes,
            'approval_hours' => round($approvalMinutes / 60, 1),
            'approval_formatted' => $formatHhMm($approvalMinutes),

            'total_deducted_minutes' => $totalDeductedMinutes,
            'total_deducted_hours' => round($totalDeductedMinutes / 60, 1),
            'total_deducted_formatted' => $formatHhMm($totalDeductedMinutes),

            'net_minutes' => $netMinutes,
            'net_hours' => round($netMinutes / 60, 1),
            'net_formatted' => $formatHhMm($netMinutes),

            'target_sla_hours' => $targetHours,
            'is_in_tat' => $isInTat,
            'tat_status_label' => $isInTat ? 'In-TAT (Compliant)' : 'Out-of-TAT (Exceeded)',
        ];
    }

    /**
     * Compute day-by-day operational feedback matrix from ticket intake to completion or now.
     * Identifies any day where daily feedback was NOT logged and marks it as 'N/A' (missing).
     */
    public function getDailyFeedbackMatrix(): array
    {
        $start = ($this->created_at ?? Carbon::now())->copy()->startOfDay();
        $isResolved = in_array($this->status, ['resolved', 'closed']);
        $end = ($this->resolved_at ? Carbon::parse($this->resolved_at) : ($isResolved ? ($this->updated_at ?? Carbon::now()) : Carbon::now()))->copy()->startOfDay();

        if ($start > $end) {
            $end = $start->copy();
        }

        // Calculate total calendar days elapsed
        $calendarDays = max(1, (int) $start->diffInDays($end) + 1);

        // In case feedbacks have higher day_numbers recorded
        $maxFeedbackDay = $this->feedbacks->max('day_number') ?? 0;
        $totalDays = max($calendarDays, (int) $maxFeedbackDay);

        // Several engineers can report on the same day: keep all of them.
        $feedbacksByDay = $this->feedbacks->groupBy('day_number');

        // After a reopen, only days inside an open tour count (days while closed are not "missing").
        $windows = null;
        if ((int) $this->reopen_count > 0) {
            $windows = $this->cycles()->get()->map(function ($c) {
                $from = ($c->opened_at ?? Carbon::now())->copy()->startOfDay();
                $to = ($c->resolved_at ?? $c->closed_at ?? Carbon::now())->copy()->startOfDay();
                return [$from, $to];
            });
        }

        $matrix = [];
        for ($day = 1; $day <= $totalDays; $day++) {
            $dayFeedbacks = $feedbacksByDay->get($day, collect());
            $dayDate = $start->copy()->addDays($day - 1);

            if ($dayFeedbacks->isEmpty() && $windows !== null) {
                $inTour = $windows->contains(fn ($w) => $dayDate->between($w[0], $w[1]));
                if (!$inTour) {
                    continue;
                }
            }

            if ($dayFeedbacks->isNotEmpty()) {
                $fb = $dayFeedbacks->sortByDesc(fn ($f) => $f->submitted_at ?? $f->created_at)->first();
                $multi = $dayFeedbacks->count() > 1;
                $nameOf = fn ($f) => $f->submittedBy?->name ?? $f->engineer?->name ?? $this->assignedEngineer?->name ?? 'Assigned Engineer';

                $matrix[] = [
                    'day_number' => $day,
                    'is_logged' => true,
                    'status' => 'logged',
                    'date_estimated' => $dayDate,
                    'feedback' => $fb,
                    'feedback_text' => $multi
                        ? $dayFeedbacks->map(fn ($f) => '[' . $nameOf($f) . '] ' . $f->feedback_text)->implode("\n")
                        : $fb->feedback_text,
                    'action_taken' => $multi
                        ? $dayFeedbacks->map(fn ($f) => '[' . $nameOf($f) . '] ' . $f->action_taken)->implode(' | ')
                        : $fb->action_taken,
                    'parts_required' => $fb->parts_required,
                    'submitted_at' => $fb->submitted_at ?? $fb->created_at,
                    'engineer_name' => $dayFeedbacks->map($nameOf)->unique()->implode(', '),
                    'tour_no' => (int) ($fb->cycle_id ? TicketCycle::where('id', $fb->cycle_id)->value('cycle_no') : 1),
                ];
            } else {
                $matrix[] = [
                    'day_number' => $day,
                    'is_logged' => false,
                    'status' => 'missing',
                    'date_estimated' => $dayDate,
                    'feedback' => null,
                    'feedback_text' => 'N/A',
                    'action_taken' => 'No action logged (N/A)',
                    'parts_required' => null,
                    'submitted_at' => null,
                    'engineer_name' => 'N/A',
                    'tour_no' => null,
                ];
            }
        }

        return $matrix;
    }

    /**
     * Get Bank Email Landing details and exact elapsed duration to NOW.
     */
    public function getEmailLandingAudit(): array
    {
        // Use actual incoming email landing date/time if available, otherwise ticket creation time
        $email = $this->incomingEmail;
        $landedAt = ($email && $email->email_date) ? $email->email_date : ($this->created_at ?? Carbon::now());
        $now = Carbon::now();
        $isResolved = in_array($this->status, ['resolved', 'closed']);

        $elapsedToNow = $landedAt->diffForHumans($now, [
            'parts' => 3,
            'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
        ]);

        $elapsedHours = round($landedAt->diffInMinutes($now) / 60, 1);

        $replyTatMinutes = ($this->email_assignment_sent_at && $landedAt)
            ? $landedAt->diffInMinutes($this->email_assignment_sent_at)
            : null;

        return [
            'landed_at' => $landedAt,
            'landed_at_formatted' => $landedAt->format('d M Y, h:i A'),
            'elapsed_to_now' => $elapsedToNow,
            'elapsed_hours_to_now' => $elapsedHours,
            'reply_tat_minutes' => $replyTatMinutes,
            'email_subject' => $this->email_subject ?? 'Bank Support Requisition',
            'customer_email' => $this->customer_email ?? 'N/A',
            'is_resolved' => $isResolved,
        ];
    }

    public function hasSupportingDocument(): bool
    {
        return !empty($this->supporting_document);
    }

    public function supportingDocumentUrl(): ?string
    {
        return $this->supporting_document ? asset('storage/' . $this->supporting_document) : null;
    }

    public function isSupportingDocumentImage(): bool
    {
        if (empty($this->supporting_document)) {
            return false;
        }
        $ext = strtolower(pathinfo($this->supporting_document, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    public function hasClaimedExpenses(): bool
    {
        return $this->currentCycleClaims()->exists();
    }

    public function isResolutionLocked(): bool
    {
        return in_array($this->status, ['resolved', 'closed']) && $this->hasClaimedExpenses();
    }

    public function activeExpenseClaim(): ?ExpenseClaim
    {
        return $this->currentCycleClaims()
            ->whereIn('status', ['submitted', 'approved', 'paid'])
            ->latest()
            ->first();
    }

    public function hasActiveExpenseClaim(?int $engineerId = null): bool
    {
        return $this->currentCycleClaims()
            ->when($engineerId, fn ($q) => $q->where('engineer_id', $engineerId))
            ->whereIn('status', ['submitted', 'approved', 'paid'])
            ->exists();
    }

    public function hasRejectedExpenseClaim(): bool
    {
        return $this->expenseClaims()
            ->where('status', 'rejected')
            ->exists();
    }

    public function canClaimExpense(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();

        if ($this->hasActiveExpenseClaim($userId)) {
            return false;
        }

        // Workshop Lifecycle Policy
        if ($this->isWorkshopFlow()) {
            // Unit must be physically received at workshop before travel expense can be claimed
            if (!$this->workshop_received_at) {
                return false;
            }

            // ONLY the original field engineer who visited the bank branch can claim expense
            $originalFieldEngId = $this->original_field_engineer_id;
            if ($userId && $originalFieldEngId && (int)$userId !== (int)$originalFieldEngId) {
                return false;
            }

            return in_array($this->status, ['in_workshop_repair', 'workshop_repaired', 'return_transit', 'closed', 'resolved']);
        }

        // Standard Complaints: Tour expense CANNOT be claimed before mark done (must be resolved or closed)
        return in_array($this->status, ['resolved', 'closed']);
    }

    public function canSendResolutionEmail(): bool
    {
        return in_array($this->status, ['resolved', 'closed']) && !empty($this->customer_email);
    }

    /**
     * Clean and normalize raw email text (stripping markdown asterisks and pairing labels).
     */
    public function cleanEmailText(): string
    {
        $text = $this->incomingEmail?->body_plain 
            ?? ($this->issue_description ?: $this->issue_summary);
        
        if (empty($text)) {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        
        $cleanedLines = [];
        $lastWasEmpty = false;
        foreach ($lines as $line) {
            $line = trim($line);
            $cleanLine = trim($line, "* \t");
            if ($cleanLine === '') {
                if (!$lastWasEmpty) {
                    $cleanedLines[] = '';
                    $lastWasEmpty = true;
                }
            } else {
                $cleanedLines[] = $cleanLine;
                $lastWasEmpty = false;
            }
        }

        $count = count($cleanedLines);
        $i = 0;
        $result = [];

        $knownLabels = [
            'complaint number', 'issue details', 'status', 'complaint type',
            'complaint date', 'logged by', 'vendor', 'vendor contact',
            'branch code', 'branch name', 'bom', 'branch contact number',
            'branch address', 'machine model', 'machine serial no', 'atm id', 'terminal id'
        ];

        while ($i < $count) {
            $line = $cleanedLines[$i];
            if ($line === '') {
                $result[] = '';
                $i++;
                continue;
            }

            $lower = strtolower($line);
            $isLabel = in_array($lower, $knownLabels) || (
                $i + 1 < $count && 
                strlen($line) <= 30 && 
                !str_contains($line, ':') && 
                !str_contains($line, '.') && 
                $cleanedLines[$i + 1] !== ''
            );

            if ($isLabel && $i + 1 < $count && $cleanedLines[$i + 1] !== '') {
                $result[] = $line . ': ' . $cleanedLines[$i + 1];
                $i += 2;
                continue;
            }

            $result[] = $line;
            $i++;
        }

        return trim(implode("\n", $result));
    }

    /**
     * Render the clean email content into a compact, beautiful HTML block.
     */
    public function formattedEmailBodyHtml(): string
    {
        $text = $this->cleanEmailText();
        
        if (empty($text)) {
            return '<div style="color:#64748b;font-style:italic;">No email content available.</div>';
        }

        $lines = explode("\n", $text);
        $html = '<div style="display:flex;flex-direction:column;gap:4px;">';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $html .= '<div style="height:6px;"></div>';
                continue;
            }

            // If line contains "Label: Value" where Label is a field title
            if (str_contains($line, ':') && !str_starts_with($line, 'http')) {
                $parts = explode(':', $line, 2);
                $lbl = trim($parts[0]);
                $val = trim($parts[1]);

                if (strlen($lbl) <= 35) {
                    $html .= '<div style="display:flex;padding:2px 0;border-bottom:1px dashed #f1f5f9;font-size:12.5px;">'
                           . '<span style="width:170px;font-weight:700;color:#334155;flex-shrink:0;">' . htmlspecialchars($lbl) . ':</span>'
                           . '<span style="font-weight:500;color:#0f172a;flex:1;">' . htmlspecialchars($val) . '</span>'
                           . '</div>';
                    continue;
                }
            }

            // Paragraph / Salutation / Signature line
            $html .= '<div style="color:#1e293b;line-height:1.45;font-size:13px;">' . htmlspecialchars($line) . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    public function isWorkshopFlow(): bool
    {
        return !empty($this->workshop_location) || !empty($this->workshop_dispatched_at);
    }

    public function isInWorkshopTransit(): bool
    {
        return in_array($this->status, ['awaiting_workshop', 'in_transit_to_workshop']);
    }

    public function isInWorkshopRepair(): bool
    {
        return $this->status === 'in_workshop_repair';
    }

    public function isWorkshopRepaired(): bool
    {
        return $this->status === 'workshop_repaired';
    }

    public function isReturnTransit(): bool
    {
        return $this->status === 'return_transit';
    }

    /**
     * Overall Ticket age in elapsed days (Day 1, Day 2, Day 5, etc.)
     */
    public function getOverallTicketDayAttribute(): int
    {
        $start = $this->created_at ?? now();
        return max(1, (int) $start->diffInDays(now()) + 1);
    }

    /**
     * Days elapsed since physical workshop intake for the Workshop Engineer
     */
    public function getWorkshopEngineerDayAttribute(): int
    {
        if (!$this->workshop_received_at) {
            return 0;
        }
        $end = $this->workshop_repaired_at ?? now();
        return max(1, (int) $this->workshop_received_at->diffInDays($end) + 1);
    }

    /**
     * Days the machine spent on-site with the original field engineer
     */
    public function getFieldDurationTextAttribute(): string
    {
        $start = $this->assigned_at ?? $this->created_at;
        $end = $this->workshop_dispatched_at ?? now();
        if (!$start) return '—';
        return $this->formatDuration($start, $end);
    }

    /**
     * Days machine spent in transit from branch to central workshop
     */
    public function getInboundTransitDurationTextAttribute(): string
    {
        if (!$this->workshop_dispatched_at) return '—';
        $end = $this->workshop_received_at ?? now();
        return $this->formatDuration($this->workshop_dispatched_at, $end);
    }

    /**
     * Days machine spent on the workshop bench under repair
     */
    public function getWorkshopRepairDurationTextAttribute(): string
    {
        if (!$this->workshop_received_at) return '—';
        $end = $this->workshop_repaired_at ?? now();
        return $this->formatDuration($this->workshop_received_at, $end);
    }

    /**
     * Days machine spent returning from workshop to bank branch
     */
    public function getReturnTransitDurationTextAttribute(): string
    {
        if (!$this->return_dispatched_at) return '—';
        $end = $this->bank_received_at ?? now();
        return $this->formatDuration($this->return_dispatched_at, $end);
    }

    /**
     * Total overall SLA resolution duration
     */
    public function getTotalResolutionDurationTextAttribute(): string
    {
        $start = $this->created_at;
        $end = $this->closed_at ?? $this->bank_received_at ?? $this->resolved_at ?? now();
        if (!$start) return '—';
        return $this->formatDuration($start, $end);
    }

    private function formatDuration(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): string
    {
        $diff = $start->diff($end);
        $parts = [];
        if ($diff->d > 0) $parts[] = "{$diff->d}d";
        if ($diff->h > 0) $parts[] = "{$diff->h}h";
        if (empty($parts) || ($diff->d === 0 && $diff->h === 0)) {
            $parts[] = max(1, $diff->i) . 'm';
        }
        return implode(' ', $parts);
    }
}

