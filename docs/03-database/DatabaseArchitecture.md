# BrewOS — Database Architecture

## 1. Architecture Overview

BrewOS will initially use a shared-database multi-tenant SaaS architecture.

The main hierarchy is:

BrewOS Platform
    ↓
Cafe / Tenant
    ↓
Branch
    ↓
Cafe Operations

The platform contains global resources such as:

- Users
- Roles
- Permissions
- Subscription Plans
- Subscriptions
- Platform-level audit records

Cafe-level resources include:

- Staff memberships
- Customers
- Categories
- Menu Items
- Tables
- Orders
- Payments
- Invoices

---

# 2. Database Principles

The database must prioritize:

- Tenant isolation
- Data integrity
- Scalability
- Query performance
- Clear relationships
- Appropriate indexing
- Referential integrity
- Maintainability

Every table must have a clearly defined ownership model.

Not every table should contain `cafe_id` automatically.

A direct `cafe_id` should be added when it provides clear tenant filtering, integrity, or query-performance benefits.

---

# 3. Users

## Table: users

Stores authenticated human users.

### Columns

| Column | Purpose |
|---|---|
| id | Primary key |
| name | User's name |
| email | Login email |
| password | Hashed password |
| phone | Optional phone number |
| status | Account status |
| email_verified_at | Email verification timestamp |
| remember_token | Authentication support |
| created_at | Creation timestamp |
| updated_at | Last update timestamp |

### Notes

Users are global identities.

A user can potentially belong to one or more cafes through cafe memberships.

---

# 4. Roles

## Table: roles

Stores roles used by the authorization system.

### Columns

| Column | Purpose |
|---|---|
| id | Primary key |
| name | Display name |
| slug | Machine-readable role identifier |
| scope | Platform or tenant |
| created_at | Creation timestamp |
| updated_at | Last update timestamp |

Examples:

- super-admin
- cafe-owner
- manager
- cashier
- waiter
- kitchen-staff

---

# 5. Permissions

## Table: permissions

Stores individual permissions.

### Columns

| Column | Purpose |
|---|---|
| id | Primary key |
| name | Permission display name |
| slug | Permission identifier |
| created_at | Creation timestamp |
| updated_at | Last update timestamp |

Examples:

- menu.view
- menu.create
- menu.update
- order.view
- order.create
- payment.create

---

# 6. Role Permissions

## Table: role_permission

Pivot table connecting roles and permissions.

### Columns

| Column | Purpose |
|---|---|
| role_id | Foreign key to roles |
| permission_id | Foreign key to permissions |

A role can have many permissions.

A permission can belong to many roles.

---

# 7. Cafes

## Table: cafes

This is the central tenant table.

### Columns

| Column | Purpose |
|---|---|
| id | Primary key |
| name | Cafe/business name |
| slug | URL-friendly unique identifier |
| email | Cafe contact email |
| phone | Cafe contact phone |
| logo | Logo storage path |
| status | Cafe account status |
| timezone | Cafe timezone |
| currency | Business currency |
| created_at | Creation timestamp |
| updated_at | Last update timestamp |

### Default Configuration

For the initial Indian market:

