# Born Angel API - Postman Testing Collection

Complete Postman collection for testing the Born Angel API with strict RBAC enforcement.

## 📦 Files

- `born_angel_collection.json` - Complete API collection with all endpoints
- `born_angel_environment.json` - Environment variables for testing
- `README.md` - This file

## 🚀 Quick Start

### Option 1: Using Collection Variables (Recommended - Easier!)

1. Open Postman
2. Click **Import** button
3. Drag and drop **ONLY** `born_angel_collection.json`
4. That's it! Variables are built into the collection.

### Option 2: Using Environment Variables (Optional)

1. Open Postman
2. Click **Import** button
3. Drag and drop both files:
   - `born_angel_collection.json`
   - `born_angel_environment.json`
4. Select **Born Angel Environment** from the environment dropdown (top right)

**Note:** The collection works perfectly with **Option 1** alone. Environment file is optional and only needed if you want to manage multiple environments (dev, staging, production).

### 2. Start Testing

1. Make sure your Laravel server is running:
   ```bash
   php artisan serve
   ```

2. Go to **1. Authentication** folder
3. Run **Login** request with appropriate credentials
4. Token will be **automatically saved** to Collection Variables
5. Test other endpoints based on your role

### 3. Verify Variables

After login, check if variables are saved:
- Click on the **Collection name** (Born Angel API - Complete RBAC Testing)
- Go to **Variables** tab
- You should see `token`, `user_role`, `user_id` filled with values

If variables are empty, see **Troubleshooting** section below.

## 🔐 Automatic Token Management

### Login
- **Automatically saves** token to environment
- **Automatically saves** user role and ID
- **Automatically saves** instructor ID (if role is instructor)
- Check console for confirmation: `✅ Login successful`

### Logout
- **Automatically clears** all auth-related variables
- Check console for confirmation: `🔓 Token cleared from environment`

## 👥 Test Accounts

Make sure you have seeded the database with test accounts:

```bash
php artisan db:seed
```

Default accounts (if using standard seeder):
- **Super Admin**: `superadmin@example.com` / `password`
- **Admin**: `admin@example.com` / `password`
- **Instructor**: `instructor@example.com` / `password`
- **User**: `user@example.com` / `password`

## 📁 Collection Structure

### 1. Authentication
- Register (New User)
- Login (with auto token save)
- Logout (with auto token clear)
- Debug - Test Auth

### 2. Profile (All Roles)
- Get My Profile
- Update My Profile
- Delete My Profile

### 3. Public Endpoints
No authentication required, but some are **context-aware**:

#### Services
- Get All Services
- Get Service by ID

#### Instructors
- Get All Instructors
- Get Instructor by ID

#### Schedules (Context-Aware)
- Get Schedules
  - **Public/User**: See upcoming only
  - **Instructor**: See own schedules (past & future)
  - **Admin**: See all schedules
- Get Schedule by ID
- Get Schedules by Instructor

#### Reviews (Context-Aware)
- Get Reviews
  - **Public/User/Admin**: See all reviews
  - **Instructor**: See only own class reviews
- Get Reviews by Instructor

### 4. User/Customer Features
Requires authentication with `user` role:

#### Bookings
- Get My Bookings (context-aware: user sees own, admin sees all)
- Create Booking
- Cancel Booking

#### Reviews
- Create Review (must own booking, class must be finished)
- Update Review (owner only)
- Delete Review (owner or admin)

### 5. Admin & Super Admin
Requires authentication with `admin` or `super_admin` role:

#### User Management
- Get All Users
- Get Users by Role (filter: user, instructor, admin, super_admin)
- Get User by ID
- Create Admin
- Create Instructor User
- Update User (hierarchy enforced)
- Delete User (hierarchy enforced)

#### Service Management
- Create Service
- Update Service
- Delete Service

#### Instructor Management
- Create Instructor Profile
- Update Instructor Profile
- Delete Instructor Profile

#### Schedule Management
- Create Schedule (validates time overlap)
- Update Schedule (validates capacity)
- Delete Schedule (checks active bookings)

## 🎯 Testing Scenarios

### Scenario 1: Customer Journey
1. Login as **User**
2. Browse **Public Endpoints** → Services, Instructors, Schedules
3. **Create Booking** for a schedule
4. View **My Bookings**
5. After class ends, **Create Review**
6. **Update** or **Delete** your review
7. **Cancel Booking** (if needed)

