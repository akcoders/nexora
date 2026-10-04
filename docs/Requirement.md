# Requirement.md — HVAC Company ERP (Phase 1)
### Repository: `nexora` | Admin Panel (Web) + Technician App (Flutter Android)

---

# 0. Environment & Repository Setup

## 0.1 Git Repository
- **Repo Name:** `nexora`
- **Repo Path:** Git (GitHub / GitLab / Bitbucket) — `nexora`
- **Branches:**
  - `main` — production-ready
  - `develop` — integration
  - `feature/*` — per feature
  - `hotfix/*` — urgent fixes

**Monorepo structure:**

```
nexora/
├── backend/                # Laravel 13 (API + Admin Panel)
├── admin-panel/            # (optional separate) — ya backend me hi blade views
├── technician-app/         # Flutter Android App
├── docs/                   # Requirement, API docs, designs
│   └── Requirement.md
├── .gitignore
├── README.md
└── .env.example
```

## 0.2 Environments

| Environment | URL | Purpose |
|---|---|---|
| **Production (App)** | `https://nexora.webignitors.in` | **Flutter App yahi URL use karega** |
| **Local (Dev)** | `http://localhost:8000` (Laravel) | Local development & testing |
| **Local (Machine)** | `http://hurd.local` / `http://192.168.x.x:8000` | LAN testing on **Hurd** machine |
| **Staging (optional)** | `https://staging.nexora.webignitors.in` | Pre-prod testing |

### 0.2.1 Flutter App Base URL Rule
- **Production build (`--release`)** → `https://nexora.webignitors.in/api/v1/`
- **Debug build (`--debug`)** → `http://10.0.2.2:8000/api/v1/` (Android emulator) ya `http://192.168.x.x:8000/api/v1/` (real device on LAN)
- Base URL `lib/core/config/env.dart` me define hoga:

```dart
class Env {
  static const String prodUrl = "https://nexora.webignitors.in/api/v1/";
  static const String localUrl = "http://192.168.x.x:8000/api/v1/"; // Hurd machine LAN IP

  static String get baseUrl =>
      kReleaseMode ? prodUrl : localUrl;
}
```

### 0.2.2 Laravel Backend Environment
`.env` (production):
```
APP_URL=https://nexora.webignitors.in
APP_ENV=production
APP_DEBUG=false
DB_HOST=...
DB_DATABASE=nexora
```

`.env` (local / Hurd):
```
APP_URL=http://localhost:8000
APP_ENV=local
APP_DEBUG=true
DB_HOST=127.0.0.1
DB_DATABASE=nexora_local
```

### 0.2.3 Running Locally on Hurd Machine
```bash
# Clone
git clone <repo-url> nexora
cd nexora/backend

# Install
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Serve (LAN accessible)
php artisan serve --host=0.0.0.0 --port=8000

# Flutter app (local testing)
cd ../technician-app
flutter pub get
flutter run --debug   # will use localUrl from env.dart
```

## 0.3 Deployment (Production)
- **Server:** `nexora.webignitors.in` (HTTPS, SSL enabled)
- **Backend:** Laravel deployed via Git pull + `composer install --no-dev` + `php artisan migrate` + queue worker + cron
- **Flutter App:** Build release APK with prod URL, sign, upload to download link on `nexora.webignitors.in/app`
- **APK Download Link:** `https://nexora.webignitors.in/downloads/nexora-technician.apk`

---

# 1. Project Overview

Ek HVAC company ke liye ERP banana hai jisme future me **Projects, Services, Sales, Accounts, Administration** handle honge.

**Phase 1 me ye modules banenge:**
1. Master Management (Customer Master, Product Master, Service Checklist, etc.)
2. Attendance Management (Flutter Android App + Admin Web Review)
3. Internal Workflow / Daily Task Workflow
4. Dashboard, Charts & Analytics (Basic)
5. Role & Permission System

**Two Frontends:**
- 🖥️ **Admin Panel** — Web (Laravel + Blade + Bootstrap 5) — sidebar layout
- 📱 **Technician App** — **Flutter Android App (APK)** — installable, push notifications, camera, GPS
  - **Server:** `https://nexora.webignitors.in`

