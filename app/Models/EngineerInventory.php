<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineerInventory extends Model
{
    protected $fillable = [
        'engineer_id',
        'part_id',
        'qty_allocated',
        'qty_used',
        'qty_on_hand',
    ];

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'part_id');
    }
}
