<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxEmail extends Model
{
    use HasFactory;

    protected $table = 'inbox_emails';

    protected $fillable = [
        'message_id',
        'uid',
        'from_email',
        'from_name',
        'to_email',
        'cc_emails',
        'subject',
        'body_text',
        'body_html',
        'email_date',
        'is_read',
        'is_sent',
        'ticket_id',
    ];

    protected $casts = [
        'email_date' => 'datetime',
        'is_read' => 'boolean',
        'is_sent' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function isComplaint(): bool
    {
        return !is_null($this->ticket_id) && !is_null($this->ticket);
    }
}
