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

    public function setBodyTextAttribute($value): void
    {
        $this->attributes['body_text'] = self::cleanUtf8($value);
    }

    public function setBodyHtmlAttribute($value): void
    {
        $this->attributes['body_html'] = self::cleanUtf8($value);
    }

    public function setSubjectAttribute($value): void
    {
        $this->attributes['subject'] = self::cleanUtf8($value);
    }

    public function setFromNameAttribute($value): void
    {
        $this->attributes['from_name'] = self::cleanUtf8($value);
    }

    public function setCcEmailsAttribute($value): void
    {
        $this->attributes['cc_emails'] = self::cleanUtf8($value);
    }

    public static function cleanUtf8(?string $str): ?string
    {
        if ($str === null || $str === '') {
            return $str;
        }

        if (!mb_check_encoding($str, 'UTF-8')) {
            $converted = @mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                $str = $converted;
            } else {
                $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
                $str = $converted !== false ? $converted : @mb_convert_encoding($str, 'UTF-8', 'UTF-8');
            }
        }

        if (function_exists('mb_scrub')) {
            $str = mb_scrub($str, 'UTF-8');
        }

        return $str;
    }
}
