<!-- Send Ticket to Central Workshop Modal -->
<div id="workshopTransferModal" style="display: none;" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full border border-slate-200 overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="bg-purple-700 px-6 py-4 text-white flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-lg shadow-inner">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm tracking-wide">Send Machine to Central Workshop</h3>
                    <p class="text-[11px] text-purple-100">
                        <span id="workshopModalTicketBadge" class="font-mono font-bold bg-purple-800/80 px-1.5 py-0.5 rounded text-white">#TICKET</span>
                        <span id="workshopModalBankName" class="ml-1 text-purple-100">&bull; Bank Name</span>
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeWorkshopModal()" class="text-purple-200 hover:text-white text-lg p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Workshop Transfer Form -->
        <form id="workshopTransferForm" action="" method="POST" class="p-6 space-y-4 text-xs">
            @csrf
            <div>
                <label for="workshopLocationSelect" class="block font-bold text-slate-700 mb-1">
                    Select Target Central Workshop Facility <span class="text-rose-500">*</span>
                </label>
                <select name="workshop_location" id="workshopLocationSelect" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none bg-white">
                    <option value="Lahore Central Workshop">Lahore Central Workshop</option>
                    <option value="Karachi Hub Workshop">Karachi Hub Workshop</option>
                    <option value="Islamabad Regional Workshop">Islamabad Regional Workshop</option>
                    <option value="Multan Service Center">Multan Service Center</option>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">
                        Shipping / Cargo Courier <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="workshop_dispatch_courier" required placeholder="e.g. TCS Cargo, Daewoo, In-Hand Van"
                           value="TCS Cargo" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none bg-white">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">
                        Bilty / Courier Tracking #
                    </label>
                    <input type="text" name="workshop_dispatch_tracking" placeholder="e.g. TCS-778899 or Cargo CN"
                           class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none bg-white">
                </div>
            </div>

            <div>
                <label for="workshopEngineerSelect" class="block font-bold text-slate-700 mb-1">
                    Target Workshop Engineer (Optional recommendation)
                </label>
                <select name="workshop_engineer_id" id="workshopEngineerSelect" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none bg-white">
                    <option value="">-- Assign upon physical intake at workshop --</option>
                    @if(isset($engineers))
                        @foreach($engineers as $eng)
                            <option value="{{ $eng->id }}">{{ $eng->name }} ({{ $eng->base_city }})</option>
                        @endforeach
                    @endif
                </select>
            </div>

            <div>
                <label for="workshopNotesInput" class="block font-bold text-slate-700 mb-1">
                    Fault Reason &amp; Dispatch Cargo Notes <span class="text-rose-500">*</span>
                </label>
                <textarea name="notes" id="workshopNotesInput" rows="3" required placeholder="Explain why machine cannot be repaired on-site (e.g. PCB repair, optical alignment, specialized roller gear replacement needed)..." class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none"></textarea>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-[11px] text-amber-900 flex items-start space-x-2">
                <i class="fa-solid fa-truck-fast text-amber-600 mt-0.5 text-sm"></i>
                <div class="leading-relaxed">
                    <strong>Transit Accountability Policy:</strong> This ticket <strong>remains assigned to the current Field Engineer</strong> during cargo transit. Once physically received at the central workshop, the ticket transfers to the Workshop Engineer for bench repair.
                </div>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeWorkshopModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                    <span>Confirm &amp; Dispatch to Workshop</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openWorkshopModal(ticketId, ticketNo, bankName, currentLocation) {
    const form = document.getElementById('workshopTransferForm');
    if (form) form.action = '/tickets/' + ticketId + '/workshop';
    
    const badge = document.getElementById('workshopModalTicketBadge');
    const bank = document.getElementById('workshopModalBankName');
    if (badge) badge.innerText = '#' + ticketNo;
    if (bank) bank.innerText = '• ' + (bankName || 'Bank');
    
    if (currentLocation) {
        const sel = document.getElementById('workshopLocationSelect');
        if (sel) sel.value = currentLocation;
    }
    
    const modal = document.getElementById('workshopTransferModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeWorkshopModal() {
    const modal = document.getElementById('workshopTransferModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

// Global modal backdrop close
window.addEventListener('click', function(e) {
    const modal = document.getElementById('workshopTransferModal');
    if (e.target === modal) closeWorkshopModal();
});
</script>
