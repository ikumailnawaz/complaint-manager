<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Part extends Model {
    protected $fillable = ['part_number', 'name', 'description', 'unit', 'unit_cost', 'reorder_level', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'unit_cost' => 'decimal:2', 'reorder_level' => 'integer'];
    
    public function machineModels(): BelongsToMany {
        return $this->belongsToMany(MachineModel::class, 'machine_model_parts')->withPivot('is_common');
    }
    public function stockLedgers(): HasMany {
        return $this->hasMany(PartStockLedger::class);
    }
    public function stockMovements(): HasMany {
        return $this->hasMany(StockMovement::class);
    }
    public function requestItems(): HasMany {
        return $this->hasMany(PartRequestItem::class);
    }
    public function grnItems(): HasMany {
        return $this->hasMany(GrnItem::class);
    }
    public function engineerInventories(): HasMany {
        return $this->hasMany(EngineerInventory::class);
    }
    public function stockAtLocation(int $locationId): ?PartStockLedger {
        return $this->stockLedgers()->where('location_id', $locationId)->first();
    }
    public function totalStock(): int {
        return (int) $this->stockLedgers()->sum('qty_on_hand');
    }
    public function availableStock(): int {
        return (int) $this->stockLedgers()->selectRaw('SUM(qty_on_hand - qty_reserved) as avail')->value('avail');
    }
    public function isLowStock(): bool {
        return $this->totalStock() <= $this->reorder_level && $this->reorder_level > 0;
    }
}
