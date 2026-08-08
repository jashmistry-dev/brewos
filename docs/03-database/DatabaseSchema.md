# BrewOS — Database Schema

## 1. Database Standards

BrewOS will use MySQL 8+.

### Primary Keys

All primary keys will use:

```text
BIGINT UNSIGNED AUTO_INCREMENT
Money

All monetary values will use:

DECIMAL(12,2)

Never use FLOAT or DOUBLE for financial amounts.

Timestamps

Laravel timestamps will be used:

created_at
updated_at
Timezone

Default application/business timezone:

Asia/Kolkata
Currency

Initial default currency:

INR

The values are configurable and must not be hardcoded throughout the application.

2. Users
Table: users
Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
name	VARCHAR(255)	No	—	—
email	VARCHAR(255)	No	—	UNIQUE
password	VARCHAR(255)	No	—	—
phone	VARCHAR(30)	Yes	NULL	—
status	VARCHAR(30)	No	active	INDEX
email_verified_at	TIMESTAMP	Yes	NULL	—
remember_token	VARCHAR(100)	Yes	NULL	—
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—
3. Roles
Table: roles

Stores platform-level and cafe-level roles.

Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
cafe_id	BIGINT UNSIGNED	Yes	NULL	FK, INDEX
name	VARCHAR(100)	No	—	—
slug	VARCHAR(100)	No	—	INDEX
scope	VARCHAR(20)	No	tenant	INDEX
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—
Rules

Platform role:

cafe_id = NULL
scope = platform

Tenant role:

cafe_id = specific cafe
scope = tenant

A tenant role must belong to its cafe.

4. Permissions
Table: permissions
Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
name	VARCHAR(150)	No	—	—
slug	VARCHAR(150)	No	—	UNIQUE
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—

Examples:

menu.view
menu.create
menu.update
menu.delete

order.view
order.create
order.update
order.cancel

payment.view
payment.create

invoice.view
invoice.create
5. Role Permissions
Table: role_permission

Pivot table connecting roles and permissions.

Column	Type	Nullable	Default	Key
role_id	BIGINT UNSIGNED	No	—	FK
permission_id	BIGINT UNSIGNED	No	—	FK
Primary Key

Composite primary key:

(role_id, permission_id)

This prevents the same permission from being assigned to the same role twice.

6. Cafes
Table: cafes

Central tenant table.

Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
name	VARCHAR(255)	No	—	—
slug	VARCHAR(255)	No	—	UNIQUE
email	VARCHAR(255)	Yes	NULL	—
phone	VARCHAR(30)	Yes	NULL	—
logo	VARCHAR(500)	Yes	NULL	—
status	VARCHAR(30)	No	active	INDEX
timezone	VARCHAR(64)	No	Asia/Kolkata	—
currency	CHAR(3)	No	INR	—
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—
7. Cafe Users
Table: cafe_users

Connects users to cafes.

Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
cafe_id	BIGINT UNSIGNED	No	—	FK, INDEX
user_id	BIGINT UNSIGNED	No	—	FK, INDEX
branch_id	BIGINT UNSIGNED	Yes	NULL	FK, INDEX
role_id	BIGINT UNSIGNED	No	—	FK, INDEX
status	VARCHAR(30)	No	active	INDEX
joined_at	TIMESTAMP	Yes	NULL	—
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—
Unique Constraint

A user should normally have one membership per cafe:

UNIQUE(cafe_id, user_id)
8. Branches
Table: branches
Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
cafe_id	BIGINT UNSIGNED	No	—	FK, INDEX
name	VARCHAR(255)	No	—	—
slug	VARCHAR(255)	No	—	INDEX
address	TEXT	Yes	NULL	—
phone	VARCHAR(30)	Yes	NULL	—
timezone	VARCHAR(64)	No	Asia/Kolkata	—
status	VARCHAR(30)	No	active	INDEX
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—
Unique Constraint

Branch slugs should be unique within a cafe:

UNIQUE(cafe_id, slug)
9. Customers
Table: customers
Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
cafe_id	BIGINT UNSIGNED	No	—	FK, INDEX
name	VARCHAR(255)	No	—	—
phone	VARCHAR(30)	Yes	NULL	INDEX
email	VARCHAR(255)	Yes	NULL	—
status	VARCHAR(30)	No	active	INDEX
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—

Phone numbers are not globally unique because the same customer may exist across different cafes.

10. Categories
Table: categories
Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
cafe_id	BIGINT UNSIGNED	No	—	FK, INDEX
name	VARCHAR(255)	No	—	—
description	TEXT	Yes	NULL	—
sort_order	INT UNSIGNED	No	0	—
status	VARCHAR(30)	No	active	INDEX
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—
11. Menu Items
Table: menu_items
Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
cafe_id	BIGINT UNSIGNED	No	—	FK, INDEX
category_id	BIGINT UNSIGNED	No	—	FK, INDEX
name	VARCHAR(255)	No	—	—
description	TEXT	Yes	NULL	—
price	DECIMAL(12,2)	No	0.00	—
image	VARCHAR(500)	Yes	NULL	—
status	VARCHAR(30)	No	active	INDEX
sort_order	INT UNSIGNED	No	0	—
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—
12. Restaurant Tables
Table: restaurant_tables
Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
branch_id	BIGINT UNSIGNED	No	—	FK, INDEX
name	VARCHAR(100)	No	—	—
capacity	SMALLINT UNSIGNED	No	1	—
status	VARCHAR(30)	No	available	INDEX
qr_token	VARCHAR(255)	No	—	UNIQUE
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—
13. Orders
Table: orders
Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
cafe_id	BIGINT UNSIGNED	No	—	FK, INDEX
branch_id	BIGINT UNSIGNED	No	—	FK, INDEX
table_id	BIGINT UNSIGNED	Yes	NULL	FK, INDEX
customer_id	BIGINT UNSIGNED	Yes	NULL	FK, INDEX
order_number	VARCHAR(50)	No	—	INDEX
status	VARCHAR(30)	No	pending	INDEX
subtotal	DECIMAL(12,2)	No	0.00	—
tax	DECIMAL(12,2)	No	0.00	—
discount	DECIMAL(12,2)	No	0.00	—
total	DECIMAL(12,2)	No	0.00	—
payment_status	VARCHAR(30)	No	unpaid	INDEX
created_at	TIMESTAMP	Yes	NULL	INDEX
updated_at	TIMESTAMP	Yes	NULL	—
Important

order_number is human-readable.

Example:

ORD-20260808-0001

It is separate from the database primary key.

Tenant Performance Index

Recommended:

INDEX(cafe_id, created_at)
14. Order Items
Table: order_items
Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
order_id	BIGINT UNSIGNED	No	—	FK, INDEX
menu_item_id	BIGINT UNSIGNED	No	—	FK, INDEX
quantity	INT UNSIGNED	No	1	—
unit_price	DECIMAL(12,2)	No	0.00	—
discount	DECIMAL(12,2)	No	0.00	—
tax	DECIMAL(12,2)	No	0.00	—
total	DECIMAL(12,2)	No	0.00	—
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—

unit_price stores the price at the time the order was placed.

15. Payments
Table: payments
Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
cafe_id	BIGINT UNSIGNED	No	—	FK, INDEX
order_id	BIGINT UNSIGNED	No	—	FK, INDEX
amount	DECIMAL(12,2)	No	0.00	—
method	VARCHAR(30)	No	—	INDEX
status	VARCHAR(30)	No	pending	INDEX
transaction_reference	VARCHAR(255)	Yes	NULL	INDEX
paid_at	TIMESTAMP	Yes	NULL	—
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—
16. Invoices
Table: invoices
Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
cafe_id	BIGINT UNSIGNED	No	—	FK, INDEX
order_id	BIGINT UNSIGNED	No	—	FK, INDEX
invoice_number	VARCHAR(100)	No	—	INDEX
subtotal	DECIMAL(12,2)	No	0.00	—
tax	DECIMAL(12,2)	No	0.00	—
discount	DECIMAL(12,2)	No	0.00	—
total	DECIMAL(12,2)	No	0.00	—
status	VARCHAR(30)	No	issued	INDEX
issued_at	TIMESTAMP	Yes	NULL	—
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—
Unique Constraint
UNIQUE(cafe_id, invoice_number)
17. Invoice Settings
Table: invoice_settings
Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
cafe_id	BIGINT UNSIGNED	No	—	FK, UNIQUE
business_name	VARCHAR(255)	No	—	—
address	TEXT	Yes	NULL	—
gst_number	VARCHAR(50)	Yes	NULL	—
logo	VARCHAR(500)	Yes	NULL	—
footer_text	TEXT	Yes	NULL	—
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—
18. Plans
Table: plans

Stores BrewOS subscription plans.

Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
name	VARCHAR(100)	No	—	—
slug	VARCHAR(100)	No	—	UNIQUE
description	TEXT	Yes	NULL	—
price	DECIMAL(12,2)	No	0.00	—
billing_interval	VARCHAR(20)	No	monthly	INDEX
status	VARCHAR(30)	No	active	INDEX
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—

Supported billing intervals initially:

monthly
yearly
19. Plan Features
Table: plan_features

Stores configurable features and limits.

Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
plan_id	BIGINT UNSIGNED	No	—	FK, INDEX
feature_key	VARCHAR(100)	No	—	INDEX
value	VARCHAR(255)	No	—	—
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—
Unique Constraint
UNIQUE(plan_id, feature_key)

Examples:

staff_limit = 10
table_limit = 25
qr_ordering = true
inventory = false
advanced_reports = true
20. Subscriptions
Table: subscriptions

Stores current and historical cafe subscriptions.

Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
cafe_id	BIGINT UNSIGNED	No	—	FK, INDEX
plan_id	BIGINT UNSIGNED	No	—	FK, INDEX
status	VARCHAR(30)	No	active	INDEX
starts_at	TIMESTAMP	No	—	—
ends_at	TIMESTAMP	Yes	NULL	—
trial_ends_at	TIMESTAMP	Yes	NULL	—
provider	VARCHAR(50)	Yes	NULL	INDEX
provider_subscription_id	VARCHAR(255)	Yes	NULL	INDEX
created_at	TIMESTAMP	Yes	NULL	—
updated_at	TIMESTAMP	Yes	NULL	—
Provider

Example:

razorpay
Provider Subscription ID

Stores the subscription identifier generated by the external payment provider.

Example:

provider = razorpay
provider_subscription_id = sub_ABC123
21. Audit Logs
Table: audit_logs

Stores important security and business events.

Column	Type	Nullable	Default	Key
id	BIGINT UNSIGNED	No	Auto Increment	PK
user_id	BIGINT UNSIGNED	Yes	NULL	FK, INDEX
cafe_id	BIGINT UNSIGNED	Yes	NULL	FK, INDEX
action	VARCHAR(100)	No	—	INDEX
entity_type	VARCHAR(100)	No	—	INDEX
entity_id	BIGINT UNSIGNED	Yes	NULL	INDEX
old_values	JSON	Yes	NULL	—
new_values	JSON	Yes	NULL	—
ip_address	VARCHAR(45)	Yes	NULL	—
user_agent	TEXT	Yes	NULL	—
created_at	TIMESTAMP	No	Current timestamp	INDEX

Audit logs should generally be append-only.

22. Foreign Key Rules

The following relationships must be enforced.

roles.cafe_id
    → cafes.id

cafe_users.cafe_id
    → cafes.id

cafe_users.user_id
    → users.id

cafe_users.branch_id
    → branches.id

cafe_users.role_id
    → roles.id

branches.cafe_id
    → cafes.id

customers.cafe_id
    → cafes.id

categories.cafe_id
    → cafes.id

menu_items.cafe_id
    → cafes.id

menu_items.category_id
    → categories.id

restaurant_tables.branch_id
    → branches.id

orders.cafe_id
    → cafes.id

orders.branch_id
    → branches.id

orders.table_id
    → restaurant_tables.id

orders.customer_id
    → customers.id

order_items.order_id
    → orders.id

order_items.menu_item_id
    → menu_items.id

payments.cafe_id
    → cafes.id

payments.order_id
    → orders.id

invoices.cafe_id
    → cafes.id

invoices.order_id
    → orders.id

invoice_settings.cafe_id
    → cafes.id

plan_features.plan_id
    → plans.id

subscriptions.cafe_id
    → cafes.id

subscriptions.plan_id
    → plans.id

audit_logs.user_id
    → users.id

audit_logs.cafe_id
    → cafes.id
23. Delete Strategy
Restrict deletion

Historical transaction records should generally not be deleted.

This applies to:

Orders
Order Items
Payments
Invoices
Subscriptions
Audit Logs
Soft deletion

Soft deletes may be used for:

Cafes
Menu Items
Categories
Customers
Users

Soft deletion will be introduced where business requirements justify it.

Cascade deletion

Cascade deletion may be used for dependent records where historical retention is not required.

Example:

role
  ↓
role_permission

Deleting a role can remove its role-permission relationships.

However, business transaction data must not be casually cascaded.

24. Tenant Isolation

Tenant-owned tables must always be queried in the context of the authenticated cafe.

Examples:

customers.cafe_id
categories.cafe_id
menu_items.cafe_id
orders.cafe_id
payments.cafe_id
invoices.cafe_id
subscriptions.cafe_id

A request for Cafe A must never return Cafe B's records.

Tenant isolation will be enforced through:

Authentication
Tenant middleware
Authorization policies
Model/query scopes
Foreign keys
Validation
25. Performance Indexing

Important query patterns should have appropriate indexes.

Expected indexes include:

cafes.slug

roles.cafe_id
roles.scope

permissions.slug

cafe_users.cafe_id
cafe_users.user_id
cafe_users.role_id

branches.cafe_id

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

payments.cafe_id
payments.order_id
payments.status

invoices.cafe_id
invoices.order_id

plans.slug
plans.status

plan_features.plan_id

subscriptions.cafe_id
subscriptions.plan_id
subscriptions.status

audit_logs.cafe_id
audit_logs.user_id
audit_logs.created_at

Composite indexes will be introduced where actual query patterns justify them.

Example:

INDEX(cafe_id, created_at)

for tenant-specific chronological order queries.

26. Data Integrity Rules

The application must validate relationships before writing data.

Examples:

A menu item category must belong to the same cafe as the menu item.

menu_items.cafe_id
=
categories.cafe_id

A cafe membership role must belong to the same cafe.

cafe_users.cafe_id
=
roles.cafe_id

unless the role is a platform-level role.

An order branch must belong to the order's cafe.

orders.branch_id
→ branch belonging to orders.cafe_id

These rules must be enforced at the application authorization/validation layer in addition to database foreign keys where necessary.

27. Historical Data Rules

Business transactions must preserve historical values.

For example:

Current menu price:

₹180

Old order:

unit_price = ₹150

The old order must continue to display ₹150.

Changing the current menu price must never modify historical order data.

28. Scalability Principles

The initial database will use a shared relational database.

The architecture should allow future scaling through:

Application Servers
        ↓
Load Balancer
        ↓
MySQL
        +
Redis
        +
Queue Workers
        +
Object Storage

Potential future optimizations include:

Read replicas
Database partitioning
Database sharding
Tenant-specific databases
Dedicated analytics storage

These should only be introduced when actual workload requires them.

29. Schema Implementation Rule

This document is the database implementation blueprint.

Laravel migrations must follow this document.

If the schema changes later:

Update this document.
Record the architectural decision.
Create/update the Laravel migration.
Test the migration.
Update the ER diagram if relationships change.

The documentation and actual database schema must remain synchronized.