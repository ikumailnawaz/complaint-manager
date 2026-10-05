<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrnItem extends Model {
    public $timestamps = false;
    protected $fillable = ['grn_id', 'part_id', 'qty_received', 'unit_cost', 'total_cost', 'condition', 'batch_number'];
    protected $casts = ['unit_cost' => 'decimal:2', 'total_cost' => 'decimal:2'];
    
    public function grn(): BelongsTo { return $this->belongsTo(Grn::class); }
    public function part(): BelongsTo { return $this->belongsTo(Part::class); }
}
