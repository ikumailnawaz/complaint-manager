# CMS Bank Complaint & Operations ERP — Pre-Deployment Testing & QA Validation Guide

---

## 1. Executive Summary & Purpose

This document provides a pre-flight quality assurance (QA) validation protocol and deployment checklist for the **CMS Bank Complaint & Operations ERP** (developed for CMS Company Pakistan).

Before putting this system into live production for banking clients (HBL, UBL, MCB, Allied Bank, Bank Alfalah, Askari, BOP, Meezan, SCB, etc.), QA engineers and system administrators must execute and sign off on each test phase described in this document.

---

## 2. System Architecture & Prerequisites Checklist

### 2.1 Server Environment Requirements
| Component | Minimum Specification | Recommended Production | Validation Command / Check |
| :--- | :--- | :--- | :--- |
| **Operating System** | Windows Server 2019+ or Ubuntu 22.04 LTS | Windows Server 2022 / Debian 12 | `systeminfo` / `uname -a` |
| **PHP Version** | PHP 8.2.0 or higher | PHP 8.2.18+ (CLI & Web) | `php -v` |
| **PHP Extensions** | `pdo_sqlite`, `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd`, `zip`, `xml`, `imap` | All verified active in `php.ini` | `php -m` |
| **Database** | SQLite 3.35+ or MySQL 8.0+ | MySQL 8.0 / Percona 8.0 | Database connection test |
| **Web Server** | Apache 2.4+ (mod_rewrite enabled) or Nginx | Apache with `.htaccess` support | `httpd -v` / `nginx -v` |
| **Memory Limit** | 256M | 512M (`memory_limit = 512M`) | `php -i | grep memory_limit` |
| **File Upload Limit** | `upload_max_filesize = 100M` | `post_max_size = 120M` | Required for fault video uploads |

### 2.2 Storage Junction & Permissions Check
The application stores ticket documents, fuel receipts, and fault videos under `storage/app/public/`.
Run the following verification in PowerShell:
```powershell
# 1. Verify NTFS directory junction exists
Test-Path "public\storage\resolutions"
Test-Path "public\storage\part-requests\videos"
Test-Path "public\storage\vouchers"

# 2. If missing, recreate NTFS junction (no admin privileges required):
cmd /c mklink /J "public\storage" "storage\app\public"
```

---

## 3. Automated Regression Test Suite

The application includes a test suite covering SLA algorithms, role boundaries, multi-stage approvals, expense calculations, and file storage.

### 3.1 Running the Full Automated Suite
Open terminal in the project directory (`complaint-manager`):
```bash
php artisan test
```

### 3.2 Expected Test Suite Metrics
- **Total Test Cases**: 121 Passed
- **Total Assertions**: 755 Assertions
- **Failures / Errors**: 0 Failed
- **Execution Time**: ~160 to 180 seconds

### 3.3 Target Test Suites Breakdown
```bash
# 1. SLA Clock, Pauses & Weekend Calculations
php artisan test --filter=ApprovalSlaPauseTest

# 2. Advance Envelope Float Inventory
php artisan test --filter=EngineerAdvanceEnvelopeTest

# 3. Two-Stage Part Requisitions, Gate Pass & Dispatch
php artisan test --filter=Phase6PartsManagementTest

# 4. Bank Audit Trail & Supplier Fault Reports (CSV & PDF)
php artisan test --filter=Phase5DetailedReportsTest

# 5. Workshop Bench Repair & Transit
php artisan test --filter=WorkshopLifecycleTest

# 6. Audio Notification Chimes & Role User Management
php artisan test --filter=NotificationAndUserManagementTest
```

---

## 4. Role-Based Access Control (RBAC) Validation Matrix

Test each user role to confirm that unauthorized actions are blocked with HTTP `403 Forbidden` or appropriate redirects.

| Role | Test Username | Allowed Modules | Forbidden Modules (Must Block / Redirect) | Verified |
| :--- | :--- | :--- | :--- | :---: |
| **Super Admin** | `kumail` | Complete system access, Stage 2 Part Approval, User Management, Reports | None | [ ] |
| **Operation Manager** | `ronald` / `nazir` | Complaints, Dispatch, Approvals, Workshop, Expenses, PM Schedules, Reports | System User Management deletion | [ ] |
| **Office Staff** | `adnan` | Open Tickets, Stage 1 Part Verification, Stock Levels, GRN, Transfers, Envelopes, Machine Faults Report | Dashboard (`/dashboard` -> redirect), Complain Registry (`/tickets` -> redirect), Expenses (403), Engineers Directory (403), Bank Audit Reports (403) | [ ] |
| **Field Engineer** | `farhankhalid` | Complain Registry (Assigned Tickets only), My PM Tasks, Submit Parts, Submit Expenses | Open Tickets (403), Escalated Tickets (403), Workshop Hub (403), Parts Approval (403), User Management (403) | [ ] |

