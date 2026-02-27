# Monastery Healthcare and Donation Management System
## Project Structure Documentation

### Project Overview
A role-based web application for managing healthcare services and donations in a monastery setting, built with PHP 8, MySQL, and Bootstrap 5.

### Technology Stack
- **Backend**: PHP 8 (Core PHP, no frameworks)
- **Database**: MySQL 8.0+  
- **Frontend**: HTML5, CSS3, Bootstrap 5
- **JavaScript**: ES6+ for client-side validation
- **Server**: XAMPP (Apache, MySQL, PHP)
- **Security**: Prepared statements, password hashing, session management

### Folder Structure

```
Monastery-Healthcare-and-Donation-Management-System/
│
├── README.md                           # Project documentation
├── index.php                          # Main entry point / Landing page
├── config.php                         # Database and system configuration
├── .htaccess                          # Apache configuration
│
├── assets/                            # Static assets
│   ├── css/                          # Stylesheets
│   │   ├── bootstrap.min.css         # Bootstrap 5 framework
│   │   ├── style.css                 # Custom styles
│   │   └── admin.css                 # Admin-specific styles
│   ├── js/                          # JavaScript files
│   │   ├── bootstrap.bundle.min.js   # Bootstrap JS
│   │   ├── validation.js             # Form validation
│   │   └── dashboard.js              # Dashboard functionality
│   └── images/                      # Images and icons
│       ├── logo.png                 # Monastery logo
│       └── avatars/                 # User profile images
│
├── includes/                          # Common PHP files
│   ├── config.php                    # Database configuration
│   ├── functions.php                 # Common utility functions
│   ├── auth.php                      # Authentication functions
│   ├── header.php                    # Common HTML header
│   ├── footer.php                    # Common HTML footer
│   ├── sidebar.php                   # Dashboard sidebar
│   └── database.php                  # Database connection class
│
├── auth/                             # Authentication module
│   ├── login.php                     # Login page
│   ├── logout.php                    # Logout handler
│   ├── register.php                  # Donator registration
│   └── forgot-password.php           # Password recovery
│
├── admin/                            # Admin module
│   ├── index.php                     # Admin dashboard
│   ├── dashboard.php                 # Admin main dashboard
│   ├── monks/                        # Monk management
│   │   ├── index.php                # List monks
│   │   ├── add.php                  # Add new monk
│   │   ├── edit.php                 # Edit monk
│   │   ├── view.php                 # View monk details
│   │   └── delete.php               # Delete monk
│   ├── doctors/                      # Doctor management
│   │   ├── index.php               
│   │   ├── add.php                 
│   │   ├── edit.php                
│   │   ├── view.php                
│   │   └── delete.php              
│   ├── rooms/                        # Room management
│   │   ├── index.php               
│   │   ├── add.php                 
│   │   ├── edit.php                
│   │   ├── assign.php               # Assign monks to rooms
│   │   └── delete.php              
│   ├── donations/                    # Donation management
│   │   ├── index.php                # All donations
│   │   ├── categories.php           # Manage categories
│   │   ├── add-category.php        
│   │   └── view.php                 # View donation details
│   ├── expenses/                     # Expense management
│   │   ├── index.php               
│   │   ├── add.php                 
│   │   ├── edit.php                
│   │   └── approve.php             
│   └── reports/                      # Financial reports
│       ├── index.php               
│       ├── monthly.php             
│       ├── transparency.php        
│       └── export.php              
│
├── monk/                             # Monk module
│   ├── index.php                     # Monk dashboard
│   ├── dashboard.php                 # Main dashboard
│   ├── profile.php                   # View profile
│   ├── medical-history.php           # Medical records
│   ├── appointments/                 # Appointment management
│   │   ├── index.php                # View appointments
│   │   ├── book.php                 # Book new appointment
│   │   └── cancel.php               # Cancel appointment
│   └── settings.php                  # Account settings
│
├── doctor/                           # Doctor module
│   ├── index.php                    # Doctor dashboard
│   ├── dashboard.php                # Main dashboard
│   ├── appointments/                # Appointment management
│   │   ├── index.php               # View assigned appointments
│   │   ├── view.php                # Appointment details
│   │   └── update-status.php       # Update appointment status
│   ├── medical-records/             # Medical records
│   │   ├── index.php               # View records
│   │   ├── add.php                 # Add new record
│   │   ├── edit.php                # Edit record
│   │   └── view.php                # View record details
│   └── patients/                    # Patient (monk) management
│       ├── index.php               # List patients
│       └── history.php             # Patient medical history
│
├── donator/                          # Donator module
│   ├── index.php                    # Donator dashboard
│   ├── dashboard.php                # Main dashboard
│   ├── donate.php                   # Make donation
│   ├── history.php                  # Donation history
│   ├── categories.php               # View donation categories
│   └── profile.php                  # Manage profile
│
├── reports/                          # Reporting module
│   ├── generate.php                 # Report generation
│   ├── templates/                   # Report templates
│   └── exports/                     # Generated report files
│
├── uploads/                          # File uploads
│   ├── profiles/                    # Profile images
│   ├── documents/                   # Document uploads
│   └── receipts/                    # Receipt images
│
└── database/                         # Database related files
    ├── schema.sql                   # Database schema
    ├── migrations/                  # Database migrations
    ├── seeders/                     # Sample data
    └── backups/                     # Database backups
```

