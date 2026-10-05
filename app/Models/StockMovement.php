<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model {
    public $timestamps = false;
    protected $fillable = ['part_id', 'location_id', 'type', 'reference_type', 'reference_id', 'qty', 'qty_after', 'note', 'created_by_id', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];
    
    public function part(): BelongsTo { return $this->belongsTo(Part::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_id'); }
    
    public function getTypeColorAttribute(): string {
        return match($this->type) {
            'grn_in', 'transfer_in' => 'emerald',
            'dispatch_out', 'transfer_out' => 'rose',
            default => 'slate',
        };
    }
    public function getTypeIconAttribute(): string {
        return match($this->type) {
            'grn_in' => 'fa-arrow-down',
            'transfer_in' => 'fa-arrow-right-to-bracket',
            'dispatch_out' => 'fa-arrow-up',
            'transfer_out' => 'fa-arrow-right-from-bracket',
            default => 'fa-arrows-rotate',
        };
    }
}
