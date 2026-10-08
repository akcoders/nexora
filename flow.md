We are already developing an HVAC Company ERP.

IMPORTANT:
This is NOT a new project.

The existing ERP already contains working modules such as:

1. Attendance
2. Internal Workflow / Daily Task Workflow
3. Masters
4. Existing authentication/users/employees/roles/permissions
5. Existing Laravel backend APIs
6. Existing Flutter Android application

Technology stack:

BACKEND:
Laravel

MOBILE APP:
Flutter Android App

The new requirement is to ADD a complete HVAC Service Job / Technician Field Service module into the existing ERP.

Before making changes:

1. Inspect the existing Laravel project.
2. Inspect the existing Flutter application.
3. Understand existing:
   - folder structure
   - architecture
   - authentication
   - API response format
   - repository/service pattern
   - state management
   - database naming conventions
   - UI components
   - theme
   - role/permission implementation
   - employee/user structure
   - Masters implementation
   - Attendance implementation
   - Internal Workflow implementation
4. Reuse existing architecture and components wherever possible.
5. Do NOT recreate authentication, employee management, roles, permissions, attendance, or workflow modules.
6. Do NOT break or redesign existing Attendance or Internal Workflow functionality.
7. Follow existing code conventions.

The Service Module should feel like another native module of the existing ERP.

==================================================
MAIN OBJECTIVE
==================================================

Implement an extremely simple technician mobile workflow for HVAC/AC service jobs.

Although a lot of information needs to be captured, the technician UI must remain very simple.

The technician should mainly perceive this journey:

JOB
↓
VISIT
↓
INSPECTION
↓
ESTIMATE
↓
CUSTOMER APPROVAL
↓
SERVICE
↓
PAYMENT
↓
COMPLETE

Use a simple progress UI such as:

Visit → Inspect → Approve → Service → Payment → Done

Technicians should NOT manually manage complicated backend statuses.

The app should automatically control most status transitions.

==================================================
EXISTING ERP INTEGRATION
==================================================

This module must integrate with existing ERP concepts.

Use existing:

- Employee/Technician records
- User authentication
- Roles and permissions
- Company/Branch structure if present
- Master tables/patterns
- Notifications infrastructure if present
- Existing file/image upload logic
- Existing API standards
- Existing audit columns
- Existing Flutter theme/components
- Existing Laravel services/repositories

Do not duplicate entities unnecessarily.

For example:

If technicians are already employees/users,
do NOT create a separate technician user table.

Technician should reference existing employee/user records.

==================================================
NEW MODULE
==================================================

Create/add:

SERVICE MANAGEMENT

Main entities should logically include:

Service Job
Service Visit
Customer
Customer Equipment / AC
Inspection
Estimate
Service Execution
Materials Used
Payment
Service Report
Customer Feedback

Before creating new tables, check whether equivalent entities already exist.

Reuse them where appropriate.

==================================================
SERVICE JOB CREATION
==================================================

Service jobs may initially be created from ERP Admin/Web.

Design backend architecture so later jobs can come from:

- Customer complaint
- CRM
- AMC
- Sales
- Project
- Preventive maintenance
- Manual service booking

A Service Job should support:

- Job number
- Customer
- Customer phone
- Alternate phone
- Address
- latitude
- longitude
- Service type
- AC/equipment
- Complaint
- Priority
- Preferred visit date
- Preferred visit time
- Assigned technician
- Branch/location if applicable
- Notes
- Status

Use automatic unique job number generation following existing ERP conventions.

==================================================
TECHNICIAN NOTIFICATION
==================================================

When admin assigns a job:

Technician should receive push/in-app notification.

Example:

New Service Job Assigned
Job #SRV-00125
Customer: ABC Customer
Service: AC Not Cooling

Tapping notification should open Service Job Details.

Reuse existing notification architecture if available.

==================================================
TECHNICIAN JOB DETAILS SCREEN
==================================================

Create a simple mobile-friendly job card.

Show:

Job Number

Customer Name

Customer Phone

Address

Service Type

AC/Equipment Details

Complaint

Scheduled Date/Time

Priority

Notes

Provide quick actions:

CALL CUSTOMER

DIRECTIONS

==================================================
CUSTOMER CONTACT
==================================================

