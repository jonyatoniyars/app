# BPDA Telemedicine App - Feature Updates

## Overview
This update implements comprehensive prescription management, video telehealth integration, admin dashboard features, and RBAC enforcement improvements to the BPDA Telemedicine platform.

## New Features

### 1. Prescription Management & Google Docs Integration

#### Real-time Collaborative Editing
- **Endpoint**: `/api/google-docs.php`
- **Features**:
  - Create Google Docs integration for each prescription
  - Live collaborative editing between Health Workers and Doctors
  - Document sync tracking with `last_synced_at` timestamp
  - Access control via Google OAuth 2.0

#### Database Tables
- `google_docs_integrations` - Links prescriptions to Google Docs
- `prescription_permissions` - Fine-grained access control (VIEW, EDIT, REVIEW)

#### API Methods
```
GET  /api/google-docs.php              - List Google Docs integrations
GET  /api/google-docs.php?id=<id>      - Get single integration
POST /api/google-docs.php              - Create new Google Doc
DELETE /api/google-docs.php?id=<id>    - Remove integration
```

### 2. Video Telehealth Integration (Google Meet API)

#### Call Escalation & Routing Logic
- **Endpoint**: `/api/video-calls.php`
- **Escalation Flow**:
  1. Health Worker initiates call to assigned Doctor
  2. System checks if Doctor is online
  3. If offline/unresponsive → auto-escalate to Admin queue
  4. Admin can manually re-route to any available Doctor

#### Database Tables
- `video_call_escalations` - Track escalation history and routing
- `doctor_online_status` - Real-time doctor availability tracking

#### API Methods
```
GET    /api/video-calls.php                        - List calls
POST   /api/video-calls.php                        - Initiate call
PATCH  /api/video-calls.php?id=<id>                - Respond to call (ACCEPTED/DECLINED)
POST   /api/video-calls.php?action=reroute         - Admin reroute call
PUT    /api/video-calls.php?action=status          - Update doctor online status
GET    /api/video-calls.php?id=<id>&details=escalation - Get escalation details
```

### 3. Prescription Template Management

#### Features
- Central repository of customizable prescription templates
- Approval workflow (DRAFT → PENDING_APPROVAL → APPROVED/REJECTED)
- Admin override capability
- Track template creator and approver

#### Database Table
- `prescription_templates` - Template storage with approval status

#### API Methods
```
GET  /api/prescription-templates.php                - List templates
GET  /api/prescription-templates.php?id=<id>        - Get template details
POST /api/prescription-templates.php                - Create template
PATCH /api/prescription-templates.php?id=<id>       - Update template
POST /api/prescription-templates.php?action=review  - Admin approve/reject
DELETE /api/prescription-templates.php?id=<id>      - Delete template
```

### 4. Admin Dashboard & Search Features

#### Global Prescription Viewer
- **Endpoint**: `/api/admin/dashboard.php`
- **Filters**:
  - By Doctor name
  - By Patient name
  - By Prescription ID
  - By Date range
  - By Status (DRAFT, SUBMITTED, REVIEWED)
- **Export**: CSV and JSON formats
- **Statistics**: Dashboard metrics and summaries

#### Role-based User Management
- **Endpoint**: `/api/admin/user-management.php`
- **Three Sub-views**:
  1. **Doctors** - List all doctors, manage assignments
  2. **Health Workers** - List workers, view prescriptions
  3. **Suspended Accounts** - Centralized storage for deactivated users

#### API Methods - Dashboard
```
GET  /api/admin/dashboard.php                           - List prescriptions (with filters)
GET  /api/admin/dashboard.php?action=statistics         - Dashboard stats
PATCH /api/admin/dashboard.php?action=assign-doctor     - Assign doctor to prescription
GET  /api/admin/dashboard.php?action=export&format=csv  - Export prescriptions
```

#### API Methods - User Management
```
GET  /api/admin/user-management.php?type=doctors          - List doctors
GET  /api/admin/user-management.php?type=health_workers   - List health workers
GET  /api/admin/user-management.php?type=suspended        - List suspended users
GET  /api/admin/user-management.php?id=<id>              - Get user details
PATCH /api/admin/user-management.php?id=<id>             - Update user info
POST /api/admin/user-management.php?action=assign        - Assign doctor to worker
DELETE /api/admin/user-management.php?action=unassign    - Remove assignment
POST /api/admin/user-management.php?action=toggle-suspend - Suspend/unsuspend user
```

