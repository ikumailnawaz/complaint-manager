<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $company_name }} — Supplier Warranty Claim &amp; Defective Hardware Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm 10mm 15mm 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color: #1e293b;
            line-height: 1.35;
            background: #ffffff;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-bottom: 2px solid #b91c1c;
            padding-bottom: 8px;
        }
        .header-logo {
            font-size: 20pt;
            font-weight: 900;
            color: #991b1b;
            letter-spacing: -0.5px;
        }
        .header-sublogo {
            font-size: 8pt;
            font-weight: bold;
            color: #b91c1c;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
        }
        .header-company-info {
            text-align: right;
            font-size: 7.5pt;
            color: #64748b;
            line-height: 1.4;
        }
        .claim-banner {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-left: 4px solid #e11d48;
            padding: 8px 12px;
            margin-bottom: 12px;
            border-radius: 4px;
        }
        .claim-title {
            font-size: 13pt;
            font-weight: 800;
            color: #9f1239;
            margin: 0;
        }
        .claim-notice {
            font-size: 7.5pt;
            color: #475569;
            margin-top: 3px;
        }
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .kpi-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
            border-radius: 4px;
            text-align: center;
        }
        .kpi-val {
            font-size: 13pt;
            font-weight: 900;
            color: #0f172a;
        }
        .kpi-lbl {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            margin-top: 2px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            margin-bottom: 14px;
        }
        .data-table th {
            background: #1e293b;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 6.8pt;
            letter-spacing: 0.5px;
            padding: 6px 5px;
            text-align: left;
            border: 1px solid #1e293b;
        }
        .data-table td {
            padding: 5px 5px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 6.8pt;
            text-transform: uppercase;
        }
        .badge-warranty {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
        .badge-video {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            font-size: 7pt;
            color: #64748b;
        }
        .sign-box {
            text-align: center;
            padding-top: 25px;
            font-size: 7pt;
            color: #334155;
        }
        .sign-line {
            border-top: 1px solid #475569;
            width: 160px;
            margin: 0 auto 3px auto;
        }
        .page-num:after {
            content: counter(page);
        }
    </style>
</head>
<body>

    <!-- CMS COMPANY SUPPLIER REQUISITION HEADER -->
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="header-logo">{{ $company_name }}</div>
                <div class="header-sublogo">Hardware Warranty Logistics &amp; OEM Component Overhaul</div>
            </td>
            <td class="header-company-info" style="width: 45%; text-align: right;">
                <!-- Header details -->
            </td>
        </tr>
    </table>

    <!-- CLAIM REQUISITION BANNER -->
    <div class="claim-banner">
        <div class="claim-title">{{ $report_title }}</div>
        <div class="claim-notice">
            <strong>Claim Reference:</strong> {{ $report_ref }} &nbsp;|&nbsp;
            <strong>Attention:</strong> OEM Hardware &amp; Free Warranty Parts Dept &nbsp;|&nbsp;
            <strong>Model Filter:</strong> {{ $model_filter }} &nbsp;|&nbsp;
            <strong>Date Generated:</strong> {{ $generated_at }}<br>
            <em>Formal claim requisition for defective terminal hardware replacement under contractual OEM warranty terms. Diagnostic video proof attached per claim item.</em>
        </div>
    </div>

    <!-- SUMMARY KPIS -->
    <table class="kpi-table">
        <tr>
            <td style="width: 25%; padding-right: 5px;">
                <div class="kpi-box">
                    <div class="kpi-val">{{ $total_claims }}</div>
                    <div class="kpi-lbl">Total Warranty Claims Filed</div>
                </div>
            </td>
            <td style="width: 25%; padding-right: 5px;">
                <div class="kpi-box" style="background: #fff1f2; border-color: #fecdd3;">
                    <div class="kpi-val" style="color: #be123c;">{{ $total_parts_count }}</div>
                    <div class="kpi-lbl">Replacement Components Requisitioned</div>
                </div>
            </td>
            <td style="width: 25%; padding-right: 5px;">
                <div class="kpi-box" style="background: #eff6ff; border-color: #bfdbfe;">
                    <div class="kpi-val" style="color: #1d4ed8;">{{ $video_count }}</div>
                    <div class="kpi-lbl">Claims with Video Evidence</div>
                </div>
            </td>
            <td style="width: 25%;">
                <div class="kpi-box" style="background: #f0fdf4; border-color: #86efac;">
                    <div class="kpi-val" style="color: #15803d;">PKR {{ number_format($total_claim_value, 2) }}</div>
                    <div class="kpi-lbl">Total Claim Replacement Value</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- PARTS & CLAIMS DATA TABLE -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 12%;">Claim Requisition #</th>
                <th style="width: 14%;">Terminal Model &amp; S/N</th>
                <th style="width: 13%;">Bank &amp; Branch Location</th>
                <th style="width: 18%;">Defective Part Requested</th>
                <th style="width: 7%; text-align: center;">Qty</th>
                <th style="width: 10%;">Warranty Status</th>
                <th style="width: 16%;">Diagnostic Fault Finding</th>
                <th style="width: 10%;">Video Proof Link</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $pr)
            @php
                $hasItems = $pr->items->isNotEmpty();
                $itemCount = $hasItems ? $pr->items->count() : 1;
            @endphp
            @if(!$hasItems)
            <tr>
                <td>
                    <strong style="color: #be123c; font-size: 8pt;">{{ $pr->request_number }}</strong><br>
                    <span style="color: #64748b; font-size: 6.8pt;">Date: {{ $pr->created_at->format('d M Y') }}</span><br>
                    @if($pr->ticket)
                        <span style="color: #0284c7; font-size: 6.8pt;">Ticket: #{{ $pr->ticket->ticket_no }}</span>
                    @endif
                </td>
                <td>
                    <strong>{{ $pr->machineModel?->name ?? $pr->machineModel?->model_name ?? 'N/A' }}</strong><br>
                    <span style="font-family: monospace; color: #0f172a; font-weight: bold;">S/N: {{ $pr->machine_serial_no }}</span>
                </td>
                <td>
                    <strong>{{ $pr->ticket?->bank_name ?? 'Commercial Bank' }}</strong><br>
                    <span style="color: #64748b;">{{ $pr->ticket?->branch_location ?? 'Field Terminal' }}</span>
                </td>
                <td>
                    <span style="color: #64748b; font-style: italic;">Component specification pending itemization</span>
                </td>
                <td style="text-align: center; font-weight: bold;">1</td>
                <td>
                    <span class="badge badge-warranty">Free Warranty</span><br>
                    <span style="font-size: 6.5pt; color: #64748b;">Status: {{ strtoupper($pr->status) }}</span>
                </td>
                <td>
                    <p style="margin: 0; color: #334155;">{{ $pr->fault_description }}</p>
                    <div style="font-size: 6.5pt; color: #64748b; margin-top: 2px;">
                        Eng: <strong>{{ $pr->engineer?->name ?? 'Ali Khan' }}</strong>
                    </div>
                </td>
                <td>
                    @if($pr->fault_video_path)
                        <span class="badge badge-video">Video Attached</span><br>
                        <a href="{{ url('storage/' . $pr->fault_video_path) }}" target="_blank" style="color: #0284c7; font-size: 6.5pt; word-break: break-all;">
                            Open Video Proof &raquo;
                        </a>
                    @else
                        <span style="color: #94a3b8; font-style: italic; font-size: 6.8pt;">No video linked</span>
                    @endif
                </td>
            </tr>
            @else
                @foreach($pr->items as $idx => $it)
                <tr>
                    @if($idx === 0)
                    <td rowspan="{{ $itemCount }}">
                        <strong style="color: #be123c; font-size: 8pt;">{{ $pr->request_number }}</strong><br>
                        <span style="color: #64748b; font-size: 6.8pt;">Date: {{ $pr->created_at->format('d M Y') }}</span><br>
                        @if($pr->ticket)
                            <span style="color: #0284c7; font-size: 6.8pt;">Ticket: #{{ $pr->ticket->ticket_no }}</span>
                        @endif
                    </td>
                    <td rowspan="{{ $itemCount }}">
                        <strong>{{ $pr->machineModel?->name ?? $pr->machineModel?->model_name ?? 'N/A' }}</strong><br>
                        <span style="font-family: monospace; color: #0f172a; font-weight: bold;">S/N: {{ $pr->machine_serial_no }}</span>
                    </td>
                    <td rowspan="{{ $itemCount }}">
                        <strong>{{ $pr->ticket?->bank_name ?? 'Commercial Bank' }}</strong><br>
                        <span style="color: #64748b;">{{ $pr->ticket?->branch_location ?? 'Field Terminal' }}</span>
                    </td>
                    @endif

                    <!-- Defective Part item -->
                    <td>
                        <strong style="color: #0f172a;">{{ $it->part?->name ?? 'Defective Spare Part' }}</strong><br>
                        <span style="font-family: monospace; color: #64748b; font-size: 6.8pt;">
                            P/N: {{ $it->part?->part_number ?? 'OEM-PART-' . $it->part_id }}
                        </span>
                        @if($it->note)
                            <br><span style="font-size: 6.5pt; color: #475569;">Note: {{ $it->note }}</span>
                        @endif
                    </td>

                    <!-- Qty -->
                    <td style="text-align: center; font-weight: bold; font-size: 8pt; color: #0284c7;">
                        {{ $it->qty_requested }}
                    </td>

                    <!-- Warranty Eligibility -->
                    <td>
                        <span class="badge badge-warranty">Free Warranty</span><br>
                        <span style="font-size: 6.5pt; color: #64748b;">Status: {{ strtoupper($pr->status) }}</span>
                    </td>

                    @if($idx === 0)
                    <td rowspan="{{ $itemCount }}">
                        <p style="margin: 0; color: #334155;">{{ $pr->fault_description }}</p>
                        <div style="font-size: 6.5pt; color: #64748b; margin-top: 3px;">
                            Engineer: <strong>{{ $pr->engineer?->name ?? 'Ali Khan' }}</strong>
                        </div>
                    </td>
                    <td rowspan="{{ $itemCount }}">
                        @if($pr->fault_video_path)
                            <span class="badge badge-video">Video Attached</span><br>
                            <a href="{{ url('storage/' . $pr->fault_video_path) }}" target="_blank" style="color: #0284c7; font-size: 6.5pt; word-break: break-all; text-decoration: underline;">
                                View Diagnostic Video &raquo;
                            </a>
                        @else
                            <span style="color: #94a3b8; font-style: italic; font-size: 6.8pt;">No video linked</span>
                        @endif
                    </td>
                    @endif
                </tr>
                @endforeach
            @endif
            @empty
            <tr>
                <td colspan="8" style="text-align: center; padding: 15px; color: #64748b;">
                    No defective hardware or parts requisitions recorded under the selected criteria.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- SUPPLIER VERIFICATION & STAMP SECTION -->
    <table class="footer-table">
        <tr>
            <td style="width: 33%;">
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <strong>CMS Workshop Chief Inspector</strong><br>
                    Hardware Defect Verification &amp; Testing
                </div>
            </td>
            <td style="width: 34%;">
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <strong>CMS Head of Supply Chain &amp; Logistics</strong><br>
                    Warranty Claim Authorization
                </div>
            </td>
            <td style="width: 33%;">
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <strong>Supplier / OEM Acknowledgment</strong><br>
                    Replacement Parts Dispatch Acceptance
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align: center; padding-top: 10px; color: #94a3b8; font-size: 6.5pt;">
                Official CMS Company Document for OEM Warranty Replacement Claims. Components itemized above were inspected and found defective during active bank terminal operations. Page <span class="page-num"></span>.
            </td>
        </tr>
    </table>

</body>
</html>
