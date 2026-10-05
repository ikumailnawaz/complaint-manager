<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartRequestItem extends Model {
    public $timestamps = false;
    protected $fillable = ['part_request_id', 'part_id', 'qty_requested', 'qty_from_envelope', 'qty_approved', 'qty_dispatched', 'unit_cost', 'note'];
    protected $casts = ['unit_cost' => 'decimal:2'];
    
    public function partRequest(): BelongsTo { return $this->belongsTo(PartRequest::class); }
    public function part(): BelongsTo { return $this->belongsTo(Part::class); }
}
