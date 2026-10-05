<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartStockLedger extends Model {
    public $timestamps = false;
    protected $fillable = ['part_id', 'location_id', 'qty_on_hand', 'qty_reserved'];
    const UPDATED_AT = 'updated_at';
    const CREATED_AT = null;
    
    protected $dates = ['updated_at'];
    
    public function part(): BelongsTo { return $this->belongsTo(Part::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    
    public function getQtyAvailableAttribute(): int {
        return max(0, $this->qty_on_hand - $this->qty_reserved);
    }
    public function isLowStock(): bool {
        return $this->qty_on_hand <= $this->part->reorder_level && $this->part->reorder_level > 0;
    }
    public function isOutOfStock(): bool {
        return $this->qty_on_hand <= 0;
    }
}
