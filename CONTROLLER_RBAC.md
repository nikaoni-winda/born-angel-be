# Controller & RBAC

This document details the functionality and Role-Based Access Control (RBAC) logic for each controller in the Born Angel API. It serves as a blueprint for the final Middleware and Route configuration.

**Role Hierarchy:**
1.  **Super Admin (God Mode):** Full access. Owns the business. Can manage Admins. Master Account (ID 1) is immutable.
2.  **Admin (Manager):** Operational access. Manages Services, Instructors, Schedules, and Users. Cannot touch Super Admins.
3.  **Instructor:** Employee. Limited access (view schedule/reviews). *Note: Currently managed via Admin; specific portal access pending.*
4.  **User:** Customer. Can book services and write reviews.

---

## 1. AuthController
Authentication entry point.

| Method | Role | Description |
| :--- | :--- | :--- |
| `register` | **Public** | creates a new account with default role `user`. |
| `login` | **Public** | Authenticates user and returns API Token + User Data. |
| `logout` | **Auth (Any)** | Revokes current access token. |

---

## 2. ServiceController
Manages services offered (Douyin Makeup, Korean Makeup, etc.).

| Method | Role (Intended Middleware) | Logic / Notes |
| :--- | :--- | :--- |
| `index` | **Public** | View all services. |
| `show` | **Public** | View specific service details. |
| `store` | **Admin, Super Admin** | Create new service. Logic check handled by Middleware. |
| `update` | **Admin, Super Admin** | Update service details. Logic check handled by Middleware. |
| `destroy` | **Admin, Super Admin** | Soft delete service. Logic check handled by Middleware. |

---

## 3. InstructorController
Manages instructor profiles.

| Method | Role (Intended Middleware) | Logic / Notes |
| :--- | :--- | :--- |
| `index` | **Public** | View all instructors (eager loads `user` relation for name). |
| `show` | **Public** | View specific instructor profile. |
| `store` | **Admin, Super Admin** | Create profile. Validates `user_id` exists. |
| `update` | **Admin, Super Admin** | Update profile. (Decision: Instructors currently do not update their own profiles directly). |
| `destroy` | **Admin, Super Admin** | Delete instructor profile. |

---

## 4. ScheduleController
Manages class schedules.

| Method | Role (Intended Middleware) | Logic / Notes |
| :--- | :--- | :--- |
| `index` | **Public / Admin / Instructor** | **Strict Logic (Context-Aware):**<br>- **Instructor:** Can ONLY see own schedules (Past, Present, Future).<br>- **Admin/Super Admin:** Can see **EVERYTHING** (Past & Future).<br>- **Public/User:** Can see only **UPCOMING** schedules. |
| `show` | **Public** | View schedule details. |
| `store` | **Admin, Super Admin** | Create schedule. **Strict Logic:** Prevents Double Booking (Instructor Overlap) & validates Time. |
| `update` | **Admin, Super Admin** | Update schedule. **Strict Logic:** Capacity cannot be reduced below current booking count. Re-checks overlaps. |
| `destroy` | **Admin, Super Admin** | Delete schedule. **Strict Logic:** Cannot delete if active bookings exist. |

---

## 5. BookingController
Handles transactions and class reservations.

| Method | Role | Functionality & RBAC Logic |
| :--- | :--- | :--- |
| `index` | **User, Admin, Super Admin** | **Logic:**<br>- **User:** Sees only *their own* bookings.<br>- **Admin/Super Admin:** Sees *all* bookings. |
| `store` | **User** | Create Booking. **Logic:** Checks slot availability, prevents double booking same slot. Creates `pending` Review & `Payment` record. |
| `cancel` | **User (Owner), Admin, Super Admin** | **Logic:**<br>- **User:** Can only cancel *own* booking.<br>- **Admin/Super Admin:** Can cancel *any* booking (Operational/Moderation). |

---

## 6. ReviewController
Handles customer feedback.

| Method | Role | Functionality & RBAC Logic |
| :--- | :--- | :--- |
| `index` | **Public / Instructor (Context-Aware)** | **Strict Logic:**<br>- **Instructor:** Can ONLY see reviews for their own classes.<br>- **Public/User/Admin:** Can see all reviews. Supports `?instructor_id=X` filter. |
| `store` | **User (Owner)** | Create Review. **Strict Logic:**<br>1. Must be own booking.<br>2. **Class must be finished** (`end_time < now`).<br>3. No double reviews. |
| `update` | **User (Owner)** | Update review. Only the writer can edit. |
| `destroy` | **User (Owner), Admin, Super Admin** | **Logic:**<br>- **User:** Delete own review.<br>- **Admin/Super Admin:** Delete any (Moderation). |

---

## 7. UserController (The Universal Manager)
Manages User Accounts, Roles, and Staff.

| Method | Role | Functionality & RBAC Logic |
| :--- | :--- | :--- |
| `index` | **Admin, Super Admin** | View all users. **Feature:** Supports `?role=instructor` filter. |
| `store` | **Admin, Super Admin** | Create Account (`admin`, `instructor`).<br>**Strict Hierarchy:**<br>- **Admin:** Can create `admin` or `instructor`. Cannot create `super_admin` or `user`.<br>- **Super Admin:** Can create ANY role (including other `super_admin`). |
| `update` | **Admin, Super Admin** | Update profile/role.<br>**Strict Hierarchy:**<br>- **Master Account (ID 1):** PROTECTED. Immutable.<br>- **Admin:** Cannot edit Super Admin profiles.<br>- **Admin:** Cannot promote anyone to Super Admin.<br>- **Super Admin:** Full access (except Master Account ID 1 protection). |
| `destroy` | **Admin, Super Admin** | Delete Account.<br>**Strict Hierarchy:**<br>- **Master Account (ID 1):** PROTECTED.<br>- **Admin:** Cannot delete Super Admin.<br>- **Admin:** Cannot delete themselves via this endpoint (Safety). |

---

### Legend
*   **Public:** No Login required.
*   **User:** Authenticated Customer (Sanctum).
*   **Admin:** Operational Manager Role.
*   **Super Admin:** Business Owner Role.
*   **Intended Middleware:** Logic checks that have been removed from Controller to be placed in `routes/api.php` via Middleware (e.g., `role:admin,super_admin`).