When technician contacts customer provide these options:

CONFIRM VISIT

RESCHEDULE

NO ANSWER

CUSTOMER DECLINED

CANCEL JOB

Use large buttons/bottom sheet.

If technician selects CONFIRM VISIT:

Store confirmation date/time.

Status automatically becomes:

CONFIRMED

If RESCHEDULE:

Ask:

New Date
New Time
Reason
Optional remark

Reason should come from Master where possible.

If NO ANSWER:

Store contact attempt.

Allow technician to try again later.

Do not permanently close the job.

If CUSTOMER DECLINED:

Capture reason.

If CANCEL JOB:

Require cancellation reason.

Cancellation permissions should respect existing ERP permissions.

==================================================
VISIT CONFIRMED
==================================================

Once confirmed, primary action should become:

START JOURNEY

Do NOT call it "Start Job".

Start Journey means technician is travelling to customer.

After pressing:

Status automatically becomes:

ON_THE_WAY

Show quick actions:

CALL

DIRECTIONS

JOB DETAILS

Use Google Maps/native map navigation integration based on customer coordinates/address.

==================================================
ARRIVAL DETECTION
==================================================

Use mobile GPS.

When technician reaches close proximity of customer location:

Allow/suggest:

ARRIVED

If reliable geofence functionality can be implemented cleanly, automatically detect arrival.

However GPS must never block technician.

Always provide manual fallback:

MARK ARRIVED

Store:

Arrival timestamp

Latitude

Longitude

Distance from customer location when available

Arrival method:
AUTO
MANUAL

Status:

ARRIVED

Primary CTA becomes:

BEGIN INSPECTION

==================================================
PRE-SERVICE INSPECTION
==================================================

Inspection must be master-driven.

Do NOT hardcode inspection checklist directly into Flutter.

Create/reuse:

Inspection Checklist Master

Inspection Condition Master

Example checklist:

Cooling Coil

Air Filter

Blower

Compressor

Gas Pressure

Drain Line

Electrical Connection

Outdoor Unit

Indoor Unit

Remote

Air Flow

Cooling Temperature

Noise/Vibration

Water Leakage

Each checklist master should allow:

Name

Category

Sequence

Mandatory yes/no

Applicable Equipment Type

Applicable Service Type

Photo Required

Remark Allowed

Active/Inactive

Allowed condition options

Example inspection condition master:

OK

CLEANING REQUIRED

REPAIR REQUIRED

REPLACE REQUIRED

NOT APPLICABLE

Different checklist items should be able to use different conditions where needed.

==================================================
SUPER EASY INSPECTION UI
==================================================

The technician should not fill a long traditional form.

Use card/list design.

Example:

Cooling Coil

[ OK ]
[ Clean ]
[ Repair ]
[ Replace ]

Optional:
Camera icon
Remark icon

Selecting one option should instantly save locally/API depending existing architecture.

Use large touch targets.

Allow multiple photos.

Technician should be able to complete inspection quickly with one-hand operation.

Use:

Autosave

Optimistic UI where safe

Clear selected state

Progress indicator:

8 / 12 Checked

Mandatory items must be completed before submission.

Primary CTA:

COMPLETE INSPECTION

==================================================
PHOTO MANAGEMENT
==================================================

Service jobs should support photo categories:

BEFORE

DURING

AFTER

Allow:

Camera

Multiple photos

Photo preview

Delete before final submission

Optional caption

Checklist-linked photos

Compress images before upload where appropriate while maintaining sufficient evidence quality.

Reuse existing media/file upload architecture if present.

==================================================
INSPECTION COMPLETION
==================================================

Once inspection is completed:

Generate:

INSPECTION REPORT

Based on findings, technician should be able to prepare:

SERVICE ESTIMATE

Use the wording:

SERVICE ESTIMATE

Do NOT use "lump sum".

==================================================
SERVICE ESTIMATE
==================================================

Estimate may contain:

Recommended Service

Labour/Service Charges

Material

Quantity

Unit Rate

Visit Charge

Additional Charge

Discount

Tax

Final Estimated Amount

Allow inspection findings to suggest recommended services.

Example:

Inspection:
Cooling Coil → Cleaning Required