**Goal:** Strong foundation, attractive UI, best UX, aur aisa architecture jisme future me naye modules easily add ho sakein.

---

# 2. Tech Stack

## 2.1 Backend / API
- **Laravel 13** (PHP 8.3+)
- Database: **MySQL 8+ / PostgreSQL**
- Authentication: **Laravel Sanctum** (API for Flutter) + **Breeze/Session** (Admin web)
- Roles & Permissions: **Spatie Laravel Permission**
- Queue: Redis / Database Queue
- File Storage: Local / S3 compatible
- **Push Notifications:** Firebase Cloud Messaging (FCM)
- API Documentation: Laravel Scribe / Postman

## 2.2 Admin Panel (Web)
- **Blade + Bootstrap 5**
- **jQuery + AJAX**
- **SweetAlert2** — confirmations, alerts
- **Select2** — searchable & multi-select dropdowns
- **DataTables** — list, filter, export
- **ApexCharts / Chart.js** — charts & analytics
- **FullCalendar** — daily / monthly view
- **Flatpickr** — date pickers
- **Dropzone.js** — file uploads
- **Leaflet / Google Maps** — location picker
- **Summernote / TinyMCE** — rich text
- **Toastr** — toast notifications

## 2.3 Technician App (Flutter Android)
- **Flutter 3.x** (Dart)
- **Target:** Android 8.0+ (API 26+)
- **Output:** Installable **APK** (release build, signed)
- **Production Base URL:** `https://nexora.webignitors.in/api/v1/`
- **Local Base URL:** `http://<hurd-lan-ip>:8000/api/v1/`

**Libraries:**

| Purpose | Package |
|---|---|
| HTTP | `dio` |
| State | `riverpod` / `provider` |
| Routing | `go_router` |
| Secure storage | `flutter_secure_storage` |
| Local DB | `hive` / `sqflite` |
| Push | `firebase_messaging` |
| Local notifications | `flutter_local_notifications` |
| Camera | `camera` |
| Image | `image_picker`, `image_compress` |
| Location | `geolocator` |
| Permissions | `permission_handler` |
| Maps | `google_maps_flutter` |
| Connectivity | `connectivity_plus` |
| Animations | `flutter_animate`, `lottie`, `rive` |
| Charts | `fl_chart` |
| Fonts | `google_fonts` |
| Icons | `font_awesome_flutter`, `lucide_icons` |
| Date | `intl` |
| Forms | `reactive_forms` |
| Dialogs | `sweet_alert_dialog` |
| Dropdown | `dropdown_search` (Select2-like) |
| Toast | `fluttertoast` / `another_flushbar` |
| Skeleton | `skeletonizer` |
| Loading | `shimmer` |
| PDF | `pdf`, `printing` |
| Signature | `signature` |
| Deep links | `app_links` |
| Analytics | `firebase_analytics` |
| Crashlytics | `firebase_crashlytics` |

## 2.4 UI/UX Style

### Admin Panel
- **Theme:** Blue & White with Dark Blue accents
  - Primary: `#1E40AF` (Dark Blue)
  - Secondary: `#3B82F6` (Blue)
  - Accent: `#0EA5E9` (Sky)
  - Background: `#F8FAFC`
  - Card: `#FFFFFF`
  - Text: `#0F172A`
- **Sidebar:** Collapsible, dark blue gradient, icon + label, active state highlight
- **Top Bar:** Search, notifications, profile, quick actions
- **Fonts:** **Poppins** (headings) + **Inter** (body)
- **Icons:** **Lucide Icons** / **Font Awesome 6** / **Heroicons**
- **Cards:** Soft shadow, rounded corners (12px)
- **Buttons:** Rounded, gradient hover, loading state
- **Charts:** Rich animated charts with ApexCharts
- **Animations:** Smooth, subtle, 200–300ms transitions

### Technician App
- **Theme:** Same Blue & White with Dark Blue accents
- **Splash Screen:** Elegant, rich, animated (logo + company name + loading)
- **Bottom Navigation:** Home, Tasks, Attendance, Notifications, Profile
- **Cards:** Rounded, shadow, icons
- **Animations:** Lottie micro-interactions
- **Dark Mode:** Optional (future)

