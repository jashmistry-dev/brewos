# BrewOS — Entity Relationship Diagram

## 1. Database Relationship Overview

BrewOS uses a shared-database multi-tenant architecture.

The primary hierarchy is:

BrewOS Platform
    ↓
Cafe / Tenant
    ↓
Branch
    ↓
Cafe Operations


---

# 2. Core Platform Relationships

## Users → Cafe Memberships

A user can belong to one or more cafes.

Relationship:

```text
users
  1
  |
  | has many
  |
  N
cafe_users

Example:

User A
├── Cafe A → Manager
└── Cafe B → Owner

This allows the same authenticated user to potentially manage multiple cafes.

3. Cafe Relationships
Cafe → Branches

One cafe can have multiple branches.

cafes
  1
  |
  | has many
  |
  N
branches

Example:

Cafe
├── Ahmedabad Branch
├── Surat Branch
└── Vadodara Branch
Cafe → Cafe Users

One cafe can have many members.

cafes
  1
  |
  | has many
  |
  N
cafe_users
Cafe → Customers

One cafe can have many customers.

cafes
  1
  |
  | has many
  |
  N
customers

Customers belong to a cafe tenant.

Cafe → Categories

One cafe can have many menu categories.

cafes
  1
  |
  | has many
  |
  N
categories
Cafe → Menu Items

One cafe can have many menu items.

cafes
  1
  |
  | has many
  |
  N
menu_items
Cafe → Invoice Settings

A cafe normally has one active invoice configuration.

cafes
  1
  |
  | has one
  |
  1
invoice_settings
4. Branch Relationships
Branch → Restaurant Tables

One branch can contain many tables.

branches
  1
  |
  | has many
  |
  N
restaurant_tables

Example:

Ahmedabad Branch
├── Table 1
├── Table 2
├── Table 3
└── Table 4
Branch → Orders

One branch can have many orders.

branches
  1
  |
  | has many
  |
  N
orders
Branch → Cafe Memberships

A cafe membership may optionally have a default branch.

branches
  1
  |
  | has many
  |
  N
cafe_users

A user can therefore be associated with a specific branch for operational purposes.

5. Menu Relationships
Category → Menu Items

One category can contain many menu items.

categories
  1
  |
  | has many
  |
  N
menu_items

Example:

Coffee
├── Espresso
├── Cappuccino
├── Latte
└── Mocha
6. Order Relationships
Customer → Orders

A customer can have many orders.

customers
  1
  |
  | has many
  |
  N
orders

customer_id may be nullable because a customer can potentially place an order without creating a customer account/profile.

Table → Orders

A table can have many historical orders.

restaurant_tables
  1
  |
  | has many
  |
  N
orders

The order stores the table associated with the order.

Order → Order Items

One order contains one or more order items.

orders
  1
  |
  | has many
  |
  N
order_items

Example:

Order #1001
├── Cappuccino × 2
├── Burger × 1
└── Fries × 1
Menu Item → Order Items

A menu item can appear in many order items across different orders.

menu_items
  1
  |
  | has many
  |
  N
order_items

This creates the logical relationship:

Orders
   ↕
Order Items
   ↕
Menu Items
7. Payment Relationships
Order → Payments

One order can have one or multiple payment records depending on future payment requirements.

orders
  1
  |
  | has many
  |
  N
payments

This allows future support for scenarios such as:

Order Total: ₹1000

Cash: ₹400
UPI:  ₹600

For the initial MVP, most orders will probably have one successful payment.

8. Invoice Relationships
Order → Invoice

An order can have an invoice.

Initial MVP relationship:

orders
  1
  |
  | has one
  |
  1
invoices

An invoice belongs to one order.

The system may later support invoice revisions or credit notes without changing the original order.

9. Subscription Relationships
Cafe → Subscriptions

A cafe can have multiple subscription records over its lifetime.

cafes
  1
  |
  | has many
  |
  N
subscriptions

Example:

Cafe A

Starter
   ↓
Professional
   ↓
Enterprise

Historical subscriptions should be retained.

Plan → Subscriptions

One plan can be used by many cafes.

plans
  1
  |
  | has many
  |
  N
subscriptions

Example:

Professional Plan
├── Cafe A
├── Cafe B
├── Cafe C
└── Cafe D
Plan → Plan Features

One plan can have many configurable features.

plans
  1
  |
  | has many
  |
  N
plan_features

Example:

Professional
├── staff_limit = 20
├── table_limit = 50
├── inventory = true
└── advanced_reports = true
10. Role and Permission Relationships
Role → Permissions

A role can have many permissions.

A permission can belong to many roles.

Therefore this is a many-to-many relationship.

roles
  N
  |
  | many-to-many
  |
  N
permissions

Implemented using:

role_permission

Relationship:

roles
  1
  |
  N
role_permission
  N
  |
  1
permissions
11. Role Assignment

The role system needs to support tenant-specific roles.

The conceptual relationship is:

User
  ↓
Cafe Membership
  ↓
Role
  ↓
Permissions

A cafe owner should be able to create a custom role such as:

Senior Cashier

and assign permissions to it.

The role must remain within the appropriate tenant scope.

Platform-level roles such as:

Super Admin

must remain controlled by BrewOS.

12. Complete Relationship Map
                              USERS
                                |
                                | 1:N
                                |
                           CAFE_USERS
                                |
                                | N:1
                                |
                              CAFES
                    ┌───────────┼────────────┐
                    |           |            |
                   1:N         1:N          1:N
                    |           |            |
                BRANCHES     CUSTOMERS    CATEGORIES
                    |                         |
                   1:N                       1:N
                    |                         |
          RESTAURANT_TABLES              MENU_ITEMS
                    |                         |
                   1:N                       1:N
                    |                         |
                    └──────────┐    ┌─────────┘
                               |    |
                               ▼    ▼
                              ORDERS
                                |
                        ┌───────┼────────┐
                        |       |        |
                       1:N     1:N      1:1
                        |       |        |
                  ORDER_ITEMS PAYMENTS INVOICES
                        |
                       N:1
                        |
                   MENU_ITEMS


CAFES
  |
  | 1:N
  ▼
SUBSCRIPTIONS
  |
  | N:1
  ▼
PLANS
  |
  | 1:N
  ▼
PLAN_FEATURES


ROLES
  |
  | 1:N
  ▼
ROLE_PERMISSION
  ▲
  | N:1
  |
PERMISSIONS
13. Tenant Ownership Map

Tenant ownership must be clear.

CAFE
│
├── Branches
│   ├── Tables
│   └── Orders
│
├── Customers
│
├── Categories
│   └── Menu Items
│
├── Cafe Users
│
├── Invoice Settings
│
└── Subscriptions

Important transaction relationships:

Order
├── Cafe
├── Branch
├── Table
├── Customer
├── Order Items
├── Payments
└── Invoice
14. Data Isolation Rule

A user belonging to Cafe A must never access private resources belonging to Cafe B.

Example:

Cafe A
cafe_id = 1

Cafe B
cafe_id = 2

A Cafe A user requesting orders must only receive:

orders.cafe_id = 1

Tenant isolation must be enforced server-side.

Frontend filtering is not sufficient.

15. Relationship Summary
Parent	Child	Relationship
User	Cafe User	1:N
Cafe	Cafe User	1:N
Cafe	Branch	1:N
Cafe	Customer	1:N
Cafe	Category	1:N
Cafe	Menu Item	1:N
Cafe	Invoice Settings	1:1
Branch	Table	1:N
Branch	Order	1:N
Category	Menu Item	1:N
Customer	Order	1:N
Table	Order	1:N
Order	Order Item	1:N
Menu Item	Order Item	1:N
Order	Payment	1:N
Order	Invoice	1:1
Cafe	Subscription	1:N
Plan	Subscription	1:N
Plan	Plan Feature	1:N
Role	Permission	N:M
16. Important Design Decisions
Decision 1

A user is a global identity.

Cafe membership determines which tenant the user is operating within.

Decision 2

A cafe can have multiple branches.

Decision 3

Customers are separate from administrative users.

Decision 4

Orders preserve historical item prices through order_items.unit_price.

Decision 5

Subscriptions retain historical records instead of replacing the previous subscription.

Decision 6

Roles and permissions use a many-to-many relationship.

Decision 7

Tenant isolation is enforced at the application and authorization layers.

Decision 8

The database should remain relational and normalized where appropriate, while allowing carefully chosen denormalization later if measured performance requirements justify it.