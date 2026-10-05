<?php

namespace App\Services;

use App\Models\Part;
use App\Models\PartStockLedger;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockLedgerService
{
    /**
     * Add stock to a location (GRN IN).
     */
    public function credit(
        int $partId,
        int $locationId,
        int $qty,
        string $type,         // grn_in, transfer_in, adjustment
        string $referenceType,
        ?int $referenceId,
        ?string $note = null
    ): PartStockLedger {
        return DB::transaction(function () use ($partId, $locationId, $qty, $type, $referenceType, $referenceId, $note) {
            $ledger = PartStockLedger::firstOrCreate(
                ['part_id' => $partId, 'location_id' => $locationId],
                ['qty_on_hand' => 0, 'qty_reserved' => 0]
            );
            $ledger->increment('qty_on_hand', $qty);
            $ledger->updated_at = now();
            $ledger->save();

            StockMovement::create([
                'part_id'        => $partId,
                'location_id'    => $locationId,
                'type'           => $type,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'qty'            => $qty,
                'qty_after'      => $ledger->fresh()->qty_on_hand,
                'note'           => $note,
                'created_by_id'  => Auth::id(),
                'created_at'     => now(),
            ]);

            return $ledger->fresh();
        });
    }

    /**
     * Remove stock from a location (Dispatch OUT / Transfer OUT).
     */
    public function debit(
        int $partId,
        int $locationId,
        int $qty,
        string $type,          // dispatch_out, transfer_out, adjustment
        string $referenceType,
        ?int $referenceId,
        ?string $note = null
    ): PartStockLedger {
        return DB::transaction(function () use ($partId, $locationId, $qty, $type, $referenceType, $referenceId, $note) {
            $ledger = PartStockLedger::where('part_id', $partId)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            $available = $ledger ? $ledger->qty_on_hand : 0;
            if (!$ledger || $available < $qty) {
                $part = \App\Models\Part::find($partId);
                $partLabel = $part ? "{$part->name} ({$part->part_number})" : "Part #{$partId}";
                $location = \App\Models\Location::find($locationId);
                $locName = $location ? $location->name : "Location #{$locationId}";
                throw new \Exception("Insufficient stock for '{$partLabel}' at {$locName}. Available on hand: {$available}, Requested for dispatch: {$qty}. Please receive stock via GRN, transfer stock, or check 'Emergency Stock Override' to proceed.");
            }

            $ledger->decrement('qty_on_hand', $qty);
            // Also release any reserved qty if present
            if ($ledger->qty_reserved > 0) {
                $ledger->decrement('qty_reserved', min($qty, $ledger->qty_reserved));
            }
            $ledger->updated_at = now();
            $ledger->save();

            StockMovement::create([
                'part_id'        => $partId,
                'location_id'    => $locationId,
                'type'           => $type,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'qty'            => -$qty,
                'qty_after'      => $ledger->fresh()->qty_on_hand,
                'note'           => $note,
                'created_by_id'  => Auth::id(),
                'created_at'     => now(),
            ]);

            return $ledger->fresh();
        });
    }

    /**
     * Reserve stock for a pending part request.
     */
    public function reserve(int $partId, int $locationId, int $qty): void {
        DB::transaction(function () use ($partId, $locationId, $qty) {
            $ledger = PartStockLedger::where('part_id', $partId)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            if ($ledger && ($ledger->qty_on_hand - $ledger->qty_reserved) >= $qty) {
                $ledger->increment('qty_reserved', $qty);
                $ledger->updated_at = now();
                $ledger->save();
            }
        });
    }

    /**
     * Release a reservation without dispatching (e.g. request rejected).
     */
    public function releaseReservation(int $partId, int $locationId, int $qty): void {
        DB::transaction(function () use ($partId, $locationId, $qty) {
            $ledger = PartStockLedger::where('part_id', $partId)
                ->where('location_id', $locationId)
                ->first();

            if ($ledger) {
                $ledger->decrement('qty_reserved', min($qty, $ledger->qty_reserved));
                $ledger->updated_at = now();
                $ledger->save();
            }
        });
    }

    /**
     * Get total available qty of a part across ALL locations.
     */
    public function totalAvailable(int $partId): int {
        return (int) PartStockLedger::where('part_id', $partId)
            ->selectRaw('SUM(qty_on_hand - qty_reserved) as avail')
            ->value('avail');
    }

    /**
     * Get stock level at a specific location.
     */
    public function stockAt(int $partId, int $locationId): PartStockLedger {
        return PartStockLedger::firstOrCreate(
            ['part_id' => $partId, 'location_id' => $locationId],
            ['qty_on_hand' => 0, 'qty_reserved' => 0]
        );
    }

    /**
     * Build a stock matrix: all parts x all locations.
     * Returns: [ part_id => [ location_id => [on_hand, reserved, available] ] ]
     */
    public function stockMatrix(): array {
        $ledgers = PartStockLedger::with(['part', 'location'])->get();
        $matrix = [];
        foreach ($ledgers as $l) {
            $matrix[$l->part_id][$l->location_id] = [
                'on_hand'   => $l->qty_on_hand,
                'reserved'  => $l->qty_reserved,
                'available' => max(0, $l->qty_on_hand - $l->qty_reserved),
            ];
        }
        return $matrix;
    }
}
