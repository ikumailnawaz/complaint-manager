<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model {
    protected $fillable = ['name', 'city', 'address', 'type', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    
    public function stockLedgers(): HasMany {
        return $this->hasMany(PartStockLedger::class);
    }
    public function grns(): HasMany {
        return $this->hasMany(Grn::class);
    }
    public function transfersFrom(): HasMany {
        return $this->hasMany(PartTransfer::class, 'from_location_id');
    }
    public function transfersTo(): HasMany {
        return $this->hasMany(PartTransfer::class, 'to_location_id');
    }
    public function stockMovements(): HasMany {
        return $this->hasMany(StockMovement::class);
    }
    public function getTypeIconAttribute(): string {
        return match($this->type) {
            'warehouse' => 'fa-warehouse',
            'hub' => 'fa-location-dot',
            default => 'fa-building',
        };
    }
}
