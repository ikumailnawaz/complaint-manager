<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartRequest extends Model {
    use \App\Models\Concerns\TagsTicketCycle;

    protected $fillable = [
        'request_number', 'ticket_id', 'cycle_id', 'engineer_id', 'machine_model_id', 'machine_serial_no',
        'fault_description', 'status', 'fault_video_path',
        'additional_machine_model_id', 'additional_machine_model', 'additional_machine_serial_no',
        'additional_machine_type', 'additional_fault_description', 'additional_fault_video_path',
        'additional_machines',
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
        'additional_machines' => 'array',
    ];
    
    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function engineer(): BelongsTo { return $this->belongsTo(User::class, 'engineer_id'); }
    public function machineModel(): BelongsTo { return $this->belongsTo(MachineModel::class); }
    public function additionalMachineModel(): BelongsTo { return $this->belongsTo(MachineModel::class, 'additional_machine_model_id'); }
    public function hasAdditionalMachine(): bool {
        return !empty($this->additional_machines) ||
               !empty($this->additional_machine_model) ||
               !empty($this->additional_machine_model_id) ||
               !empty($this->additional_machine_serial_no);
    }

    public function getAdditionalMachinesListAttribute(): array {
        if (!empty($this->additional_machines) && is_array($this->additional_machines)) {
            return array_values($this->additional_machines);
        }
        if (!empty($this->additional_machine_model) || !empty($this->additional_machine_model_id) || !empty($this->additional_machine_serial_no)) {
            return [[
                'id'                => 1,
                'model_id'          => $this->additional_machine_model_id,
                'model_name'        => $this->additionalMachineModel?->name ?? $this->additional_machine_model,
                'serial_no'         => $this->additional_machine_serial_no,
                'machine_type'      => $this->additional_machine_type,
                'fault_description' => $this->additional_fault_description,
                'fault_video_url'   => $this->additional_fault_video_path ? asset('storage/' . $this->additional_fault_video_path) : null,
            ]];
        }
        return [];
    }
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
