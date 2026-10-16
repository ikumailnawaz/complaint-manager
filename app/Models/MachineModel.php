<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MachineModel extends Model {
    protected $fillable = ['name', 'manufacturer', 'machine_type', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    
    public function parts(): BelongsToMany {
        return $this->belongsToMany(Part::class, 'machine_model_parts')->withPivot('is_common');
    }
    public function partRequests(): HasMany {
        return $this->hasMany(PartRequest::class);
    }
    public function pmMachines(): HasMany {
        return $this->hasMany(PmMachine::class);
    }
    public const STANDARD_TYPES = [
        'Cash Sorting Machine',
        'Cash Counting Machine',
        'Cash Binding Machine',
        'Shrink Wrapping Machine',
        'ATM',
        'CDM',
        'POS',
        'Kiosk',
        'Other Equipment',
    ];

    public function getMachineTypeIconAttribute(): string {
        $normalized = strtolower(str_replace([' ', '_', '-'], '', $this->machine_type ?? ''));
        return match($normalized) {
            'atm' => 'fa-building-columns',
            'cdm' => 'fa-dollar-sign',
            'pos' => 'fa-credit-card',
            'kiosk' => 'fa-tablet-screen-button',
            'cashsortingmachine', 'cashsorting', 'sortingmachine' => 'fa-money-bill-transfer',
            'cashcountingmachine', 'cashcounting', 'countingmachine' => 'fa-money-bill-1-wave',
            'cashbindingmachine', 'cashbinding', 'bindingmachine' => 'fa-box-archive',
            'shrinkwrappingmachine', 'shrinkwrapmachine', 'shrinkwrap' => 'fa-boxes-packing',
            default => 'fa-server',
        };
    }

    public function getMachineTypeLabelAttribute(): string {
        return match(strtolower(trim($this->machine_type ?? ''))) {
            'atm' => 'ATM (Cash Dispenser)',
            'cdm' => 'CDM (Cash Deposit)',
            'pos' => 'POS Terminal',
            'kiosk' => 'Self-Service Kiosk',
            'other' => 'Other Equipment',
            default => $this->machine_type ?: 'Unknown',
        };
    }
}
