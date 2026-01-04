# Mawared ERP System

A comprehensive ERP (Enterprise Resource Planning) system built with Laravel and Filament, designed for managing inventory, sales, purchases, finances, and reporting.

## Features

- Inventory Management
- Sales & Purchase Management
- Financial Accounting & Treasury
- Partner (Customer/Supplier) Management
- Multi-warehouse Support
- Comprehensive Reporting & Analytics
- Arabic Language Support
- Role-based Access Control

## Tech Stack

- **Framework**: Laravel 11.x
- **Admin Panel**: Filament 3.x
- **Database**: MySQL/SQLite
- **Frontend**: Blade, Alpine.js, Tailwind CSS
- **Charts**: ApexCharts

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js & NPM
- MySQL or SQLite

### Installation

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   npm install
   ```

3. Configure environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Run migrations and seeders:
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. Build assets:
   ```bash
   npm run build
   ```

6. Start the development server:
   ```bash
   php artisan serve
   ```

## Documentation

### Architecture & Project Guidelines
- [System Architecture](docs/SYSTEM_ARCHITECTURE.md) - Complete system architecture overview
- [Project Rules](docs/architecture/PROJECT_RULES.md) - Development guidelines and coding standards
- [Code Audit Report](docs/architecture/CODE_AUDIT_REPORT.md) - Code quality and security audit

### Development Notes
- [Filament Fix README](docs/development/FILAMENT-FIX-README.md) - Filament-specific fixes and customizations
- [Financial Fix Summary](docs/development/FINANCIAL-FIX-SUMMARY.md) - Financial module fixes and enhancements
- [Payment UI Implementation](docs/development/PAYMENT-UI-IMPLEMENTATION.md) - Payment interface implementation details
- [Selection Fix Notes](docs/development/SELECTION-FIX-NOTES.md) - UI selection component fixes

### Testing & Quality Assurance
- [Test Report](docs/testing/TEST_REPORT.md) - Test execution results
- [Testing Gap Analysis](docs/testing/TESTING_GAP_ANALYSIS_REPORT.md) - Testing coverage analysis
- [Profit Loss Report Verification](docs/testing/PROFIT_LOSS_REPORT_VERIFICATION.md) - Financial report verification

### Migration & Data Import
- [Migration Guide](docs/migration/MIGRATION.md) - Database migration and data import procedures

## Contributing

Thank you for considering contributing to this project. Please follow the guidelines in [Project Rules](docs/architecture/PROJECT_RULES.md).

## License

This project is proprietary software.

---

Built with Laravel and Filament