### Common
- Color-coded status:
  - Pending — Orange `#F59E0B`
  - Closed — Green `#16A34A`
  - Outside Premises — Red `#DC2626`
  - Approved — Green
  - Under Review — Blue `#3B82F6`
- Skeleton loaders, toasts, empty states
- Fully responsive (Admin) / Adaptive (Flutter)

---

# 3. User Roles

| Role | Description |
|---|---|
| Super Admin | Full system access |
| Admin | Masters, attendance, workflow, reports |
| Manager / Senior | Attendance review, task monitoring |
| Technician / Engineer | Flutter Android App — attendance, task action |
| Sales | Future phase |
| Accounts | Future phase |
| Viewer / Observer | Only monitoring / view |

Permissions granular: create, read, update, delete, approve, assign, export.

---

# 4. Admin Panel — Layout & Design

## 4.1 Login Page
- **Email + Password** authentication
- Remember Me, Forgot Password, Show/Hide password
- Sleek design — left gradient illustration, right form
- Fade-in animations, input focus glow
- Inline live validation
- SweetAlert2 on error/success
- CAPTCHA after 3 failed attempts (optional)
- 2FA (future)

## 4.2 Sidebar Navigation

```
┌─────────────────────────┐
│  🏢 NEXORA              │  ← Logo + Company
├─────────────────────────┤
│  🏠 Dashboard           │
│  📋 Masters             │  ← Collapsible
│    ├─ Customer Master   │
│    ├─ Product Master    │
│    ├─ Service Checklist │
│    ├─ User Master       │
│    ├─ Role & Permission │
│    └─ Other Masters     │
│  📍 Attendance          │
│    ├─ Today             │
│    ├─ Review Pending    │
│    ├─ Reports           │
│    └─ Premises          │
│  ✅ Tasks / Workflow     │
│    ├─ My Tasks          │
│    ├─ Created by Me     │
│    ├─ Watching          │
│    ├─ All Tasks         │
│    ├─ Kanban Board      │
│    └─ Calendar          │
│  📊 Reports & Analytics │
│  🔔 Notifications       │
│  ⚙️  Settings            │
│  👤 Profile             │
│  🚪 Logout              │
└─────────────────────────┘
```

**Sidebar Features:** Collapsible, dark blue gradient, active highlight, hover slide, Lucide icons, pending badges, smooth accordion submenus, user card at bottom.

## 4.3 Top Bar
- Breadcrumb, Global Search, Quick Action `+ New`, Notification Bell, Theme toggle (future), Profile dropdown

## 4.4 Dashboard
- Summary Cards: Total Technicians, Present Today, Absent, Pending Reviews, Total Tasks, Pending, Closed, Overdue
- Charts: Attendance Trend (line), Task Status (donut), Pending vs Closed (bar), Technician-wise Tasks, Outside Premises (area)
- Recent Activity feed, Quick Actions

---

# 5. Technician Android App (Flutter)

## 5.1 App Overview
- **Platform:** Android (installable APK)
- **Distribution:** Direct APK download link (`https://nexora.webignitors.in/downloads/nexora-technician.apk`) + future Play Store
- **APK Signing:** Release keystore, versioned

## 5.2 Splash Screen
- Elegant, rich, animated
- Logo animation (fade + scale + glow)
- Company name slide-in
- Custom loading indicator
- Duration: 2–3 sec max
- Background: Dark blue gradient
- `flutter_native_splash` + custom animated screen

## 5.3 Login
- Email + Password
- Remember Me, Forgot Password
- Secure token storage — `flutter_secure_storage`
- Auto-login if token valid
- Error dialog (sweet alert style)

## 5.4 Permissions

| Permission | Purpose |
|---|---|
| Camera | Live selfie for attendance |
| Location (Fine + Coarse) | Attendance geofence check |
| Location (Background) | Optional — future tracking |
| Notifications | Task alerts, attendance reminders |
| Storage | Attachments, selfie cache |
| Internet | API sync |

Custom rationale screen with icons before system dialog.

