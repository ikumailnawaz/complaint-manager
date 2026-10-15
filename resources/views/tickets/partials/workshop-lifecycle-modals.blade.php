<!-- MODAL 1: PHYSICAL WORKSHOP INTAKE -->
<div id="workshopReceiveModal" style="display: none;" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full border border-slate-200 overflow-hidden">
        <div class="bg-purple-700 px-6 py-4 text-white flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-lg">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm">Physical Workshop Intake &amp; Handover</h3>
                    <p class="text-[11px] text-purple-100" id="receiveModalTicketBadge">#TICKET</p>
                </div>
            </div>
            <button type="button" onclick="closeWorkshopReceiveModal()" class="text-purple-200 hover:text-white text-lg cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="workshopReceiveForm" method="POST" action="" class="p-6 space-y-4 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-slate-700 mb-1">
                    Workshop Facility Location <span class="text-rose-500">*</span>
                </label>
                <select name="workshop_location" id="workshopReceiveLocation" onchange="onWorkshopLocationChanged(this.value)" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none bg-white font-semibold">
                    <option value="Karachi Hub Workshop">Karachi Hub Workshop (Karachi &amp; Sindh)</option>
                    <option value="Lahore Central Workshop">Lahore Central Workshop (Lahore &amp; Punjab)</option>
                    <option value="Islamabad Regional Workshop">Islamabad Regional Workshop (Islamabad / Rawalpindi / KP)</option>
                    <option value="Multan Service Center">Multan Service Center (South Punjab)</option>
                </select>
                <p class="text-[11px] text-slate-500 mt-1">Select the physical workshop facility receiving this machine.</p>
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">
                    Assign Workshop Incharge / Bench Handler <span class="text-rose-500">*</span>
                </label>
                @php
                    $allStaffAndEngineers = \App\Models\User::orderBy('name')->get();
                    $inchargeUsers = $allStaffAndEngineers->filter(function($u) {
                        return in_array($u->role, ['workshop_incharge', 'office_staff', 'admin', 'coordinator']);
                    });
                    $karachiEngineers = $allStaffAndEngineers->filter(function($u) {
                        return in_array($u->role, ['engineer', 'workshop_engineer']) && stripos($u->base_city ?? '', 'karachi') !== false;
                    });
                    $lahoreEngineers = $allStaffAndEngineers->filter(function($u) {
                        return in_array($u->role, ['engineer', 'workshop_engineer']) && (stripos($u->base_city ?? '', 'lahore') !== false || empty($u->base_city));
                    });
                    $otherEngineers = $allStaffAndEngineers->filter(function($u) {
                        return in_array($u->role, ['engineer', 'workshop_engineer']) 
                            && stripos($u->base_city ?? '', 'karachi') === false 
                            && stripos($u->base_city ?? '', 'lahore') === false 
                            && !empty($u->base_city);
                    });
                @endphp
                <select name="workshop_incharge_id" id="workshopReceiveInchargeId" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none bg-white">
                    <optgroup label="Workshop Incharges &amp; Operations Staff">
                        @foreach($inchargeUsers as $inc)
                            <option value="{{ $inc->id }}" data-city="{{ strtolower($inc->base_city ?? '') }}">
                                {{ $inc->name }} ({{ ucfirst(str_replace('_', ' ', $inc->role)) }} - {{ $inc->base_city ?? 'Lahore' }})
                            </option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Karachi Technical Team &amp; Bench Engineers">
                        @foreach($karachiEngineers as $eng)
                            <option value="{{ $eng->id }}" data-city="karachi">
                                {{ $eng->name }} (Bench Engineer - Karachi)
                            </option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Lahore Technical Team &amp; Bench Engineers">
                        @foreach($lahoreEngineers as $eng)
                            <option value="{{ $eng->id }}" data-city="lahore">
                                {{ $eng->name }} (Bench Engineer - Lahore)
                            </option>
                        @endforeach
                    </optgroup>
                    @if($otherEngineers->isNotEmpty())
                    <optgroup label="Regional Engineers">
                        @foreach($otherEngineers as $eng)
                            <option value="{{ $eng->id }}" data-city="{{ strtolower($eng->base_city ?? '') }}">
                                {{ $eng->name }} (Engineer - {{ $eng->base_city }})
                            </option>
                        @endforeach
                    </optgroup>
                    @endif
                </select>
                <p class="text-[11px] text-purple-600 mt-1" id="workshopInchargeHelpText">Machine will be handed over to the selected handler for intake &amp; bench diagnosis.</p>
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">
                    Physical Condition &amp; Intake Inspection Remarks <span class="text-rose-500">*</span>
                </label>
                <textarea name="workshop_intake_remarks" rows="3" required
                          placeholder="e.g. Machine arrived via TCS Cargo. Inspected by Adnan, no physical transit damage. Assigned to Ikram for bench allocation."
                          class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none"></textarea>
            </div>
            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeWorkshopReceiveModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Confirm Receipt &amp; Handover to Incharge</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 1.5: WORKSHOP INCHARGE REALIGN TO BENCH TECH WITH TAT -->
