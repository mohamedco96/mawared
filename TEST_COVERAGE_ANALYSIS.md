# Test Coverage Analysis & Gap Report

## 1. Executive Summary

The current test suite provides strong coverage for the core business logic of the ERP system, specifically focusing on **Financial Integrity**, **Stock Management**, **Treasury Operations**, and **Background Jobs**. The `tests/Feature/Services` and `tests/Feature/Integration` directories contain robust scenarios for happy paths, edge cases, and complex accounting principles.

However, there is a significant gap in testing the **User Interface (Filament Resources)** and **Dashboard Widgets**. While the backend logic is well-guarded, the frontend interactions and CRUD operations for most resources rely on manual verification or basic Smoke Tests.

---

## 2. Coverage Analysis by Component

### A. Services (Backend Logic)

| Service | Status | Notes |
| :--- | :--- | :--- |
| **StockService** | ✅ **High** | Extensive tests for invoices, returns, movements, and avg cost calc. |
| **TreasuryService** | ✅ **High** | Covers transactions, balances, partner debt, and expense posting. |
| **InstallmentService** | ✅ **High** | Covers scheduling, payments, and overdue logic. |
| **FinancialReportService**| ✅ **High** | Validated calculation methods and report generation. |
| **ReportService** | ✅ **High** | Covers Partner Statements and Stock Cards. |
| **CapitalService** | ✅ **High** | Tests cover equity injection, drawings, period management, and ledger. |
| **CommissionService** | ✅ **High** | Tests cover calculation, payout, reversal, and reporting. |
| **DepreciationService** | ✅ **High** | Tests cover monthly depreciation, salvage value, and asset updates. |

### B. Filament Resources (UI & CRUD)
Most Filament resources lack specific automated tests. A **Smoke Test** suite has been added to ensure all Index and Create pages load without errors, but UI-specific logic (validations, visibility rules, complex form interactions) is still largely unchecked.

| Resource | Status | Notes |
| :--- | :--- | :--- |
| **SalesInvoiceResource** | 🟡 **Partial** | UI state tests exist (draft vs posted actions), but full CRUD flow is missing. |
| **QuotationResource** | 🟡 **Partial** | Conversion logic is tested (`QuotationConversionTest`), but UI is not. |
| **PurchaseInvoiceResource**| 🔴 **Untested** | Relies on generic service tests. |
| **ProductResource** | 🔴 **Untested** | |
| **PartnerResource** | 🔴 **Untested** | |
| **UserResource** | 🔴 **Untested** | |
| **TreasuryResource** | 🔴 **Untested** | |
| **WarehouseResource** | 🔴 **Untested** | |
| **(All other resources)** | 🔴 **Untested** | Approx. 15+ other resources have no specific UI tests. |

### C. Widgets & Dashboards

| Widget | Status | Notes |
| :--- | :--- | :--- |
| **LowStockTableWidget** | ✅ **Covered** | Verifies data retrieval and display logic. |
| **BestSellersWidget** | 🔴 **Untested** | |
| **FinancialOverview** | 🔴 **Untested** | |
| **OperationalStats** | 🔴 **Untested** | |
| **(All other widgets)** | 🔴 **Untested** | |

### D. Integration & Business Logic

| Area | Status | Notes |
| :--- | :--- | :--- |
| **Financial Integrity** | ✅ **Excellent**| `FinancialIntegrityTest.php` ensures end-to-end accounting correctness. |
| **Business Logic** | ✅ **Excellent**| `BusinessLogicTest.php` covers edge cases and accounting principles. |
| **RBAC / Security** | 🟡 **Partial** | `RBACTest.php` exists but coverage depth for all resources needs verification. |
| **Controllers** | ✅ **Covered** | All non-UI controllers (`Invoice`, `PublicQuotation`, `Report`, `Showroom`) are fully tested. |
| **Commands** | ✅ **Covered** | `FixFinancialDiscrepancies`, `InitializeCompanySettings`, `RestoreBackupCommand` covered. |
| **Jobs** | ✅ **Covered** | `RestoreBackupJob` tested for success and failure scenarios (including notification handling). |

---

## 3. Gap Analysis & Risks

### Critical Gaps (High Risk)
1.  **Filament Resource Validations**: While services handle data, UI-level validations (e.g., dependent fields, complex form rules) are untested.
2.  **Permissions (RBAC)**: Ensure that "Viewer" roles strictly cannot perform "Editor" actions across *all* resources, not just the few tested ones.

### Moderate Gaps (Medium Risk)
1.  **Widgets**: Incorrect data on dashboards can mislead users, though it doesn't corrupt the database.

---

## 4. Recommendations

### Immediate Priorities (Next Sprint)
1.  **Widget Tests**: Add simple data-fetching tests for all dashboard widgets.
2.  **Detailed Resource Tests**: Select critical resources (Sales, Purchases) and add deep Livewire tests for their forms.

### Completed (Recent)
-   **Filament Smoke Tests**: `SmokeTest.php` now verifies that all Resource Index and Create pages load correctly (200 OK) and checks for permission issues.
-   **Controller Tests**: Added feature tests for all standalone controllers.
-   **Command Tests**: Added tests for financial fixes, settings initialization, and backup restoration.
-   **Job Tests**: Added tests for `RestoreBackupJob` (backup restoration logic).
-   **Service Logic Hardening**: Improved `FinancialReportService` to correctly handle mixed partner types (Supplier as Debtor, etc.) and updated related tests.

### Maintenance
-   **Review `SalesInvoiceResourceTest`**: Expand it to cover the full lifecycle (Create -> Post -> Pay) via Livewire assertions to simulate the user journey.