## 5.5 Push Notifications
- **Firebase Cloud Messaging (FCM)**
- **Custom Ringtone** — bundled in app (`android/app/src/main/res/raw/notification_sound.mp3`)
- Notification types: New task, Task action, Task overdue, Attendance reminder, Review approved/rejected, Auto checkout reminder
- **Notification channels:** `tasks` (custom ringtone), `attendance` (custom ringtone), `general` (default)
- Tap → deep link
- Foreground → in-app banner + sound
- Background / Terminated → system notification + custom sound

## 5.6 Attendance Flow
- Auto popup on app open (if not marked today)
- Live selfie (front camera, timestamp watermark)
- GPS high accuracy + reverse geocode
- Distance from premises, Inside/Outside indicator
- Outside → remark mandatory → review
- Checkout till 12:00 AM; after → auto checkout (23:59)

## 5.7 Task Flow
- Task list — Pending / Closed tabs
- Task detail — timeline, observers, attachments
- Action — Remark + Close / Next Assign (searchable user picker)
- Notifications on every action
- Filter by category, priority, due date

## 5.8 Profile
- Avatar, name, role, department, contact
- Change password, notification preferences, logout, app version

## 5.9 Bottom Navigation
Home · Tasks · Attendance · Notifications · Profile

## 5.10 APK Build & Distribution
- **Flavor:** production
- **Build:** `flutter build apk --release --split-per-abi`
- **Output:** `app-release.apk` (signed)
- **Keystore:** stored securely, versioned
- **Version code/name:** in `pubspec.yaml`
- **Distribution:** ERP backend download link + Firebase App Distribution (testing) + future Play Store
- **Update check:** in-app check for new version → prompt to download

---

# 6. Phase 1 Modules

## 6.1 Master Management

### 6.1.1 Customer Master (Detailed)

Levels:
1. **Parent Customer** (HO / Main)
2. **Customer Branches** (multi-branch, Parent ID linked)
3. **Contact Persons** (sub-master)
4. **Documents** (sub-master)
Plus **Customer Group Master**.

---

#### 6.1.1.1 Basic Information

| Field | Type | Notes |
|---|---|---|
| Parent Customer ID | Auto / Lookup | Multi-branch parent ID |
| Customer Type | Dropdown (multi) | Individual / Corporate / Architect / Consultant / Agent / Dealer / Govt. |
| Segment | Dropdown (multi) | Health care / Education / Industrials / Builder / Hospitality / Office |
| Priority | Dropdown | Normal / High / Very High |
| Status | Dropdown | Active / Inactive / Blacklisted |
| Customer Group | Dropdown (Select2) | Related customers grouping |
| Customer Name | Text | e.g. Tata Aia Life Insurance |

**Validation:** Name + Type mandatory; Blacklisted → warning.

---

#### 6.1.1.2 Customer Details

| Field | Type | Notes |
|---|---|---|
| Address Line 1 / 2 | Text | |
| Landmark | Text | |
| Area | Text | |
| City | Text / Dropdown | |
| District | Text / Dropdown | |
| State | Dropdown | |
| Pin Code | Text (6 digit) | Validation |
| Country | Dropdown | Default: India |
| GPS / Map Location | Lat-Long Picker | Optional |
| Contact No 1 / 2 | Text | |
| Email ID 1 / 2 | Email | |
| Site Access Instructions | Textarea | For technicians |
| Billing Address | Textarea / "Same as above" | |
| Remarks | Textarea | |

---

#### 6.1.1.3 Contact Person Master

| Field | Type | Notes |
|---|---|---|
| Name | Text | Mandatory |
| Designation | Dropdown | Manager / Director / VP |
| Department | Dropdown | Admin / Account / Purchase / Engineering |
| Contact No. | Text | |
| WhatsApp No. | Text | |
| Email ID | Email | |
| Mother Tongue | Dropdown | State-wise greeting |
| DOB | Date | Greeting |
| Anniversary | Date | Greeting |
| Remarks | Textarea | Greeting preferences |

**Tags:** Primary / Service / Billing / Escalation (Yes/No each)
**Rules:** Only one Primary per customer/branch.

---

#### 6.1.1.4 Regulatory Details