Suggested service:
Cooling Coil Cleaning

Inspection:
Capacitor → Replace Required

Suggested:
Capacitor Replacement

These mappings should ideally be configurable through Masters.

==================================================
SERVICE MASTER
==================================================

Create/reuse Service Checklist / Service Master.

Fields may include:

Service Name

Service Code

Category

Equipment Type

Standard Price

Tax

Estimated Duration

Active

Example services:

General AC Service

Filter Cleaning

Cooling Coil Cleaning

Blower Cleaning

Outdoor Unit Cleaning

Drain Cleaning

Gas Charging

Electrical Repair

Capacitor Replacement

Leakage Repair

Compressor Work

Standard prices should come from this master.

Do not hardcode service prices in mobile app.

==================================================
ESTIMATE REVIEW
==================================================

Show technician/customer a clean estimate screen.

Example:

Inspection Findings

Cooling Coil
Cleaning Required

Filter
Cleaning Required

Gas Pressure
Low

Recommended Services

General Service            ₹800

Coil Cleaning              ₹400

Gas Charging               ₹1,200

-------------------------------

Estimated Total            ₹2,400

Allow authorized technician to add/remove allowed items.

Final total calculation MUST be validated/recalculated by Laravel backend.

Do not trust Flutter-calculated totals.

==================================================
CUSTOMER APPROVAL
==================================================

Before chargeable service begins, customer should approve estimate.

Provide:

ACCEPT

REQUEST CHANGE

DECLINE SERVICE

If customer accepts:

Capture:

Customer Name

Digital Signature

Date/Time

Estimate Amount

Estimate Version

Status:

CUSTOMER_APPROVED

Maintain estimate revision/version history where practical.

Generate customer-facing inspection + estimate summary.

Primary CTA becomes:

BEGIN SERVICE

==================================================
SERVICE EXECUTION SCREEN
==================================================

Keep this screen very easy.

Only two major sections:

1. WORK PERFORMED

2. MATERIALS USED

==================================================
WORK PERFORMED
==================================================

Load services from Service Master.

Technician simply selects services actually performed.

Example:

☑ Filter Cleaning

☑ Coil Cleaning

☑ Drain Cleaning

☐ Gas Charging

Rate should automatically come from service master.

Allow quantity if applicable.

Allow remarks where necessary.

==================================================
MATERIALS USED
==================================================

Integrate with ERP Inventory if inventory already exists.

If Inventory Module is not yet implemented, create clean interfaces/contracts/data structure so it can integrate later without major redesign.

Technician should be able to:

Search Product

Select Product

Enter Quantity

View Unit

View Rate

View Available Stock if inventory exists

Example:

Capacitor 35 MFD

Qty:
1

Rate:
₹650

When added, track it against service job.

If inventory exists, follow existing stock transaction mechanism.

Do NOT directly update stock balance if existing inventory uses stock ledger/transactions.

Create proper stock issue/consumption transaction.

==================================================
NON-INVENTORY MATERIAL
==================================================

Sometimes technician uses something not available in system.

Provide:

ADD OTHER MATERIAL

Fields:

Material Name

Quantity

Unit

Rate

Remark

Optional Photo

Mark record as:

NON INVENTORY ITEM

If ERP already has approval workflow patterns, keep architecture ready so supervisor approval can later be enabled.

==================================================
SERVICE IN PROGRESS
==================================================

While technician works:

Status should automatically be:

SERVICE_IN_PROGRESS

Allow technician to add:

During photos

Remarks

Services

Materials

Do not make technician repeatedly save entire forms.

Use autosave/draft behaviour.

==================================================
POST-SERVICE CHECKLIST
==================================================

After work technician should complete Post-Service Checklist.

Post-Service Checklist must also be master-driven.

Examples:

AC Tested

Cooling Checked

Airflow Checked

Filter Cleaned

Drain Checked

No Water Leakage

Electrical Connection Checked

Noise/Vibration Checked

Temperature Checked

Work Area Cleaned

Allow simple:

YES
NO
N/A

or configurable options from master.

Require at least one AFTER photo before completion.

Primary CTA:

REVIEW SERVICE

==================================================
FINAL SERVICE REVIEW
==================================================

Show final customer-facing summary.

