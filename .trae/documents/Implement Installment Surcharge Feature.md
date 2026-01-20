I will implement the installment surcharge feature by extending the existing `InstallmentService` and `SalesInvoice` model.

### 1. Database Changes
*   Create a migration to add two new columns to `sales_invoices` table:
    *   `installment_interest_percentage` (decimal 5,2, default 0): To store the user-input percentage.
    *   `installment_interest_amount` (decimal 18,4, default 0): To store the calculated surcharge amount.

### 2. Backend Logic (`InstallmentService.php`)
*   Update `generateInstallmentSchedule` method to:
    1.  Check if `installment_interest_percentage` > 0.
    2.  Calculate `interestAmount` = `remaining_amount` * (`percentage` / 100).
    3.  Update the invoice:
        *   Set `installment_interest_amount` = `interestAmount`.
        *   Increase `remaining_amount` by `interestAmount`.
        *   Increase `total` by `interestAmount` (to reflect the new total payable).
    4.  Use the new adjusted `remaining_amount` as the basis for splitting installments.
    5.  Log the interest application in the activity log.

### 3. Frontend Implementation (`SalesInvoiceResource.php`)
*   **Form Schema**:
    *   Add a numeric input for `installment_interest_percentage` (0-100%) inside the installment settings section.
    *   Update the "Installment Preview" placeholder to calculate and display:
        *   Original Amount (Before Surcharge)
        *   Surcharge Amount (Percentage & Value)
        *   New Total Payable
        *   The breakdown of installments based on the new total.
*   **Actions**:
    *   Update the `post` action (single and bulk) to ensure `generateInstallmentSchedule` is triggered when an invoice is posted (currently missing in some actions).

### 4. Verification
*   Create a new test file `tests/Feature/Services/InstallmentService/SurchargeTest.php` to verify:
    *   Surcharge is calculated correctly.
    *   Invoice totals are updated correctly.
    *   Installments sum up to the new total.
    *   Percentage validation works.
