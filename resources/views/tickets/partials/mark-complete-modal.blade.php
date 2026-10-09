<!-- Mark Ticket as Completed Modal with Live Upload Progress -->
<div id="engineerCompleteModal" style="display: none;" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full border border-slate-200 overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="bg-emerald-600 px-6 py-4 text-white flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-lg shadow-inner">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm tracking-wide">Mark Service Call as Completed</h3>
                    <p class="text-[11px] text-emerald-100">
                        <span id="modalTicketBadge" class="font-mono font-bold bg-emerald-700/60 px-1.5 py-0.5 rounded text-white">#TICKET</span>
                        <span id="modalBankName" class="ml-1 text-emerald-100">&bull; Bank Name</span>
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeCompleteModal()" class="text-emerald-100 hover:text-white text-lg p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Resolution & Proof Form -->
        <form id="completeTicketForm" action="" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 text-xs">
            @csrf
            <input type="hidden" name="uploaded_document_path" id="uploaded_document_path" value="">
            <input type="hidden" name="uploaded_document_name" id="uploaded_document_name" value="">

            <!-- Work Carried Out -->
            <div>
                <label for="resolution_summary_input" class="block font-bold text-slate-700 mb-1">
                    Resolution Summary / Work Carried Out <span class="text-rose-500">*</span>
                </label>
                <textarea name="resolution_summary" id="resolution_summary_input" rows="3" required 
                    placeholder="Describe maintenance or service performed, parts replaced or calibrated, and testing results..." 
                    class="w-full border border-slate-300 rounded-xl p-3 text-xs focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none transition shadow-sm">Completed on-site field maintenance, calibrated sensors, and machine tested successfully in presence of branch staff.</textarea>
            </div>

            <!-- Supporting Document / Proof of Completion -->
            <div>
                <label class="block font-bold text-slate-700 mb-1 flex items-center justify-between">
                    <span><i class="fa-solid fa-file-arrow-up text-emerald-600 mr-1"></i> Supporting Document / Proof of Completion</span>
                    <span class="text-[10px] text-emerald-600 font-semibold bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">Picture (JPG, PNG) or PDF Accepted</span>
                </label>
                <p class="text-[11px] text-slate-500 mb-2">
                    Upload signed service slip, customer handover receipt, or photo of the operational machine.
                </p>

                <!-- Dropzone / File Picker Container -->
                <div id="dropzone_container" class="border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-xl p-4 text-center bg-slate-50/70 hover:bg-emerald-50/30 transition cursor-pointer relative" onclick="triggerDocFileInput()">
                    <input type="file" name="supporting_document" id="supporting_doc_input" 
                        accept="image/jpeg,image/png,image/jpg,image/webp,application/pdf" 
                        class="hidden" onchange="handleDocFileSelection(event)">
                    
                    <div id="dropzone_prompt" class="space-y-1 py-2">
                        <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:text-emerald-600 transition">
                            <i class="fa-solid fa-cloud-arrow-up text-lg text-slate-500"></i>
                        </div>
                        <div class="font-semibold text-slate-700 text-xs">
                            <span class="text-emerald-600 font-bold hover:underline">Click to upload</span> or drag and drop
                        </div>
                        <p class="text-[10px] text-slate-400">PNG, JPG, JPEG, WEBP or PDF (up to 10 MB)</p>
                    </div>

                    <!-- Live Upload Progress Indicator -->
                    <div id="upload_progress_wrapper" class="hidden py-2 space-y-2">
                        <div class="flex items-center justify-between text-[11px] font-semibold text-slate-700">
                            <span id="upload_status_label" class="flex items-center space-x-1.5 text-sky-700">
                                <i class="fa-solid fa-spinner fa-spin text-sky-600"></i>
                                <span>Uploading supporting document...</span>
                            </span>
                            <span id="upload_percentage_text" class="font-mono text-xs font-bold text-sky-600">0%</span>
                        </div>
                        <!-- Progress Track -->
                        <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden p-0.5 shadow-inner">
                            <div id="upload_progress_bar" class="bg-gradient-to-r from-sky-500 to-emerald-500 h-1.5 rounded-full transition-all duration-200" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Upload Success Banner & Preview -->
                    <div id="upload_success_wrapper" class="hidden space-y-2.5 text-left">
                        <div class="flex items-center justify-between p-2.5 bg-emerald-50 border border-emerald-300 rounded-xl text-emerald-800">
                            <div class="flex items-center space-x-2">
                                <span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs">
                                    <i class="fa-solid fa-check"></i>
                                </span>
                                <div>
                                    <div class="font-bold text-xs text-emerald-900 flex items-center space-x-1">
                                        <span>Image uploaded successfully</span>
                                    </div>
                                    <div id="uploaded_file_info" class="text-[10px] text-emerald-700 font-mono">file.jpg</div>
                                </div>
                            </div>
                            <button type="button" onclick="resetDocUpload(event)" class="text-slate-400 hover:text-rose-600 p-1 rounded transition text-xs" title="Remove / Upload different file">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>

                        <!-- Image Preview Box -->
                        <div id="uploaded_img_preview_box" class="hidden rounded-xl border border-slate-200 overflow-hidden bg-slate-100 max-h-36 flex items-center justify-center p-1">
                            <img id="uploaded_img_element" src="" alt="Proof Preview" class="max-h-32 object-contain rounded-lg shadow-sm">
                        </div>

                        <!-- PDF Preview Box -->
                        <div id="uploaded_pdf_preview_box" class="hidden p-3 rounded-xl border border-slate-200 bg-slate-50 flex items-center space-x-2.5">
                            <i class="fa-solid fa-file-pdf text-rose-500 text-2xl"></i>
                            <div class="overflow-hidden">
                                <div id="uploaded_pdf_name" class="font-bold text-xs text-slate-800 truncate">document.pdf</div>
                                <span class="text-[10px] text-slate-400">PDF Document ready for submission</span>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Error Banner -->
                    <div id="upload_error_wrapper" class="hidden p-2.5 bg-rose-50 border border-rose-300 text-rose-800 rounded-xl text-left text-xs flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                            <span id="upload_error_text">Upload failed. Please try again.</span>
                        </div>
                        <button type="button" onclick="resetDocUpload(event)" class="text-xs text-rose-700 font-bold hover:underline">Retry</button>
                    </div>
                </div>
            </div>

            <!-- Resolution Info Callout -->
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-[11px] text-emerald-800 flex items-start space-x-2">
                <i class="fa-solid fa-circle-info text-emerald-600 mt-0.5"></i>
                <div>
                    Marking this ticket as completed sets its status to <strong class="font-bold">Resolved</strong>. You can claim your tour expenses anytime from the Actions menu.
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeCompleteModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="btnSubmitResolution" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center space-x-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-check-double"></i>
                    <span id="btnSubmitText">Confirm &amp; Mark Completed</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentCompleteTicketId = null;
