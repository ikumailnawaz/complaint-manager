<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grn extends Model {
    protected $fillable = ['grn_number', 'location_id', 'supplier_name', 'invoice_number', 'invoice_date', 'received_at', 'status', 'remarks', 'created_by_id', 'confirmed_by_id', 'confirmed_at'];
    protected $casts = ['invoice_date' => 'date', 'received_at' => 'date', 'confirmed_at' => 'datetime'];
    
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_id'); }
    public function confirmedBy(): BelongsTo { return $this->belongsTo(User::class, 'confirmed_by_id'); }
    public function items(): HasMany { return $this->hasMany(GrnItem::class); }
    
    public function getTotalValueAttribute(): float {
        return (float) $this->items()->sum('total_cost');
    }
    public function getTotalItemsAttribute(): int {
        return (int) $this->items()->sum('qty_received');
    }
    
    public static function generateGrnNumber(): string {
        $year = now()->format('Y');
        $last = static::whereYear('created_at', $year)->orderByDesc('id')->value('grn_number');
        $seq = $last ? ((int) substr($last, -4) + 1) : 1;
        return 'GRN-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
