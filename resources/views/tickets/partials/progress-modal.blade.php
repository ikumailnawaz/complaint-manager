<!-- Ticket View Progress Modal -->
<div id="viewProgressModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden transform transition-all animate-in fade-in zoom-in-95 duration-150">
        <!-- Modal Top Bar -->
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50 shrink-0">
            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-xl bg-sky-600 text-white flex items-center justify-center text-sm shadow-xs">
                    <i class="fa-solid fa-timeline"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-900">Ticket Progress &amp; Audit Breakdown</h3>
                    <p class="text-[11px] text-slate-500">Chronological lifecycle, stage hours &amp; day-wise feedback</p>
                </div>
            </div>
            <button type="button" onclick="closeViewProgressModal()" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-200 transition cursor-pointer" title="Close Modal">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <!-- Modal Dynamic Body -->
        <div id="progressModalContent" class="p-5 overflow-y-auto flex-1">
            <div class="py-16 text-center text-slate-400">
                <i class="fa-solid fa-circle-notch fa-spin text-2xl text-sky-600 mb-2"></i>
                <p class="text-xs font-semibold">Loading lifecycle progress details...</p>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-5 py-3 border-t border-slate-200 bg-slate-50 flex items-center justify-between text-xs shrink-0">
            <span class="text-[11px] text-slate-400">
                <i class="fa-solid fa-circle-info mr-1 text-slate-400"></i>
                Elapsed metrics calculated dynamically from logged audit trail
            </span>
            <button type="button" onclick="closeViewProgressModal()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl transition cursor-pointer">
                Close
            </button>
        </div>
    </div>
</div>

<script>
function openViewProgressModal(ticketId) {
    const modal = document.getElementById('viewProgressModal');
    const content = document.getElementById('progressModalContent');
    if (!modal || !content) return;

    // Reset to loading state
    content.innerHTML = `
        <div class="py-16 text-center text-slate-400">
            <i class="fa-solid fa-circle-notch fa-spin text-2xl text-sky-600 mb-2"></i>
            <p class="text-xs font-semibold">Loading lifecycle progress details...</p>
        </div>
    `;

    modal.classList.remove('hidden');

    // Fetch progress modal content from backend
    fetch(`/tickets/${ticketId}/progress-modal`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'text/html'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Failed to load ticket progress');
        }
        return response.text();
    })
    .then(html => {
        content.innerHTML = html;
    })
    .catch(error => {
        content.innerHTML = `
            <div class="py-12 text-center text-rose-500">
                <i class="fa-solid fa-triangle-exclamation text-2xl mb-2"></i>
                <p class="text-xs font-bold">${error.message || 'Error loading ticket progress'}</p>
                <button type="button" onclick="openViewProgressModal(${ticketId})" class="mt-3 px-3 py-1.5 bg-rose-100 text-rose-800 rounded-lg text-xs font-bold hover:bg-rose-200 transition">
                    Retry
                </button>
            </div>
        `;
    });
}

function closeViewProgressModal() {
    const modal = document.getElementById('viewProgressModal');
    if (modal) modal.classList.add('hidden');
}

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeViewProgressModal();
    }
});
</script>