Example:

SERVICES

General AC Service       ₹800

Coil Cleaning            ₹400

MATERIALS

Capacitor                ₹650

ADDITIONAL

Visit Charge             ₹200

--------------------------------

Subtotal                ₹2,050

Tax                      ₹___

Discount                 ₹___

FINAL TOTAL             ₹____

Show:

Inspection findings

Services performed

Materials used

Before photos

After photos

Technician remarks

==================================================
FINAL CUSTOMER SIGNATURE
==================================================

Before final completion capture:

Customer Signature

Customer Name

Completion Date/Time

Optional Customer Remark

This signature confirms service completion.

Technician signature can be:

Existing stored technician signature

or

Captured once/configured according to existing ERP architecture.

==================================================
PAYMENT
==================================================

After customer confirmation:

Primary CTA:

COLLECT PAYMENT

Payment options:

CASH

UPI

CARD

ONLINE

CREDIT / PAY LATER

Only show payment methods allowed through Payment Method Master/company configuration.

Capture:

Amount

Payment Method

Transaction Reference

Payment Date/Time

Optional payment proof

Status:

PAID

PARTIALLY_PAID

PENDING

Support partial payment architecture.

Example:

Final Total:
₹3,000

Received:
₹2,000

Pending:
₹1,000

==================================================
UPI
==================================================

Keep architecture ready for:

Company UPI QR

Dynamic payment link

Payment gateway

Do NOT fake successful payments.

For now if gateway is not integrated, technician may capture payment reference manually.

==================================================
SERVICE COMPLETION
==================================================

Before allowing final completion validate:

Inspection complete

Required checklist complete

Required photos uploaded

Estimate decision recorded

Customer approval available where required

Service work recorded

Materials recorded

Post-service checklist complete

At least one after-service photo

Customer completion signature

Payment status recorded

Then enable:

COMPLETE SERVICE

On click:

Status:

COMPLETED

Store:

Completion timestamp

Technician

Customer

Payment status

Final amount

==================================================
SERVICE REPORT
==================================================

Generate professional PDF Service Report from Laravel.

PDF should include:

Company Logo

Company Information

Service Job Number

Service Date

Customer Details

Customer Address

Technician Details

Equipment Details

Customer Complaint

Inspection Checklist

Inspection Findings

Recommended Work

Approved Estimate

Services Performed

Materials Used

Before/After Images if practical

Amount Breakdown

Payment Status

Customer Signature

Technician Signature

Completion Date/Time

==================================================
PROFORMA INVOICE
==================================================

Generate:

PROFORMA INVOICE

Do not treat it as tax/final invoice unless Accounting module explicitly converts it later.

Proforma should contain:

Customer

Services

Materials

Charges

Tax

Discount

Total

Payment Status

Service Job reference

Later Accounts module should be able to convert/fetch data from this transaction.

==================================================
CUSTOMER COMMUNICATION
==================================================

After service completion:

Send or provide hooks for:

WhatsApp Service Report

Email Service Report

Proforma Invoice

Payment Receipt/Status

Feedback Link

Reuse any existing communication infrastructure if available.

Do not hardcode API credentials.

Use environment/configuration.

==================================================
FEEDBACK
==================================================

Customer should receive a simple feedback link.

Feedback form:

Rating:
1 to 5 Stars

Optional Comments

Issue Still Exists:
Yes / No

Request Callback:
Yes / No

Link feedback to:

Service Job

Customer

Technician

==================================================
STATUS MANAGEMENT
==================================================

Internally support controlled service statuses such as:

ASSIGNED

CONTACTED

CONFIRMED

RESCHEDULED

ON_THE_WAY

ARRIVED

INSPECTION_IN_PROGRESS

INSPECTION_COMPLETED

ESTIMATE_CREATED

ESTIMATE_PENDING_APPROVAL

CUSTOMER_APPROVED

SERVICE_IN_PROGRESS

SERVICE_COMPLETED

PAYMENT_PENDING

PARTIALLY_PAID

PAID

COMPLETED

Exceptional:

NO_ANSWER

CUSTOMER_DECLINED

CANCELLED

ON_HOLD

Do NOT expose all these technical statuses to technician.

Flutter UI should display friendly labels.

