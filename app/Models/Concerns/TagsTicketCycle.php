<?php

namespace App\Models\Concerns;

use App\Models\Ticket;
use App\Models\TicketCycle;

/**
 * Automatically stamps new records with the ticket's current cycle, so reports,
 * expenses, parts and logs created after a reopen belong to the right tour
 * without every call site needing to remember it.
 */
trait TagsTicketCycle
{
    public static function bootTagsTicketCycle(): void
    {
        static::creating(function ($model) {
            if (!empty($model->cycle_id) || empty($model->ticket_id)) {
                return;
            }

            $cycle = TicketCycle::where('ticket_id', $model->ticket_id)
                ->orderByDesc('cycle_no')
                ->first();

            if ($cycle) {
                $model->cycle_id = $cycle->id;
                if (array_key_exists('tour_no', $model->getAttributes()) || in_array('tour_no', $model->getFillable(), true)) {
                    $model->tour_no = $cycle->cycle_no;
                }
            }
        });
    }
}
