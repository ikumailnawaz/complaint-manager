<?php

namespace App\Services;

use App\Models\EngineerAdvanceTransaction;
use App\Models\EngineerInventory;
use App\Models\Location;
use App\Models\Part;
use App\Models\PartStockLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EngineerEnvelopeService
{
    public function __construct(
        protected StockLedgerService $stockLedger
    ) {}

    /**
     * Lend / Issue advance parts from a warehouse location into an engineer's envelope.
     */
    public function lendAdvanceStock(
        int $engineerId,
        int $sourceLocationId,
        int $partId,
        int $qty,
        ?string $notes = null,
        ?int $createdById = null
    ): EngineerInventory {
        return DB::transaction(function () use ($engineerId, $sourceLocationId, $partId, $qty, $notes, $createdById) {
            $engineer = User::findOrFail($engineerId);
            $part = Part::findOrFail($partId);
            $location = Location::findOrFail($sourceLocationId);

            // Auto-provision warehouse stock if insufficient (just like dispatch auto-balance)
            $ledger = PartStockLedger::firstOrCreate(
                ['part_id' => $partId, 'location_id' => $sourceLocationId],
                ['qty_on_hand' => 0, 'qty_reserved' => 0]
            );
            if ($ledger->qty_on_hand < $qty) {
                $shortfall = $qty - $ledger->qty_on_hand;
                $this->stockLedger->credit(
                    $partId,
                    $sourceLocationId,
                    $shortfall,
                    'adjustment',
                    'advance_envelope_provision',
                    $engineerId,
                    "Auto-provisioned {$shortfall} units for Advance Envelope issue to {$engineer->name}"
                );
            }

            // Debit from warehouse
            $this->stockLedger->debit(
                $partId,
                $sourceLocationId,
                $qty,
                'dispatch_out',
                'engineer_advance_envelope',
                $engineerId,
                "Lent {$qty} advance units to {$engineer->name}" . ($notes ? " ({$notes})" : "")
            );

            // Credit engineer's envelope
            $envelope = EngineerInventory::firstOrCreate(
                ['engineer_id' => $engineerId, 'part_id' => $partId],
                ['qty_allocated' => 0, 'qty_used' => 0, 'qty_on_hand' => 0]
            );
            $envelope->increment('qty_allocated', $qty);
            $envelope->increment('qty_on_hand', $qty);

            // Record transaction
            EngineerAdvanceTransaction::create([
                'engineer_id'        => $engineerId,
                'part_id'            => $partId,
                'type'               => 'advance_issue',
                'qty'                => $qty,
                'source_location_id' => $sourceLocationId,
                'created_by_id'      => $createdById,
                'notes'              => $notes,
            ]);

            return $envelope->fresh();
        });
    }

    /**
     * Return unused advance parts from an engineer's envelope back into a warehouse.
     */
    public function returnToWarehouse(
        int $engineerId,
        int $destinationLocationId,
        int $partId,
        int $qty,
        ?string $notes = null,
        ?int $createdById = null
    ): EngineerInventory {
        return DB::transaction(function () use ($engineerId, $destinationLocationId, $partId, $qty, $notes, $createdById) {
            $engineer = User::findOrFail($engineerId);
            $part = Part::findOrFail($partId);

            $envelope = EngineerInventory::where('engineer_id', $engineerId)
                ->where('part_id', $partId)
                ->first();

            $availableInEnvelope = $envelope ? $envelope->qty_on_hand : 0;
            if ($availableInEnvelope < $qty) {
                throw new \Exception("Cannot return {$qty} units of '{$part->name}'. The engineer only has {$availableInEnvelope} on hand in their envelope.");
            }

            // Debit from engineer envelope
            $envelope->decrement('qty_on_hand', $qty);

            // Credit destination warehouse
            $this->stockLedger->credit(
                $partId,
                $destinationLocationId,
                $qty,
                'grn_in',
                'engineer_advance_return',
                $engineerId,
                "Returned {$qty} units from {$engineer->name}'s advance envelope to warehouse" . ($notes ? " ({$notes})" : "")
            );

            // Record transaction
            EngineerAdvanceTransaction::create([
                'engineer_id'             => $engineerId,
                'part_id'                 => $partId,
                'type'                    => 'returned_to_warehouse',
                'qty'                     => $qty,
                'destination_location_id' => $destinationLocationId,
                'created_by_id'           => $createdById,
                'notes'                   => $notes,
            ]);

            return $envelope->fresh();
        });
    }

    /**
     * Automatically consume parts from engineer's advance envelope for a complaint ticket.
     * Returns the quantity actually fulfilled/deducted from the envelope.
     */
    public function consumeFromEnvelope(
        int $engineerId,
        int $partId,
        int $qtyRequested,
        int $ticketId,
        ?int $partRequestId = null,
        ?int $machineModelId = null,
        ?string $machineSerialNo = null,
        ?int $createdById = null
    ): int {
        return DB::transaction(function () use (
            $engineerId, $partId, $qtyRequested, $ticketId,
            $partRequestId, $machineModelId, $machineSerialNo, $createdById
        ) {
            $envelope = EngineerInventory::where('engineer_id', $engineerId)
                ->where('part_id', $partId)
                ->lockForUpdate()
                ->first();

            if (!$envelope || $envelope->qty_on_hand <= 0) {
                return 0; // None in envelope
            }

            $qtyToDeduct = min($envelope->qty_on_hand, $qtyRequested);

            $envelope->decrement('qty_on_hand', $qtyToDeduct);
            $envelope->increment('qty_used', $qtyToDeduct);

            // Record transaction
            EngineerAdvanceTransaction::create([
                'engineer_id'       => $engineerId,
                'part_id'           => $partId,
                'type'              => 'consumed_complaint',
                'qty'               => $qtyToDeduct,
                'ticket_id'         => $ticketId,
                'part_request_id'   => $partRequestId,
                'machine_model_id'  => $machineModelId,
                'machine_serial_no' => $machineSerialNo,
                'created_by_id'     => $createdById ?? $engineerId,
                'notes'             => "Consumed from advance float for complaint #{$ticketId}",
            ]);

            return $qtyToDeduct;
        });
    }
}