Example:

ON_THE_WAY
Display:
"Going to Customer"

CUSTOMER_APPROVED
Display:
"Approved"

SERVICE_IN_PROGRESS
Display:
"Service in Progress"

==================================================
STATE TRANSITION RULES
==================================================

Implement controlled transitions.

For example:

ASSIGNED
→ CONFIRMED

CONFIRMED
→ ON_THE_WAY

ON_THE_WAY
→ ARRIVED

ARRIVED
→ INSPECTION_IN_PROGRESS

INSPECTION_IN_PROGRESS
→ INSPECTION_COMPLETED

INSPECTION_COMPLETED
→ ESTIMATE_CREATED

ESTIMATE_CREATED
→ CUSTOMER_APPROVED

CUSTOMER_APPROVED
→ SERVICE_IN_PROGRESS

SERVICE_IN_PROGRESS
→ SERVICE_COMPLETED

SERVICE_COMPLETED
→ PAYMENT_PENDING / PAID

PAID/PAYMENT_PENDING
→ COMPLETED

Prevent invalid status jumps.

Prefer Laravel service/domain logic rather than trusting client-side transitions.

==================================================
STATUS HISTORY
==================================================

Every important change should create status/audit history.

Store:

Service Job

Previous Status

New Status

Changed By

Changed At

Reason

Remark

Latitude/Longitude if relevant

This should allow management to see complete job timeline.

Example:

10:02 Job Assigned

10:08 Customer Called

10:09 Visit Confirmed

11:17 Journey Started

11:48 Technician Arrived

11:53 Inspection Started

12:04 Estimate Submitted

12:08 Customer Approved

12:10 Service Started

01:15 Service Completed

01:22 Payment Received

01:25 Job Completed

==================================================
FLUTTER UI REQUIREMENTS
==================================================

This module is mainly used by field technicians.

The UI must be extremely easy.

Use existing ERP Flutter design system.

Do not unnecessarily redesign whole application.

Service module can appear as:

Home Dashboard

→ My Service Jobs

Possible dashboard cards:

Today's Jobs

Upcoming

Pending

Completed

Provide simple job cards.

Example:

JOB #SRV-0125

Rajesh Sharma

AC Not Cooling

Today • 11:30 AM

Confirmed

[ CALL ] [ DIRECTIONS ]

Primary CTA:

START JOURNEY

==================================================
ONE MAIN CTA RULE
==================================================

Every major screen should preferably have only ONE primary bottom action.

Examples:

CONFIRM VISIT

START JOURNEY

MARK ARRIVED

BEGIN INSPECTION

COMPLETE INSPECTION

CREATE ESTIMATE

BEGIN SERVICE

REVIEW SERVICE

COLLECT PAYMENT

COMPLETE SERVICE

Other actions should be secondary.

==================================================
NO HUGE FORMS
==================================================

Avoid long traditional forms.

Prefer:

Cards

Chips

Bottom sheets

Step-based screens

Search

Quick selectors

Camera button

Dropdown only where necessary

Numeric keypad for quantities/prices

==================================================
MINIMAL TYPING
==================================================

Technicians should mostly tap/select.

Use Master data wherever possible.

Typing should mainly be required only for:

Remark

Other material

Reason if needed

Manual payment reference

==================================================
OFFLINE / BAD NETWORK SUPPORT
==================================================

Field technicians may have poor internet.

Use existing app architecture to support practical resilience.

At minimum:

Do not lose entered checklist values if network temporarily drops.

Maintain local draft state.

Retry uploads/API where appropriate.

Show clear sync/loading/error status.

Do not falsely show synced data when server call failed.

If existing app already has offline/cache architecture, reuse it.

==================================================
LARAVEL BACKEND
==================================================

Follow existing architecture.

Create only required:

Migrations

Models

Relationships

Services

Repositories if project uses repository pattern

Controllers

Form Requests

Resources

Policies

API routes

Events/Notifications

PDF services

Tests

Use existing base classes/helpers.

Do NOT create duplicate infrastructure.

==================================================
API DESIGN
==================================================

Do NOT arbitrarily invent API style.

Follow existing ERP API versioning and response structure.

Potential logical endpoints may include:

Technician jobs