```text
timezone = Asia/Kolkata
currency = INR
These are defaults, not permanent hardcoded values.

The Cafe Owner can change the appropriate settings later.

# 8. Cafe Memberships

## Table: cafe_users

Connects users to cafes and determines their tenant-specific membership and role.

### Columns

| Column | Purpose |
|---|---|
| id | Primary key |
| cafe_id | Foreign key to cafes |
| user_id | Foreign key to users |
| branch_id | Optional branch assignment |
| role_id | Foreign key to roles |
| status | Membership status |
| joined_at | Membership timestamp |
| created_at | Creation timestamp |
| updated_at | Last update timestamp |

### Purpose

A user can potentially belong to multiple cafes.

Example:

User A
├── Cafe A → Manager
└── Cafe B → Owner

The `role_id` determines the user's role within that specific cafe membership.

The `branch_id` can optionally determine the user's default operational branch.

Example:

User A
↓
Cafe A
├── role → Manager
└── branch → Ahmedabad Branch


9. Branches
Table: branches

Stores physical locations of a cafe.

Columns
Column	Purpose
id	Primary key
cafe_id	Foreign key to cafes
name	Branch name
slug	URL-friendly identifier within the cafe
address	Branch address
phone	Branch phone
timezone	Branch timezone
status	Branch status
created_at	Creation timestamp
updated_at	Last update timestamp
Default Configuration

Initially:

timezone = Asia/Kolkata

A branch can override the cafe timezone in the future.

For a single-location cafe, BrewOS can create one default branch.

10. Customers
Table: customers

Stores cafe customers.

Columns
Column	Purpose
id	Primary key
cafe_id	Tenant owner
name	Customer name
phone	Customer phone
email	Customer email
status	Customer status
created_at	Creation timestamp
updated_at	Last update timestamp

Customers are separate from administrative users.

11. Categories
Table: categories

Stores menu categories.

Columns
Column	Purpose
id	Primary key
cafe_id	Tenant owner
name	Category name
description	Category description
sort_order	Display order
status	Active/inactive
created_at	Creation timestamp
updated_at	Last update timestamp

Examples:

Coffee
Tea
Burgers
Desserts
12. Menu Items
Table: menu_items

Stores individual menu products.

Columns
Column	Purpose
id	Primary key
cafe_id	Tenant owner
category_id	Category relationship
name	Menu item name
description	Item description
price	Current selling price
image	Image storage path
status	Available/unavailable
sort_order	Display order within category
created_at	Creation timestamp
updated_at	Last update timestamp
sort_order

Controls the order in which items appear to customers.

Example:

Coffee Category

Espresso     → 1
Cappuccino   → 2
Latte        → 3
Mocha        → 4

The value does not need to be globally unique.

13. Restaurant Tables
Table: restaurant_tables

Stores physical cafe tables.

Columns
Column	Purpose
id	Primary key
branch_id	Branch relationship
name	Table name/number
capacity	Maximum seating capacity
status	Table status
qr_token	Unique token used by QR ordering
created_at	Creation timestamp
updated_at	Last update timestamp

Examples:

Table 1
Table 2
Table 3
14. Orders
Table: orders

Stores customer orders.

Columns
Column	Purpose
id	Primary key
cafe_id	Tenant owner
branch_id	Branch relationship
table_id	Table relationship
customer_id	Optional customer relationship
order_number	Human-readable order number
status	Order status
subtotal	Order subtotal
tax	Tax amount
discount	Discount amount
total	Final total
payment_status	Payment state
created_at	Creation timestamp
updated_at	Last update timestamp
Order Status Examples
pending
confirmed
preparing
ready
served
completed
cancelled
15. Order Items
Table: order_items

Stores individual items belonging to an order.

Columns
Column	Purpose
id	Primary key
order_id	Order relationship
menu_item_id	Menu item relationship
quantity	Quantity ordered
unit_price	Price at time of order
discount	Item discount
tax	Item tax
total	Item total
created_at	Creation timestamp
updated_at	Last update timestamp
Important

unit_price is stored separately from the current menu item price.

This is necessary because a cafe may change the menu price after an order has been placed.

Historical orders must retain their original price.

16. Payments
Table: payments

Stores payment transactions.

Columns
Column	Purpose
id	Primary key
cafe_id	Tenant owner
order_id	Related order
amount	Payment amount
method	Payment method
status	Payment status
transaction_reference	External/internal transaction reference
paid_at	Successful payment timestamp
created_at	Creation timestamp
updated_at	Last update timestamp
Payment Methods

Examples:

cash
upi
card
razorpay
17. Invoices
Table: invoices

Stores generated invoices.

Columns
Column	Purpose
id	Primary key
cafe_id	Tenant owner
order_id	Related order
invoice_number	Human-readable invoice number
subtotal	Invoice subtotal
tax	Tax amount
discount	Discount amount
total	Final amount
status	Invoice status
issued_at	Issue timestamp
created_at	Creation timestamp
updated_at	Last update timestamp
18. Invoice Settings
Table: invoice_settings

Stores cafe-specific invoice configuration.

Columns
Column	Purpose
id	Primary key
cafe_id	Tenant owner
business_name	Name displayed on invoice
address	Invoice address
gst_number	Optional GST number
logo	Invoice logo
footer_text	Custom invoice footer
created_at	Creation timestamp
updated_at	Last update timestamp

There should normally be one active invoice configuration per cafe.

19. Subscription Plans
Table: plans

Stores plans offered by BrewOS.

Columns
Column	Purpose
id	Primary key
name	Plan name
slug	Machine-readable identifier
description	Plan description
price	Base subscription price
billing_interval	Billing frequency
status	Plan availability
created_at	Creation timestamp
updated_at	Last update timestamp
Billing Interval

Initial supported values:

monthly
yearly

Example:

Professional
₹2499
monthly

or:

Professional
₹24999
yearly
20. Plan Features
Table: plan_features

Stores configurable features and limits for each plan.

Columns
Column	Purpose
id	Primary key
plan_id	Related plan
feature_key	Feature identifier
value	Feature value
created_at	Creation timestamp
updated_at	Last update timestamp

Examples:

staff_limit = 5
table_limit = 10
qr_ordering = true
inventory = true
advanced_reports = false

This prevents subscription limits from being hardcoded in application logic.

21. Subscriptions
Table: subscriptions

Stores the active and historical subscription relationship between a cafe and a plan.

Columns
Column	Purpose
id	Primary key
cafe_id	Tenant
plan_id	Subscription plan
status	Subscription status
starts_at	Subscription start
ends_at	Subscription end
trial_ends_at	Trial expiration
provider	Payment provider
provider_subscription_id	Provider's subscription identifier
created_at	Creation timestamp
updated_at	Last update timestamp
Provider

Identifies the payment provider.

Example:

razorpay

Future examples could include:

stripe
provider_subscription_id

The payment provider creates its own subscription identifier.

Example:

provider = razorpay

provider_subscription_id = sub_ABC123

This allows BrewOS to connect its internal subscription to the external payment provider.

Example:

BrewOS Subscription #15
        ↓
Razorpay Subscription sub_ABC123
        ↓
Cafe A

This is especially important when processing payment-provider webhooks.

22. Audit Logs
Table: audit_logs

Stores important security and business activity.

Columns
Column	Purpose
id	Primary key
user_id	User who performed the action
cafe_id	Related tenant when applicable
action	Action performed
entity_type	Affected model/resource
entity_id	Affected record
old_values	Previous values
new_values	New values
ip_address	Request IP
user_agent	Client information
created_at	Event timestamp

Examples:

Menu item price changed
Staff role changed
Cafe settings changed
Subscription changed
Order cancelled
23. Future Inventory Tables

Inventory is outside the first MVP but should be designed as a future module.

Potential tables:

inventory_items
stock_transactions
suppliers
purchase_orders
purchase_order_items

These should not be implemented until the core ordering system is stable.

24. Future Loyalty Tables

Potential future tables:

loyalty_accounts
loyalty_transactions
rewards
customer_rewards
25. Future Reservation Tables

Potential future tables:

reservations
reservation_guests
26. Major Relationships
USERS
  │
  └── CAFE_USERS
          │
          └── CAFES
                │
                ├── BRANCHES
                │      │
                │      ├── RESTAURANT_TABLES
                │      │
                │      └── ORDERS
                │
                ├── CUSTOMERS
                │
                ├── CATEGORIES
                │      │
                │      └── MENU_ITEMS
                │
                ├── INVOICE_SETTINGS
                │
                └── SUBSCRIPTIONS
                       │
                       └── PLANS
                              │
                              └── PLAN_FEATURES

ORDERS
  │
  ├── ORDER_ITEMS
  │       │
  │       └── MENU_ITEMS
  │
  ├── PAYMENTS
  │
  └── INVOICES


ROLES
  │
  └── ROLE_PERMISSIONS
          │
          └── PERMISSIONS
27. Tenant Isolation

Cafe-owned data must never be accessible across tenants.

Example:

Cafe A
cafe_id = 1

must never access:

Cafe B
cafe_id = 2

Tenant isolation will be enforced through multiple layers:

Authentication
Tenant context
Middleware
Authorization policies
Model/query scopes
Foreign key relationships
Validation
Database constraints

Frontend visibility is never considered sufficient security.

28. Indexing Strategy

Indexes will be designed according to actual query patterns.

Likely indexed fields include:

cafes.slug
branches.cafe_id
branches.slug
cafe_users.cafe_id
cafe_users.user_id
customers.cafe_id
customers.phone
categories.cafe_id
menu_items.cafe_id
menu_items.category_id
restaurant_tables.branch_id
orders.cafe_id
orders.branch_id
orders.customer_id
orders.status
orders.created_at
order_items.order_id
payments.order_id
payments.cafe_id
subscriptions.cafe_id
subscriptions.status
plans.slug

Composite indexes may be introduced when queries commonly filter by multiple columns.

Example:

orders(cafe_id, created_at)

This can support tenant-specific chronological order queries.

Indexes must be validated using actual query patterns and database query plans.

29. Foreign Key Strategy

Foreign keys should be used where appropriate to maintain referential integrity.

Examples:

branches.cafe_id → cafes.id

categories.cafe_id → cafes.id

menu_items.category_id → categories.id

orders.branch_id → branches.id

orders.customer_id → customers.id

order_items.order_id → orders.id

order_items.menu_item_id → menu_items.id

payments.order_id → orders.id

invoices.order_id → orders.id

subscriptions.cafe_id → cafes.id

subscriptions.plan_id → plans.id
30. Delete Strategy

Business-critical historical data should generally not be physically deleted immediately.

Examples:

Orders
Payments
Invoices
Subscription history
Audit logs

These records may require retention for reporting, accounting, security, or support.

Soft deletes may be used for appropriate entities such as:

Menu items
Categories
Staff accounts
Customers
Cafes

Deletion policies will be finalized before implementing the migrations.

31. Historical Data

Business transactions must preserve historical values.

For example:

If a menu item costs:

₹150

and a customer places an order,

the order item stores:

unit_price = 150

If the cafe later changes the menu price to:

₹180

the old order must still display:

₹150

Historical business data must not depend on current menu configuration.

32. Performance Principles

The database must be designed for large datasets.

BrewOS should:

Avoid N+1 queries
Use appropriate indexes
Paginate large result sets
Avoid unnecessary columns in queries
Use eager loading when appropriate
Use database-side aggregation where appropriate
Avoid loading millions of records into application memory
Use transactions for critical multi-step operations
Use queues for heavy background processing
Monitor slow queries

Performance should be measured rather than assumed.

33. Scalability

The initial architecture uses one shared database.

As BrewOS grows, the architecture should allow:

Application Servers
        ↓
Load Balancer
        ↓
Database
        +
Redis
        +
Queue Workers
        +
Object Storage

If tenant count and database load become extremely large, future architectural options may include:

Read replicas
Database partitioning
Database sharding
Tenant-specific databases
Service separation

These should only be introduced when actual scale justifies the complexity.

34. Database Design Principle

Do not optimize for theoretical scale by making the schema unnecessarily complicated.

BrewOS should start with a clean relational architecture that can handle the MVP efficiently.

As actual traffic and data volume increase:

Measure
→ Identify bottleneck
→ Optimize
→ Load test
→ Monitor

Architecture decisions should be based on evidence.


---

# One correction I want you to remember

You asked whether we should set the **same timezone for all branches**.

For now:

```text
Cafe default:
Asia/Kolkata

Branch default:
Asia/Kolkata

But the database will still allow a branch-specific timezone.

That's important because later:

BrewOS
└── Cafe XYZ
    ├── Ahmedabad → Asia/Kolkata
    └── London → Europe/London

can work without redesigning the database.