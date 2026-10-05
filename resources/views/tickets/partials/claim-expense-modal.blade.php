<!-- Quick Claim Expense Modal for Completed Tickets -->
<div id="ticketClaimExpenseModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full border border-slate-200 overflow-hidden transform transition-all">
        <!-- Header -->
        <div class="bg-emerald-600 px-6 py-4 text-white flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-lg shadow-inner">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm tracking-wide">Claim Tour Travel &amp; Field Expense</h3>
                    <p class="text-[11px] text-emerald-100">
                        <span id="claimTicketBadge" class="font-mono font-bold bg-emerald-700/60 px-1.5 py-0.5 rounded text-white">#TICKET</span>
                        <span id="claimBankName" class="ml-1 text-emerald-100">&bull; Bank Name</span>
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeTicketClaimExpenseModal()" class="text-emerald-100 hover:text-white text-lg p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Policy Note -->
        <div class="bg-amber-50 border-b border-amber-200 px-6 py-2.5 flex items-start space-x-2 text-[11px] text-amber-900 font-medium">
            <i class="fa-solid fa-shield-halved text-amber-600 mt-0.5 flex-shrink-0"></i>
            <div>
                <strong>Single Active Claim Policy:</strong> Once submitted, this claim will enter the audit queue. A new claim cannot be created for this ticket unless Operations Management rejects it.
            </div>
        </div>

        <!-- Form -->
        <form id="ticketClaimExpenseForm" action="{{ route('expenses.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 text-xs">
            @csrf
            <input type="hidden" name="ticket_id" id="claim_modal_ticket_id" value="">
            <input type="hidden" name="redirect_to" value="tickets">
            <input type="hidden" name="uploaded_voucher_path" id="uploaded_voucher_path" value="">
            <input type="hidden" name="uploaded_voucher_name" id="uploaded_voucher_name" value="">

            <!-- Route Origin & Destination -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">
                        From City (Base / Departure) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="from_city" id="claim_from_city" required 
                           placeholder="e.g. Lahore, Karachi" 
                           class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none bg-slate-50 focus:bg-white transition">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">
                        To City (Branch Destination) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="to_city" id="claim_to_city" required 
                           placeholder="e.g. Multan, Faisalabad" 
                           class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none bg-slate-50 focus:bg-white transition">
                </div>
            </div>

            <!-- Trip Type & Expense Category -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Trip Type <span class="text-rose-500">*</span></label>
                    <select name="trip_type" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-emerald-500 bg-white">
                        <option value="round_trip">Round Trip (Both Ways)</option>
                        <option value="one_way">One Way</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Expense Category <span class="text-rose-500">*</span></label>
                    <select name="category" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-emerald-500 bg-white">
                        <option value="travel">Intercity Travel / Bus / Train</option>
                        <option value="fuel">Fuel / Petrol / Diesel</option>
                        <option value="accommodation">Hotel / Accommodation</option>
                        <option value="food">Daily Food / Per Diem</option>
                        <option value="parts">Emergency Local Parts Purchase</option>
                        <option value="misc">Miscellaneous</option>
                    </select>
                </div>
            </div>

            <!-- Amount Claimed -->
            <div>
                <label class="block font-bold text-slate-700 mb-1">
                    Claimed Amount (PKR) <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 font-bold text-slate-400">Rs.</span>
                    <input type="number" step="1" min="1" name="claimed_amount" required 
                           placeholder="e.g. 4500" 
                           class="w-full border border-slate-300 rounded-xl p-2.5 pl-10 text-xs font-mono font-bold text-slate-900 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <p class="text-[10px] text-slate-400 mt-1">
                    AI will benchmark distance and suggest rate based on official PKR 25/km rate.
                </p>
            </div>

            <!-- Upload Supporting Document / Receipt Voucher (Interactive Dropzone with Live Progress Bar & Done Status) -->
            <div>
                <label class="block font-bold text-slate-700 mb-1 flex items-center justify-between">
                    <span><i class="fa-solid fa-file-arrow-up text-emerald-600 mr-1"></i> Upload Supporting Document / Receipt Voucher</span>
                    <span class="text-[10px] text-emerald-600 font-semibold bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">Picture (JPG, PNG) or PDF Accepted</span>
                </label>
                <p class="text-[11px] text-slate-500 mb-2">
                    Attach travel ticket, toll plaza receipt, fuel slip, or hotel voucher (Picture or PDF format up to 10MB) for audit.
                </p>

                <!-- Interactive Upload Dropzone -->
                <div id="voucher_dropzone_container" 
                     class="border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-xl p-4 text-center bg-slate-50/70 hover:bg-emerald-50/30 transition cursor-pointer relative" 
                     onclick="triggerVoucherFileInput()">
                    
                    <input type="file" name="voucher_file" id="voucher_file_input" 
                           accept="image/jpeg,image/png,image/jpg,image/webp,application/pdf" 
                           class="hidden" onchange="handleVoucherFileSelection(event)">

                    <!-- Initial Prompt State -->
                    <div id="voucher_dropzone_prompt" class="space-y-1 py-2">
                        <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:text-emerald-600 transition">
                            <i class="fa-solid fa-cloud-arrow-up text-lg text-emerald-600"></i>
                        </div>
                        <div class="font-semibold text-slate-700 text-xs">
                            <span class="text-emerald-600 font-bold hover:underline">Click to upload voucher</span> or drag and drop
                        </div>
                        <p class="text-[10px] text-slate-400">Pictures (PNG, JPG, JPEG, WEBP) or PDF documents (up to 10 MB)</p>
                    </div>

                    <!-- Live Upload Progress Bar State -->
                    <div id="voucher_upload_progress_wrapper" class="hidden py-2 space-y-2">
                        <div class="flex items-center justify-between text-[11px] font-semibold text-slate-700">
                            <span id="voucher_upload_status_label" class="flex items-center space-x-1.5 text-emerald-700">
                                <i class="fa-solid fa-spinner fa-spin text-emerald-600"></i>
                                <span>Uploading supporting document / voucher...</span>
                            </span>
                            <span id="voucher_upload_percentage_text" class="font-mono text-xs font-bold text-emerald-700">0%</span>
                        </div>
                        <!-- Progress Track -->
                        <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden p-0.5 shadow-inner">
                            <div id="voucher_upload_progress_bar" class="bg-gradient-to-r from-emerald-500 to-teal-500 h-1.5 rounded-full transition-all duration-200" style="width: 0%"></div>
                        </div>
                        <p class="text-[10px] text-slate-400 text-left">Please wait while your voucher is uploading and verified...</p>
                    </div>

                    <!-- Upload Done / Success Banner State -->
                    <div id="voucher_upload_success_wrapper" class="hidden space-y-2.5 text-left">
                        <div class="flex items-center justify-between p-2.5 bg-emerald-50 border border-emerald-300 rounded-xl text-emerald-800">
                            <div class="flex items-center space-x-2">
                                <span class="w-7 h-7 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs flex-shrink-0 shadow-sm">
                                    <i class="fa-solid fa-check"></i>
                                </span>
                                <div>
                                    <div class="font-bold text-xs text-emerald-900 flex items-center space-x-1">
                                        <span>Upload Done! Voucher Attached</span>
                                    </div>
                                    <div id="voucher_uploaded_file_info" class="text-[10px] text-emerald-700 font-mono">voucher.jpg</div>
                                </div>
                            </div>
                            <button type="button" onclick="resetVoucherUpload(event)" class="text-slate-400 hover:text-rose-600 p-1.5 rounded-lg hover:bg-white/80 transition text-xs cursor-pointer" title="Remove / Upload different voucher">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>

                        <!-- Image Preview Box (If image file) -->
                        <div id="voucher_uploaded_img_preview_box" class="hidden rounded-xl border border-slate-200 overflow-hidden bg-slate-100 max-h-36 flex items-center justify-center p-1">
                            <img id="voucher_uploaded_img_element" src="" alt="Voucher Preview" class="max-h-32 rounded object-contain shadow-xs">
                        </div>

                        <!-- PDF Preview Box (If PDF document) -->
                        <div id="voucher_uploaded_pdf_preview_box" class="hidden p-2.5 bg-rose-50 border border-rose-200 rounded-xl flex items-center space-x-2 text-rose-800">
                            <i class="fa-solid fa-file-pdf text-rose-600 text-xl flex-shrink-0"></i>
                            <div class="overflow-hidden">
                                <span class="font-bold text-xs block truncate" id="voucher_uploaded_pdf_name">voucher.pdf</span>
                                <span class="text-[10px] text-rose-600">PDF receipt voucher attached</span>
                            </div>
                        </div>
                    </div>

                    <!-- Error Alert State -->
                    <div id="voucher_upload_error_wrapper" class="hidden p-2.5 bg-rose-50 border border-rose-300 rounded-xl text-rose-800 text-left">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-triangle-exclamation text-rose-600 text-sm flex-shrink-0"></i>
                            <div>
                                <span class="font-bold text-xs">Upload Failed</span>
                                <p id="voucher_upload_error_text" class="text-[10px] text-rose-600">Failed to upload document. Please try again.</p>
                            </div>
                        </div>
                        <button type="button" onclick="resetVoucherUpload(event)" class="mt-2 text-[10px] text-rose-700 underline font-bold">
                            Click to try again
                        </button>
                    </div>

                </div>
            </div>

            <!-- Description / Notes -->
            <div>
                <label class="block font-bold text-slate-700 mb-1">Trip Details &amp; Expense Notes (Optional)</label>
                <textarea name="description" rows="2" 
                          placeholder="e.g. Traveled by Daewoo bus to perform emergency sensor replacement, returned same evening..." 
                          class="w-full border border-slate-300 rounded-xl p-2.5 text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
            </div>

            <!-- Footer Buttons -->
            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-200">
                <button type="button" onclick="closeTicketClaimExpenseModal()" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="btnSubmitExpenseClaim" 
                        class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md shadow-emerald-900/20 transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Submit Tour Expense Claim</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let isVoucherUploading = false;

