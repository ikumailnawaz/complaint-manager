<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartTransferItem extends Model {
    public $timestamps = false;
    protected $fillable = ['part_transfer_id', 'part_id', 'qty', 'qty_received'];
    
    public function partTransfer(): BelongsTo { return $this->belongsTo(PartTransfer::class); }
    public function part(): BelongsTo { return $this->belongsTo(Part::class); }
}