### Scenario 2: Instructor View
1. Login as **Instructor**
2. Get **Schedules** → Should see only your own schedules
3. Get **Reviews** → Should see only reviews for your classes
4. Try to **Create Service** → Should get 403 Forbidden

### Scenario 3: Admin Operations
1. Login as **Admin**
2. **Create Instructor User** (role: instructor)
3. **Create Instructor Profile** for that user
4. **Create Service**
5. **Create Schedule** (assign instructor to service)
6. View **All Bookings**
7. Try to **Create Super Admin** → Should fail (hierarchy)

### Scenario 4: Super Admin God Mode
1. Login as **Super Admin**
2. **Create another Super Admin** → Should succeed
3. **View All Users**
4. Try to **Delete Master Account (ID 1)** → Should fail (protected)
5. Full access to all management endpoints

## 🔍 Debugging

### Check Authentication Status
Use the **Debug - Test Auth** endpoint to verify:
- Current user data
- Role
- Token validity
- Guard status

### Common Issues

**Variables are Empty After Login**
1. Check Postman Console (View → Show Postman Console or Cmd+Option+C)
2. Look for `✅ Login successful` message
3. If you see the message but variables are still empty:
   - Click on Collection name → Variables tab
   - Check if `token`, `user_role`, `user_id` have values in "Current Value" column
   - If "Current Value" is empty but "Initial Value" has data, click **Save** button
4. If console shows `❌ Login failed`:
   - Check your credentials in the request body
   - Verify Laravel server is running (`php artisan serve`)
   - Check response body for error message

**401 Unauthorized**
- Token not saved → Check Login response in console
- Token expired → Login again
- Variables not accessible → Make sure you're using `{{token}}` syntax in Auth header
- Collection Variables not saved → Click Collection → Variables → Save

**403 Forbidden**
- Insufficient permissions → Check your role via Debug - Test Auth endpoint
- Hierarchy violation → Admin trying to edit Super Admin
- Wrong role → You might be logged in as User trying to access Admin endpoints

**422 Validation Error**
- Check request body format (must be valid JSON)
- Ensure all required fields are present
- Check field constraints (e.g., email format, password length)
- Check Laravel logs for detailed validation errors

**Environment vs Collection Variables**
- **Collection Variables** (Recommended): Built into the collection, always available
- **Environment Variables** (Optional): Only available when environment is selected
- The collection saves to BOTH, so it works either way
- To check Collection Variables: Click Collection name → Variables tab
- To check Environment Variables: Click eye icon (👁️) in top right

## 📝 Collection & Environment Variables

The collection includes built-in variables that work automatically:
- `base_url` - API base URL (default: http://127.0.0.1:8000)
- `token` - Auth token (auto-managed by Login/Logout scripts)
- `user_role` - Current user role (auto-managed)
- `user_id` - Current user ID (auto-managed)
- `my_instructor_id` - Instructor ID if role is instructor (auto-managed)

**Where are they stored?**
- **Collection Variables**: Always available, built into the collection
- **Environment Variables**: Available when you import and select the environment file

**Which should I use?**
- Use **Collection Variables** if you only need one environment (localhost)
- Use **Environment Variables** if you need multiple environments (dev, staging, production)

**How to view them?**
- Collection Variables: Click Collection name → Variables tab
- Environment Variables: Click eye icon (👁️) in top right corner

## 🛡️ RBAC Hierarchy

```
Super Admin (God Mode)
    ↓ Can manage
Admin (Manager)
    ↓ Can manage
Instructor (Employee)
    ↓ No management access
User (Customer)
```

**Rules:**
- Master Account (ID 1) is **immutable**
- Admin **cannot** touch Super Admin accounts
- Admin **cannot** create Super Admin
- Users **cannot** delete themselves via admin endpoint
- Instructors use **context-aware** public endpoints

## 📚 Additional Resources

- **API Documentation**: See `CONTROLLER_RBAC_AUDIT.md` in project root
- **Routes**: See `routes/api.php`
- **Controllers**: See `app/Http/Controllers/Api/`

## 🎉 Happy Testing!

All endpoints are ready to test. The collection includes proper headers, authentication, and validation examples.

For issues or questions, check the Laravel logs:
```bash
tail -f storage/logs/laravel.log
```
