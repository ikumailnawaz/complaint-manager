<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $company_name }} — Bank Service SLA &amp; Audit Trail Report</title>
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
            border-bottom: 2px solid #0284c7;
            padding-bottom: 8px;
        }
        .header-logo {
            font-size: 20pt;
            font-weight: 900;
            color: #0c4a6e;
            letter-spacing: -0.5px;
        }
        .header-sublogo {
            font-size: 8pt;
            font-weight: bold;
            color: #0284c7;
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
        .report-title-bar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #0284c7;
            padding: 8px 12px;
            margin-bottom: 12px;
            border-radius: 4px;
        }
        .report-title {
            font-size: 13pt;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }
        .report-meta {
            font-size: 7.5pt;
            color: #64748b;
            margin-top: 3px;
        }
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .kpi-box {
            background: #f1f5f9;
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
            background: #0f172a;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 6.8pt;
            letter-spacing: 0.5px;
            padding: 6px 5px;
            text-align: left;
            border: 1px solid #0f172a;
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
        .badge-success {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
        .badge-danger {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }
        .badge-amber {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .deduction-pill {
            font-size: 6.5pt;
            color: #475569;
            background: #e2e8f0;
            padding: 1px 3px;
            border-radius: 2px;
            display: inline-block;
            margin-top: 2px;
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

    <!-- CMS COMPANY LETTERHEAD -->
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="header-logo">{{ $company_name }}</div>
                <div class="header-sublogo">Commercial Banking Automation &amp; Terminal Services</div>
            </td>
            <td class="header-company-info" style="width: 45%; text-align: right;">
                <!-- Header details -->
            </td>
        </tr>
    </table>

    <!-- REPORT TITLE -->
    <div class="report-title-bar">
        <div class="report-title">{{ $report_title }}</div>
        <div class="report-meta">
            <strong>Ref:</strong> {{ $report_ref }} &nbsp;|&nbsp;
            <strong>Bank Scope:</strong> {{ $bank_scope }} &nbsp;|&nbsp;
            <strong>Audited Period:</strong> {{ $date_range_label }} &nbsp;|&nbsp;
            <strong>Generated On:</strong> {{ $generated_at }}
        </div>
    </div>

    <!-- EXECUTIVE KPI SUMMARY CARDS -->
    <table class="kpi-table">
        <tr>
            <td style="width: 20%; padding-right: 5px;">
                <div class="kpi-box">
                    <div class="kpi-val">{{ $audited_count }}</div>
                    <div class="kpi-lbl">Total Audited Complaints</div>
                </div>
            </td>
            <td style="width: 20%; padding-right: 5px;">
                <div class="kpi-box" style="background: #f0fdf4; border-color: #86efac;">
                    <div class="kpi-val" style="color: #15803d;">{{ $adherence_rate }}%</div>
                    <div class="kpi-lbl">Net SLA Adherence Rate</div>
                </div>
            </td>
            <td style="width: 20%; padding-right: 5px;">
                <div class="kpi-box">
                    <div class="kpi-val">{{ $avg_gross_hours }}h</div>
                    <div class="kpi-lbl">Avg Gross Calendar TAT</div>
                </div>
            </td>
            <td style="width: 20%; padding-right: 5px;">
                <div class="kpi-box" style="background: #eff6ff; border-color: #bfdbfe;">
                    <div class="kpi-val" style="color: #1d4ed8;">-{{ $avg_deductions_hours }}h</div>
                    <div class="kpi-lbl">Avg Permitted Deductions</div>
                </div>
            </td>
            <td style="width: 20%;">
                <div class="kpi-box" style="background: #faf5ff; border-color: #e9d5ff;">
                    <div class="kpi-val" style="color: #7e22ce;">{{ $avg_net_hours }}h</div>
                    <div class="kpi-lbl">Avg Net Business SLA Time</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- AUDIT TRAIL TABLE -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 12%;">Ticket &amp; Bank</th>
                <th style="width: 13%;">Branch &amp; Hardware</th>
                <th style="width: 11%;">Intake &amp; Reply</th>
                <th style="width: 9%;">Gross TAT</th>
                <th style="width: 15%;">Permitted Deductions Breakdown</th>
                <th style="width: 9%;">Net Business Time</th>
                <th style="width: 10%;">Contracted SLA</th>
                <th style="width: 21%;">Resolution &amp; Operational Feedback</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $row)
            @php
                $t = $row['ticket'];
                $m = $row['metrics'];
                $fbs = $row['daily_feedbacks'];
            @endphp
            <tr>
                <!-- Ticket & Bank -->
                <td>
                    <strong style="color: #0284c7; font-size: 8pt;">#{{ $t->ticket_no }}</strong><br>
                    @if($t->customer_ref_no)
                        <span style="color: #64748b; font-size: 6.8pt;">Ref: {{ $t->customer_ref_no }}</span><br>
                    @endif
                    <strong>{{ $t->bank_name }}</strong><br>
                    <span class="badge {{ $t->urgency === 'high' ? 'badge-danger' : ($t->urgency === 'medium' ? 'badge-amber' : 'badge-success') }}">
                        {{ strtoupper($t->urgency ?? 'NORMAL') }}
                    </span>
                </td>

                <!-- Branch & Hardware -->
                <td>
                    <strong>{{ $t->branch_location }}</strong><br>
                    <span style="color: #475569;">{{ $t->branch_name ?: 'Main Branch' }}</span><br>
                    <span style="color: #0f172a; font-weight: bold;">{{ $t->machine_model ?: $t->machine_type }}</span><br>
                    <span style="font-family: monospace; color: #64748b;">S/N: {{ $t->machine_serial_no ?: 'N/A' }}</span>
                </td>

                <!-- Intake & Reply -->
                <td>
                    <span style="color: #64748b;">Landed:</span><br>
                    <strong>{{ $t->created_at->format('d M Y, H:i') }}</strong><br>
                    @if($t->email_assignment_sent_at)
                        <span style="color: #15803d; font-size: 6.8pt;">Reply: +{{ $t->created_at->diffInMinutes($t->email_assignment_sent_at) }}m TAT</span>
                    @else
                        <span style="color: #b45309; font-size: 6.8pt;">Reply: Pending</span>
                    @endif
                </td>

                <!-- Gross TAT -->
                <td>
                    <strong style="font-size: 8.5pt;">{{ $m['gross_formatted'] }}</strong><br>
                    <span style="color: #64748b;">({{ $m['gross_hours'] }}h calendar)</span><br>
                    <span style="font-size: 6.5pt; color: #94a3b8;">
                        End: {{ $t->resolved_at ? \Carbon\Carbon::parse($t->resolved_at)->format('d M, H:i') : 'In-Progress' }}
                    </span>
                </td>

                <!-- Deductions Breakdown -->
                <td>
                    @if($m['total_deducted_minutes'] > 0)
                        @if($m['weekend_minutes'] > 0)
                            <div class="deduction-pill">Weekend (Sat/Sun): -{{ $m['weekend_formatted'] }}</div>
                        @endif
                        @if($m['transit_minutes'] > 0)
                            <div class="deduction-pill">Workshop Cargo: -{{ $m['transit_formatted'] }}</div>
                        @endif
                        @if($m['approval_minutes'] > 0)
                            <div class="deduction-pill">Approval Paused: -{{ $m['approval_formatted'] }}</div>
                        @endif
                        <div style="margin-top: 3px; font-weight: bold; color: #0f172a; font-size: 7.2pt;">
                            Total Excluded: -{{ $m['total_deducted_formatted'] }}
                        </div>
                    @else
                        <span style="color: #94a3b8; font-style: italic;">No deductions incurred</span>
                    @endif
                </td>

                <!-- Net Business Time -->
                <td>
                    <strong style="font-size: 9pt; color: #0284c7;">{{ $m['net_formatted'] }}</strong><br>
                    <span style="color: #64748b;">({{ $m['net_hours'] }}h net)</span><br>
                    <div style="margin-top: 3px;">
                        <span class="badge {{ $m['is_in_tat'] ? 'badge-success' : 'badge-danger' }}">
                            {{ $m['is_in_tat'] ? '✓ In-TAT' : '⚠ Overdue' }}
                        </span>
                    </div>
                </td>

                <!-- Contracted SLA -->
                <td>
                    <strong>{{ $m['target_sla_hours'] }} Hours</strong><br>
                    <span style="color: #64748b; font-size: 6.8pt;">
                        Target: {{ $t->sla_deadline ? $t->sla_deadline->format('d M Y, H:i') : 'Contract Standard' }}
                    </span><br>
                    <span style="font-size: 6.8pt; color: {{ $m['is_in_tat'] ? '#15803d' : '#b91c1c' }}; font-weight: bold;">
                        {{ $m['tat_status_label'] }}
                    </span>
                </td>

                <!-- Resolution & Daily Feedback -->
                <td>
                    @if($t->resolution_summary)
                        <div style="margin-bottom: 3px; color: #0f172a; font-weight: bold;">
                            Resolution: <span style="font-weight: normal; color: #334155;">{{ $t->resolution_summary }}</span>
                        </div>
                    @endif
                    @if($fbs->isNotEmpty())
                        <div style="font-size: 6.8pt; color: #475569;">
                            @foreach($fbs as $fb)
                                <div>
                                    <strong style="color: #0284c7;">Day {{ $fb->day_number }}:</strong> {{ Str::limit($fb->feedback_text, 65) }}
                                    @if($fb->action_taken) <em style="color: #64748b;">({{ Str::limit($fb->action_taken, 30) }})</em> @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <span style="color: #94a3b8; font-style: italic;">No daily feedback records</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align: center; padding: 15px; color: #64748b;">
                    No ticket audit records matched the selected bank and date filter criteria.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- SIGN-OFF SECTION & FOOTER -->
    <table class="footer-table">
        <tr>
            <td style="width: 33%;">
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <strong>Field Engineering Lead</strong><br>
                    CMS Company Engineering Operations
                </div>
            </td>
            <td style="width: 34%;">
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <strong>Head of SLA &amp; Quality Audit</strong><br>
                    CMS Company Executive Management
                </div>
            </td>
            <td style="width: 33%;">
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <strong>Commercial Bank Verification</strong><br>
                    Operations / Branch Administration
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align: center; padding-top: 10px; color: #94a3b8; font-size: 6.5pt;">
                This audit document is generated by CMS Company Service ERP. All turnaround time deductions (weekends, workshop transit, approval freezes) are verified against digital server logs and courier intake receipts. Page <span class="page-num"></span>.
            </td>
        </tr>
    </table>

</body>
</html>
