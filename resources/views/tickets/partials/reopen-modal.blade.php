<!-- REOPEN TICKET MODAL (OPERATIONS MANAGER & ADMIN) -->
<div id="reopenTicketModal" style="display: none;" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <!-- Header -->
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-lg bg-rose-500/20 text-rose-400 flex items-center justify-center text-sm font-bold border border-rose-500/30">
                    <i class="fa-solid fa-rotate-left"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black uppercase tracking-wider text-white">Reopen Complaint Ticket</h3>
                    <p class="text-[11px] text-slate-400">Initiate Tour {{ ((int)$ticket->current_cycle_no ?: 1) + 1 }} &bull; Ticket #{{ $ticket->ticket_no }}</p>
                </div>
            </div>
            <button type="button" onclick="closeReopenModal()" class="text-slate-400 hover:text-white transition cursor-pointer p-1">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Form Body -->
        <form action="{{ route('tickets.reopen', $ticket) }}" method="POST" class="p-6 space-y-4">
            @csrf

            <!-- Audit Notice -->
            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-[11px] text-amber-900 leading-relaxed flex items-start space-x-2">
                <i class="fa-solid fa-shield-halved text-amber-600 mt-0.5 text-xs shrink-0"></i>
                <div>
                    <strong>Data Preservation &amp; 15-Day Policy:</strong>
                    Reopening is permitted within 15 days of resolution/closing (<strong>{{ $ticket->reopenDaysRemaining() }} days remaining</strong>). All previous activity reports, resolution documents, and tour expenses are permanently archived and preserved. A fresh SLA clock and tour expense allowance will be initialized.
                </div>
            </div>

            <!-- Mandatory Reason -->
            <div>
                <label class="block text-xs font-bold text-slate-800 mb-1">
                    Reopening Reason / Bank Recurrence Description <span class="text-rose-600">*</span>
                </label>
                <textarea name="reason" rows="3" required minlength="10" maxlength="2000"
                    placeholder="Provide detailed justification (e.g. Bank reported cash jam recurring after 2 days of initial repair)..."
                    class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-rose-500 focus:border-rose-500 bg-white"></textarea>
                <p class="text-[10px] text-slate-400 mt-1">Minimum 10 characters. Reason is recorded in the bank-wise audit trail.</p>
            </div>

            <!-- Lead Engineer Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-800 mb-1">
                    Assign Lead Engineer for this Reopen Tour:
                </label>
                <select name="engineer_id" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-rose-500 bg-white">
                    @foreach($engineers as $eng)
                        <option value="{{ $eng->id }}" {{ $ticket->assigned_engineer_id == $eng->id ? 'selected' : '' }}>
                            {{ $eng->name }} &bull; {{ $eng->base_city }} &bull; {{ $eng->specialization }} &bull; (WA: {{ $eng->phone_whatsapp }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Support Engineers Selection -->
            <div>
                <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                    Additional Support Engineers (Optional):
                </label>
                @php $activeSuppIds = $ticket->activeTicketEngineers()->where('role', 'support')->pluck('engineer_id')->all(); @endphp
                <select name="support_engineer_ids[]" multiple size="3" class="w-full border border-slate-300 rounded-xl p-2 text-xs focus:ring-2 focus:ring-rose-500 bg-white">
                    @foreach($engineers as $eng)
                        <option value="{{ $eng->id }}" {{ in_array($eng->id, $activeSuppIds) ? 'selected' : '' }}>
                            {{ $eng->name }} &bull; {{ $eng->base_city }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[10px] text-slate-400 mt-1">Hold Ctrl (Windows) / Cmd (Mac) to select multiple engineers.</p>
            </div>

            <!-- Buttons -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeReopenModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs shadow-md shadow-rose-900/30 transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-rotate-left"></i>
                    <span>Confirm &amp; Reopen Ticket</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openReopenModal() {
    const m = document.getElementById('reopenTicketModal');
    if (m) {
        m.style.display = 'flex';
        m.classList.remove('hidden');
    }
}
function closeReopenModal() {
    const m = document.getElementById('reopenTicketModal');
    if (m) {
        m.style.display = 'none';
        m.classList.add('hidden');
    }
}
window.addEventListener('click', function(e) {
    const m = document.getElementById('reopenTicketModal');
    if (e.target === m) closeReopenModal();
});
</script>