### 5. Bug Fixes & RBAC Enforcement

#### Doctor Dashboard "My Workers" Fix
- **File**: `/api/doctor/my_workers.php`
- **Fixed Issues**:
  - Proper authorization middleware
  - Correct role-based access control
  - Full permission validation
- **Response includes**:
  - Worker details (name, email, phone, status)
  - Assignment timestamp
  - Recent prescriptions for each worker

#### Enhanced RBAC
- Strict assignment hierarchy enforcement
- Admin maintains full override capabilities
- Audit logging for all administrative actions

## Database Schema Updates

### New Tables
```sql
CREATE TABLE prescription_templates { ... }
CREATE TABLE google_docs_integrations { ... }
CREATE TABLE video_call_escalations { ... }
CREATE TABLE doctor_online_status { ... }
CREATE TABLE prescription_permissions { ... }
```

### Modified Tables
- `prescriptions` - Added `doctor_id` and `google_doc_id` fields
- Indexes added for improved query performance

## Installation & Setup

1. **Database Migration**:
   ```bash
   # Import updated schema
   mysql -u root -p database_name < database/schema.sql
   ```

2. **Google OAuth Configuration** (for Google Docs/Meet):
   ```php
   // In config/database.php or .env
   define('GOOGLE_CLIENT_ID', 'your-client-id.apps.googleusercontent.com');
   define('GOOGLE_CLIENT_SECRET', 'your-secret');
   define('GOOGLE_REDIRECT_URI', 'https://your-domain.com/api/google-docs.php?action=callback');
   ```

3. **Environment Variables** (if using .env):
   ```
   GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=your-secret
   ```

## Usage Examples

### Create a Prescription with Doctor Assignment
```bash
POST /api/prescriptions.php
{
  "patientName": "John Doe",
  "patientAge": 45,
  "patientGender": "male",
  "chiefComplaints": "Fever and cough",
  "items": [
    {
      "medicineId": "m-001",
      "dose": "1 tablet",
      "frequency": "3 times daily",
      "duration": "5 days"
    }
  ],
  "status": "DRAFT"
}
```
Response includes auto-assigned doctor based on health worker's assignment.

### Initiate Video Call with Auto-Escalation
```bash
POST /api/video-calls.php
{
  "note": "Patient consultation needed"
}
```
System automatically escalates to admin if assigned doctor is offline.

### Manage Prescription Templates
```bash
POST /api/prescription-templates.php
{
  "name": "Cold & Flu Template",
  "description": "Standard template for viral respiratory infections",
  "templateContent": {
    "chiefComplaints": ["Fever", "Cough", "Sore throat"],
    "onExamination": "Temperature, Throat examination",
    "medicines": [
      {"medicineId": "m-001", "dose": "1 tablet", "frequency": "3x daily"}
    ]
  }
}
```

## Security Considerations

- **Authentication**: Google OAuth 2.0 for Docs/Meet integration
- **Authorization**: Strict RBAC with role-based access control
- **Audit Logging**: All administrative actions logged in `audit_logs`
- **Data Isolation**: Health Workers see only their assigned Doctor's data
- **Permission Validation**: Multi-layer permission checks on all endpoints

## Performance Optimizations

- Database indexes on frequently queried fields
- Pagination support (default 20 items, max 100)
- Efficient JOIN queries with proper filtering
- Prepared statements for SQL injection prevention

## API Response Format

All endpoints follow standard JSON response format:

**Success (200-201)**:
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 100,
    "totalPages": 5
  }
}
```

**Error (400-403-404-500)**:
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": { ... }
  }
}
```

## Support & Documentation

For detailed API documentation, see the individual endpoint files in `/api/` directory.

## Version Information

- **Release**: v2.0.0
- **Last Updated**: 2026-07-23
- **PHP Version**: 7.4+
- **MySQL Version**: 5.7+