---

## 5. End-to-End Functional Test Scenarios

### Scenario 1: Bank Complaint Email Ingestion & Auto-Assignment
1. **Trigger**: Navigate to `/settings/email` and trigger Email Sync or send incoming test email.
2. **Verify**:
   - Incoming bank email creates a record in `inbox_emails`.
   - AI auto-triage classifies Bank, Branch, Model, Serial Number, and Fault.
   - Ticket is created with status `open`.
   - SLA Clock starts with deadline based on bank agreement (e.g. 4 Hours, 8 Hours, or 24 Hours).
3. **Assign Engineer**:
   - Login as Operations Manager (`nazir`).
   - Assign engineer `farhankhalid`.
   - Verify assignment confirmation email is prepared and sent to bank with RFC headers (`In-Reply-To`, `References`).

### Scenario 2: SLA Pause & Resume (Parts / Approvals Delay)
1. **Trigger**: Field Engineer visits assigned ticket at `/tickets/{id}`.
2. **Action**: Click "Request Approval / Pause SLA".
3. **Verify**:
   - Status changes to `awaiting_approval`.
   - SLA countdown clock pauses immediately.
   - An alert appears in the Super Admin "Pending Approvals" dashboard (`/approvals`).
4. **Approve**:
   - Super Admin clicks "Grant Approval".
   - SLA deadline shifts forward by the exact paused duration.
   - Status returns to `in_progress`.

### Scenario 3: Two-Stage Part Requisition & Dispatch
1. **Submission**:
   - Field Engineer visits `/parts/requests/create` for Ticket #11.
   - Selects machine model `Glory USF-51` (Verify model name has NO `ATM` suffix).
   - Selects part (e.g. *Feed Roller* x 2) and enters defective serial number.
   - Submits request -> Initial status is `pending_stock_check`.
2. **Stage 1 Verification (Office Staff)**:
   - Login as `adnan` (Office Staff).
   - Navigate to `/parts/requests/{id}`.
   - Verify button says: **"Stage 1: Stock Verification & Evidence (Office Staff)"**.
   - Optional: Attach diagnosis video (`.mp4` or `.webm`).
   - Click "Verify Stock & Forward for Approval".
   - Status changes to `pending_approval`.
3. **Stage 2 Approval (Super Admin)**:
   - Verify `adnan` (Office Staff) CANNOT approve (approval button hidden, POST blocked with 403).
   - Login as `kumail` (Super Admin).
   - Button says: **"Stage 2: Super Admin Final Approval & Allocation"**.
   - Review quantities, click "Approve Requisition".
   - Status changes to `approved`.
   - Click "View Gate Pass" -> Gate Pass PDF renders cleanly for security/storekeeper.
4. **Physical Dispatch (Office Staff)**:
   - Office Staff visits approved requisition.
   - Enters mandatory Courier Name (e.g. *TCS*) and Tracking Number (e.g. *7719283401*).
   - Selects source (Main Warehouse vs Advance Envelope).
   - Clicks "Confirm Dispatch & Deduct Stock".
   - Stock level in warehouse is decremented automatically.
5. **Reverse Core Return (Defective Part Tracking)**:
   - Defective faulty part arrives back at Head Office.
   - Office Staff marks "Verify Return of Defective Core".
   - Status completes as `closed_returned`.

### Scenario 4: Ticket Resolution & Supporting Proof Upload
1. **Resolution Upload**:
   - Field Engineer or Manager clicks "Mark Resolved" on Ticket.
   - Uploads signed Service Slip / Handover Photo (`.pdf` or `.jpg`).
   - Verifies file is saved to storage.
2. **Direct Browser Viewing**:
   - Click "View Proof" (`/tickets/{id}/document`).
   - File opens inline inside browser tab with HTTP 200 (no 404).
3. **Replacement & Re-Verification**:
   - Click "Replace Proof", choose new file, submit.
   - Confirm previous file is purged and replaced document displays immediately.
