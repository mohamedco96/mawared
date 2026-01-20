# Installment Payment System Documentation

## 1. Overview
The Installment Payment System in Mawared ERP is a fully integrated module that allows credit sales invoices to be split into scheduled payments. It features automatic schedule generation, FIFO (First-In-First-Out) payment application, and automated overdue detection.

---

## 2. Workflow

### 2.1 Order Placement & Plan Creation
1.  **Invoice Creation**: A user creates a `SalesInvoice` with:
    *   `payment_method`: 'credit' (Credit)
    *   `has_installment_plan`: `true`
    *   `installment_months`: Number of months (e.g., 3, 6, 12)
    *   `installment_start_date`: Date of the first installment
2.  **Posting**: When the invoice status changes to `posted`:
    *   The `InstallmentService::generateInstallmentSchedule()` method is triggered.
    *   The system calculates equal monthly payments based on the remaining balance.

### 2.2 Payment Processing
1.  **Recording Payment**: A user records a payment via the `InvoicePayment` module.
2.  **FIFO Allocation**: The system automatically applies this payment to the installments:
    *   It locks the installment records (`lockForUpdate`) to prevent race conditions.
    *   It finds the oldest unpaid/partial installment.
    *   It applies the payment amount to that installment.
    *   If the payment exceeds the installment amount, the remainder is applied to the next installment.
    *   Installment status updates to `paid` immediately upon full settlement.

### 2.3 Status Tracking
*   **Pending**: Default status upon creation.
*   **Paid**: Automatically set when `paid_amount` equals `amount`.
*   **Overdue**: Automatically set via a daily scheduled task or real-time accessor when `due_date` has passed.

---

## 3. Database Schema

### 3.1 `installments` Table
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BigInt | Primary Key |
| `sales_invoice_id` | ULID | Foreign Key to `sales_invoices` |
| `installment_number` | Integer | Sequence number (1, 2, 3...) |
| `amount` | Decimal(18,4) | Total amount due for this installment |
| `paid_amount` | Decimal(18,4) | Amount currently paid |
| `due_date` | Date | Scheduled due date |
| `status` | Enum | `pending`, `paid`, `overdue` |
| `invoice_payment_id` | ULID | Reference to the payment that settled this (if fully paid) |
| `paid_at` | Timestamp | Date of full completion |
| `paid_by` | ULID | User who recorded the final payment |

### 3.2 `sales_invoices` Extensions
The `sales_invoices` table was extended to support installments:
*   `has_installment_plan` (boolean)
*   `installment_months` (integer)
*   `installment_start_date` (date)
*   `installment_notes` (text)

---

## 4. Calculation Methodology

### 4.1 Installment Amount
The system uses `bcdiv` for high-precision division to avoid floating-point errors.

```php
// Core Logic in InstallmentService.php
$installmentAmount = bcdiv($totalToInstall, $months, 4);

// Handle Rounding: Any remainder is added to the last installment
$totalAllocated = bcmul($installmentAmount, ($months - 1), 4);
$lastInstallmentAmount = bcsub($totalToInstall, $totalAllocated, 4);
```

### 4.2 Interest & Fees
*   **Interest**: The current implementation **does not** calculate interest. The total installment amount equals the invoice remaining balance.
*   **Fees**: No specific fee logic is implemented in the installment generation.

---

## 5. Business Rules

1.  **Eligibility**:
    *   Invoice must be `posted`.
    *   Payment method must be `credit`.
    *   `remaining_amount` must be > 0.
    *   `installment_months` must be > 0.
2.  **Immutability**: Once generated, installments are generally locked unless the invoice is voided/returned (though specific edit logic is restricted in UI).
3.  **Payment Order**: Payments generally strictly follow FIFO (oldest due date first).
4.  **Overpayment**: If a payment exceeds the total of all remaining installments, the excess is logged as a warning in the Activity Log but accepted (handled as general invoice credit).

---

## 6. Integration Points

### 6.1 Treasury System
*   **No External Gateway**: The system does not currently integrate with Stripe, PayPal, or other external gateways. It acts as an internal ledger.
*   **Manual Entry**: Payments are recorded manually by treasurers/accountants via the Filament Admin Panel.
*   **Ledger Updates**: `TreasuryService` handles the actual money movement (updating `treasury_transactions` and `partner_balances`), while `InstallmentService` only tracks the schedule status.

### 6.2 Notifications
*   **UI Alerts**: Overdue installments are highlighted in red in the admin dashboard.
*   **No Email**: There is no automated email notification system for upcoming or missed payments in the current codebase.

---

## 7. Automation & State Machine

### 7.1 Scheduled Tasks
A daily cron job runs at **01:00 AM** to mark overdue installments.

**File:** `routes/console.php`
```php
Schedule::call(function () {
    \App\Models\Installment::where('status', 'pending')
        ->where('due_date', '<', now()->format('Y-m-d'))
        ->update(['status' => 'overdue']);
})->dailyAt('01:00');
```

### 7.2 State Machine
The state transitions are:
1.  `pending` -> `paid` (via Payment)
2.  `pending` -> `overdue` (via Scheduled Task or Real-time Accessor)
3.  `overdue` -> `paid` (via Payment)

---

## 8. Security & Concurrency

To prevent "Double Spending" or concurrent modification during payment application, the system uses pessimistic locking:

```php
// InstallmentService.php
$installments = $invoice->installments()
    ->where('status', '!=', 'paid')
    ->lockForUpdate() // Database-level row lock
    ->get();
```

---

## 9. API & Reporting

### 9.1 API
The system is built on **Filament (Livewire)** and does not expose a public REST API for installments. All operations occur via the Admin Panel.

### 9.2 Reporting
*   **Installment Resource**: A dedicated view (`/admin/installments`) allows filtering by status (Overdue, Pending, Paid).
*   **Badge Indicators**: Navigation badges show the count of overdue installments.