Job detail

Confirm visit

Reschedule

Contact attempt

Start journey

Mark arrival

Start inspection

Save inspection item

Submit inspection

Create/update estimate

Capture customer approval

Start service

Add performed service

Add material

Upload photo

Complete post-service checklist

Submit final signature

Record payment

Complete service

Get service report

But structure them according to current project conventions.

==================================================
MASTER INTEGRATION
==================================================

Reuse existing Master module.

Add master types/tables using same architecture.

Required logical Masters:

Service Type

Equipment Type

Inspection Checklist

Inspection Condition

Service Checklist / Service Master

Post-Service Checklist

Reschedule Reason

Cancellation Reason

Payment Method

Units

If the ERP Master Module supports generic master categories, use it instead of creating unnecessary separate CRUD architectures.

==================================================
ADMIN ERP SIDE
==================================================

Add Service Management pages into existing Laravel ERP/admin frontend if admin frontend exists.

Use current UI framework.

Management should be able to:

Create Service Job

Assign Technician

Reschedule

View Status

View Complete Job Timeline

View Inspection

View Estimate

View Customer Approval

View Photos

View Material Consumption

View Services Performed

View Payment

View Service Report

View Feedback

Do not rebuild existing employee/customer selectors if already available.

==================================================
JOB TIMELINE
==================================================

Admin should get a clean chronological timeline.

Example:

Assigned to Rahul

Customer Contacted

Visit Confirmed

Journey Started

Reached Location

Inspection Completed

Estimate ₹2,450 Generated

Customer Approved

Service Started

2 Materials Used

Service Completed

₹2,450 Paid via UPI

Customer Signed

Job Completed

==================================================
DASHBOARD INTEGRATION
==================================================

Where appropriate add Service module metrics to existing dashboard architecture.

Possible metrics:

Today's Service Jobs

Pending

In Progress

Completed

Cancelled

Payment Pending

Total Service Amount

Technician-wise jobs

Do not overhaul existing dashboard.

==================================================
ROLE & PERMISSION
==================================================

Use existing role/permission system.

Potential permissions:

service_job.view

service_job.create

service_job.assign

service_job.update

service_job.cancel

service_job.inspect

service_job.create_estimate

service_job.approve_override

service_job.perform

service_job.payment

service_job.complete

service_job.view_financials

service_job.reopen

Map them according to existing permission naming conventions.

Technician should generally only access jobs assigned to them unless existing permissions allow otherwise.

==================================================
DATABASE DESIGN
==================================================

Before creating any migration inspect current database.

Avoid duplicate tables.

Use existing:

users

employees

customers

products

branches

masters

etc.

where available.

Potential new logical data structures may include:

service_jobs

service_job_status_history

service_visits

service_inspections

service_inspection_items

service_estimates

service_estimate_items

service_job_services

service_job_materials

service_job_photos

service_post_checklist

service_payments

service_signatures

service_feedback

But these are conceptual names.

Adapt table naming to existing ERP conventions.

Normalize appropriately without overengineering.

==================================================
CALCULATION SECURITY
==================================================

Flutter may display calculated amounts for UX.

But backend Laravel must be authoritative.

Backend should verify/recalculate:

Service price

Material rate where controlled

Subtotal

Discount

Tax

Final Total

Paid Amount

Pending Amount

Do not trust totals submitted from mobile app.

==================================================
INVENTORY INTEGRATION
==================================================

If Inventory already exists:

Use its current product and stock architecture.

Material consumption should generate proper stock movement.

If Inventory does NOT exist yet:

Do not build a giant inventory module as part of this task.

Create the Service Material data model in a way that can later connect to Inventory.

==================================================
ACCOUNTS INTEGRATION
==================================================

Do not build the complete Accounts module now.

Store financial/service transaction information cleanly.

Generate Proforma Invoice.

Keep architecture ready for Accounts to later:

Generate Tax Invoice

Record Receivable

Record Cash/Bank

Post Journal Entries

==================================================
INTERNAL WORKFLOW MODULE
==================================================

IMPORTANT:

We already have an Internal Workflow / Daily Task Workflow module.

Do NOT replace it.

Service Jobs are a separate operational module.

However, where useful create integration hooks.

Examples:

