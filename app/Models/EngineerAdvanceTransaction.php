<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineerAdvanceTransaction extends Model
{
    protected $fillable = [
        'engineer_id',
        'part_id',
        'type', // advance_issue, consumed_complaint, returned_to_warehouse
        'qty',
        'source_location_id',
        'destination_location_id',
        'ticket_id',
        'part_request_id',
        'machine_model_id',
        'machine_serial_no',
        'created_by_id',
        'notes',
    ];

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'part_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function partRequest(): BelongsTo
    {
        return $this->belongsTo(PartRequest::class, 'part_request_id');
    }

    public function machineModel(): BelongsTo
    {
        return $this->belongsTo(MachineModel::class, 'machine_model_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'advance_issue' => 'Advance Lent / Issued',
            'consumed_complaint' => 'Used on Complaint',
            'returned_to_warehouse' => 'Returned to Store',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    public function getTypeBadgeClassAttribute(): string
    {
        return match($this->type) {
            'advance_issue' => 'bg-sky-100 text-sky-800 border-sky-200',
            'consumed_complaint' => 'bg-amber-100 text-amber-800 border-amber-200',
            'returned_to_warehouse' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            default => 'bg-slate-100 text-slate-800 border-slate-200',
        };
    }
}