4. **Bank Resolution Email with Quoted Thread Chain**:
   - Click "Send Official Bank Resolution Email".
   - Add new CC email addresses (e.g. `audit@bank.com.pk`, `regional.manager@bank.com.pk`).
   - Dispatch email.
   - Check sent message: Confirm the email body includes the **"Previous Conversation History"** block containing the full chronological chain of prior messages, timestamps, and complaints.

### Scenario 5: Central Workshop Lifecycle
1. **Transit to Workshop**:
   - Field Engineer unable to repair complex sorting motherboard on site.
   - Marks ticket "Send to Workshop".
   - Machine transitions to `in_transit_to_workshop`.
2. **Bench Receipt**:
   - Workshop technician confirms receipt at Central Workshop (`/workshop`).
   - Machine status changes to `in_workshop`.
3. **Repair & Dispatch Back**:
   - Bench technician completes repair, records parts consumed.
   - Dispatches back to branch with courier tracking details.
   - Field Engineer re-installs on site and closes ticket.

### Scenario 6: Field Travel Expense Claim
1. **Submission**:
   - Field Engineer submits travel claim from Lahore to Gujranwala (`/expenses/create`).
   - System calculates AI suggested distance and standard PKR reimbursement.
   - Engineer attaches toll receipt image (`.png` / `.pdf`).
2. **View & Edit Voucher**:
   - Click `/expenses/{id}/voucher` -> Image opens inline with HTTP 200.
   - Click "Replace Voucher" -> New image updates cleanly.
3. **Operations Approval & Bulk Payout**:
   - Operations Manager reviews, approves or amends claimed amount.
   - Accountant selects multiple approved claims and clicks "Mark Bulk Paid".

### Scenario 7: Executive Reports Export (CSV & PDF)
1. **Bank Audit Trail**:
   - Visit `/reports?tab=bank_tickets`.
   - Verify Net Business Time formula is accurate:
     $$\text{Net Business Time} = \text{Gross TAT} - \text{Weekend} - \text{Transit} - \text{Paused Approvals}$$
   - Click "Export CSV" and "Export PDF".
   - Verify report is branded professionally as **CMS Company** with no internal debug markers.
2. **Machine Faults & Chronic Failures**:
   - Visit `/reports?tab=machine_faults`.
   - Filter by Machine Model (e.g. *Glory USF-51* or *KISAN K5*).
   - Click "Export Supplier Free Parts Claim CSV".
   - Confirm exported columns provide Serial Number, Defective Part Name, Replaced Date, and Fault Description suitable for OEM warranty claims.

---

## 6. Pre-Production Deployment Execution Steps

When moving the application to the production server:

```bash
# 1. Update Environment Variables
# In .env set:
APP_ENV=production
APP_DEBUG=false
APP_URL=https://complaints.cmscompany.biz

# 2. Configure Live SMTP Credentials
MAIL_MAILER=smtp
MAIL_HOST=smtpout.secureserver.net
MAIL_PORT=465
MAIL_USERNAME=support@cmscompany.biz
MAIL_PASSWORD=YourLiveStrongPassword
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=support@cmscompany.biz
MAIL_FROM_NAME="CMS Company Help Desk"

# 3. Optimize Dependencies
composer install --optimize-autoloader --no-dev

# 4. Migrate and Seed Catalog Master Data
php artisan migrate --force
php artisan db:seed --class=CleanTeamRosterSeeder --force

# 5. Create Storage Directory Junction
cmd /c mklink /J "public\storage" "storage\app\public"

# 6. Cache Configurations, Routes, and Views for Maximum Speed
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Start Background Queue & Scheduler
# Windows Task Scheduler or Supervisor to run every minute:
# php artisan schedule:run
```

---

## 7. QA Sign-Off Form

| Verification Area | QA Engineer Name | Date | Status (Pass / Fail) | Notes |
| :--- | :--- | :--- | :--- | :--- |
| **Server & PHP Environment** | | | [ ] | |
| **Automated Tests (121 Passed)** | | | [ ] | |
| **Role-Based Access Control** | | | [ ] | |
| **Email Threading & CC Preservation**| | | [ ] | |
| **File Uploads & Direct Viewing** | | | [ ] | |
| **Two-Stage Part Requisitions** | | | [ ] | |
| **SLA Clock & Weekend Deductions** | | | [ ] | |
| **CSV & PDF Bank / Supplier Reports**| | | [ ] | |

**Final Deployment Authorization:**

*Authorized By (Operations Director):* _______________________  
*Date:* _______________________  
*System Version:* CMS Company ERP v2.6 Production
