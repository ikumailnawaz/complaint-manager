<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\PartRequest;
use App\Models\PmSchedule;
use App\Models\Ticket;
use App\Models\User;

class NotificationService
{
    /**
     * Notify engineer and operations when a ticket is assigned.
     */
    public static function notifyTicketAssigned(Ticket $ticket, User $engineer): void
    {
        // 1. Notify the assigned engineer
        AppNotification::create([
            'user_id' => $engineer->id,
            'type' => 'ticket_assigned',
            'title' => "Ticket Assigned: #{$ticket->ticket_no}",
            'message' => "You have been assigned to {$ticket->bank_name} ({$ticket->branch_location}) for {$ticket->machine_model}.",
            'link' => route('tickets.show', $ticket),
            'icon' => 'fa-solid fa-ticket',
            'color' => 'indigo',
            'data' => [
                'ticket_id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
            ],
        ]);

        // 2. Notify Operations Admin & Super Admin
        AppNotification::create([
            'user_id' => null,
            'role_target' => 'admin',
            'type' => 'ticket_assigned',
            'title' => "Ticket #{$ticket->ticket_no} Assigned",
            'message' => "Assigned to {$engineer->name} for {$ticket->bank_name} - {$ticket->branch_location}.",
            'link' => route('tickets.show', $ticket),
            'icon' => 'fa-solid fa-user-check',
            'color' => 'blue',
            'data' => [
                'ticket_id' => $ticket->id,
                'engineer_id' => $engineer->id,
            ],
        ]);

        // 3. Send Native Android Notification Center Push Alert
        try {
            FirebasePushService::sendToUser(
                $engineer,
                "Ticket Assigned: #{$ticket->ticket_no}",
                "Assigned to {$ticket->bank_name} ({$ticket->branch_location}) • {$ticket->machine_model}",
                [
                    'ticket_id' => (string) $ticket->id,
                    'ticket_no' => (string) $ticket->ticket_no,
                    'type'      => 'ticket_assigned',
                ]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('FCM Push failed for ticket assignment: ' . $e->getMessage());
        }
    }

    /**
     * Notify every aligned engineer (and admins) that a resolved/closed ticket was reopened.
     */
    public static function notifyTicketReopened(Ticket $ticket, \App\Models\TicketCycle $cycle, string $reason): void
    {
        $tour = $cycle->cycle_no;
        $title = "Ticket Reopened (Tour {$tour}): #{$ticket->ticket_no}";
        $message = "{$ticket->bank_name} ({$ticket->branch_location}) reopened. Reason: {$reason}";

        foreach ($ticket->activeEngineers() as $engineer) {
            AppNotification::create([
                'user_id' => $engineer->id,
                'type' => 'ticket_reopened',
                'title' => $title,
                'message' => $message,
                'link' => route('tickets.show', $ticket),
                'icon' => 'fa-solid fa-rotate-left',
                'color' => 'rose',
                'data' => ['ticket_id' => $ticket->id, 'ticket_no' => $ticket->ticket_no, 'cycle_no' => $tour],
            ]);

            try {
                FirebasePushService::sendToUser($engineer, $title, $message, [
                    'ticket_id' => (string) $ticket->id,
                    'ticket_no' => (string) $ticket->ticket_no,
                    'type' => 'ticket_reopened',
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('FCM Push failed for ticket reopen: ' . $e->getMessage());
            }
        }

        AppNotification::create([
            'user_id' => null,
            'role_target' => 'admin',
            'type' => 'ticket_reopened',
            'title' => $title,
            'message' => "Reopened. Engineers have been notified. Reason: {$reason}",
            'link' => route('tickets.show', $ticket),
            'icon' => 'fa-solid fa-rotate-left',
            'color' => 'rose',
            'data' => ['ticket_id' => $ticket->id, 'cycle_no' => $tour],
        ]);
    }

    /**
     * Notify engineers who were added to (or removed from) a ticket.
     */
    public static function notifyEngineerAlignment(Ticket $ticket, array $added, array $removed): void
    {
        foreach ($added as $engineer) {
            self::notifyTicketAssigned($ticket, $engineer);
        }

        foreach ($removed as $engineer) {
            AppNotification::create([
                'user_id' => $engineer->id,
                'type' => 'ticket_unassigned',
                'title' => "Removed from Ticket: #{$ticket->ticket_no}",
                'message' => "You are no longer aligned to {$ticket->bank_name} ({$ticket->branch_location}).",
                'link' => route('tickets.show', $ticket),
                'icon' => 'fa-solid fa-user-minus',
                'color' => 'slate',
                'data' => ['ticket_id' => $ticket->id],
            ]);
        }
    }

    /**
     * Notify Office Staff and Managers when an engineer creates a part request.
     */
    public static function notifyPartRequestCreated(PartRequest $partRequest): void
    {
        $engineerName = $partRequest->engineer?->name ?? 'Field Engineer';
        $itemsCount = $partRequest->items()->count();

        // Notify Office Staff for Stage 1 Verification
        AppNotification::create([
            'user_id' => null,
            'role_target' => 'office_staff',
            'type' => 'part_request_created',
            'title' => "New Part Request: {$partRequest->request_number}",
            'message' => "{$engineerName} requested {$itemsCount} part(s). Stage 1 stock verification required.",
            'link' => route('parts.requests.show', $partRequest),
            'icon' => 'fa-solid fa-boxes-stacked',
            'color' => 'amber',
            'data' => [
                'part_request_id' => $partRequest->id,
            ],
        ]);

        // Notify Admins
        AppNotification::create([
            'user_id' => null,
            'role_target' => 'admin',
            'type' => 'part_request_created',
            'title' => "Part Request Submitted: {$partRequest->request_number}",
            'message' => "Requisition created by {$engineerName} for ticket #{$partRequest->ticket?->ticket_no}.",
            'link' => route('parts.requests.show', $partRequest),
            'icon' => 'fa-solid fa-clipboard-list',
            'color' => 'amber',
            'data' => [
                'part_request_id' => $partRequest->id,
            ],
        ]);
    }

    /**
     * Notify Super Admin when Stage 1 stock verification is completed.
     */
    public static function notifyPartRequestVerified(PartRequest $partRequest): void
    {
        AppNotification::create([
            'user_id' => null,
            'role_target' => 'super_admin',
            'type' => 'part_request_verified',
            'title' => "Stage 1 Verified: {$partRequest->request_number}",
            'message' => "Stock verification completed by Office Staff. Awaiting Stage 2 Super Admin approval.",
            'link' => route('parts.requests.show', $partRequest),
            'icon' => 'fa-solid fa-shield-halved',
            'color' => 'purple',
            'data' => [
                'part_request_id' => $partRequest->id,
            ],
        ]);
    }

    /**
     * Notify Engineer and Office Staff when Super Admin approves a part request.
     */
    public static function notifyPartRequestApproved(PartRequest $partRequest): void
    {
        // 1. Notify requesting engineer
        if ($partRequest->engineer_id) {
            AppNotification::create([
                'user_id' => $partRequest->engineer_id,
                'type' => 'part_request_approved',
                'title' => "Part Request Approved: {$partRequest->request_number}",
                'message' => "Super Admin authorized parts for ticket #{$partRequest->ticket?->ticket_no}. Awaiting dispatch.",
                'link' => route('parts.requests.show', $partRequest),
                'icon' => 'fa-solid fa-circle-check',
                'color' => 'emerald',
                'data' => [
                    'part_request_id' => $partRequest->id,
                ],
            ]);
        }

        // 2. Notify Office Staff to dispatch
        AppNotification::create([
            'user_id' => null,
            'role_target' => 'office_staff',
            'type' => 'part_request_approved',
            'title' => "Ready for Dispatch: {$partRequest->request_number}",
            'message' => "Super Admin approved request. Ready to ship to engineer {$partRequest->engineer?->name}.",
            'link' => route('parts.requests.show', $partRequest),
            'icon' => 'fa-solid fa-truck-fast',
            'color' => 'emerald',
            'data' => [
                'part_request_id' => $partRequest->id,
            ],
        ]);

        if ($partRequest->engineer) {
            try {
                FirebasePushService::sendToUser(
                    $partRequest->engineer,
                    "Parts Approved: #{$partRequest->request_number}",
                    "Super Admin approved parts for ticket #{$partRequest->ticket?->ticket_no}. Awaiting dispatch.",
                    ['part_request_id' => (string) $partRequest->id, 'type' => 'part_request_approved']
                );
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Notify Engineer when parts are dispatched.
     */
    public static function notifyPartRequestDispatched(PartRequest $partRequest): void
    {
        if ($partRequest->engineer_id) {
            AppNotification::create([
                'user_id' => $partRequest->engineer_id,
                'type' => 'part_request_dispatched',
                'title' => "Parts Dispatched: {$partRequest->request_number}",
                'message' => "Dispatched via {$partRequest->dispatch_courier} (Tracking: {$partRequest->dispatch_tracking_number}).",
                'link' => route('parts.requests.show', $partRequest),
                'icon' => 'fa-solid fa-truck-arrow-right',
                'color' => 'teal',
                'data' => [
                    'part_request_id' => $partRequest->id,
                    'courier' => $partRequest->dispatch_courier,
                    'tracking' => $partRequest->dispatch_tracking_number,
                ],
            ]);

            if ($partRequest->engineer) {
                try {
                    FirebasePushService::sendToUser(
                        $partRequest->engineer,
                        "Parts Dispatched: #{$partRequest->request_number}",
                        "Shipped via {$partRequest->dispatch_courier} (Tracking: {$partRequest->dispatch_tracking_number}).",
                        ['part_request_id' => (string) $partRequest->id, 'type' => 'part_request_dispatched']
                    );
                } catch (\Throwable $e) {}
            }
        }
    }

    /**
     * Notify when PM maintenance is due or overdue.
     */
    public static function notifyPmDue(PmSchedule $schedule): void
    {
        $machineDesc = $schedule->machine ? "{$schedule->machine->model_name} (S/N: {$schedule->machine->serial_number})" : 'Machine';

        // Notify assigned engineer if available
        if ($schedule->assigned_engineer_id) {
            AppNotification::create([
                'user_id' => $schedule->assigned_engineer_id,
                'type' => 'pm_due',
                'title' => "PM Due: {$machineDesc}",
                'message' => "Scheduled maintenance is due on {$schedule->next_due_date}.",
                'link' => route('pm.tasks.index'),
                'icon' => 'fa-solid fa-calendar-check',
                'color' => 'rose',
                'data' => [
                    'pm_schedule_id' => $schedule->id,
                ],
            ]);
        }

        // Notify operations managers
        AppNotification::create([
            'user_id' => null,
            'role_target' => 'admin',
            'type' => 'pm_due',
            'title' => "PM Alert: {$machineDesc}",
            'message' => "PM cycle is due on {$schedule->next_due_date}.",
            'link' => route('pm.schedules.index'),
            'icon' => 'fa-solid fa-calendar-exclamation',
            'color' => 'rose',
            'data' => [
                'pm_schedule_id' => $schedule->id,
            ],
        ]);
    }

    /**
     * Notify Operations when a ticket is escalated.
     */
    public static function notifyTicketEscalated(Ticket $ticket): void
    {
        AppNotification::create([
            'user_id' => null,
            'role_target' => 'admin',
            'type' => 'ticket_escalated',
            'title' => "⚠️ ESCALATION: #{$ticket->ticket_no}",
            'message' => "Ticket SLA breached or escalated for {$ticket->bank_name} - {$ticket->branch_location}.",
            'link' => route('tickets.show', $ticket),
            'icon' => 'fa-solid fa-triangle-exclamation',
            'color' => 'rose',
            'data' => [
                'ticket_id' => $ticket->id,
            ],
        ]);
    }
}