<div id="workshopRealignModal" style="display: none;" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full border border-slate-200 overflow-hidden">
        <div class="bg-amber-600 px-6 py-4 text-white flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-lg">
                    <i class="fa-solid fa-user-gear"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm">Re-align Machine to Bench Engineer</h3>
                    <p class="text-[11px] text-amber-100" id="realignModalTicketBadge">#TICKET</p>
                </div>
            </div>
            <button type="button" onclick="closeWorkshopRealignModal()" class="text-amber-200 hover:text-white text-lg cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="workshopRealignForm" method="POST" action="" class="p-6 space-y-4 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-slate-700 mb-1">
                    Select Bench Engineer <span class="text-rose-500">*</span>
                </label>
                <select name="workshop_engineer_id" id="realignWorkshopEngineerId" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                    <option value="">-- Select Bench Engineer --</option>
                    @foreach(\App\Models\User::where('role', 'engineer')->orderBy('name')->get() as $eng)
                        <option value="{{ $eng->id }}">{{ $eng->name }} ({{ $eng->base_city }})</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Engineer will receive a push notification and ticket will show in their mobile app.</p>
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">
                    Target Turnaround Time (TAT in Hours) <span class="text-rose-500">*</span>
                </label>
                <div class="grid grid-cols-4 gap-2 mb-2">
                    <button type="button" onclick="document.getElementById('realignTatInput').value = 12" class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg border border-amber-200 font-medium text-[11px]">12 hrs</button>
                    <button type="button" onclick="document.getElementById('realignTatInput').value = 24" class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg border border-amber-200 font-medium text-[11px]">24 hrs (1d)</button>
                    <button type="button" onclick="document.getElementById('realignTatInput').value = 48" class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg border border-amber-200 font-medium text-[11px]">48 hrs (2d)</button>
                    <button type="button" onclick="document.getElementById('realignTatInput').value = 72" class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg border border-amber-200 font-medium text-[11px]">72 hrs (3d)</button>
                </div>
                <input type="number" name="workshop_tat_hours" id="realignTatInput" required min="1" max="720" value="24"
                       placeholder="e.g. 24"
                       class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                <p class="text-[11px] text-slate-400 mt-1">Countdown will track against this SLA deadline.</p>
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">
                    Repair Instructions &amp; Diagnosis Notes
                </label>
                <textarea name="workshop_instructions" id="realignInstructions" rows="3"
                          placeholder="e.g. Clean optical sensors, replace feeder roller belt, run diagnostic calibration test."
                          class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"></textarea>
            </div>
            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeWorkshopRealignModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Assign to Engineer &amp; Start TAT</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: BENCH REPAIR COMPLETE -->
<div id="workshopResolveModal" style="display: none;" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full border border-slate-200 overflow-hidden">
        <div class="bg-emerald-700 px-6 py-4 text-white flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-lg">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm">Mark Bench Repair Complete</h3>
                    <p class="text-[11px] text-emerald-100" id="resolveModalTicketBadge">#TICKET</p>
                </div>
            </div>
            <button type="button" onclick="closeWorkshopResolveModal()" class="text-emerald-200 hover:text-white text-lg cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="workshopResolveForm" method="POST" action="" class="p-6 space-y-4 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-slate-700 mb-1">
                    Bench Repair Summary &amp; Verification QA Notes <span class="text-rose-500">*</span>
                </label>
                <textarea name="workshop_repair_summary" rows="4" required
                          placeholder="e.g. Micro-soldered optical sensor board, replaced 28-pin flat cable and motor drive belt. Calibrated sensors and successfully tested 500 currency notes with zero jams."
                          class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
            </div>
            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeWorkshopResolveModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-check"></i>
                    <span>Mark Tested OK &amp; Ready for Bank</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 3: DISPATCH RETURN TO BANK -->
