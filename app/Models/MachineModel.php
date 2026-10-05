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
    public function getMachineTypeIconAttribute(): string {
        return match($this->machine_type) {
            'atm' => 'fa-building-columns',
            'cdm' => 'fa-dollar-sign',
            'pos' => 'fa-credit-card',
            'kiosk' => 'fa-tablet-screen-button',
            default => 'fa-server',
        };
    }
}
