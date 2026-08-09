# BrewOS — Role & Permission Matrix

## 1. Purpose

This document defines the roles and permissions used throughout BrewOS.

BrewOS will use Role-Based Access Control (RBAC).

Users receive roles, and roles contain permissions.

The system should avoid hardcoded role checks throughout the application.

---

# 2. User Hierarchy

BrewOS has two major administrative levels:

BrewOS Platform
│
└── Super Admin

BrewOS Tenant
│
└── Cafe Owner
    │
    ├── Manager
    ├── Cashier
    ├── Waiter
    └── Kitchen Staff

Customer
└── Customer-facing system

---

# 3. Super Admin

The Super Admin represents the BrewOS platform owner.

## Access

The Super Admin has platform-level access.

## Permissions

### Platform

- View platform dashboard
- View platform analytics
- View platform activity
- Manage platform settings
- Manage announcements

### Cafe Management

- View cafes
- Create cafes
- Approve cafes
- Reject cafes
- Update cafe information
- Suspend cafes
- Activate cafes
- Delete cafes

### Subscription Management

- View plans
- Create plans
- Update plans
- Disable plans
- View subscriptions
- Cancel subscriptions
- View subscription history

### Payment Management

- View platform payments
- View payment transactions
- View payment failures
- View refunds

### Support

- View support tickets
- Respond to support tickets
- Close support tickets

### Security

- View audit logs
- View security events

The Super Admin must not be able to access sensitive customer payment credentials or passwords.

---

# 4. Cafe Owner

The Cafe Owner is the primary tenant administrator.

The Cafe Owner can access only their own cafe's data.

## Cafe

- View cafe
- Update cafe
- Manage cafe settings
- Manage cafe branding
- Configure taxes
- Configure cafe policies

## Branches

- View branches
- Create branches
- Update branches
- Activate/deactivate branches

## Staff

- View staff
- Create staff
- Update staff
- Disable staff
- Manage staff roles

## Roles

- View roles
- Create custom roles
- Update roles
- Delete custom roles
- Assign permissions

## Menu

- View categories
- Create categories
- Update categories
- Delete categories
- View menu items
- Create menu items
- Update menu items
- Delete menu items
- Activate/deactivate menu items

## Tables

- View tables
- Create tables
- Update tables
- Delete tables
- Generate QR codes

## Orders

- View orders
- Create orders
- Update orders
- Cancel orders
- View order history

## Customers

- View customers
- View customer history
- Update customer information
- Manage customer records

## Payments

- View payments
- View payment history
- Process supported payments
- View payment status

## Invoices

- Create invoices
- View invoices
- Download invoices
- Configure invoice settings

## Reports

- View sales reports
- View order reports
- View customer reports
- View basic analytics

## Subscription

- View current plan
- View usage
- View billing information
- Upgrade plan
- Downgrade plan
- Cancel subscription

The Cafe Owner cannot access another cafe's data.

---

# 5. Manager

The Manager manages day-to-day cafe operations.

## Dashboard

- View cafe dashboard

## Orders

- View orders
- Create orders
- Update orders
- Cancel orders
- View order history

## Menu

- View menu
- Create menu items
- Update menu items
- Activate/deactivate menu items

## Tables

- View tables
- Manage table status

## Customers

- View customers
- View customer order history

## Staff

- View staff
- Manage operational staff information

## Kitchen

- View kitchen orders
- Update order status

## Payments

- View payments
- Process supported payments

## Invoices

- Create invoices
- View invoices
- Download invoices

## Reports

- View sales reports
- View operational reports

The Manager cannot:

- Manage BrewOS subscription
- Delete the cafe
- Access platform administration
- Access another cafe

---

# 6. Cashier

The Cashier primarily handles billing and payments.

## Orders

- View orders
- Create orders
- Update orders
- Cancel orders where permitted

## Tables

- View tables
- View table status

## Customers

- Search customers
- Create customer records
- View customer history

## Payments

- Process payments
- View payment status
- View payment history

## Invoices

- Create invoices
- View invoices
- Print/download invoices

The Cashier cannot:

- Manage subscriptions
- Manage staff
- Change roles
- Change cafe settings
- Manage platform settings

---

# 7. Waiter

The Waiter primarily handles table service and order creation.

## Tables

- View tables
- View table status

## Orders

- Create orders
- View assigned orders
- Update permitted order information

## Customers

- Create customer records
- View relevant customer information

## Menu

- View menu
- View item availability

The Waiter cannot:

- Process administrative settings
- Manage staff
- Manage subscriptions
- View sensitive business reports
- Manage platform settings

---

# 8. Kitchen Staff

Kitchen Staff have a focused operational interface.

## Kitchen

- View kitchen orders
- View order items
- Update order status
- Mark items as preparing
- Mark items as ready

Example flow:

