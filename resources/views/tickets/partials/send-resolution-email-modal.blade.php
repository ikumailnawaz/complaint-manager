<!-- Send Resolution Confirmation Email Modal -->
<div id="sendResolutionEmailModal" style="display: none;" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full border border-slate-200 overflow-hidden transform transition-all">
        <!-- Header -->
        <div class="bg-gradient-to-r from-emerald-800 to-teal-800 px-6 py-4 text-white flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-lg shadow-inner">
                    <i class="fa-solid fa-envelope-circle-check"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm tracking-wide">Dispatch Resolution Email to Bank</h3>
                    <p class="text-[11px] text-emerald-100">
                        <span id="resEmailTicketBadge" class="font-mono font-bold bg-emerald-900/60 px-1.5 py-0.5 rounded text-white">#TICKET</span>
                        <span id="resEmailBankName" class="ml-1 text-emerald-100">&bull; Bank Name</span>
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeResolutionEmailModal()" class="text-emerald-100 hover:text-white text-lg p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Thread Continuity & RFC Notice -->
        <div class="bg-sky-50 border-b border-sky-200 px-6 py-2.5 flex items-start space-x-2 text-[11px] text-sky-950 font-medium">
            <i class="fa-solid fa-reply-all text-sky-600 mt-0.5 flex-shrink-0"></i>
            <div>
                <strong>Thread Continuity &amp; Same CCs:</strong> This dispatches a direct threaded reply email to the bank sender, including original CC recipients, work resolution summary, and attaching the engineer's uploaded supporting document/picture.
            </div>
        </div>

        <!-- Form -->
        <form id="resEmailForm" action="" method="POST" class="p-6 space-y-4 text-xs">
            @csrf

            <!-- Recipient To -->
            <div>
                <label class="block font-bold text-slate-700 mb-1 flex items-center justify-between">
                    <span><i class="fa-regular fa-envelope text-sky-600 mr-1"></i> Bank To Recipient (From Complaint Thread)</span>
                    <span class="text-[10px] text-slate-400 font-normal">Primary Recipient</span>
                </label>
                <input type="email" id="res_email_to" readonly 
                       class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-mono font-bold text-slate-800 bg-slate-100 focus:outline-none">
            </div>

            <!-- CC Recipients -->
            <div>
                <label class="block font-bold text-slate-700 mb-1 flex items-center justify-between">
                    <span><i class="fa-solid fa-copy text-sky-600 mr-1"></i> Bank CC Recipients (Same CC Thread)</span>
                    <span class="text-[10px] text-slate-400 font-normal">Comma-separated</span>
                </label>
                <input type="text" name="customer_cc" id="res_email_cc" 
                       placeholder="e.g. branch.mgr@bank.com, area.mgr@bank.com" 
                       class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-mono text-slate-800 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-sky-500 focus:outline-none transition">
            </div>

            <!-- Supporting Document Attachment Status -->
            <div id="res_attachment_container" class="rounded-xl border p-3 text-xs">
                <div class="flex items-center space-x-2">
                    <span id="res_attachment_icon" class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm flex-shrink-0">
                        <i class="fa-solid fa-paperclip"></i>
                    </span>
                    <div class="overflow-hidden flex-1">
                        <div class="font-bold text-slate-800" id="res_attachment_title">
                            Supporting Document / Picture Uploaded by Engineer
                        </div>
                        <div class="text-[10px] text-slate-500 truncate" id="res_attachment_detail">
                            Checking for engineer uploaded proof...
                        </div>
                    </div>
                    <a id="res_attachment_link" href="#" target="_blank" class="hidden px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg text-[10px] font-bold transition flex-shrink-0">
                        <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> View
                    </a>
                </div>
            </div>

            <!-- Custom Notes -->
            <div>
                <label class="block font-bold text-slate-700 mb-1">
                    Custom Handover Notes / Remarks for Bank (Optional)
                </label>
                <textarea name="custom_notes" id="res_custom_notes" rows="3" 
                          placeholder="Optional notes or remarks to include with resolution email..." 
                          class="w-full border border-slate-300 rounded-xl p-2.5 text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
                <p class="text-[10px] text-slate-400 mt-1">Optional handover remarks or instructions for the bank team.</p>
            </div>

            <!-- Email Thread Chain Preview Indicator -->
            <div class="rounded-xl border border-sky-200 bg-sky-50/70 p-3 text-xs">
                <details class="group">
                    <summary class="cursor-pointer flex items-center justify-between font-bold text-slate-800 select-none list-none">
                        <span class="flex items-center space-x-1.5 text-sky-800">
                            <i class="fa-solid fa-link text-sky-600"></i>
                            <span>Email Chain &amp; Historical Thread (Visible to CC Recipients)</span>
                        </span>
                        <span class="text-[10px] text-sky-600 flex items-center space-x-1 font-semibold">
                            <span>Details</span>
                            <i class="fa-solid fa-chevron-down text-[9px] transition group-open:rotate-180"></i>
                        </span>
                    </summary>
                    <div class="mt-2 pt-2 border-t border-sky-200/80 text-[11px] text-slate-600 space-y-1 leading-relaxed">
                        <p class="text-slate-700 font-medium">
                            <i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i>
                            The outgoing reply automatically embeds the complete chronological chain of mail (original bank complaint, timestamps, From/To/Cc headers, and prior updates) beneath this message.
                        </p>
                        <p class="text-slate-500 text-[10px]">
                            Anyone added in CC above will be able to read the entire historical thread with full context.
                        </p>
                    </div>
                </details>
            </div>

            <!-- Footer Buttons -->
            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-200">
                <button type="button" onclick="closeResolutionEmailModal()" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-5 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold rounded-xl text-xs shadow-md shadow-emerald-900/20 transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Dispatch Resolution Email via GoDaddy SMTP</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openResolutionEmailModal(ticketId, ticketNo, bankName, toEmail, ccEmails, docUrl, docName) {
    const form = document.getElementById('resEmailForm');
    form.action = '/tickets/' + ticketId + '/send-resolution-email';

    document.getElementById('resEmailTicketBadge').innerText = '#' + ticketNo;
    document.getElementById('resEmailBankName').innerText = '• ' + bankName;
    document.getElementById('res_email_to').value = toEmail || 'No recipient email on record';
    document.getElementById('res_email_cc').value = ccEmails || '';

    const notesElem = document.getElementById('res_custom_notes');
    if (notesElem) {
        notesElem.value = '';
    }

    const attachBox = document.getElementById('res_attachment_container');
    const attachTitle = document.getElementById('res_attachment_title');
    const attachDetail = document.getElementById('res_attachment_detail');
    const attachLink = document.getElementById('res_attachment_link');
    const attachIcon = document.getElementById('res_attachment_icon');

    if (docUrl && docUrl.length > 0) {
        attachBox.className = 'rounded-xl border border-emerald-300 bg-emerald-50/70 p-3 text-xs';
        attachIcon.className = 'w-7 h-7 rounded-lg bg-emerald-200 text-emerald-800 flex items-center justify-center text-sm flex-shrink-0';
        attachIcon.innerHTML = '<i class="fa-solid fa-paperclip"></i>';
        attachTitle.innerText = 'Engineer Uploaded Proof (Will be attached)';
        attachDetail.innerText = docName || 'Supporting document attached';
        attachLink.href = docUrl;
        attachLink.classList.remove('hidden');
    } else {
        attachBox.className = 'rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs';
        attachIcon.className = 'w-7 h-7 rounded-lg bg-slate-200 text-slate-500 flex items-center justify-center text-sm flex-shrink-0';
        attachIcon.innerHTML = '<i class="fa-solid fa-info"></i>';
        attachTitle.innerText = 'No supporting document attached';
        attachDetail.innerText = 'Engineer did not upload a supporting receipt or photo for this ticket.';
        attachLink.classList.add('hidden');
    }

    const modal = document.getElementById('sendResolutionEmailModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }
}

function closeResolutionEmailModal() {
    const modal = document.getElementById('sendResolutionEmailModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
}

window.addEventListener('click', function(e) {
    const modal = document.getElementById('sendResolutionEmailModal');
    if (e.target === modal) {
        closeResolutionEmailModal();
    }
});
</script>
