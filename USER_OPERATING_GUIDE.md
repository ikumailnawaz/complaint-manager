# CMS Bank Complaint & Operations System — Simple User Guide
### A Friendly, Step-by-Step Guide for Daily Office & Field Operations
**Company:** CMS Company Pakistan  
**System URL:** `http://127.0.0.1:8000` (or your company server link)

---

## Welcome to the CMS Operations Portal!

Welcome! This system is designed to make your daily work easier, faster, and well-organized. Whether you are managing complaints at the head office, dispatching spare parts from the warehouse, or fixing banknote sorters at a bank branch, this guide will walk you through every step in **simple, everyday language**.

No computer science knowledge is needed! Just follow the steps below.

---

## Quick Navigation: Table of Contents
1. [How to Log In (Usernames & Passwords)](#1-how-to-log-in)
2. [Understanding Your Role & What You Can See](#2-understanding-your-role)
3. [The Top Bar: Notifications & Sound Chimes](#3-the-top-bar-notifications--sound-chimes)
4. [Complaints & Tickets Module](#4-complaints--tickets-module)
   - [4.1 Complain Registry (All Bank Calls)](#41-complain-registry)
   - [4.2 Open Tickets (Active Work)](#42-open-tickets)
   - [4.3 Escalated Tickets (Urgent & Delayed Machines)](#43-escalated-tickets)
   - [4.4 Assigning an Engineer to a Call](#44-assigning-an-engineer-to-a-call)
   - [4.5 Pausing the SLA Timer (Awaiting Bank / Parts)](#45-pausing-the-sla-timer)
   - [4.6 Closing a Ticket & Uploading Customer Sign-off Slip](#46-closing-a-ticket--uploading-customer-sign-off-slip)
5. [Waited Approvals (Management Authorizations)](#5-waited-approvals)
6. [Parts & Inventory (Spare Parts Management)](#6-parts--inventory)
   - [6.1 The 2-Stage Part Requisition Flow (Very Important!)](#61-the-2-stage-part-requisition-flow)
   - [6.2 Checking Stock Levels & Locations](#62-checking-stock-levels--locations)
   - [6.3 Advance Envelopes (Parts in Engineer's Bag)](#63-advance-envelopes)
   - [6.4 Goods Received (GRN) & Moving Stock Between Cities](#64-goods-received-grn--moving-stock-between-cities)
   - [6.5 Sending the Broken Part Back (Reverse Core Tracking)](#65-sending-the-broken-part-back)
7. [Central Workshop (Head Office Lab Repairs)](#7-central-workshop)
8. [Field & Disbursements (Travel Expense Claims)](#8-field--disbursements)
9. [Preventive Maintenance (Regular Routine Servicing)](#9-preventive-maintenance)
10. [Reports & Bank Audits](#10-reports--bank-audits)
11. [Troubleshooting & Helpful Tips](#11-troubleshooting--helpful-tips)

---

## 1. How to Log In

When you open the website, you will see the **CMS Company Login Screen**.

![Login Screen](file:///c:/Users/kumail/Downloads/Engineers/complaint-manager/public/favicon.ico)

### How to Sign In:
1. In the **Username or Email** box, type either:
   - Your **First Name** in lowercase (e.g., `kumail`, `adnan`, `farhankhalid`, `ronald`, `nazir`, `usman`), OR
   - Your official email (e.g., `kumail@banksupport.com`).
2. In the **Password** box, type your password:
   - For all staff members, your initial password is your **name in lowercase followed by 123** (e.g., `kumail123`, `adnan123`, `farhankhalid123`, `ronald123`).
3. Click the blue **Sign In to Portal** button.
4. **Convenient Shortcut**: On test systems, you can simply click on your name card on the login screen to fill your login instantly!

---

## 2. Understanding Your Role

The system automatically customizes the left menu bar depending on your job:

| Your Job Role | Who has this role? | What you will use this system for: |
| :--- | :--- | :--- |
| **Super Admin** | Kumail | Oversees the entire company, gives final Stage 2 approval for spare parts, manages staff accounts, and views all bank reports. |
| **Operations Manager** | Ronald (Karachi), Nazir (Lahore) | Assigns engineers, monitors SLA countdowns, handles escalated calls, reviews travel expenses, and schedules routine servicing. |
| **Office Staff** | Adnan (Lahore) | Inspects open tickets, does Stage 1 warehouse stock checks on part requests, enters courier dispatch tracking, and reviews machine faults. |
| **Field Engineer** | Farhan Khalid, Usman, Ikram, and all field teams | Sees tickets assigned to them, visits bank branches, requests spare parts, records daily progress, claims fuel/toll money, and uploads customer sign-off slips. |

---

## 3. The Top Bar: Notifications & Sound Chimes

Look at the top-right corner of your screen:
- **Bell Icon (🔔)**: Shows a red counter whenever something needs your attention (like a new ticket assigned to you, or a part request awaiting your approval).
- **Sound Alert 🔊**: Whenever a new notification arrives, a gentle notification sound plays on your computer or mobile speakers so you never miss an urgent bank call.
- **Mark As Read**: Click on the notification bell to view the list, and click "Mark All as Read" once you have reviewed them.

---

## 4. Complaints & Tickets Module

This is the heart of the system where all bank complaints (HBL, Alfalah, MCB, UBL, Meezan, etc.) are tracked from start to finish.

```
Bank Sends Email ──► Ticket Created ──► Engineer Assigned ──► Engineer Fixes Machine ──► Proof Uploaded ──► Bank Notified & Closed
```

### 4.1 Complain Registry
- **What it is:** The complete library of every complaint ever recorded.
- **How to use:**
  1. Click **Complains** > **Complain Registry** on the left menu.
  2. Use the search box to find a ticket by **Ticket Number** (e.g. `CMP-2026-00101`), **Bank Name**, **Branch Name**, or **Machine Serial Number**.
  3. Click any ticket row to open its full details page.

### 4.2 Open Tickets
- **What it is:** Shows only the tickets that are currently active and being worked on right now.
- **Why it matters:** Office staff and managers use this screen to make sure no bank complaint is left unattended.

### 4.3 Escalated Tickets & The SLA Timer
- **What is SLA?** SLA (Service Level Agreement) is the agreed time limit we have to fix a bank's machine (for example, within 4 hours, 8 hours, or 24 hours).
- **The Color Countdown:**
  - 🟢 **Green**: Plenty of time left.
  - 🟡 **Yellow**: Less than 2 hours remaining — hurry up!
  - 🔴 **Red (BREACHED)**: Time has expired! The ticket is automatically moved to **Escalated Tickets**.
- **Fair Time Deductions:** The system automatically **stops the timer on weekends (Saturdays & Sundays)** and during courier transit hours so your performance score stays 100% fair.

### 4.4 Assigning an Engineer to a Call
*(For Operations Managers & Supervisors)*
1. Open an unassigned ticket.
2. Under **Assigned Field Engineer**, select the engineer stationed nearest to that city/branch.
3. Click **Assign Engineer**.
4. The system automatically sends a professional assignment confirmation email directly to the bank's branch manager with the engineer's name and phone number!

### 4.5 Pausing the SLA Timer (When Waiting for Bank or Parts)
Sometimes you cannot fix the machine immediately because the bank branch is closed, or you are waiting for a spare part to arrive from Lahore head office.
1. On the ticket page, click the button **"Request Approval / Pause SLA"**.
2. That's it! The clock pauses immediately so the ticket does not show as breached.
3. As soon as the part arrives or the bank re-opens, the manager clicks **"Grant Approval"**, and the timer resumes smoothly.

### 4.6 Closing a Ticket & Uploading Customer Sign-off Slip
When the machine is repaired and running smoothly:
1. Click the green button **"Mark Ticket as Resolved"**.
2. Type a short summary of the fix (e.g., *"Replaced worn pickup roller, cleaned optical sensors, performed 500-note test batch. Working normal."*).
3. Click **Browse / Choose File** and attach the **Signed Customer Service Report (FSR)** or a clear photo of the working machine.
4. Click **Submit Resolution**.
5. **How to view the file later:** On the ticket page, click **"View Proof"** — the document or photo opens immediately in a new tab.
6. **Made a mistake or need a clearer picture?** Simply click **"Replace Proof"**, select the new clear photo, and click Save. The system updates it instantly.
7. **Notify the Bank**: Click **"Send Official Bank Resolution Email"**. The system sends a professional handover email to the bank, attaching your signed slip, and automatically quoting the entire previous email history so everyone in CC can see what happened!

---

## 5. Waited Approvals (Management Authorizations)

- **What it is:** A centralized inbox for Managers and Super Admins.
- **What appears here:**
  - SLA Pause requests from engineers.
  - Travel expense claims needing financial sign-off.
  - Special machine replacement authorizations.
- **How to operate:**
  1. Click **Waited Approval** > **Pending Approvals** in the left menu.
  2. Click **Review** on any pending item.
  3. Click **Approve** (green) or **Reject** (red) with a short explanation note.

---

## 6. Parts & Inventory (Spare Parts Management)

This module handles every spare part: belts, optical sensors, motor gears, sorting rollers, and mainboards.

### 6.1 The 2-Stage Part Requisition Flow (Very Important!)
To prevent missing inventory and ensure financial control, spare parts are issued through a secure **2-stage approval system**:

```
[1. Field Engineer]               [2. Office Staff]               [3. Super Admin]               [4. Office Staff]
Submits Part Request     ──►   Verifies Stock & Evidence   ──►   Final Financial Approval  ──►   Dispatches Courier
(Selects Model & Part)         (Stage 1 Verification)            (Stage 2 Authorization)         (Enters Tracking #)
```

#### Step 1: Field Engineer submits requisition
1. Go to **Parts & Inventory** > **Parts Requisitions**.
2. Click **"+ New Part Requisition"**.
3. Select the machine model (e.g., `Glory USF-51` or `KISAN K5`), select the required part, enter the quantity, and type the serial number of the broken part.
4. Click **Submit Requisition**. The status becomes `Pending Stock Check`.

#### Step 2: Office Staff checks stock & video (Stage 1)
1. Office Staff (`adnan`) opens the requisition.
2. Checks which warehouse or engineer has the part in stock.
3. *(Optional)* Can attach or review a short video showing the machine fault (e.g., motor grinding noise or sensor error).
4. Clicks **"Stage 1: Verify Stock & Forward to Admin"**.
5. The status changes to `Pending Approval`.

#### Step 3: Super Admin grants authorization (Stage 2)
1. Super Admin (`kumail`) opens the requisition.
2. Reviews the requested quantities and cost.
3. Clicks **"Stage 2: Super Admin Final Approval"**.
4. The status becomes `Approved`, and an official **Gate Pass** is created!

#### Step 4: Dispatching the parcel (Office Staff)
1. Office Staff prepares the physical package.
2. Clicks **"Dispatch Requisition"**.
3. Selects the courier company (e.g. **TCS**, **Leopards**, or **M&P**) and types the **Courier Tracking Number** (mandatory).
4. Clicks **Confirm Dispatch**.
5. The stock is automatically deducted from the warehouse ledger!

### 6.2 Checking Stock Levels & Locations
- Go to **Parts & Inventory** > **Stock Levels**.
- You will see a clear grid showing how many units of each part exist in:
  - **Lahore Head Office Warehouse**
  - **Karachi Regional Center**
  - **Islamabad Regional Center**
- Any item running dangerously low shows in **Red (Low Stock Alert)**.

### 6.3 Advance Envelopes (Spare Parts in Engineer Bag/Van)
- **What is an Advance Envelope?** Frequently used small parts (like belts and cleaning pads) that a trusted field engineer carries in their bag for immediate fixes.
- **How to lend:** A manager selects an engineer and clicks **"Lend Advance Parts"**.
- **How to return:** When an engineer returns unused parts to the head office, click **"Return to Store"**.

### 6.4 Goods Received (GRN) & Moving Stock Between Cities
- **GRN (Goods Received Note):** When a new shipment of spare parts arrives from international suppliers (Glory, Kisan, Julong), go to **GRN** > **Create GRN**, enter the incoming quantities, and confirm. The stock increases automatically.
- **Location Transfers:** When sending 10 rollers from Lahore to Karachi, go to **Location Transfers** > **New Transfer**. Karachi staff can click "Confirm Receipt" once the box arrives.

### 6.5 Sending the Broken Part Back (Reverse Core Tracking)
Bank contracts require us to keep and return defective parts for warranty credit from manufacturers.
1. When the engineer replaces the broken part at the bank, they bring or courier the old broken part back to the Head Office.
2. Office Staff opens the original requisition and clicks **"Verify Return of Defective Core"**.
3. The request is now 100% closed and filed for supplier warranty claims!

---

## 7. Central Workshop (Head Office Lab Repairs)

Some bank machines suffer severe damage (like short-circuited power boards or jammed feeder chassis) that cannot be repaired on the branch floor.

1. **Sending to Workshop:** On the ticket, click **"Send to Central Workshop"**.
2. **In Transit:** The machine status shows as in transit via courier.
3. **Bench Technician:** The workshop lab team in Lahore or Karachi receives the machine, puts it on the testing bench, replaces damaged components, and marks it **"Workshop Repaired"**.
4. **Return to Branch:** The machine is couriered back to the bank branch where the local engineer re-installs it and tests it in front of the bank manager.

---

## 8. Field & Disbursements (Travel Expense Claims)

Engineers who travel between cities or pay motorway tolls and fuel can claim their reimbursement quickly:

1. Go to **Field & Disbursements** > **Tour Expenses**.
2. Click **"+ New Expense Claim"**.
3. Select the Ticket Number you traveled for.
4. Choose the trip details (e.g., from *Lahore* to *Sahiwal*).
5. The system's smart calculator will automatically suggest the standard travel rate based on exact highway distance!
6. **Attach Receipt:** Take a clear photo of your fuel receipt or motorway toll slip and upload it.
7. Click **Submit Claim**.
8. **Manager Approval:** The Operations Manager reviews the receipt and clicks **Approve**.
9. **Payout:** Once accounts releases the cash or bank transfer, click **"Mark as Paid"**.

---

## 9. Preventive Maintenance (Routine Servicing)

Banks require regular monthly or quarterly servicing of their banknote sorters to prevent unexpected breakdowns.

1. Go to **Preventive Maintenance** > **PM Schedules**.
2. See the list of bank branches due for maintenance this month.
3. **For Field Engineers:** Go to **"My PM Tasks"**.
   - See the branches assigned to you.
   - After servicing the machine, click **Complete PM**, type what you serviced (e.g., *Dust vacuumed, optics cleaned, belt tension checked*), and upload the branch's signed PM slip.

---

## 10. Reports & Bank Audits

This module generates professional reports that you can send directly to bank executives or foreign spare part suppliers.

### 10.1 Bank SLA Audit Trail Report (For Bank Operations Heads)
- **Direct Link:** `/reports?tab=bank_tickets`
- **What it shows:** Every ticket handled for HBL, Alfalah, MCB, etc., with exact timestamps.
- **Net Business Time Formula:**
  $$\text{Net Working Time} = \text{Total Time} - \text{Weekends} - \text{Courier Transit} - \text{Paused Approvals}$$
- Click **"Export CSV"** or **"Export PDF"** to get a clean, official report bearing **CMS Company** branding ready to email to the bank!

### 10.2 Machine Faults & Supplier Free Parts Claim
- **Direct Link:** `/reports?tab=machine_faults`
- **What it shows:** Chronic machine faults, serial numbers, and broken parts.
- Filter by Machine Model (e.g., `Glory USF-51`).
- Click **"Export Supplier Free Parts Claim CSV"**. You can send this directly to the manufacturer to claim free replacement spare parts under warranty!

---

## 11. Troubleshooting & Helpful Tips

### Q: "I cannot see a button that my colleague can see."
> **Answer:** Buttons are shown based on your role. For example, only the Super Admin can click the final "Stage 2 Part Approval" button. Only Field Engineers see the "Claim Expenses" button.

### Q: "My file upload failed or says file too large."
> **Answer:** 
> - Service slips and fuel receipts can be `.pdf`, `.jpg`, `.png`, or `.webp` (up to 10MB).
> - Diagnostic videos can be `.mp4` or `.webm` (up to 100MB).
> - If taking a photo on a mobile phone, standard photo resolution is plenty.

### Q: "I uploaded the wrong document by accident. Can I change it?"
> **Answer:** Yes! On both the ticket page and the expense claim page, there is a **"Replace Proof"** / **"Replace Voucher"** button. Just click it, choose the correct file, and submit. The old file is deleted automatically.

### Q: "The bank manager says they didn't receive the email."
> **Answer:** Check the ticket details. Ensure the branch email address is entered correctly (e.g. `operations.gulberg@bank.com.pk`). When you click **"Send Resolution Email"**, the system uses our corporate SMTP mail server with full email thread history included.

---

## Need Support or Help?
- **Company IT Helpdesk:** `support@cmscompany.biz`
- **Operations Directorate:** CMS Company Head Office, Lahore
- **System Administrator:** Kumail (`kumail@banksupport.com`)