If a service job needs supervisor escalation:

Create/link Internal Workflow Task.

If special material approval is required:

Internal Workflow may later be triggered.

If customer complaint is unresolved:

Create follow-up workflow.

Do not tightly couple Service Job lifecycle to Daily Workflow.

Keep integration optional/modular.

==================================================
ATTENDANCE MODULE
==================================================

Attendance module already exists.

Do NOT modify its logic unnecessarily.

Technician service visits should NOT automatically become Attendance unless existing business logic specifically supports it.

However employee/technician identity should reuse the same employee records used by Attendance.

==================================================
FUTURE HVAC ERP INTEGRATIONS
==================================================

Keep this module extensible for:

AMC

Preventive Maintenance

Customer Assets

Equipment Warranty

Projects

Sales

Inventory

Accounts

CRM

Complaint Management

Technician Performance

SLA

Repeated Complaints

Next Service Reminder

Do not implement all these now.

Only make Service module architecture compatible with them.

==================================================
CUSTOMER EQUIPMENT HISTORY
==================================================

Design service jobs so they can be associated with a specific customer's equipment/AC.

Example:

Customer:
ABC Office

Equipment:
Daikin Split AC

Capacity:
1.5 Ton

Serial No:
XXXX

Location:
Conference Room

Previous services should later be viewable.

This will allow future:

Complete AC service history

Warranty

AMC

Repeated complaint tracking

==================================================
ERROR HANDLING
==================================================

Provide friendly Flutter errors.

Example:

Instead of:
"422 Validation Exception"

Show:
"Please complete all required inspection items."

Instead of:
"500 Error"

Show:
"Something went wrong. Your entered data is saved. Please try again."

Use existing global error handling where available.

==================================================
LOADING STATES
==================================================

Every API-dependent screen should have:

Loading state

Empty state

Error state

Retry

Success feedback

Prevent duplicate button submissions.

==================================================
FINAL IMPLEMENTATION PROCESS
==================================================

Do not start coding blindly.

First inspect the existing Laravel and Flutter code.

Then provide a short implementation understanding of:

Existing Laravel architecture

Existing Flutter architecture

Existing Attendance patterns

Existing Workflow patterns

Existing Master patterns

Existing API conventions

Existing auth/permissions

Then implement Service module using those patterns.

DO NOT rewrite unrelated code.

DO NOT create a separate standalone application.

DO NOT create duplicate employees/users/masters.

DO NOT modify Attendance or Workflow unless an integration point is genuinely required.

==================================================
TEST COMPLETE FLOW
==================================================

Test this complete happy path:

Admin Creates Job

→ Assign Technician

→ Technician Gets Job

→ Calls Customer

→ Confirms Visit

→ Starts Journey

→ Arrives

→ Begins Inspection

→ Completes Pre-Service Checklist

→ Adds Before Photos

→ Generates Estimate

→ Customer Accepts + Signs

→ Technician Begins Service

→ Selects Services Performed

→ Adds Materials Used

→ Adds During Photos

→ Completes Post-Service Checklist

→ Adds After Photos

→ Customer Reviews Final Amount

→ Customer Signs

→ Payment Captured

→ Proforma Generated

→ Service Report Generated

→ Job Completed

→ Feedback Link Generated/Sent

Also test:

Customer No Answer

Reschedule

Customer Declined

Cancelled Job

GPS Unavailable

Internet Temporary Failure

Estimate Modified

Payment Pending

Partial Payment

Inventory Item Not Found

Non-Inventory Material

Photo Upload Failure

==================================================
FINAL OUTPUT AFTER IMPLEMENTATION
==================================================

After making changes provide a concise implementation report containing:

1. Existing architecture identified

2. Laravel files changed

3. Flutter files changed

4. Database migrations created

5. Models created/modified

6. APIs created

7. Masters added

8. UI screens created

9. Reusable widgets/components created

10. Status flow implemented

11. Inventory integration status

12. PDF/report generation status

13. Notification integration status

14. WhatsApp/email integration status

15. Tests executed

16. Any remaining configuration required

17. Any external credentials/API setup still needed

18. Recommended next module/integration

The final result must look and behave like a natural extension of our existing HVAC ERP, not like a separately developed application.