| Field | Type |
|---|---|
| GSTIN | Text (15 char) |
| PAN | Text (10 char) |
| GST Registration Type | Regular / Composition / Unregistered |
| TAN | Text |
| TDS % | Decimal |
| MSME / Udyam No. | Text |
| MSME Type | Micro / Small / Medium |

**Note:** Branch-level GST override allowed.

---

#### 6.1.1.5 Branch Details (if Branch Type = Multi)

| Field | Type |
|---|---|
| Branch Type | Single / Multi |
| Branch ID | Auto (e.g. `CUST0001-B01`) |

**Branch Address:** Same as Customer Details block.
**Branch Billing Address:** Textarea / "HO" / "Same as Branch".
**Branch Contact Persons:** Repeatable.
**Rules:** Multi → min 1 branch, multiple allowed.

---

#### 6.1.1.6 Additional Data

| Field | Type |
|---|---|
| Customer Classification | Strategic / Regular / Transactional / One-time |
| Customer Source | Multi — Direct / Reference / Existing / Website / Tender / Architect / Consultant / Dealer / Agent / Marketing / Google / Other |
| Business Potential | Textarea |
| Customer Owner / Created By | User Lookup |
| Sales Person | User Lookup |
| Customer Manager | User Lookup |

**Credit & Payment:** Credit Limit, Credit Days, Payment Mode, Advance Required Y/N, TDS Applicable Y/N, Retention Applicable Y/N, Retention %, Billing Cycle, Invoice Submission Method, PO Mandatory Y/N, E-way Bill Applicable Y/N, Default Payment Terms, Default Discount %.

**Note:** Additional discount approval workflow — Phase 2.

---

#### 6.1.1.7 Document Management
Types: GST Certificate, PAN Card, PO, Work Order, AMC Agreement, Vendor Registration, Rate Contract, Compliance, Other.
Fields: Type, Name, File, Expiry, Remarks, Uploaded By/At.

---

#### 6.1.1.8 Customer Group Master
Group Code (auto), Group Name, Description, Linked Customers (Select2), Status.

---

#### 6.1.1.9 Validations
- Name, Type required
- At least one Contact No + Email
- GSTIN 15 char, PAN 10 char
- Pin 6 digits
- Only one Primary Contact
- Multi → min 1 branch
- Blacklisted warning
- Duplicate Name + City warning

---

### 6.1.2 Customer Creation — Wizard Style Form (BEST UX)

**7-Step Wizard** — bada form chhota lage, progress always visible, smart defaults, save draft, resume.

#### Layout
```
┌──────────────────────────────────────────────────────────────┐
│  ← Customers / New Customer                     [Save Draft] │
├──────────────────────────────────────────────────────────────┤
│   ①━━━━②━━━━③━━━━④━━━━⑤━━━━⑥━━━━⑦                        │
│  Basic  Address  Contact  Regul.  Branch  Credit  Docs       │
├──────────────────────────────────────────────────────────────┤
│   ┌────────────────────────────────────────────────────┐    │
│   │  STEP CONTENT (dynamic)                            │    │
│   └────────────────────────────────────────────────────┘    │
├──────────────────────────────────────────────────────────────┤
│  [← Back]           Step 1 of 7           [Next →] [Save]    │
└──────────────────────────────────────────────────────────────┘
```

#### The 7 Steps
1. **Basic Information** 🏢 — Name, Type (chips), Segment (chips), Priority (segmented), Status (segmented), Group (Select2)
2. **Address & Location** 📍 — Address, Map Picker, GPS, Site Instructions (template chips)
3. **Contact Persons** 👥 — Repeatable cards, tags as toggles, drag reorder
4. **Regulatory Details** 📋 — GSTIN (live validate), PAN, GST Type, TAN, TDS %, MSME
5. **Branch Details (Conditional)** 🏬 — Auto-skip if Single; cards with inline edit
6. **Credit & Payment Terms** 💳 — Classification (radio cards), Source (chips), Y/N as toggles
7. **Documents & Review** 📎 — Drag & drop + Review with edit per section

