#!/bin/bash

echo "🚀 Deploying to Production..."

# Clear all caches
echo "🧹 Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations (if any)
echo "📊 Running migrations..."
php artisan migrate --force

# Verify super admin access
echo "🔍 Verifying super admin..."
php artisan tinker --execute="
\$user = App\Models\User::where('email', 'mohamed@osoolerp.com')->first();
if (\$user) {
    echo 'User: ' . \$user->email . PHP_EOL;
    echo 'Roles: ' . \$user->roles->pluck('name')->implode(', ') . PHP_EOL;
    echo 'Can Access Panel: ' . (\$user->canAccessPanel(Filament\Facades\Filament::getCurrentPanel()) ? 'YES ✅' : 'NO ❌') . PHP_EOL;
} else {
    echo '❌ User not found!' . PHP_EOL;
}
"

echo "✅ Deployment complete!"