### User Roles & Access Control

#### 1. Admin Role
- **Access**: Full system access
- **Capabilities**: 
  - Manage all users (monks, doctors, donators)
  - Manage rooms and assignments
  - Oversee donations and expenses
  - Generate financial reports
  - System configuration

#### 2. Monk Role
- **Access**: Limited to personal data and appointments
- **Capabilities**:
  - View personal medical history
  - Book medical appointments
  - Update personal profile
  - View appointment status

#### 3. Doctor Role  
- **Access**: Medical records and appointments
- **Capabilities**:
  - View assigned appointments
  - Add/edit medical records
  - View patient medical history
  - Manage appointment status

#### 4. Donator Role
- **Access**: Donation-related features only
- **Capabilities**:
  - Make donations
  - View donation history
  - View donation categories
  - Manage personal profile

### Security Features

1. **Authentication**
   - Secure password hashing using `password_hash()`
   - Session-based authentication
   - Role-based access control
   - Session timeout management

2. **Data Protection**
   - Prepared statements for SQL operations
   - Input validation and sanitization
   - XSS protection
   - CSRF token validation

3. **Access Control**
   - Role-based page restrictions
   - Function-level security checks
   - Audit logging for sensitive operations

### Database Design

The system uses 12 main tables:
- **User Tables**: admins, monks, doctors, donators
- **Healthcare**: appointments, medical_records, rooms
- **Financial**: donations, donation_categories, expenses  
- **System**: system_logs, user_sessions

### Key Features

1. **Healthcare Management**
   - Appointment booking system
   - Medical record keeping
   - Patient history tracking
   - Room assignment management

2. **Donation Management**
   - Multi-category donation system
   - Expense tracking by category
   - Financial transparency dashboard
   - Receipt generation

3. **Reporting System**
   - Monthly financial reports
   - Donation summaries
   - Expense analysis
   - Transparency dashboard

4. **User Management**
   - Role-based dashboards
   - Profile management
   - Activity logging
   - Session management

### Development Guidelines

1. **Code Standards**
   - Follow PSR-12 coding standards
   - Use meaningful variable names
   - Comment complex logic
   - Implement error handling

2. **Security Practices**
   - Always use prepared statements
   - Validate all user inputs
   - Implement proper session management
   - Log security events

3. **Database Practices**
   - Use transactions for related operations
   - Implement proper indexing
   - Regular backup procedures
   - Monitor performance

### Installation Requirements

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache web server (XAMPP recommended)
- Modern web browser with JavaScript enabled

### Next Steps

After setting up the folder structure and database:
1. Configure database connection
2. Implement authentication system
3. Create base dashboard templates
4. Develop CRUD operations
5. Build role-specific modules
6. Implement reporting features
7. Add security layers
8. Testing and optimization