#### Navigation
- Back / Next / Save Draft / Submit
- Auto-save every 30 sec
- Resume from same step
- Keyboard: `Alt+→`, `Alt+←`, `Ctrl+S`

#### Visual
- Colors: Primary `#1E40AF`, Secondary `#3B82F6`, Success `#16A34A`, Warning `#F59E0B`, Danger `#DC2626`
- Fonts: Poppins + Inter
- Components: Chips, Segmented, Toggle, Radio Cards, Stepper, Cards, Badges
- Animations: Slide + fade, shake, green pulse, skeleton loaders

#### Responsive
- Desktop ≥1200px → stepper + right summary
- Tablet 768–1199px → summary top card
- Mobile <768px → thin progress + bottom nav + accordion

#### Tech
- Frontend: Blade + BS5 + jQuery + AJAX + Select2 + SweetAlert2 + Leaflet + Dropzone + Flatpickr + custom `wizard.js`
- Backend: `POST /customers/draft`, `PUT /customers/{id}/step/{n}`, `POST /customers/{id}/submit`, `GET /customers/{id}/resume`
- DB: `customers`, `customer_drafts` (JSON + current_step)

#### Acceptance
- [ ] 7-step wizard with clickable stepper
- [ ] Save Draft, Resume, Auto-save every 30 sec
- [ ] Live validation, conditional fields
- [ ] Auto-fill City → State → Country
- [ ] Map picker with drag pin
- [ ] Contact cards drag reorder
- [ ] Branch cards copy-from-HO
- [ ] Y/N as toggle switches
- [ ] Review page with edit links
- [ ] Mobile responsive, SweetAlert2, toast, skeleton
- [ ] Duplicate warning

---

### 6.1.3 Product Master
Code, Name, Category, Brand, Model, Serial No., Specifications, Warranty, Service Checklist mapping, Status

### 6.1.4 Product Service Checklist Master
Name, Product/Category, Items, Frequency, Estimated Time, Required Skill, Safety Notes, Status

### 6.1.5 Other Masters
User/Employee, Role & Permission, Department, Designation, Location/Premises, Task Category, Priority, Service Type, Unit Type, Status, Notification Template, Customer Group, Contact Person.

---

## 6.2 Attendance Management

### 6.2.1 Admin Side (Web)
- Premises setup: Name, Lat/Long, Radius, Assigned Technicians, Shift
- Dashboard: Today's list, Selfie preview, Map view, Inside/Outside, Pending reviews
- Approve/Reject with remark
- Daily/Monthly reports, Export Excel/PDF

### 6.2.2 Technician Side (Flutter Android)
- Auto popup on app open
- Live selfie + GPS + remark
- Inside → auto-approved; Outside → review
- Checkout till 12:00 AM, auto checkout after
- Attendance history, calendar

---

## 6.3 Internal Workflow / Daily Task Workflow

### Task Creation
Title, Description, Customer/Unit (link), Category, Priority, Due Date, Assignee, Observers, Attachments, Status (Pending/Closed)

### Task Assignment
Initial assignee + multiple observers/vigilance. Observers view + timeline + notifications.

### Task Action
Assignee: Remark mandatory + Close / Next Assign. Next → status Pending, task to next person. Only 2 statuses.

### Timeline
Create, assign, action, remark, next assignee, timestamps, attachments.

### Views
My Pending, Created by Me, Watching, All, Kanban, Calendar, Overdue.

### UI
Attractive cards, badges, timeline/stepper, SweetAlert2 modal, Select2 assignee/observers, AJAX updates, notification bell.

---

## 6.4 Dashboard, Charts & Analytics

**Admin Dashboard**
- Summary cards: Technicians, Present, Absent, Outside, Pending Reviews, Tasks

**Charts**
- Attendance Trend (line), Task Status (donut), Pending vs Closed (bar), Technician-wise Task, Outside Premises (area)

---

# 7. Database Entities