<div id="workshopReturnModal" style="display: none;" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full border border-slate-200 overflow-hidden">
        <div class="bg-indigo-700 px-6 py-4 text-white flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-lg">
                    <i class="fa-solid fa-truck"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm">Dispatch Repaired Machine Back to Bank</h3>
                    <p class="text-[11px] text-indigo-100" id="returnModalTicketBadge">#TICKET</p>
                </div>
            </div>
            <button type="button" onclick="closeWorkshopReturnModal()" class="text-indigo-200 hover:text-white text-lg cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="workshopReturnForm" method="POST" action="" class="p-6 space-y-4 text-xs">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">
                        Return Courier Service <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="return_courier" required value="TCS Priority Cargo"
                           class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none bg-white">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">
                        Courier Tracking / Bilty # <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="return_tracking_number" required placeholder="e.g. TCS-99887766"
                           class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none bg-white">
                </div>
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">
                    Return Dispatch Remarks
                </label>
                <textarea name="return_dispatch_notes" rows="2"
                          placeholder="e.g. Shipped in wooden safety crate with bubble wrap, addressed to Branch Operations Manager."
                          class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
            </div>
            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeWorkshopReturnModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-plane-departure"></i>
                    <span>Confirm Return Cargo Dispatch</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 4: CONFIRM BANK RECEIPT & CLOSE -->