function openTicketClaimExpenseModal(ticketId, ticketNo, bankName, branchCity, engineerCity) {
    document.getElementById('claim_modal_ticket_id').value = ticketId;
    document.getElementById('claimTicketBadge').innerText = '#' + ticketNo;
    document.getElementById('claimBankName').innerText = '• ' + bankName;
    document.getElementById('claim_from_city').value = engineerCity || 'Lahore';
    document.getElementById('claim_to_city').value = branchCity || '';

    // Reset upload state on modal open
    resetVoucherUpload();

    const modal = document.getElementById('ticketClaimExpenseModal');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function closeTicketClaimExpenseModal() {
    const modal = document.getElementById('ticketClaimExpenseModal');
    if (modal) {
        modal.classList.add('hidden');
    }
    isVoucherUploading = false;
}

function triggerVoucherFileInput() {
    if (isVoucherUploading) return;
    const input = document.getElementById('voucher_file_input');
    if (input) input.click();
}

function resetVoucherUpload(e) {
    if (e) e.stopPropagation();

    const input = document.getElementById('voucher_file_input');
    if (input) input.value = '';

    const pathInput = document.getElementById('uploaded_voucher_path');
    const nameInput = document.getElementById('uploaded_voucher_name');
    if (pathInput) pathInput.value = '';
    if (nameInput) nameInput.value = '';

    const prompt = document.getElementById('voucher_dropzone_prompt');
    const progress = document.getElementById('voucher_upload_progress_wrapper');
    const success = document.getElementById('voucher_upload_success_wrapper');
    const error = document.getElementById('voucher_upload_error_wrapper');
    const imgBox = document.getElementById('voucher_uploaded_img_preview_box');
    const pdfBox = document.getElementById('voucher_uploaded_pdf_preview_box');

    if (prompt) prompt.classList.remove('hidden');
    if (progress) progress.classList.add('hidden');
    if (success) success.classList.add('hidden');
    if (error) error.classList.add('hidden');
    if (imgBox) imgBox.classList.add('hidden');
    if (pdfBox) pdfBox.classList.add('hidden');

    const btnSubmit = document.getElementById('btnSubmitExpenseClaim');
    if (btnSubmit) btnSubmit.disabled = false;
    isVoucherUploading = false;
}

function handleVoucherFileSelection(event) {
    if (event) event.stopPropagation();
    const file = event.target.files[0];
    if (!file) return;

    const prompt = document.getElementById('voucher_dropzone_prompt');
    const progress = document.getElementById('voucher_upload_progress_wrapper');
    const success = document.getElementById('voucher_upload_success_wrapper');
    const error = document.getElementById('voucher_upload_error_wrapper');
    const progressBar = document.getElementById('voucher_upload_progress_bar');
    const percentText = document.getElementById('voucher_upload_percentage_text');
    const btnSubmit = document.getElementById('btnSubmitExpenseClaim');

    // Switch views to progress
    prompt.classList.add('hidden');
    success.classList.add('hidden');
    error.classList.add('hidden');
    progress.classList.remove('hidden');
    progressBar.style.width = '0%';
    percentText.innerText = '0%';

    if (btnSubmit) btnSubmit.disabled = true;
    isVoucherUploading = true;

    // Build FormData
    const formData = new FormData();
    formData.append('voucher', file);

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content 
              || document.querySelector('input[name="_token"]')?.value;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '{{ route("expenses.upload-voucher") }}', true);
    if (csrf) {
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
    }
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    // Upload Progress event listener: updates progress bar in real-time
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + '%';
            percentText.innerText = percent + '%';
        }
    });

    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            isVoucherUploading = false;
            if (btnSubmit) btnSubmit.disabled = false;

            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        // Store uploaded path & original name
                        document.getElementById('uploaded_voucher_path').value = data.path;
                        document.getElementById('uploaded_voucher_name').value = data.filename;

                        // Display Success (Done notification)
                        progress.classList.add('hidden');
                        success.classList.remove('hidden');

                        const fileInfo = document.getElementById('voucher_uploaded_file_info');
                        if (fileInfo) fileInfo.innerText = data.filename + ' (' + data.filesize + ')';

                        if (data.is_image) {
                            const imgBox = document.getElementById('voucher_uploaded_img_preview_box');
                            const imgEl = document.getElementById('voucher_uploaded_img_element');
                            imgEl.src = data.url;
                            imgBox.classList.remove('hidden');
                        } else {
                            const pdfBox = document.getElementById('voucher_uploaded_pdf_preview_box');
                            const pdfName = document.getElementById('voucher_uploaded_pdf_name');
                            pdfName.innerText = data.filename;
                            pdfBox.classList.remove('hidden');
                        }
                        return;
                    }
                } catch (e) {
                    console.error(e);
                }
            }

            // Error occurred
            progress.classList.add('hidden');
            error.classList.remove('hidden');
            let errorMsg = 'Failed to upload receipt voucher. Please try again.';
            try {
                const errData = JSON.parse(xhr.responseText);
                if (errData.message) errorMsg = errData.message;
            } catch(e) {}
            document.getElementById('voucher_upload_error_text').innerText = errorMsg;
        }
    };

    xhr.send(formData);
}

// Drag & drop support on voucher container
document.addEventListener('DOMContentLoaded', function() {
    const dropzone = document.getElementById('voucher_dropzone_container');
    if (!dropzone) return;

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('border-emerald-500', 'bg-emerald-50/50');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('border-emerald-500', 'bg-emerald-50/50');
        }, false);
    });

    dropzone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            const input = document.getElementById('voucher_file_input');
            input.files = files;
            handleVoucherFileSelection({ target: input, stopPropagation: () => {} });
        }
    }, false);
});

window.addEventListener('click', function(e) {
    const modal = document.getElementById('ticketClaimExpenseModal');
    if (e.target === modal) {
        closeTicketClaimExpenseModal();
    }
});
</script>
