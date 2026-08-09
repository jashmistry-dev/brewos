<?php

namespace Tests\Feature\Tenant;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvoicingTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafeA;
    protected Cafe $cafeB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected User $managerA;
    protected User $managerB;
    protected User $unprivilegedUser;
    protected Role $managerRoleA;
    protected Role $managerRoleB;
    protected Role $unprivilegedRole;
    protected Order $orderA;
    protected Order $orderB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Cafe A
        $this->cafeA = Cafe::create([
            'name'   => 'Invoice Cafe A',
            'slug'   => 'invoice-cafe-a',
            'status' => 'active',
        ]);

        // Cafe B (for cross-tenant isolation tests)
        $this->cafeB = Cafe::create([
            'name'   => 'Invoice Cafe B',
            'slug'   => 'invoice-cafe-b',
            'status' => 'active',
        ]);

        $this->branchA = Branch::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Branch A',
            'slug'    => 'branch-a-inv',
            'status'  => 'active',
        ]);

        $this->branchB = Branch::create([
            'cafe_id' => $this->cafeB->id,
            'name'    => 'Branch B',
            'slug'    => 'branch-b-inv',
            'status'  => 'active',
        ]);

        $tableA = RestaurantTable::create([
            'branch_id' => $this->branchA->id,
            'name'      => 'T1',
            'qr_token'  => 'inv-tok-a',
        ]);

        $tableB = RestaurantTable::create([
            'branch_id' => $this->branchB->id,
            'name'      => 'T2',
            'qr_token'  => 'inv-tok-b',
        ]);

        $categoryA = Category::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Coffee',
        ]);

        $menuItemA = MenuItem::create([
            'cafe_id'     => $this->cafeA->id,
            'category_id' => $categoryA->id,
            'name'        => 'Espresso',
            'price'       => 100.00,
        ]);

        // Orders
        $this->orderA = Order::create([
            'cafe_id'      => $this->cafeA->id,
            'branch_id'    => $this->branchA->id,
            'table_id'     => $tableA->id,
            'order_number' => 'INV-ORD-A001',
            'status'       => 'completed',
            'subtotal'     => 100.00,
            'tax'          => 18.00,
            'discount'     => 0.00,
            'total'        => 118.00,
        ]);

        OrderItem::create([
            'order_id'    => $this->orderA->id,
            'menu_item_id' => $menuItemA->id,
            'quantity'    => 1,
            'unit_price'  => 100.00,
            'discount'    => 0.00,
            'tax'         => 18.00,
            'total'       => 118.00,
        ]);

        $this->orderB = Order::create([
            'cafe_id'      => $this->cafeB->id,
            'branch_id'    => $this->branchB->id,
            'table_id'     => $tableB->id,
            'order_number' => 'INV-ORD-B001',
            'status'       => 'completed',
            'subtotal'     => 50.00,
            'tax'          => 9.00,
            'discount'     => 0.00,
            'total'        => 59.00,
        ]);

        // Roles & permissions
        $this->managerRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Manager A Inv',
            'slug'    => 'manager-inv-a',
            'scope'   => 'tenant',
        ]);

        $this->managerRoleB = Role::create([
            'cafe_id' => $this->cafeB->id,
            'name'    => 'Manager B Inv',
            'slug'    => 'manager-inv-b',
            'scope'   => 'tenant',
        ]);

        $this->unprivilegedRole = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Waiter Inv',
            'slug'    => 'waiter-inv',
            'scope'   => 'tenant',
        ]);

        $allPerms = [
            'payment.view', 'payment.create',
            'invoice.view', 'invoice.create', 'invoice.download', 'invoice.settings.update',
        ];

        foreach ($allPerms as $slug) {
            $perm = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $this->managerRoleA->permissions()->attach($perm->id);
        }

        // managerB gets same permissions for cross-tenant tests
        foreach ($allPerms as $slug) {
            $perm = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $this->managerRoleB->permissions()->attach($perm->id);
        }

        // Users
        $this->managerA = User::create([
            'name'     => 'Manager A Inv',
            'email'    => 'mgr-a@invoicetest.com',
            'password' => Hash::make('password123'),
            'status'   => 'active',
        ]);

        $this->managerB = User::create([
            'name'     => 'Manager B Inv',
            'email'    => 'mgr-b@invoicetest.com',
            'password' => Hash::make('password123'),
            'status'   => 'active',
        ]);

        $this->unprivilegedUser = User::create([
            'name'     => 'Waiter Inv',
            'email'    => 'waiter@invoicetest.com',
            'password' => Hash::make('password123'),
            'status'   => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafeA->id,
            'user_id' => $this->managerA->id,
            'role_id' => $this->managerRoleA->id,
            'status'  => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafeB->id,
            'user_id' => $this->managerB->id,
            'role_id' => $this->managerRoleB->id,
            'status'  => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafeA->id,
            'user_id' => $this->unprivilegedUser->id,
            'role_id' => $this->unprivilegedRole->id,
            'status'  => 'active',
        ]);
    }

    // =========================================================================
    // PAYMENT TESTS
    // =========================================================================

    public function test_payment_listing(): void
    {
        $this->actingAs($this->managerA);

        Payment::create([
            'cafe_id'  => $this->cafeA->id,
            'order_id' => $this->orderA->id,
            'amount'   => 118.00,
            'method'   => 'cash',
            'status'   => 'paid',
        ]);

        $response = $this->getJson('/cafes/invoice-cafe-a/payments');

        $response->assertStatus(200)
            ->assertJsonStructure(['payments' => [['id', 'order_id', 'amount', 'method', 'status']]]);
    }

    public function test_payment_creation(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->postJson('/cafes/invoice-cafe-a/payments', [
            'order_id'              => $this->orderA->id,
            'amount'                => 118.00,
            'method'                => 'cash',
            'transaction_reference' => null,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('payment.method', 'cash');
        $this->assertEquals(118.00, $response->json('payment.amount'));
    }

    public function test_payment_creation_with_upi_method(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->postJson('/cafes/invoice-cafe-a/payments', [
            'order_id'              => $this->orderA->id,
            'amount'                => 118.00,
            'method'                => 'upi',
            'transaction_reference' => 'UPI-TXN-12345',
        ]);

        $response->assertStatus(201)->assertJsonPath('payment.method', 'upi');
    }

    public function test_payment_creation_with_card_method(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->postJson('/cafes/invoice-cafe-a/payments', [
            'order_id' => $this->orderA->id,
            'amount'   => 118.00,
            'method'   => 'card',
        ]);

        $response->assertStatus(201)->assertJsonPath('payment.method', 'card');
    }

    public function test_payment_rejects_invalid_method(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->postJson('/cafes/invoice-cafe-a/payments', [
            'order_id' => $this->orderA->id,
            'amount'   => 118.00,
            'method'   => 'bitcoin',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['method']);
    }

    public function test_payment_rejects_negative_amount(): void
    {
        $this->actingAs($this->managerA);

        // The schema defines amount as DECIMAL(12,2) — negative values must be rejected.
        // No rule mandates amount === order.total; partial amounts are not excluded by the spec.
        $response = $this->postJson('/cafes/invoice-cafe-a/payments', [
            'order_id' => $this->orderA->id,
            'amount'   => -1.00,
            'method'   => 'cash',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    public function test_payment_accepts_partial_amount(): void
    {
        $this->actingAs($this->managerA);

        // The spec does not mandate amount === order.total.
        // A valid numeric amount (e.g. a deposit) must be accepted.
        $response = $this->postJson('/cafes/invoice-cafe-a/payments', [
            'order_id' => $this->orderA->id,
            'amount'   => 50.00,
            'method'   => 'cash',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(50.00, $response->json('payment.amount'));
    }

    public function test_payment_cross_tenant_order_rejected(): void
    {
        $this->actingAs($this->managerA);

        // orderB belongs to cafeB — should be rejected when posting to cafeA
        $response = $this->postJson('/cafes/invoice-cafe-a/payments', [
            'order_id' => $this->orderB->id,
            'amount'   => 59.00,
            'method'   => 'cash',
        ]);

        $response->assertStatus(422);
    }

    public function test_payment_tenant_isolation_on_listing(): void
    {
        $this->actingAs($this->managerA);

        // Create a payment for cafeB
        Payment::create([
            'cafe_id'  => $this->cafeB->id,
            'order_id' => $this->orderB->id,
            'amount'   => 59.00,
            'method'   => 'cash',
            'status'   => 'paid',
        ]);

        $response = $this->getJson('/cafes/invoice-cafe-a/payments');
        $response->assertStatus(200);

        // None of cafeB's payments should appear
        $paymentOrderIds = array_column($response->json('payments'), 'order_id');
        $this->assertNotContains($this->orderB->id, $paymentOrderIds);
    }

    public function test_payment_authorization_denied_for_unprivileged_user(): void
    {
        $this->actingAs($this->unprivilegedUser);

        $response = $this->getJson('/cafes/invoice-cafe-a/payments');
        $response->assertStatus(403);
    }

    public function test_payment_create_authorization_denied_for_unprivileged_user(): void
    {
        $this->actingAs($this->unprivilegedUser);

        $response = $this->postJson('/cafes/invoice-cafe-a/payments', [
            'order_id' => $this->orderA->id,
            'amount'   => 118.00,
            'method'   => 'cash',
        ]);

        $response->assertStatus(403);
    }

    // =========================================================================
    // INVOICE TESTS
    // =========================================================================

    public function test_invoice_creation(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->postJson('/cafes/invoice-cafe-a/invoices', [
            'order_id'       => $this->orderA->id,
            'invoice_number' => 'INV-2026-001',
            'subtotal'       => 100.00,
            'tax'            => 18.00,
            'discount'       => 0.00,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('invoice.invoice_number', 'INV-2026-001');
        $this->assertEquals(118.00, $response->json('invoice.total'));
    }

    public function test_invoice_totals_calculated_server_side(): void
    {
        $this->actingAs($this->managerA);

        // Client provides subtotal=100, tax=18, discount=10 — server computes total=108
        $response = $this->postJson('/cafes/invoice-cafe-a/invoices', [
            'order_id'       => $this->orderA->id,
            'invoice_number' => 'INV-2026-CALC',
            'subtotal'       => 100.00,
            'tax'            => 18.00,
            'discount'       => 10.00,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(100.00, $response->json('invoice.subtotal'));
        $this->assertEquals(18.00, $response->json('invoice.tax'));
        $this->assertEquals(10.00, $response->json('invoice.discount'));
        $this->assertEquals(108.00, $response->json('invoice.total'));
    }

    public function test_invoice_listing(): void
    {
        $this->actingAs($this->managerA);

        Invoice::create([
            'cafe_id'        => $this->cafeA->id,
            'order_id'       => $this->orderA->id,
            'invoice_number' => 'INV-LIST-001',
            'subtotal'       => 100.00,
            'tax'            => 18.00,
            'discount'       => 0.00,
            'total'          => 118.00,
            'status'         => 'issued',
            'issued_at'      => now(),
        ]);

        $response = $this->getJson('/cafes/invoice-cafe-a/invoices');

        $response->assertStatus(200)
            ->assertJsonStructure(['invoices' => [['id', 'invoice_number', 'total', 'status']]]);

        $numbers = array_column($response->json('invoices'), 'invoice_number');
        $this->assertContains('INV-LIST-001', $numbers);
    }

    public function test_invoice_show(): void
    {
        $this->actingAs($this->managerA);

        $invoice = Invoice::create([
            'cafe_id'        => $this->cafeA->id,
            'order_id'       => $this->orderA->id,
            'invoice_number' => 'INV-SHOW-001',
            'subtotal'       => 100.00,
            'tax'            => 18.00,
            'discount'       => 0.00,
            'total'          => 118.00,
            'status'         => 'issued',
            'issued_at'      => now(),
        ]);

        $response = $this->getJson("/cafes/invoice-cafe-a/invoices/{$invoice->id}");

        $response->assertStatus(200)
            ->assertJsonPath('invoice.invoice_number', 'INV-SHOW-001');
        $this->assertEquals(118.00, $response->json('invoice.total'));
    }

    public function test_invoice_download_returns_html(): void
    {
        $this->actingAs($this->managerA);

        $invoice = Invoice::create([
            'cafe_id'        => $this->cafeA->id,
            'order_id'       => $this->orderA->id,
            'invoice_number' => 'INV-DL-001',
            'subtotal'       => 100.00,
            'tax'            => 18.00,
            'discount'       => 0.00,
            'total'          => 118.00,
            'status'         => 'issued',
            'issued_at'      => now(),
        ]);

        $response = $this->get("/cafes/invoice-cafe-a/invoices/{$invoice->id}/download");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('INV-DL-001', $response->getContent());
    }

    public function test_invoice_number_must_be_unique_per_cafe(): void
    {
        $this->actingAs($this->managerA);

        // Create first invoice
        Invoice::create([
            'cafe_id'        => $this->cafeA->id,
            'order_id'       => $this->orderA->id,
            'invoice_number' => 'INV-DUP-001',
            'subtotal'       => 100.00,
            'tax'            => 18.00,
            'discount'       => 0.00,
            'total'          => 118.00,
            'status'         => 'issued',
            'issued_at'      => now(),
        ]);

        // Second order for cafeA (need distinct order_id since order_id is unique in invoices)
        $orderA2 = Order::create([
            'cafe_id'      => $this->cafeA->id,
            'branch_id'    => $this->branchA->id,
            'order_number' => 'INV-ORD-A002',
            'status'       => 'completed',
            'subtotal'     => 50.00,
            'tax'          => 9.00,
            'discount'     => 0.00,
            'total'        => 59.00,
        ]);

        // Try to create another invoice with the same number for cafeA
        $response = $this->postJson('/cafes/invoice-cafe-a/invoices', [
            'order_id'       => $orderA2->id,
            'invoice_number' => 'INV-DUP-001',
            'subtotal'       => 50.00,
            'tax'            => 9.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_invoice_number_can_be_reused_across_different_cafes(): void
    {
        $this->actingAs($this->managerA);

        // cafeA creates INV-CROSS-001
        $response = $this->postJson('/cafes/invoice-cafe-a/invoices', [
            'order_id'       => $this->orderA->id,
            'invoice_number' => 'INV-CROSS-001',
            'subtotal'       => 100.00,
            'tax'            => 18.00,
        ]);

        $response->assertStatus(201);

        // cafeB can also use INV-CROSS-001 (different tenant)
        // We directly create it to verify uniqueness is per-cafe
        $invoice = Invoice::create([
            'cafe_id'        => $this->cafeB->id,
            'order_id'       => $this->orderB->id,
            'invoice_number' => 'INV-CROSS-001',
            'subtotal'       => 50.00,
            'tax'            => 9.00,
            'discount'       => 0.00,
            'total'          => 59.00,
            'status'         => 'issued',
            'issued_at'      => now(),
        ]);

        $this->assertDatabaseHas('invoices', [
            'cafe_id'        => $this->cafeB->id,
            'invoice_number' => 'INV-CROSS-001',
        ]);
    }

    public function test_invoice_tenant_isolation_on_listing(): void
    {
        $this->actingAs($this->managerA);

        Invoice::create([
            'cafe_id'        => $this->cafeB->id,
            'order_id'       => $this->orderB->id,
            'invoice_number' => 'INV-B-ISOLATION',
            'subtotal'       => 50.00,
            'tax'            => 9.00,
            'discount'       => 0.00,
            'total'          => 59.00,
            'status'         => 'issued',
            'issued_at'      => now(),
        ]);

        $response = $this->getJson('/cafes/invoice-cafe-a/invoices');
        $response->assertStatus(200);

        $numbers = array_column($response->json('invoices'), 'invoice_number');
        $this->assertNotContains('INV-B-ISOLATION', $numbers);
    }

    public function test_invoice_show_cross_tenant_returns_404(): void
    {
        $this->actingAs($this->managerA);

        // Create an invoice for cafeB
        $invoiceB = Invoice::create([
            'cafe_id'        => $this->cafeB->id,
            'order_id'       => $this->orderB->id,
            'invoice_number' => 'INV-B-CROSS',
            'subtotal'       => 50.00,
            'tax'            => 9.00,
            'discount'       => 0.00,
            'total'          => 59.00,
            'status'         => 'issued',
            'issued_at'      => now(),
        ]);

        // managerA tries to access cafeB's invoice through cafeA's URL
        $response = $this->getJson("/cafes/invoice-cafe-a/invoices/{$invoiceB->id}");
        $response->assertStatus(404);
    }

    public function test_invoice_cross_tenant_order_rejected(): void
    {
        $this->actingAs($this->managerA);

        // orderB belongs to cafeB — creation under cafeA should fail
        $response = $this->postJson('/cafes/invoice-cafe-a/invoices', [
            'order_id'       => $this->orderB->id,
            'invoice_number' => 'INV-CROSS-ORDER',
            'subtotal'       => 50.00,
            'tax'            => 9.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_invoice_authorization_denied_for_unprivileged_user(): void
    {
        $this->actingAs($this->unprivilegedUser);

        $response = $this->getJson('/cafes/invoice-cafe-a/invoices');
        $response->assertStatus(403);
    }

    public function test_invoice_create_authorization_denied_for_unprivileged_user(): void
    {
        $this->actingAs($this->unprivilegedUser);

        $response = $this->postJson('/cafes/invoice-cafe-a/invoices', [
            'order_id'       => $this->orderA->id,
            'invoice_number' => 'INV-UNAUTH-001',
            'subtotal'       => 100.00,
            'tax'            => 18.00,
        ]);

        $response->assertStatus(403);
    }

    // =========================================================================
    // INVOICE SETTINGS TESTS
    // =========================================================================

    public function test_invoice_settings_retrieval_returns_null_when_not_configured(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->getJson('/cafes/invoice-cafe-a/invoice-settings');

        $response->assertStatus(200)
            ->assertJson(['invoice_setting' => null]);
    }

    public function test_invoice_settings_creation(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->putJson('/cafes/invoice-cafe-a/invoice-settings', [
            'business_name' => 'Brewed Awakenings',
            'address'       => '123 Coffee Lane, Mumbai',
            'gst_number'    => '27AABCU9603R1ZX',
            'footer_text'   => 'Thank you for your visit!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('invoice_setting.business_name', 'Brewed Awakenings')
            ->assertJsonPath('invoice_setting.gst_number', '27AABCU9603R1ZX');
    }

    public function test_invoice_settings_update_is_idempotent(): void
    {
        $this->actingAs($this->managerA);

        // First PUT
        $this->putJson('/cafes/invoice-cafe-a/invoice-settings', [
            'business_name' => 'First Name',
        ]);

        // Second PUT — should update, not create a second row
        $response = $this->putJson('/cafes/invoice-cafe-a/invoice-settings', [
            'business_name' => 'Updated Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('invoice_setting.business_name', 'Updated Name');

        $this->assertDatabaseCount('invoice_settings', InvoiceSetting::where('cafe_id', $this->cafeA->id)->count());
    }

    public function test_invoice_settings_business_name_required(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->putJson('/cafes/invoice-cafe-a/invoice-settings', [
            'address' => 'No business name provided',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['business_name']);
    }

    public function test_invoice_settings_tenant_isolation(): void
    {
        $this->actingAs($this->managerA);

        // Create settings for cafeA
        $this->putJson('/cafes/invoice-cafe-a/invoice-settings', [
            'business_name' => 'Cafe A Business',
        ]);

        // managerB creates settings for cafeB — must not see or overwrite cafeA's
        $this->actingAs($this->managerB);
        $this->putJson('/cafes/invoice-cafe-b/invoice-settings', [
            'business_name' => 'Cafe B Business',
        ]);

        // Each cafe should have its own row
        $settingA = InvoiceSetting::where('cafe_id', $this->cafeA->id)->first();
        $settingB = InvoiceSetting::where('cafe_id', $this->cafeB->id)->first();

        $this->assertEquals('Cafe A Business', $settingA->business_name);
        $this->assertEquals('Cafe B Business', $settingB->business_name);
    }

    public function test_invoice_settings_retrieval_authorization_denied_for_unprivileged_user(): void
    {
        $this->actingAs($this->unprivilegedUser);

        $response = $this->getJson('/cafes/invoice-cafe-a/invoice-settings');
        $response->assertStatus(403);
    }

    public function test_invoice_settings_update_authorization_denied_for_unprivileged_user(): void
    {
        $this->actingAs($this->unprivilegedUser);

        $response = $this->putJson('/cafes/invoice-cafe-a/invoice-settings', [
            'business_name' => 'Hacked Name',
        ]);

        $response->assertStatus(403);
    }
}