| Table | Purpose |
|---|---|
| users | Users / employees |
| roles / permissions | RBAC |
| customers | Parent customer |
| customer_drafts | Draft step data + current_step |
| customer_groups | Group master |
| customer_branches | Branches |
| customer_contacts | Contact persons |
| customer_contact_tags | Contact tags |
| customer_documents | Documents |
| customer_credit_terms | Credit & payment terms |
| products / product_categories | Product master |
| service_checklists / checklist_items | Checklist |
| premises | Geofence locations |
| attendances / attendance_reviews | Attendance + review |
| tasks / task_members / task_actions | Workflow |
| notifications / device_tokens | Push + in-app |
| activity_logs | Audit trail |
| states / cities / districts / countries | Geo masters |
| departments / designations | Dropdown masters |

---

# 8. Key Business Rules

1. Attendance ke bina technician task action nahi le sakta — configurable.
2. Selfie live honi chahiye.
3. Inside premises → auto-approved; Outside → senior review.
4. 12:00 AM ke baad auto checkout.
5. Task sirf Pending ya Closed.
6. Next assign par status Pending.
7. Observers sirf monitor karenge.
8. Har action ka audit trail.
9. Role-based access control mandatory.
10. Only one Primary Contact per customer/branch.
11. Branch Type Multi → min 1 branch.
12. Blacklisted customer par warning.
13. Customer Wizard me Save Draft + Resume.
14. Step-wise validation on Next.

---

# 9. Non-Functional Requirements

- Responsive Admin Panel
- Installable Android APK (signed, release)
- Push notifications with custom ringtone
- Fast loading, secure auth
- Role-based permissions, audit trail
- Data validation, backup & restore
- Browser support: Chrome, Edge, Safari, Firefox
- Mobile-first technician app
- Hindi / English (future)
- Offline support in app (basic queue)
- **App production URL:** `https://nexora.webignitors.in`
- **Local dev:** `http://<hurd-lan-ip>:8000`

---

# 10. Acceptance Criteria — Phase 1

### Admin Panel
- [ ] Sidebar with collapsible nav, icons, active state
- [ ] Email + password login with SweetAlert2
- [ ] Blue & White theme with Dark Blue accents
- [ ] Poppins + Inter fonts, Lucide icons
- [ ] Customer Master 7-step wizard — Save Draft + Resume
- [ ] Multi-branch, multi-contact, documents
- [ ] Premises setup with map picker
- [ ] Attendance review dashboard with selfie + map
- [ ] Task workflow with timeline, observers, Kanban
- [ ] Dashboard with rich animated charts
- [ ] Role & permission system
- [ ] Select2 searchable dropdowns everywhere
- [ ] DataTables export to Excel / PDF

### Technician Android App
- [ ] Elegant animated splash screen
- [ ] Email + password login, auto-login
- [ ] Camera, Location, Notification permissions with rationale
- [ ] Push notifications with **custom ringtone**
- [ ] Attendance popup on app open
- [ ] Live selfie + GPS + remark
- [ ] Inside → auto-approved; Outside → review
- [ ] Checkout till 12:00 AM, auto checkout after
- [ ] Task list, action, next assign
- [ ] Bottom nav: Home, Tasks, Attendance, Notifications, Profile
- [ ] Blue & White theme, Material 3, animated UI
- [ ] **Production base URL:** `https://nexora.webignitors.in/api/v1/`
- [ ] **Debug base URL:** local (Hurd machine LAN)
- [ ] Installable signed APK
- [ ] Flutter best libraries (Dio, Riverpod, Hive, etc.)
- [ ] App version check + update prompt

### Repository & Deployment
- [ ] All code in `nexora` Git repository
- [ ] `backend/` (Laravel) + `technician-app/` (Flutter) + `docs/`
- [ ] Local dev on **Hurd machine** — `php artisan serve --host=0.0.0.0`
- [ ] Production deploy on `nexora.webignitors.in` (HTTPS)
- [ ] Flutter app uses prod URL in release build
- [ ] APK download link on production server
- [ ] README with setup instructions

---

# 11. Repository & Setup Instructions