let isDocUploading = false;

function openCompleteModal(ticketId, ticketNo, bankName) {
    currentCompleteTicketId = ticketId;
    
    // Set labels
    const badge = document.getElementById('modalTicketBadge');
    const bank = document.getElementById('modalBankName');
    if (badge) badge.innerText = '#' + ticketNo;
    if (bank) bank.innerText = '• ' + (bankName || 'Bank');

    // Set Form Action
    const form = document.getElementById('completeTicketForm');
    if (form) {
        form.action = '/tickets/' + ticketId + '/resolve';
    }

    // Reset upload elements
    resetDocUpload();

    // Show Modal
    const modal = document.getElementById('engineerCompleteModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }
}

function closeCompleteModal() {
    const modal = document.getElementById('engineerCompleteModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
    currentCompleteTicketId = null;
    isDocUploading = false;
}

function triggerDocFileInput() {
    if (isDocUploading) return;
    const fileInput = document.getElementById('supporting_doc_input');
    if (fileInput) fileInput.click();
}

function resetDocUpload(e) {
    if (e) e.stopPropagation();
    
    const fileInput = document.getElementById('supporting_doc_input');
    if (fileInput) fileInput.value = '';

    const pathInput = document.getElementById('uploaded_document_path');
    const nameInput = document.getElementById('uploaded_document_name');
    if (pathInput) pathInput.value = '';
    if (nameInput) nameInput.value = '';

    const prompt = document.getElementById('dropzone_prompt');
    const progress = document.getElementById('upload_progress_wrapper');
    const success = document.getElementById('upload_success_wrapper');
    const error = document.getElementById('upload_error_wrapper');
    const imgBox = document.getElementById('uploaded_img_preview_box');
    const pdfBox = document.getElementById('uploaded_pdf_preview_box');

    if (prompt) prompt.classList.remove('hidden');
    if (progress) progress.classList.add('hidden');
    if (success) success.classList.add('hidden');
    if (error) error.classList.add('hidden');
    if (imgBox) imgBox.classList.add('hidden');
    if (pdfBox) pdfBox.classList.add('hidden');

    const btnSubmit = document.getElementById('btnSubmitResolution');
    if (btnSubmit) btnSubmit.disabled = false;
    isDocUploading = false;
}

function handleDocFileSelection(event) {
    event.stopPropagation();
    const file = event.target.files[0];
    if (!file) return;

    if (!currentCompleteTicketId) {
        alert('Ticket reference missing. Please re-open the modal.');
        return;
    }

    // UI elements
    const prompt = document.getElementById('dropzone_prompt');
    const progress = document.getElementById('upload_progress_wrapper');
    const success = document.getElementById('upload_success_wrapper');
    const error = document.getElementById('upload_error_wrapper');
    const progressBar = document.getElementById('upload_progress_bar');
    const percentText = document.getElementById('upload_percentage_text');
    const btnSubmit = document.getElementById('btnSubmitResolution');

    // Switch views
    prompt.classList.add('hidden');
    success.classList.add('hidden');
    error.classList.add('hidden');
    progress.classList.remove('hidden');
    progressBar.style.width = '0%';
    percentText.innerText = '0%';
    if (btnSubmit) btnSubmit.disabled = true;
    // Instant local preview for image files
    const isImageFile = file.type ? file.type.startsWith('image/') : /\.(jpe?g|png|webp|gif)$/i.test(file.name);
    let localPreviewUrl = null;
    if (isImageFile) {
        try {
            localPreviewUrl = URL.createObjectURL(file);
        } catch(e) {}
    }

    // Build FormData
    const formData = new FormData();
    formData.append('document', file);

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content 
              || document.querySelector('input[name="_token"]')?.value;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/tickets/' + currentCompleteTicketId + '/upload-document', true);
    if (csrf) {
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
    }
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    // Upload Progress event listener
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + '%';
            percentText.innerText = percent + '%';
        }
    });

    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            isDocUploading = false;
            if (btnSubmit) btnSubmit.disabled = false;

            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        // Store uploaded path & original name
                        document.getElementById('uploaded_document_path').value = data.path;
                        document.getElementById('uploaded_document_name').value = data.filename;

                        // Display Success
                        progress.classList.add('hidden');
                        success.classList.remove('hidden');

                        const fileInfo = document.getElementById('uploaded_file_info');
                        if (fileInfo) fileInfo.innerText = data.filename + ' (' + data.filesize + ')';

                        if (data.is_image || isImageFile) {
                            const imgBox = document.getElementById('uploaded_img_preview_box');
                            const imgEl = document.getElementById('uploaded_img_element');
                            imgEl.onerror = function() {
                                if (localPreviewUrl) imgEl.src = localPreviewUrl;
                            };
                            imgEl.src = data.url || localPreviewUrl;
                            imgBox.classList.remove('hidden');
                        } else {
                            const pdfBox = document.getElementById('uploaded_pdf_preview_box');
                            const pdfName = document.getElementById('uploaded_pdf_name');
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
            let errorMsg = 'Failed to upload document. Please try again.';
            try {
                const errData = JSON.parse(xhr.responseText);
                if (errData.message) errorMsg = errData.message;
            } catch(e) {}
            document.getElementById('upload_error_text').innerText = errorMsg;
        }
    };

    xhr.send(formData);
}

// Drag & drop support
document.addEventListener('DOMContentLoaded', function() {
    const dropzone = document.getElementById('dropzone_container');
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
            const input = document.getElementById('supporting_doc_input');
            input.files = files;
            handleDocFileSelection({ target: input, stopPropagation: () => {} });
        }
    }, false);
});
</script>