<div id="workshopCloseModal" style="display: none;" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full border border-slate-200 overflow-hidden">
        <div class="bg-emerald-700 px-6 py-4 text-white flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-lg">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm">Confirm Bank Delivery &amp; Final Close</h3>
                    <p class="text-[11px] text-emerald-100" id="closeModalTicketBadge">#TICKET</p>
                </div>
            </div>
            <button type="button" onclick="closeWorkshopCloseModal()" class="text-emerald-200 hover:text-white text-lg cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="workshopCloseForm" method="POST" action="" class="p-6 space-y-4 text-xs">
            @csrf
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-emerald-900 text-[11px] leading-relaxed">
                <i class="fa-solid fa-circle-info text-emerald-600 mr-1"></i>
                Confirming bank receipt will officially mark this complaint <strong>Closed</strong> and finalize all SLA turnaround performance metrics for both field &amp; workshop engineers.
            </div>
            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeWorkshopCloseModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-lock"></i>
                    <span>Confirm Re-Installation &amp; Close Ticket</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openWorkshopReceiveModal(ticketId, ticketNo, bankName, location, branchCity, engineerCity) {
    const form = document.getElementById('workshopReceiveForm');
    if (form) form.action = '/tickets/' + ticketId + '/workshop-receive';
    const badge = document.getElementById('receiveModalTicketBadge');
    if (badge) badge.innerText = '#' + ticketNo + ' • ' + (bankName || 'Bank');

    // Auto-detect & set Workshop Facility
    const locSelect = document.getElementById('workshopReceiveLocation');
    if (locSelect) {
        const targetCity = (location || branchCity || engineerCity || '').toLowerCase();
        if (targetCity.includes('karachi')) {
            locSelect.value = 'Karachi Hub Workshop';
        } else if (targetCity.includes('islamabad') || targetCity.includes('rawalpindi') || targetCity.includes('peshawar')) {
            locSelect.value = 'Islamabad Regional Workshop';
        } else if (targetCity.includes('multan')) {
            locSelect.value = 'Multan Service Center';
        } else if (location && location.trim() !== '') {
            let matched = false;
            for (let opt of locSelect.options) {
                if (opt.value.toLowerCase().includes(location.toLowerCase()) || location.toLowerCase().includes(opt.value.toLowerCase())) {
                    opt.selected = true;
                    matched = true;
                    break;
                }
            }
            if (!matched) {
                locSelect.value = 'Lahore Central Workshop';
            }
        } else {
            locSelect.value = 'Lahore Central Workshop';
        }
        updateInchargeOptionsForLocation(locSelect.value);
    }

    const modal = document.getElementById('workshopReceiveModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }
}

function onWorkshopLocationChanged(location) {
    updateInchargeOptionsForLocation(location);
}

function updateInchargeOptionsForLocation(location) {
    const inchargeSelect = document.getElementById('workshopReceiveInchargeId');
    const helpText = document.getElementById('workshopInchargeHelpText');
    if (!inchargeSelect) return;
    const locLower = (location || '').toLowerCase();
    const isKarachi = locLower.includes('karachi');

    if (isKarachi) {
        if (helpText) helpText.innerText = 'Karachi Hub Workshop selected: Handover to a Karachi bench technician (e.g. Shariq, Zahid, Raghib, Ronald).';
        for (let opt of inchargeSelect.options) {
            const city = (opt.getAttribute('data-city') || '').toLowerCase();
            const text = opt.text.toLowerCase();
            if (city.includes('karachi') || text.includes('karachi')) {
                opt.selected = true;
                break;
            }
        }
    } else {
        if (helpText) helpText.innerText = 'Lahore Central Workshop selected: Handover to Incharge (Ikram) or a Lahore bench technician.';
        for (let opt of inchargeSelect.options) {
            if (opt.text.toLowerCase().includes('ikram')) {
                opt.selected = true;
                break;
            }
        }
    }
}

function closeWorkshopReceiveModal() {
    const modal = document.getElementById('workshopReceiveModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
}

function openWorkshopRealignModal(ticketId, ticketNo, bankName, currentEngineerId, currentTat, instructions) {
    const form = document.getElementById('workshopRealignForm');
    if (form) form.action = '/tickets/' + ticketId + '/workshop-realign';
    const badge = document.getElementById('realignModalTicketBadge');
    if (badge) badge.innerText = '#' + ticketNo + ' • ' + (bankName || 'Bank');
    if (currentEngineerId) {
        const engSelect = document.getElementById('realignWorkshopEngineerId');
        if (engSelect) engSelect.value = currentEngineerId;
    }
    if (currentTat) {
        const tatInput = document.getElementById('realignTatInput');
        if (tatInput) tatInput.value = currentTat;
    }
    if (instructions) {
        const instInput = document.getElementById('realignInstructions');
        if (instInput) instInput.value = instructions;
    }
    const modal = document.getElementById('workshopRealignModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }
}

function closeWorkshopRealignModal() {
    const modal = document.getElementById('workshopRealignModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
}

function openWorkshopResolveModal(ticketId, ticketNo, bankName) {
    const form = document.getElementById('workshopResolveForm');
    if (form) form.action = '/tickets/' + ticketId + '/workshop-resolve';
    const badge = document.getElementById('resolveModalTicketBadge');
    if (badge) badge.innerText = '#' + ticketNo + ' • ' + (bankName || 'Bank');
    const modal = document.getElementById('workshopResolveModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }
}

function closeWorkshopResolveModal() {
    const modal = document.getElementById('workshopResolveModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
}

function openWorkshopReturnModal(ticketId, ticketNo, bankName) {
    const form = document.getElementById('workshopReturnForm');
    if (form) form.action = '/tickets/' + ticketId + '/workshop-return-dispatch';
    const badge = document.getElementById('returnModalTicketBadge');
    if (badge) badge.innerText = '#' + ticketNo + ' • ' + (bankName || 'Bank');
    const modal = document.getElementById('workshopReturnModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }
}

function closeWorkshopReturnModal() {
    const modal = document.getElementById('workshopReturnModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
}

function openWorkshopCloseModal(ticketId, ticketNo, bankName) {
    const form = document.getElementById('workshopCloseForm');
    if (form) form.action = '/tickets/' + ticketId + '/workshop-bank-received';
    const badge = document.getElementById('closeModalTicketBadge');
    if (badge) badge.innerText = '#' + ticketNo + ' • ' + (bankName || 'Bank');
    const modal = document.getElementById('workshopCloseModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }
}

function closeWorkshopCloseModal() {
    const modal = document.getElementById('workshopCloseModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
}
</script>