## 11.1 Repository Structure
```
nexora/
├── backend/                # Laravel 13
│   ├── app/
│   ├── routes/
│   ├── database/
│   ├── resources/views/    # Admin Panel blades
│   ├── public/
│   ├── .env.example
│   └── composer.json
├── technician-app/         # Flutter Android App
│   ├── lib/
│   │   ├── core/config/env.dart   # prod + local URLs
│   │   ├── features/
│   │   └── main.dart
│   ├── android/
│   │   └── app/src/main/res/raw/notification_sound.mp3
│   ├── pubspec.yaml
│   └── README.md
├── docs/
│   └── Requirement.md
├── .gitignore
└── README.md
```

## 11.2 Local Setup (Hurd Machine)

### Backend
```bash
git clone <nexora-repo-url>
cd nexora/backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
# Access from LAN: http://<hurd-lan-ip>:8000
```

### Flutter App (Local Testing)
```bash
cd nexora/technician-app
flutter pub get
# env.dart -> use localUrl (Hurd LAN IP)
flutter run --debug
```

## 11.3 Production Deployment

### Backend
```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
# Queue worker + cron for scheduler
```
Server: `https://nexora.webignitors.in` (SSL via Let's Encrypt / Cloudflare).

### Flutter App (Release APK)
```bash
cd nexora/technician-app
# env.dart -> kReleaseMode uses prodUrl = https://nexora.webignitors.in/api/v1/
flutter build apk --release --split-per-abi
# Output: build/app/outputs/flutter-apk/app-arm64-v8a-release.apk
```
Upload APK → `https://nexora.webignitors.in/downloads/nexora-technician.apk`

## 11.4 Environment URLs Summary

| Context | URL |
|---|---|
| Flutter App — Production | `https://nexora.webignitors.in/api/v1/` |
| Flutter App — Local (Debug) | `http://<hurd-lan-ip>:8000/api/v1/` |
| Admin Panel — Production | `https://nexora.webignitors.in` |
| Admin Panel — Local | `http://localhost:8000` |
| APK Download | `https://nexora.webignitors.in/downloads/nexora-technician.apk` |
| Git Repo | `nexora` |

---

# 12. Future Phases (Out of Scope Phase 1)

- Sales & CRM
- Accounts & Billing
- Projects Management
- Service & AMC
- Inventory & Purchase
- Payroll & HR
- Advanced Analytics & AI Reports
- Customer Portal, Vendor Portal
- Additional discount approval workflow
- iOS app
- Offline-first full sync
- Background location tracking

---

# 13. Open Questions

1. Shift timing fixed ya multiple?
2. Default geofence radius?
3. Ek technician ke multiple premises?
4. Offline attendance allow?
5. Overtime / late mark rules?
6. Attendance approval hierarchy?
7. Notification channels: Push / Email / SMS / WhatsApp?
8. Laravel 13 available na ho to kaunsa version?
9. Data retention & backup policy?
10. HO vs Branches GST same ya different?
11. Ek customer multiple groups me?
12. Contact Person parent / branch / dono?
13. Additional discount approval — Phase 1 ya 2?
14. Blacklist karne par existing tasks?
15. Customer delete vs Inactive?
16. Customer code format? Branch code format?
17. Document expiry reminder Phase 1 me?
18. Credit integration with Accounts kab?
19. Custom ringtone — company branded sound final?
20. APK distribution channel final?
21. Hurd machine ka LAN IP / hostname final?
22. SSL certificate provider — Let's Encrypt / Cloudflare / other?

---

# 14. Conclusion

Phase 1 me ERP ka strong base banega:

- 🖥️ **Admin Panel (Web)** — Blue & White theme, sidebar layout, rich charts, Select2, SweetAlert2, DataTables
- 📱 **Technician App (Flutter Android)** — Animated splash, push notifications with custom ringtone, camera + GPS, installable signed APK
- 🌐 **Production URL:** `https://nexora.webignitors.in`
- 🖥️ **Local Dev:** Hurd machine par `php artisan serve --host=0.0.0.0`
- 📦 **Git Repo:** `nexora` (backend + technician-app + docs)
- 📋 **Customer Master** with **7-step Wizard UX**
- 📍 **Attendance** with geofence + selfie + senior review
- ✅ **Internal task workflow** with monitoring & timeline
- 📊 **Dashboard & charts**

Architecture aisa hoga ki future me Sales, Accounts, Projects, Services aur Administration modules easily plug ho sakein.