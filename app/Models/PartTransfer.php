<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartTransfer extends Model {
    protected $fillable = [
        'transfer_number', 'from_location_id', 'to_location_id', 'status',
        'requested_by_id', 'dispatched_by_id', 'dispatched_at',
        'received_by_id', 'received_at', 'remarks',
    ];
    protected $casts = ['dispatched_at' => 'datetime', 'received_at' => 'datetime'];
    
    public function fromLocation(): BelongsTo { return $this->belongsTo(Location::class, 'from_location_id'); }
    public function toLocation(): BelongsTo { return $this->belongsTo(Location::class, 'to_location_id'); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_id'); }
    public function dispatchedBy(): BelongsTo { return $this->belongsTo(User::class, 'dispatched_by_id'); }
    public function receivedBy(): BelongsTo { return $this->belongsTo(User::class, 'received_by_id'); }
    public function items(): HasMany { return $this->hasMany(PartTransferItem::class); }
    
    public function getStatusColorAttribute(): string {
        return match($this->status) {
            'pending' => 'amber',
            'in_transit' => 'blue',
            'received' => 'emerald',
            'cancelled' => 'rose',
            default => 'slate',
        };
    }
    public static function generateTransferNumber(): string {
        $year = now()->format('Y');
        $last = static::whereYear('created_at', $year)->orderByDesc('id')->value('transfer_number');
        $seq = $last ? ((int) substr($last, -4) + 1) : 1;
        return 'TRF-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
