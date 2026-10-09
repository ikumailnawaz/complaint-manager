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
                    Assign Workshop Bench Engineer <span class="text-rose-500">*</span>
                </label>
                <select name="workshop_engineer_id" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none bg-white">
                    <option value="">-- Select Workshop Bench Engineer --</option>
                    @if(isset($engineers))
                        @foreach($engineers as $eng)
                            <option value="{{ $eng->id }}">{{ $eng->name }} ({{ $eng->base_city }})</option>
                        @endforeach
                    @else
                        @foreach(\App\Models\User::where('role', 'engineer')->orderBy('name')->get() as $eng)
                            <option value="{{ $eng->id }}">{{ $eng->name }} ({{ $eng->base_city }})</option>
                        @endforeach
                    @endif
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Ticket will formally transfer from field engineer to this workshop engineer.</p>
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">
                    Physical Condition &amp; Intake Inspection Remarks <span class="text-rose-500">*</span>
                </label>
                <textarea name="workshop_intake_remarks" rows="3" required
                          placeholder="e.g. Machine arrived via TCS Cargo. Casing inspected, no physical transit damage. Confirmed PCB error code E-14 on initial bench test."
                          class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none"></textarea>
            </div>
            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeWorkshopReceiveModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Confirm Receipt &amp; Transfer to Bench Tech</span>
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
function openWorkshopReceiveModal(ticketId, ticketNo, bankName, location) {
    const form = document.getElementById('workshopReceiveForm');
    if (form) form.action = '/tickets/' + ticketId + '/workshop-receive';
    const badge = document.getElementById('receiveModalTicketBadge');
    if (badge) badge.innerText = '#' + ticketNo + ' • ' + (bankName || 'Bank');
    const modal = document.getElementById('workshopReceiveModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }
}

function closeWorkshopReceiveModal() {
    const modal = document.getElementById('workshopReceiveModal');
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
