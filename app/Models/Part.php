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

    /**
     * Auto-generate a unique Part Number / SKU based on selected machine model or standard prefix.
     */
    public static function generatePartNumber($modelIds = null): string {
        $prefix = 'PRT';
        if (!empty($modelIds)) {
            $firstId = is_array($modelIds) ? reset($modelIds) : $modelIds;
            $model = MachineModel::find($firstId);
            if ($model && !empty($model->name)) {
                $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $model->name));
                if (!empty($clean)) {
                    $prefix = substr($clean, 0, 8);
                }
            }
        }

        $existing = static::where('part_number', 'like', "{$prefix}-%")->pluck('part_number');
        $maxSeq = 0;
        foreach ($existing as $pn) {
            if (preg_match('/' . preg_quote($prefix, '/') . '-(\d+)/i', $pn, $m)) {
                $val = (int) $m[1];
                if ($val > $maxSeq) {
                    $maxSeq = $val;
                }
            }
        }

        $seq = $maxSeq + 1;
        do {
            $candidate = $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while (static::where('part_number', $candidate)->exists());

        return $candidate;
    }
}