Pending
→ Preparing
→ Ready

## Restrictions

Kitchen Staff cannot:

- View cafe revenue
- Manage subscriptions
- Manage staff
- Modify cafe settings
- Manage payments
- Access platform administration

---

# 9. Customer

Customers interact with the customer-facing system.

## Menu

- View menu
- View categories
- View item details

## Ordering

- Create cart
- Create order
- View order status
- View order history where applicable

## Payments

- Initiate supported payment
- View payment status

## Invoice

- View invoice
- Download invoice where supported

## Feedback

- Submit feedback
- View their own feedback/history where applicable

Customers cannot access:

- Cafe administration
- Staff management
- Business reports
- Cafe settings
- Subscription management
- Platform administration

---

# 10. Permission Naming Convention

Permissions should follow a consistent naming convention.

Format:

`resource.action`

Examples:

- cafe.view
- cafe.update
- staff.view
- staff.create
- staff.update
- staff.delete
- menu.view
- menu.create
- menu.update
- menu.delete
- order.view
- order.create
- order.update
- order.cancel
- payment.view
- payment.create
- invoice.view
- invoice.create
- report.view
- subscription.view
- subscription.update

This makes permissions easier to manage programmatically.

---

# 11. Tenant Isolation

Every cafe-level role must only access resources belonging to its cafe.

Example:

Cafe A user:

```text
cafe_id = 1
must not access:

cafe_id = 2

Tenant isolation must be enforced through:

Authentication
Authorization
Middleware
Policies
Model scopes
Database relationships
Request validation

Tenant isolation is a critical security requirement.

12. Custom Roles

Cafe Owners should eventually be able to create custom roles.

Example:

Senior Cashier

Permissions:

order.view
order.create
payment.view
payment.create
invoice.view
invoice.create

Another example:

Inventory Manager

Permissions:

inventory.view
inventory.create
inventory.update
inventory.purchase
report.view

Custom roles must remain inside the cafe tenant.

A Cafe Owner cannot create permissions that grant platform-level Super Admin access.

13. Permission Design Principles
Permissions should be granular.
Roles should group permissions.
Cafe Owners should control cafe-level roles.
Super Admin permissions must remain platform-controlled.
Customers should have a restricted customer-facing access model.
Authorization must be checked server-side.
Frontend hiding of buttons must never be considered security.
Tenant isolation must always be enforced.
Sensitive actions should require appropriate permissions.
Permission changes should be recorded in audit logs.
14. Future Permission Features

Future versions may support:

Branch-specific permissions
Department-based permissions
Time-based permissions
Approval workflows
Temporary permissions
Custom permission groups
Permission audit history
15. Final RBAC Structure

User
│
└── Role
│
└── Permissions

The following examples use the canonical `resource.action` permission format established in Section 10
and finalized in ADR-005. Aggregate permissions (`*.manage`) are prohibited.

> Decision (ADR-005): All `*.manage` and `kitchen.*` permissions from the previous version of this
> section have been replaced with specific `resource.action` permissions. This resolves contradiction
> C4 identified in the Architecture Review.

Example:

Cafe Owner
│
├── cafe.view
├── cafe.update
├── cafe.settings.update
├── branch.view
├── branch.create
├── branch.update
├── staff.view
├── staff.create
├── staff.update
├── staff.delete
├── role.view
├── role.create
├── role.update
├── role.delete
├── menu.view
├── menu.create
├── menu.update
├── menu.delete
├── category.view
├── category.create
├── category.update
├── category.delete
├── table.view
├── table.create
├── table.update
├── table.delete
├── order.view
├── order.create
├── order.update
├── order.cancel
├── order.kitchen.view
├── order.kitchen.update
├── customer.view
├── customer.create
├── customer.update
├── payment.view
├── payment.create
├── invoice.view
├── invoice.create
├── invoice.download
├── invoice.settings.update
├── report.view
├── subscription.view
└── subscription.update

Manager
│
├── order.view
├── order.create
├── order.update
├── order.cancel
├── order.kitchen.view
├── order.kitchen.update
├── menu.view
├── menu.create
├── menu.update
├── category.view
├── table.view
├── table.update
├── customer.view
├── staff.view
├── payment.view
├── payment.create
├── invoice.view
├── invoice.create
├── invoice.download
└── report.view

Cashier
│
├── order.view
├── order.create
├── order.update
├── order.cancel
├── table.view
├── customer.view
├── customer.create
├── payment.view
├── payment.create
├── invoice.view
├── invoice.create
└── invoice.download

Waiter
│
├── table.view
├── order.view
├── order.create
├── order.update
├── customer.view
├── customer.create
└── menu.view

Kitchen Staff
│
├── order.kitchen.view
└── order.kitchen.update

Customer
│
├── menu.view
├── order.create
├── order.view
└── payment.create