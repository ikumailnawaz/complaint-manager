<!-- MODAL: MARK APPROVAL ARRIVED (Admin Only) -->
<div id="grantApprovalModal" style="display: none;" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 transform transition-all">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-slate-800 text-base">Record Approval Arrival</h3>
                    <p class="text-xs text-slate-500">Resume ticket SLA timer &amp; extend engineer TAT</p>
                </div>
            </div>
            <button type="button" onclick="closeGrantApprovalModal()" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="grantApprovalForm" method="POST" action="" class="space-y-4 mt-4">
            @csrf

            <!-- Ticket Info Highlight -->
            <div class="bg-slate-50 rounded-xl p-3 border border-slate-200 text-xs space-y-1">
                <div class="flex justify-between">
                    <span class="text-slate-500 font-semibold">Ticket:</span>
                    <span id="grantModalTicketNo" class="font-mono font-bold text-slate-800"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500 font-semibold">Bank / Client:</span>
                    <span id="grantModalBankName" class="font-bold text-slate-800"></span>
                </div>
                <div class="flex justify-between text-amber-700">
                    <span class="font-semibold">Paused Duration:</span>
                    <span id="grantModalPausedDuration" class="font-bold"></span>
                </div>
            </div>

            <!-- Smart Notice -->
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-900 flex items-start space-x-2">
                <i class="fa-solid fa-circle-info text-emerald-600 text-sm mt-0.5 shrink-0"></i>
                <div class="leading-relaxed">
                    <strong>Smart SLA Adjustment:</strong> Resuming the ticket shifts the SLA deadline forward by exactly the paused duration, ensuring the engineer gets their full remaining TAT without penalty.
                </div>
            </div>

            <!-- Input: Approval Remarks -->
            <div class="space-y-1.5">
                <label for="grantApprovalRemarks" class="block text-xs font-bold text-slate-700">
                    Approval Confirmation Remarks / Reference <span class="text-rose-500">*</span>
                </label>
                <textarea id="grantApprovalRemarks" name="remarks" rows="3" required
                          placeholder="e.g. Received written approval from Bank Operations Head / Branch Manager via email (Ref #10482). Authorized to proceed with part replacement."
                          class="w-full text-xs p-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 outline-none leading-relaxed"></textarea>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-2 pt-2">
                <button type="button" onclick="closeGrantApprovalModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-extrabold text-white bg-emerald-600 hover:bg-emerald-500 shadow-md shadow-emerald-900/20 transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-play"></i>
                    <span>Confirm Approval &amp; Resume Clock</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: REQUEST APPROVAL / PAUSE SLA -->
<div id="requestApprovalModal" style="display: none;" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 transform transition-all">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-pause"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-slate-800 text-base">Waiting for Approval</h3>
                    <p class="text-xs text-slate-500">Pause SLA clock awaiting customer or bank sign-off</p>
                </div>
            </div>
            <button type="button" onclick="closeRequestApprovalModal()" class="text-slate-400 hover:text-slate-600 text-lg cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="requestApprovalForm" method="POST" action="" enctype="multipart/form-data" class="space-y-4 mt-4">
            @csrf

            <!-- Ticket Info Highlight -->
            <div class="bg-slate-50 rounded-xl p-3 border border-slate-200 text-xs space-y-1">
                <div class="flex justify-between">
                    <span class="text-slate-500 font-semibold">Ticket:</span>
                    <span id="requestModalTicketNo" class="font-mono font-bold text-slate-800"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500 font-semibold">Bank / Client:</span>
                    <span id="requestModalBankName" class="font-bold text-slate-800"></span>
                </div>
            </div>

            <!-- Approval Authority / Source -->
            <div class="space-y-1.5">
                <label for="requestApprovalSource" class="block text-xs font-bold text-slate-700">
                    Approval Authority / Source
                </label>
                <input type="text" id="requestApprovalSource" name="approval_source"
                       placeholder="e.g. Branch Manager Sign-off, Operations Head Authorization"
                       class="w-full text-xs p-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 outline-none">
            </div>

            <!-- Reason for Pause -->
            <div class="space-y-1.5">
                <label for="requestApprovalReason" class="block text-xs font-bold text-slate-700">
                    Reason for Requesting Approval <span class="text-rose-500">*</span>
                </label>
                <textarea id="requestApprovalReason" name="reason" rows="3" required
                          placeholder="Explain what approval is required (e.g. Quotation approval for main PCB replacement, site access permit)..."
                          class="w-full text-xs p-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 outline-none leading-relaxed"></textarea>
            </div>

            <!-- Supporting Document Upload -->
            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">
                    Supporting Document / Email / Sign-off Sheet (Optional)
                </label>
                <input type="file" name="document" accept=".jpeg,.png,.jpg,.gif,.webp,.pdf"
                       class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200 border border-slate-200 rounded-xl p-2 bg-slate-50">
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeRequestApprovalModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-extrabold text-white bg-amber-600 hover:bg-amber-500 shadow-md shadow-amber-900/20 transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-pause"></i>
                    <span>Confirm Pause SLA Clock</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRequestApprovalModal(ticketId, ticketNo, bankName) {
        var form = document.getElementById('requestApprovalForm');
        if (form) {
            form.action = "/tickets/" + ticketId + "/request-approval";
        }
        var noElem = document.getElementById('requestModalTicketNo');
        if (noElem) noElem.textContent = '#' + ticketNo;
        var bankElem = document.getElementById('requestModalBankName');
        if (bankElem) bankElem.textContent = bankName;
        var modal = document.getElementById('requestApprovalModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.remove('hidden');
        }
    }

    function closeRequestApprovalModal() {
        var modal = document.getElementById('requestApprovalModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.add('hidden');
        }
    }

    function openGrantApprovalModal(ticketId, ticketNo, bankName, pausedDuration) {
        var form = document.getElementById('grantApprovalForm');
        if (form) {
            form.action = "/tickets/" + ticketId + "/grant-approval";
        }
        var noElem = document.getElementById('grantModalTicketNo');
        if (noElem) noElem.textContent = '#' + ticketNo;
        var bankElem = document.getElementById('grantModalBankName');
        if (bankElem) bankElem.textContent = bankName;
        var durElem = document.getElementById('grantModalPausedDuration');
        if (durElem) durElem.textContent = pausedDuration || 'Calculated automatically';
        var remarksElem = document.getElementById('grantApprovalRemarks');
        if (remarksElem) remarksElem.value = '';
        var modal = document.getElementById('grantApprovalModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.remove('hidden');
        }
    }

    function closeGrantApprovalModal() {
        var modal = document.getElementById('grantApprovalModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.add('hidden');
        }
    }
</script>
