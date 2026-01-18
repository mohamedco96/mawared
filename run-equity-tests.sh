#!/bin/bash

echo "================================================"
echo "EQUITY PERIOD COMPREHENSIVE TEST RUNNER"
echo "================================================"
echo ""

# Check if we should reset the database
read -p "Do you want to reset the database first? (y/n): " reset_db

if [ "$reset_db" = "y" ] || [ "$reset_db" = "Y" ]; then
    echo ""
    echo "Step 1: Resetting database..."
    php artisan migrate:fresh

    if [ $? -ne 0 ]; then
        echo "❌ Migration failed!"
        exit 1
    fi
    echo "✅ Database reset complete"
    echo ""

    echo "Step 2: Seeding base data..."
    php artisan db:seed --class=DummyDataSeeder

    if [ $? -ne 0 ]; then
        echo "❌ Base data seeding failed!"
        exit 1
    fi
    echo "✅ Base data seeded"
else
    echo ""
    echo "Skipping database reset..."
fi

echo ""
echo "Step 3: Running comprehensive equity period tests..."
echo "================================================"
echo ""

php artisan db:seed --class=EquityPeriodTestSeeder

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ TESTS FAILED!"
    exit 1
fi

echo ""
echo "================================================"
echo "✅ ALL TESTS COMPLETED"
echo "================================================"
echo ""
echo "You can now verify the results by checking:"
echo "  1. equity_periods table"
echo "  2. equity_period_partners table"
echo "  3. partners table (current_capital and equity_percentage)"
echo "  4. treasury_transactions table"
echo ""
echo "See EQUITY_PERIOD_TEST_GUIDE.md for detailed verification queries."
echo ""
