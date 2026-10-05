<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartRequest extends Model {
    protected $fillable = [
        'request_number', 'ticket_id', 'engineer_id', 'machine_model_id', 'machine_serial_no',
        'fault_description', 'status', 'fault_video_path',
        'stock_verified_by_id', 'stock_verified_at', 'stock_remarks',
        'approved_by_id', 'approved_at', 'approval_remarks',
        'rejected_by_id', 'rejected_at', 'rejection_reason',
        'dispatched_by_id', 'dispatched_at', 'dispatch_location_id',
        'dispatch_courier', 'dispatch_tracking_number',
        'faulty_return_status', 'faulty_returned_at', 'faulty_return_received_by_id',
        'faulty_return_location_id', 'faulty_return_courier_tracking', 'faulty_return_remarks',
    ];
    protected $casts = [
        'stock_verified_at' => 'datetime', 'approved_at' => 'datetime',
        'rejected_at' => 'datetime', 'dispatched_at' => 'datetime',
        'faulty_returned_at' => 'datetime',
    ];
    
    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function engineer(): BelongsTo { return $this->belongsTo(User::class, 'engineer_id'); }
    public function machineModel(): BelongsTo { return $this->belongsTo(MachineModel::class); }
    public function stockVerifiedBy(): BelongsTo { return $this->belongsTo(User::class, 'stock_verified_by_id'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by_id'); }
    public function rejectedBy(): BelongsTo { return $this->belongsTo(User::class, 'rejected_by_id'); }
    public function dispatchedBy(): BelongsTo { return $this->belongsTo(User::class, 'dispatched_by_id'); }
    public function dispatchLocation(): BelongsTo { return $this->belongsTo(Location::class, 'dispatch_location_id'); }
    public function faultyReturnReceivedBy(): BelongsTo { return $this->belongsTo(User::class, 'faulty_return_received_by_id'); }
    public function faultyReturnLocation(): BelongsTo { return $this->belongsTo(Location::class, 'faulty_return_location_id'); }
    public function items(): HasMany { return $this->hasMany(PartRequestItem::class); }
    public function isEditable(): bool {
        return !in_array($this->status, ['approved', 'dispatched']);
    }

    public function getStatusColorAttribute(): string {
        return match($this->status) {
            'pending_stock_check' => 'amber',
            'stock_verified' => 'blue',
            'pending_approval' => 'purple',
            'approved' => 'green',
            'dispatched' => 'emerald',
            'rejected' => 'rose',
            default => 'slate',
        };
    }
    public function getStatusLabelAttribute(): string {
        return match($this->status) {
            'pending_stock_check' => 'Pending Stock Check',
            'stock_verified' => 'Stock Verified',
            'pending_approval' => 'Pending Approval',
            'approved' => 'Approved',
            'dispatched' => 'Dispatched',
            'rejected' => 'Rejected',
            default => ucfirst($this->status),
        };
    }
    public static function generateRequestNumber(): string {
        $year = now()->format('Y');
        $last = static::whereYear('created_at', $year)->orderByDesc('id')->value('request_number');
        $seq = $last ? ((int) substr($last, -4) + 1) : 1;
        return 'PR-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
