@extends('layouts.app')

@section('title', 'Operations Webmail & Complaint Triage')

@section('content')
<div class="h-[calc(100vh-6.5rem)] flex flex-col bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden">
    
    <!-- Top Outlook Ribbon / Command Bar -->
    <div class="bg-slate-900 border-b border-slate-800 px-5 py-3 text-white flex flex-wrap items-center justify-between gap-3 shrink-0">
        <!-- Brand & Account Status -->
        <div class="flex items-center space-x-3">
            <div class="w-9 h-9 rounded-lg bg-sky-600 flex items-center justify-center text-white shadow">
                <i class="fa-solid fa-inbox text-lg"></i>
            </div>
            <div>
                <div class="flex items-center space-x-2">
                    <h1 class="text-base font-bold text-white tracking-wide">Operations Webmail & AI Triage</h1>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse mr-1.5"></span>
                        Live GoDaddy IMAP
                    </span>
                </div>
                <div class="text-xs text-slate-400 font-mono flex items-center gap-2">
                    <span>{{ $currentConfig['username'] ?: 'support@cmscompany.biz' }}</span>
                    <span>•</span>
                    <span>Host: {{ $currentConfig['host'] }}:{{ $currentConfig['port'] }}</span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center space-x-2.5">
            <!-- Compose New Email Button (Clean: strictly To, Subject, Message - No CC/BCC) -->
            <button type="button" onclick="openComposeModal()" class="inline-flex items-center space-x-2 bg-sky-600 hover:bg-sky-500 text-white px-3.5 py-2 rounded-lg font-semibold text-xs shadow-md transition active:scale-95">
                <i class="fa-solid fa-pen-to-square"></i>
                <span>New Message</span>
            </button>

            <!-- Sync Mailbox Form with Limit Selection -->
            <form action="{{ route('settings.email.sync') }}" method="POST" class="inline-flex items-center">
                @csrf
                <div class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-800 p-0.5 shadow-sm">
                    <button type="submit" class="inline-flex items-center space-x-1.5 text-slate-200 hover:text-white px-2.5 py-1.5 font-medium text-xs transition active:scale-95" title="Fetch recent emails from IMAP server">
                        <i class="fa-solid fa-rotate text-emerald-400"></i>
                        <span>Sync</span>
                    </button>
                    <select name="limit" onchange="this.form.submit()" class="bg-slate-900 text-slate-300 text-[11px] rounded border-0 py-1 px-1.5 focus:ring-1 focus:ring-sky-500 cursor-pointer" title="Number of recent emails to scan">
                        <option value="50">50 msgs</option>
                        <option value="150" selected>150 msgs</option>
                        <option value="300">300 msgs</option>
                        <option value="500">500 msgs</option>
                    </select>
                </div>
            </form>

            <!-- Empty Mailbox -->
            <form action="{{ route('settings.email.empty') }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to empty the mailbox records?\n\nThis will clear all stored email logs in the database. Tickets already created from emails will NOT be deleted.\n\nProceed?');">
                @csrf
                <button type="submit" class="inline-flex items-center space-x-1.5 bg-slate-800 hover:bg-rose-900/60 text-slate-300 hover:text-rose-200 border border-slate-700 hover:border-rose-700/60 px-3 py-2 rounded-lg font-medium text-xs transition active:scale-95" title="Clear all stored emails in this mailbox">
                    <i class="fa-regular fa-trash-can text-rose-400"></i>
                    <span class="hidden sm:inline">Empty Mailbox</span>
                </button>
            </form>

            <!-- Auto-Triage All with AI -->
            <form action="{{ route('settings.email.auto-triage') }}" method="POST" class="inline" onsubmit="return confirm('Run Gemini AI auto-triage on all untriaged emails?\n\nReal complaint emails will automatically have unassigned tickets opened in the ERP. Spam/bounces will be safely skipped.\n\nProceed?');">
                @csrf
                <button type="submit" class="inline-flex items-center space-x-1.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white px-3.5 py-2 rounded-lg font-bold text-xs shadow transition active:scale-95" title="Analyze untriaged emails with Gemini AI and auto-create complaints">
                    <i class="fa-solid fa-wand-magic-sparkles text-amber-300"></i>
                    <span>Auto-Triage All with AI</span>
                </button>
            </form>

            <!-- Settings / Credentials Modal Trigger -->
            <button type="button" onclick="openSettingsModal()" class="inline-flex items-center space-x-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 px-3 py-2 rounded-lg text-xs font-medium transition" title="GoDaddy & Gemini Settings">
                <i class="fa-solid fa-sliders text-sky-400"></i>
                <span class="hidden sm:inline">Settings</span>
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
    <div class="bg-emerald-50 border-b border-emerald-200 px-4 py-2.5 text-xs text-emerald-800 flex items-center justify-between shrink-0">
        <div class="flex items-center space-x-2">
            <i class="fa-solid fa-circle-check text-emerald-600"></i>
            <span class="font-medium">{{ session('success') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900"><i class="fa-solid fa-xmark"></i></button>
    </div>
    @endif

    @if(session('warning'))
    <div class="bg-amber-50 border-b border-amber-200 px-4 py-2.5 text-xs text-amber-800 flex items-center justify-between shrink-0">
        <div class="flex items-center space-x-2">
            <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
            <span class="font-medium">{{ session('warning') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-amber-600 hover:text-amber-900"><i class="fa-solid fa-xmark"></i></button>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-rose-50 border-b border-rose-200 px-4 py-2.5 text-xs text-rose-800 flex items-center justify-between shrink-0">
        <div class="flex items-center space-x-2">
            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
            <span class="font-medium">{{ session('error') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900"><i class="fa-solid fa-xmark"></i></button>
    </div>
    @endif

    <!-- Main Outlook 3-Pane Body -->
    <div class="flex-1 flex overflow-hidden">
        
        <!-- PANE 1: Left Folders Sidebar (~230px) -->
        <div class="w-56 bg-slate-50 border-r border-slate-200 flex flex-col shrink-0 p-3 select-none">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 py-2">
                Mail Folders
            </div>

            <nav class="space-y-1">
                <!-- Inbox (Inbound) -->
                <a href="{{ route('settings.email', ['folder' => 'inbox']) }}" class="flex items-center justify-between px-3 py-2 text-xs font-medium rounded-lg transition {{ $folder === 'inbox' ? 'bg-sky-100 text-sky-900 font-semibold shadow-sm' : 'text-slate-700 hover:bg-slate-200/60' }}">
                    <span class="flex items-center space-x-2">
                        <i class="fa-solid fa-inbox {{ $folder === 'inbox' ? 'text-sky-600' : 'text-slate-500' }}"></i>
                        <span>Inbox</span>
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] {{ $folder === 'inbox' ? 'bg-sky-200 text-sky-800 font-bold' : 'bg-slate-200 text-slate-600' }}">
                        {{ $counts['inbox'] }}
                    </span>
                </a>

                <!-- Sent Items (Outbound) -->
                <a href="{{ route('settings.email', ['folder' => 'sent']) }}" class="flex items-center justify-between px-3 py-2 text-xs font-medium rounded-lg transition {{ $folder === 'sent' ? 'bg-sky-100 text-sky-900 font-semibold shadow-sm' : 'text-slate-700 hover:bg-slate-200/60' }}">
                    <span class="flex items-center space-x-2">
                        <i class="fa-solid fa-paper-plane {{ $folder === 'sent' ? 'text-sky-600' : 'text-slate-500' }}"></i>
                        <span>Sent Items</span>
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] {{ $folder === 'sent' ? 'bg-sky-200 text-sky-800 font-bold' : 'bg-slate-200 text-slate-600' }}">
                        {{ $counts['sent'] }}
                    </span>
                </a>

                <!-- Complaints Registered -->
                <a href="{{ route('settings.email', ['folder' => 'complaints']) }}" class="flex items-center justify-between px-3 py-2 text-xs font-medium rounded-lg transition {{ $folder === 'complaints' ? 'bg-emerald-100 text-emerald-900 font-semibold shadow-sm' : 'text-slate-700 hover:bg-slate-200/60' }}">
                    <span class="flex items-center space-x-2">
                        <i class="fa-solid fa-ticket-simple {{ $folder === 'complaints' ? 'text-emerald-600' : 'text-emerald-500' }}"></i>
                        <span>Complaints</span>
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] {{ $folder === 'complaints' ? 'bg-emerald-200 text-emerald-800 font-bold' : 'bg-emerald-100 text-emerald-700 font-semibold' }}">
                        {{ $counts['complaints'] }}
                    </span>
                </a>

                <!-- Unassigned / Pending Triage -->
                <a href="{{ route('settings.email', ['folder' => 'unassigned']) }}" class="flex items-center justify-between px-3 py-2 text-xs font-medium rounded-lg transition {{ $folder === 'unassigned' ? 'bg-amber-100 text-amber-900 font-semibold shadow-sm' : 'text-slate-700 hover:bg-slate-200/60' }}">
                    <span class="flex items-center space-x-2">
                        <i class="fa-solid fa-hourglass-half {{ $folder === 'unassigned' ? 'text-amber-600' : 'text-amber-500' }}"></i>
                        <span>Needs Triage</span>
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] {{ $folder === 'unassigned' ? 'bg-amber-200 text-amber-800 font-bold' : 'bg-amber-100 text-amber-800 font-semibold' }}">
                        {{ $counts['unassigned'] }}
                    </span>
                </a>

                <!-- Unread -->
                <a href="{{ route('settings.email', ['folder' => 'unread']) }}" class="flex items-center justify-between px-3 py-2 text-xs font-medium rounded-lg transition {{ $folder === 'unread' ? 'bg-indigo-100 text-indigo-900 font-semibold shadow-sm' : 'text-slate-700 hover:bg-slate-200/60' }}">
                    <span class="flex items-center space-x-2">
                        <i class="fa-regular fa-envelope {{ $folder === 'unread' ? 'text-indigo-600' : 'text-indigo-500' }}"></i>
                        <span>Unread</span>
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] {{ $folder === 'unread' ? 'bg-indigo-200 text-indigo-800 font-bold' : 'bg-slate-200 text-slate-600' }}">
                        {{ $counts['unread'] }}
                    </span>
                </a>

                <!-- All Messages -->
                <a href="{{ route('settings.email', ['folder' => 'all']) }}" class="flex items-center justify-between px-3 py-2 text-xs font-medium rounded-lg transition {{ $folder === 'all' ? 'bg-slate-200 text-slate-900 font-semibold' : 'text-slate-600 hover:bg-slate-200/50' }}">
                    <span class="flex items-center space-x-2">
                        <i class="fa-solid fa-layer-group text-slate-400"></i>
                        <span>All Messages</span>
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 text-slate-600">
                        {{ $counts['all'] }}
                    </span>
                </a>
            </nav>

            <hr class="my-4 border-slate-200">

            <!-- Quick Links -->
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 py-1">
                Quick Shortcuts
            </div>
            <div class="space-y-1 text-xs">
                <a href="{{ route('tickets.open') }}" class="flex items-center space-x-2 px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-200/50">
                    <i class="fa-solid fa-clock text-amber-500 w-4"></i>
                    <span>Open Tickets SLA</span>
                </a>
                <a href="{{ route('tickets.simulate-ingest') }}" class="flex items-center space-x-2 px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-200/50">
                    <i class="fa-solid fa-flask text-sky-500 w-4"></i>
                    <span>AI Simulator</span>
                </a>
                <a href="{{ route('tickets.create') }}" class="flex items-center space-x-2 px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-200/50">
                    <i class="fa-solid fa-square-plus text-emerald-500 w-4"></i>
                    <span>Register Manual Ticket</span>
                </a>
            </div>

            <!-- Footer Mailbox Connection Info -->
            <div class="mt-auto p-3 bg-white rounded-lg border border-slate-200 text-[11px] text-slate-500 space-y-1">
                <div class="font-semibold text-slate-700 flex items-center justify-between">
                    <span>IMAP Engine</span>
                    <span class="text-emerald-600 font-bold">Online</span>
                </div>
                <div>Connected to GoDaddy</div>
                <div class="text-[10px] text-slate-400 font-mono truncate">{{ $currentConfig['username'] }}</div>
            </div>
        </div>

        <!-- PANE 2: Email List Pane (~380px) -->
        <div class="w-96 border-r border-slate-200 flex flex-col bg-white shrink-0">
            <!-- Search & Filter Bar -->
            <div class="p-3 border-b border-slate-200 bg-slate-50/50 space-y-2">
                <form action="{{ route('settings.email') }}" method="GET" class="relative">
                    <input type="hidden" name="folder" value="{{ $folder }}">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-xs text-slate-400"></i>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search sender, recipient, subject, ticket#..." class="w-full bg-white border border-slate-300 rounded-lg pl-8 pr-8 py-1.5 text-xs focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition">
                    @if($search)
                    <a href="{{ route('settings.email', ['folder' => $folder]) }}" class="absolute right-2.5 top-2 text-xs text-slate-400 hover:text-slate-600" title="Clear search">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                    @endif
                </form>

                <div class="flex items-center justify-between text-[11px] text-slate-500 px-1">
                    <span>Showing {{ $emails->count() }} of {{ $counts[$folder] ?? $counts['all'] }} stored</span>
                    <span class="font-medium capitalize text-slate-700">{{ str_replace('_', ' ', $folder) }} Folder</span>
                </div>

                @if($search && $emails->isEmpty())
                <div class="p-2 bg-amber-50 border border-amber-200 rounded-lg text-[11px] text-amber-800 space-y-1">
                    <p class="font-semibold"><i class="fa-solid fa-cloud-arrow-down mr-1"></i> Not found in local database?</p>
                    <form action="{{ route('settings.email.sync') }}" method="POST" class="flex items-center gap-1.5 pt-0.5">
                        @csrf
                        <input type="hidden" name="server_search" value="{{ $search }}">
                        <input type="hidden" name="limit" value="100">
                        <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-bold px-2 py-1 rounded text-[10px] shadow-sm transition">
                            Search Remote Mail Server for "{{ Str::limit($search, 15) }}"
                        </button>
                    </form>
                </div>
                @endif

                @if($counts['unassigned'] > 0)
                <div class="pt-1">
                    <form action="{{ route('settings.email.auto-triage') }}" method="POST" onsubmit="return confirm('Run Gemini AI auto-triage on all {{ $counts['unassigned'] }} untriaged emails?\n\nReal complaint emails will automatically have unassigned tickets opened in the ERP. Spam/bounces will be safely skipped.\n\nProceed?');">
                        @csrf
                        <button type="submit" class="w-full flex items-center justify-center space-x-1.5 py-1.5 px-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm transition active:scale-95">
                            <i class="fa-solid fa-wand-magic-sparkles text-amber-300"></i>
                            <span>Auto-Triage {{ $counts['unassigned'] }} Emails with AI</span>
                        </button>
                    </form>
                </div>
                @endif
            </div>

            <!-- Scrollable Message List -->
            <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
                @forelse($emails as $email)
                @php
                    $isSelected = ($selectedEmail && $selectedEmail->id === $email->id);
                @endphp
                <a href="{{ route('settings.email', array_merge(request()->query(), ['selected' => $email->id])) }}" class="block p-3.5 transition {{ $isSelected ? 'bg-sky-50 border-l-4 border-sky-600 shadow-sm' : 'hover:bg-slate-50' }}">
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <div class="font-semibold text-xs truncate {{ !$email->is_read ? 'text-slate-950 font-bold' : 'text-slate-800' }}">
                            @if($email->is_sent)
                                <span class="text-sky-700"><i class="fa-solid fa-paper-plane mr-1 text-[10px]"></i> To: {{ $email->to_email }}</span>
                            @else
                                {{ $email->from_name ?: $email->from_email }}
                            @endif
                        </div>
                        <div class="text-[10px] text-slate-400 whitespace-nowrap">
                            {{ $email->email_date ? $email->email_date->format('d M, H:i') : '' }}
                        </div>
                    </div>

                    <div class="text-xs truncate mb-1 {{ !$email->is_read ? 'font-bold text-slate-900' : 'font-medium text-slate-700' }}">
                        {{ $email->subject ?: '(No Subject)' }}
                    </div>

                    <div class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed mb-2">
                        {{ Str::limit($email->body_text, 110) }}
                    </div>

                    <!-- Tags & Badges -->
                    <div class="flex items-center justify-between text-[10px]">
                        @if($email->is_sent)
                            <span class="inline-flex items-center px-2 py-0.5 rounded font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                <i class="fa-solid fa-paper-plane mr-1 text-sky-600"></i>
                                Sent Item
                            </span>
                        @elseif($email->isComplaint())
                            <span class="inline-flex items-center px-2 py-0.5 rounded font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                <i class="fa-solid fa-circle-check mr-1 text-emerald-600"></i>
                                Ticket #{{ $email->ticket->ticket_no }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded font-medium bg-amber-50 text-amber-800 border border-amber-200">
                                <i class="fa-regular fa-clock mr-1 text-amber-600"></i>
                                Needs Triage
                            </span>
                        @endif

                        @if(!empty($email->cc_emails))
                            <span class="text-[10px] text-slate-400 font-mono" title="CC: {{ $email->cc_emails }}">
                                <i class="fa-solid fa-copy mr-0.5 text-slate-400"></i> CC saved
                            </span>
                        @else
                            <span class="text-slate-400 text-[10px]">
                                {{ $email->is_sent ? $email->from_email : $email->from_email }}
                            </span>
                        @endif
                    </div>
                </a>
                @empty
                <div class="p-8 text-center text-slate-400 space-y-3">
                    <i class="fa-regular fa-folder-open text-4xl text-slate-300"></i>
                    <p class="text-xs">No emails found in this folder.</p>
                    <form action="{{ route('settings.email.sync') }}" method="POST">
                        @csrf
                        <button type="submit" class="text-xs font-semibold text-sky-600 hover:text-sky-800 underline">
                            Click here to sync from GoDaddy
                        </button>
                    </form>
                </div>
                @endforelse
            </div>

            <!-- Pagination link if needed -->
            @if($emails->hasPages())
            <div class="p-2 border-t border-slate-200 bg-slate-50 text-xs">
                {{ $emails->links() }}
            </div>
            @endif
        </div>

        <!-- PANE 3: Reading Pane & Action Command Center (Flex-1) -->
        <div class="flex-1 flex flex-col bg-slate-50/30 overflow-hidden">
            @if($selectedEmail)
            <!-- Email Header / Action Ribbon -->
            <div class="bg-white border-b border-slate-200 p-4 shrink-0 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                    <!-- Complaint Status & Action Button -->
                    <div class="flex items-center flex-wrap gap-2.5">
                        @if($selectedEmail->is_sent)
                            <!-- Outbound Sent Email -->
                            <div class="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-800 text-white text-xs font-bold shadow-sm">
                                <i class="fa-solid fa-paper-plane text-sky-400 mr-1.5"></i>
                                Outbound Sent Message
                            </div>
                            @if($selectedEmail->ticket_id)
                                <a href="{{ route('tickets.show', $selectedEmail->ticket_id) }}" class="inline-flex items-center space-x-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition active:scale-95">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    <span>Linked Ticket #{{ $selectedEmail->ticket->ticket_no }}</span>
                                </a>
                            @endif
                        @elseif($selectedEmail->isComplaint())
                            <!-- ALREADY MARKED AS COMPLAINT: System strictly blocks duplicate marking! -->
                            <div class="inline-flex items-center px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-900 border border-emerald-300 text-xs font-bold">
                                <i class="fa-solid fa-check-double text-emerald-600 mr-1.5 text-sm"></i>
                                Registered as Complaint Ticket #{{ $selectedEmail->ticket->ticket_no }}
                            </div>

                            <a href="{{ route('tickets.show', $selectedEmail->ticket_id) }}" class="inline-flex items-center space-x-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition active:scale-95">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                <span>View Ticket in ERP</span>
                            </a>

                            @if(!$selectedEmail->ticket->is_human_verified)
                                <!-- Option to Delete / Unmark Wrong Complaint (Operations authority for unfinalized complaints) -->
                                <form action="{{ route('tickets.destroy', $selectedEmail->ticket_id) }}" method="POST" class="inline" onsubmit="return confirm('DELETE WRONGLY IDENTIFIED COMPLAINT?\n\nThis will delete Ticket #{{ $selectedEmail->ticket->ticket_no }} (not yet human finalized) and reset this email back to Needs Triage.\n\nProceed?');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="source" value="webmail">
                                    <button type="submit" class="inline-flex items-center space-x-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 text-xs font-bold px-3 py-1.5 rounded-lg shadow-sm transition active:scale-95" title="Wrongly marked as complaint? Delete unfinalized ticket and reset email">
                                        <i class="fa-solid fa-trash-can text-rose-600"></i>
                                        <span>Delete Wrong Complaint</span>
                                    </button>
                                </form>
                            @else
                                <span class="inline-flex items-center space-x-1 px-2.5 py-1.5 rounded-lg text-slate-400 bg-slate-100 border border-slate-200 text-xs font-medium cursor-not-allowed" title="Human finalized complaints are protected and cannot be deleted.">
                                    <i class="fa-solid fa-lock text-slate-400"></i>
                                    <span>Finalized (Delete Locked)</span>
                                </span>
                            @endif
                        @else
                            <!-- NOT YET MARKED: Primary "Mark as Complaint" Action -->
                            <form action="{{ route('settings.email.convert', $selectedEmail->id) }}" method="POST" onsubmit="return confirm('Extract hardware and branch details via Gemini AI and create an unassigned complaint ticket? (CC addresses will be preserved)');">
                                @csrf
                                <button type="submit" class="inline-flex items-center space-x-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white text-xs font-bold px-4 py-2 rounded-lg shadow-md hover:shadow-lg transition active:scale-95">
                                    <i class="fa-solid fa-wand-magic-sparkles text-amber-300"></i>
                                    <span>Mark as Complaint (AI Extract & Open Ticket)</span>
                                </button>
                            </form>
                        @endif
                    </div>

                    <!-- Email Actions (Reply / Toggle HTML) -->
                    <div class="flex items-center space-x-2">
                        @if(!$selectedEmail->is_sent)
                        <button type="button" onclick="toggleReplyBox()" class="inline-flex items-center space-x-1.5 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition active:scale-95">
                            <i class="fa-solid fa-reply text-sky-600"></i>
                            <span>Reply to Bank</span>
                        </button>
                        @endif
                        <button type="button" onclick="toggleRawView()" class="inline-flex items-center space-x-1 text-slate-500 hover:text-slate-800 text-xs px-2 py-1.5 rounded border border-transparent hover:border-slate-200">
                            <i class="fa-solid fa-code"></i>
                            <span>Toggle HTML/Text</span>
                        </button>
                    </div>
                </div>

                <!-- Subject & Sender / Recipient / CC Details -->
                <div class="pt-3">
                    <h2 class="text-base font-bold text-slate-900 mb-2 leading-snug">
                        {{ $selectedEmail->subject ?: '(No Subject)' }}
                    </h2>

                    <div class="flex items-start justify-between text-xs text-slate-600">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-full bg-slate-800 text-white font-bold flex items-center justify-center text-xs uppercase shadow">
                                {{ strtoupper(substr($selectedEmail->is_sent ? $selectedEmail->to_email : ($selectedEmail->from_name ?: $selectedEmail->from_email), 0, 1)) }}
                            </div>
                            <div class="space-y-0.5">
                                @if($selectedEmail->is_sent)
                                    <div class="font-bold text-slate-900">
                                        To: <span class="text-sky-700 font-mono">{{ $selectedEmail->to_email }}</span>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        From: <span class="font-mono text-slate-700">{{ $selectedEmail->from_email }}</span>
                                    </div>
                                @else
                                    <div class="font-bold text-slate-900">
                                        From: {{ $selectedEmail->from_name ?: $selectedEmail->from_email }}
                                        <span class="font-normal text-slate-500 font-mono text-[11px]">&lt;{{ $selectedEmail->from_email }}&gt;</span>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        To: <span class="font-mono text-slate-700">{{ $selectedEmail->to_email }}</span>
                                    </div>
                                @endif

                                <!-- Preserved CC Addresses -->
                                @if(!empty($selectedEmail->cc_emails))
                                    <div class="text-[11px] text-slate-600 flex items-center gap-1 pt-0.5">
                                        <span class="font-bold text-slate-700">Cc:</span>
                                        <span class="bg-slate-100 text-slate-800 px-2 py-0.5 rounded font-mono border border-slate-200 text-[10px] break-all">
                                            {{ $selectedEmail->cc_emails }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="text-right">
                            <div class="font-medium text-slate-800">
                                {{ $selectedEmail->email_date ? $selectedEmail->email_date->format('D, d M Y, h:i A') : 'N/A' }}
                            </div>
                            <div class="text-[11px] text-slate-400">
                                {{ $selectedEmail->email_date ? $selectedEmail->email_date->diffForHumans() : '' }}
                            </div>
                        </div>
                    </div>

                    @if($selectedEmail->message_id)
                    <div class="mt-2 text-[10px] text-slate-400 font-mono truncate">
                        RFC Message-ID: {{ $selectedEmail->message_id }}
                    </div>
                    @endif
                </div>
            </div>

            <!-- In-Line Reply Panel (Hidden by default, toggled via Reply button) -->
            @if(!$selectedEmail->is_sent)
            <div id="replyPanel" class="hidden bg-sky-50/70 border-b border-sky-200 p-4 shrink-0 transition-all">
                <form action="{{ route('settings.email.reply', $selectedEmail->id) }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-bold text-sky-900 flex items-center space-x-2">
                            <i class="fa-solid fa-reply text-sky-600"></i>
                            <span>Reply to {{ $selectedEmail->from_email }}</span>
                            <span class="text-[10px] font-normal text-sky-700 font-mono">(RFC In-Reply-To preserved)</span>
                        </div>
                        <button type="button" onclick="toggleReplyBox()" class="text-slate-400 hover:text-slate-600 text-xs">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div>
                        <textarea name="reply_body" rows="4" required placeholder="Type your response to the bank branch here..." class="w-full bg-white border border-sky-300 rounded-lg p-3 text-xs focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none leading-relaxed"></textarea>
                    </div>

                    <!-- Quick Reply Templates -->
                    <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                        <span class="text-slate-500 font-medium">Quick Snippets:</span>
                        <button type="button" onclick="insertReplySnippet('Complaint has been logged into our Central ERP. A field service engineer will be dispatched within defined SLA TAT.')" class="bg-white hover:bg-sky-100 text-sky-800 border border-sky-200 px-2 py-0.5 rounded text-[10px]">
                            + Acknowledged & Logged
                        </button>
                        <button type="button" onclick="insertReplySnippet('Kindly share the machine model and serial number so our technician can carry the required spare parts.')" class="bg-white hover:bg-sky-100 text-sky-800 border border-sky-200 px-2 py-0.5 rounded text-[10px]">
                            + Request Serial Number
                        </button>
                        <button type="button" onclick="insertReplySnippet('Please ensure security gate clearance is arranged for our field technician on arrival.')" class="bg-white hover:bg-sky-100 text-sky-800 border border-sky-200 px-2 py-0.5 rounded text-[10px]">
                            + Gate Entry Facilitation
                        </button>
                    </div>

                    <div class="flex items-center justify-end space-x-2 pt-1">
                        <button type="button" onclick="toggleReplyBox()" class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-slate-700 text-xs hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center space-x-2 bg-sky-600 hover:bg-sky-500 text-white font-semibold text-xs px-4 py-1.5 rounded-lg shadow-sm transition active:scale-95">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>Send Reply via GoDaddy SMTP</span>
                        </button>
                    </div>
                </form>
            </div>
            @endif

            <!-- Email Body Content Viewport -->
            <div class="flex-1 p-6 overflow-y-auto bg-white">
                <!-- HTML View Container -->
                <div id="htmlView" class="prose prose-sm max-none text-slate-800 leading-relaxed font-sans">
                    @if(!empty($selectedEmail->body_html))
                        {!! $selectedEmail->body_html !!}
                    @else
                        <div class="whitespace-pre-wrap font-sans text-xs text-slate-800 leading-relaxed">
                            {{ $selectedEmail->body_text }}
                        </div>
                    @endif
                </div>

                <!-- Plain Text View Container (Toggled via button) -->
                <div id="plainView" class="hidden font-mono text-xs bg-slate-50 p-4 rounded-lg border border-slate-200 text-slate-800 whitespace-pre-wrap leading-relaxed">
{{ $selectedEmail->body_text }}
                </div>
            </div>
            @else
            <!-- Empty State when no email is selected -->
            <div class="flex-1 flex flex-col items-center justify-center text-center p-8 space-y-4">
                <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                    <i class="fa-regular fa-envelope-open text-3xl"></i>
                </div>
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-slate-700">No Email Selected</h3>
                    <p class="text-xs text-slate-500 max-w-sm">
                        Select an email from the left list to review complaint details, extract hardware fields via Gemini AI, or reply directly.
                    </p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- COMPOSE NEW EMAIL MODAL (Clean: strictly To, Subject, Message - NO CC or BCC options) -->
<div id="composeModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl border border-slate-200 w-full max-w-2xl overflow-hidden animate-fadeIn">
        <div class="bg-slate-900 px-5 py-3.5 text-white flex items-center justify-between">
            <h3 class="text-sm font-bold flex items-center space-x-2">
                <i class="fa-solid fa-pen-to-square text-sky-400"></i>
                <span>Compose New Message (GoDaddy SMTP)</span>
            </h3>
            <button type="button" onclick="closeComposeModal()" class="text-slate-400 hover:text-white text-sm">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="{{ route('settings.email.compose') }}" method="POST" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Recipient Email (To) *</label>
                <input type="email" name="to_email" required placeholder="bank.officer@ubl.com.pk" class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Subject *</label>
                <input type="text" name="subject" required placeholder="Service Call Update - CMS Central Desk" class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Message Body *</label>
                <textarea name="body" rows="6" required placeholder="Type your message here..." class="w-full bg-white border border-slate-300 rounded-lg p-3 text-xs focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none leading-relaxed"></textarea>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                <span class="text-[11px] text-slate-500 flex items-center gap-1">
                    <i class="fa-solid fa-shield-halved text-emerald-500"></i>
                    Sent from: <code class="text-sky-700 font-bold">{{ $currentConfig['username'] ?: 'support@cmscompany.biz' }}</code>
                </span>
                <div class="flex space-x-2">
                    <button type="button" onclick="closeComposeModal()" class="px-4 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 text-xs font-medium hover:bg-slate-50">
                        Discard
                    </button>
                    <button type="submit" class="inline-flex items-center space-x-2 bg-sky-600 hover:bg-sky-500 text-white font-semibold text-xs px-5 py-2 rounded-lg shadow-md transition active:scale-95">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Send Message</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- SETTINGS & CREDENTIALS MODAL -->
<div id="settingsModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl border border-slate-200 w-full max-w-xl overflow-hidden animate-fadeIn">
        <div class="bg-slate-900 px-5 py-3.5 text-white flex items-center justify-between">
            <h3 class="text-sm font-bold flex items-center space-x-2">
                <i class="fa-solid fa-sliders text-sky-400"></i>
                <span>Mailbox & Gemini AI Settings</span>
            </h3>
            <button type="button" onclick="closeSettingsModal()" class="text-slate-400 hover:text-white text-sm">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="{{ route('settings.email.save') }}" method="POST" class="p-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">IMAP Host</label>
                    <input type="text" name="imap_host" value="{{ $currentConfig['host'] }}" required class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Port</label>
                    <input type="number" name="imap_port" value="{{ $currentConfig['port'] }}" required class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 text-xs">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Encryption</label>
                    <select name="imap_encryption" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 text-xs">
                        <option value="ssl" {{ $currentConfig['encryption'] === 'ssl' ? 'selected' : '' }}>SSL (Recommended)</option>
                        <option value="tls" {{ $currentConfig['encryption'] === 'tls' ? 'selected' : '' }}>TLS</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mailbox Username</label>
                    <input type="email" name="imap_username" value="{{ $currentConfig['username'] }}" required class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 text-xs">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">IMAP Password</label>
                <input type="password" name="imap_password" placeholder="{{ $currentConfig['has_password'] ? '•••••••••••• (Leave blank to keep existing)' : 'Enter password' }}" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Google Gemini API Key</label>
                <input type="password" name="gemini_api_key" value="{{ $currentConfig['gemini_key'] }}" placeholder="AIzaSy..." class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-mono">
            </div>

            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeSettingsModal()" class="px-4 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 text-xs font-medium hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white font-semibold text-xs px-5 py-2 rounded-lg shadow-sm transition">
                    Save Configuration
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleReplyBox() {
        const panel = document.getElementById('replyPanel');
        if (panel) {
            panel.classList.toggle('hidden');
        }
    }

    function toggleRawView() {
        const htmlView = document.getElementById('htmlView');
        const plainView = document.getElementById('plainView');
        if (htmlView && plainView) {
            htmlView.classList.toggle('hidden');
            plainView.classList.toggle('hidden');
        }
    }

    function insertReplySnippet(text) {
        const textarea = document.querySelector('textarea[name="reply_body"]');
        if (textarea) {
            textarea.value = textarea.value ? (textarea.value + "\n\n" + text) : text;
            textarea.focus();
        }
    }

    function openComposeModal() {
        document.getElementById('composeModal').classList.remove('hidden');
    }

    function closeComposeModal() {
        document.getElementById('composeModal').classList.add('hidden');
    }

    function openSettingsModal() {
        document.getElementById('settingsModal').classList.remove('hidden');
    }

    function closeSettingsModal() {
        document.getElementById('settingsModal').classList.add('hidden');
    }
</script>
@endsection
