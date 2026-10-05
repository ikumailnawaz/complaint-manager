@extends('layouts.app')

@section('title', 'AI Email Ingestion Simulator - Bank Complaint Manager')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
        <div>
            <div class="flex items-center space-x-2">
                <span class="p-2 bg-purple-100 text-purple-700 rounded-xl text-sm">
                    <i class="fa-solid fa-robot"></i>
                </span>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                    AI Email Triage &amp; n8n Simulator
                </h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-10">
                Simulate an incoming email sent by a bank branch to your support inbox. Gemini AI extracts bank details, machine serial, and urgency, creating an unassigned ticket.
            </p>
        </div>
        <a href="{{ route('tickets.index') }}" class="text-xs text-slate-600 hover:text-slate-900 bg-white border border-slate-300 px-3 py-1.5 rounded-lg shadow-sm">
            <i class="fa-solid fa-arrow-left mr-1"></i> Back to Complaints
        </a>
    </div>

    <!-- How It Works Flow Pipeline -->
    <div class="bg-gradient-to-r from-slate-900 to-indigo-950 rounded-2xl p-5 text-white shadow-md text-xs">
        <div class="font-bold text-sky-400 uppercase text-[10px] tracking-wider mb-2">Automated Architecture (n8n + Gemini)</div>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-[11px]">
            <div class="bg-white/10 p-3 rounded-xl border border-white/10">
                <strong class="text-white block mb-0.5">1. IMAP Email Poll</strong>
                <span class="text-slate-300">n8n watches bank support inbox every minute</span>
            </div>
            <div class="bg-white/10 p-3 rounded-xl border border-white/10">
                <strong class="text-sky-300 block mb-0.5">2. Gemini 1.5 AI</strong>
                <span class="text-slate-300">Classifies complaint, extracts bank, city, serial, urgency</span>
            </div>
            <div class="bg-white/10 p-3 rounded-xl border border-white/10">
                <strong class="text-emerald-300 block mb-0.5">3. Ticket Created</strong>
                <span class="text-slate-300">Status: OPEN (Unassigned, engineer not picked yet)</span>
            </div>
            <div class="bg-white/10 p-3 rounded-xl border border-white/10">
                <strong class="text-purple-300 block mb-0.5">4. Admin Alignment</strong>
                <span class="text-slate-300">Admin notified, assigns engineer &rarr; WhatsApp &rarr; Email</span>
            </div>
        </div>
    </div>

    <!-- Simulator Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
        
        <!-- 1-Click Sample Email Presets -->
        <div>
            <div class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center justify-between">
                <span>1-Click Load Realistic Bank Support Emails:</span>
                <span class="text-[10px] text-slate-400 font-normal">Click any preset to populate below</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2.5 text-xs">
                <button type="button" onclick="loadSample(1)" class="p-3 bg-slate-50 hover:bg-sky-50 border border-slate-200 hover:border-sky-400 rounded-xl text-left transition">
                    <strong class="text-slate-900 block font-bold">1. Cash Sorting Machine</strong>
                    <div class="text-[11px] text-slate-500 mt-0.5">Glory Sorter &bull; 2 Days SLA</div>
                </button>

                <button type="button" onclick="loadSample(2)" class="p-3 bg-slate-50 hover:bg-purple-50 border border-slate-200 hover:border-purple-400 rounded-xl text-left transition">
                    <strong class="text-slate-900 block font-bold">2. Counting Machine</strong>
                    <div class="text-[11px] text-slate-500 mt-0.5">Note Counter &bull; 1 Day SLA</div>
                </button>

                <button type="button" onclick="loadSample(3)" class="p-3 bg-slate-50 hover:bg-emerald-50 border border-slate-200 hover:border-emerald-400 rounded-xl text-left transition">
                    <strong class="text-slate-900 block font-bold">3. Binding Machine</strong>
                    <div class="text-[11px] text-slate-500 mt-0.5">Bundle Strapping &bull; 3 Days SLA</div>
                </button>

                <button type="button" onclick="loadSample(4)" class="p-3 bg-slate-50 hover:bg-amber-50 border border-slate-200 hover:border-amber-400 rounded-xl text-left transition">
                    <strong class="text-slate-900 block font-bold">4. Unknown Machine (Null)</strong>
                    <div class="text-[11px] text-slate-500 mt-0.5">Hardware General &bull; 4 Days SLA</div>
                </button>
            </div>
        </div>

        <form action="{{ route('tickets.process-simulate-ingest') }}" method="POST" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Sender Email Address</label>
                    <input type="email" id="from_email" name="from_email" required value="operations.sahiwal@bankalfalah.com" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-mono focus:ring-2 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Email Subject Header</label>
                    <input type="text" id="subject" name="subject" required value="CRITICAL: ATM Cash Dispenser Fault - Bank Alfalah High Street Sahiwal" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-semibold focus:ring-2 focus:ring-sky-500">
                </div>
            </div>

            <div class="text-xs">
                <label class="block font-bold text-slate-700 mb-1">Raw Email Body Received from Bank</label>
                <textarea id="body" name="body" rows="6" required class="w-full border border-slate-300 rounded-xl p-3 text-xs font-mono text-slate-800 focus:ring-2 focus:ring-sky-500">Dear Technical Support Team,

Our ATM machine model NCR SelfServ 26 (Serial: NCR-26019-SWL) located at Bank Alfalah High Street Branch, Sahiwal has developed a critical cash shutter jam since 10:00 AM. 
Customers are unable to withdraw cash and transactions are reversing. Branch is under heavy customer pressure.

Contact: Branch Manager Khurram Shehzad (Mobile: 03006981234).
Please dispatch an engineer urgently under our 4-hour warranty SLA.

Regards,
Operations Department
Bank Alfalah Sahiwal</textarea>
            </div>

            <div class="pt-3 border-t border-slate-200 flex items-center justify-between text-xs">
                <div class="text-slate-500 flex items-center space-x-1">
                    <i class="fa-solid fa-code text-indigo-500"></i>
                    <span>Webhook endpoint: <code class="font-mono text-slate-700 bg-slate-100 px-1 py-0.5 rounded">/api/v1/tickets/ingest</code></span>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white font-bold rounded-xl text-xs shadow-md hover:shadow-lg transition flex items-center space-x-2">
                    <i class="fa-solid fa-brain"></i>
                    <span>Run AI Triage &amp; Create Ticket</span>
                </button>
            </div>
        </form>

    </div>

</div>

<script>
    const samples = {
        1: {
            from: "branch.ops@mcb.com.pk",
            subject: "Fault in Cash Sorting Machine - MCB Gulberg Lahore [REF: MCB-CSM-4401]",
            body: "Dear Support,\n\nPlease attend to our 2-pocket Cash Sorting Machine (Glory GFS-120, Serial: GFS-8812-LHR) at MCB Gulberg Branch, Lahore. The note rejection sensor is giving false counterfeit alarms on 5000 rupee notes.\nContact: Cash Officer Asad (03009988112)\nSLA Requirement: 2 days SLA turnaround time.\nWarranty: In Warranty.\n\nRegards,\nMCB Operations Gulberg"
        },
        2: {
            from: "support@abl.com.pk",
            subject: "Counting Machine Motor Failure - Allied Bank Blue Area Islamabad",
            body: "Attention Helpdesk,\n\nOur Currency Counting Machine (Magner 150, Serial: MAG-7701-ISB) has stopped spinning. Motor smells burnt. Cash counter is halted.\nBranch: Blue Area, Islamabad.\nContact: Operations Manager Noman (03335544112)\nSLA Expectation: 1 day TAT required urgently.\nWarranty: In Warranty.\n\nThanks,\nAllied Bank Islamabad"
        },
        3: {
            from: "lahore.ops@ubl.com.pk",
            subject: "Urgent: Binding Machine Heating Element Broken - UBL Mall Road Lahore",
            body: "Dear Vendor Partner,\n\nOur Cash Bundle Binding Machine (Model: StraPack B-200, Serial: BND-6619-LHR) has a failed heating element and is not sealing currency bundle straps.\nLocation: UBL Mall Road Branch, Lahore.\nContact: Branch Rep Bilawal (03218844221)\nSLA: 3 days TAT.\nWarranty: In Warranty.\n\nRegards,\nUBL Operations"
        },
        4: {
            from: "ops@askari.com.pk",
            subject: "Hardware Peripheral Malfunction - Askari Bank Cantt Rawalpindi",
            body: "Dear Support,\n\nBranch staff reported general hardware device connection failure at counter #2. Equipment serial or type not specified by counter staff.\nBranch: Askari Bank Cantt, Rawalpindi.\nContact: CSO Hamza (03451122334)\nSLA: 4 days TAT acceptable.\nWarranty: Unknown.\n\nRegards,\nAskari Bank"
        }
    };

    function loadSample(num) {
        const s = samples[num];
        document.getElementById('from_email').value = s.from;
        document.getElementById('subject').value = s.subject;
        document.getElementById('body').value = s.body;
    }
</script>
@endsection
