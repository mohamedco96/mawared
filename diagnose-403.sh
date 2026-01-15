#!/bin/bash

echo "🔍 Diagnosing 403 Forbidden Issue..."
echo ""

echo "1️⃣ Checking APP_ENV:"
php artisan tinker --execute="echo 'Environment: ' . app()->environment() . PHP_EOL;"

echo ""
echo "2️⃣ Checking all users with authorized emails:"
php artisan tinker --execute="
\$emails = ['mohamed@osoolerp.com', 'ashraf@osoolerp.com', 'mahmoud@osoolerp.com', 'rehab@osoolerp.com'];
foreach (\$emails as \$email) {
    \$user = App\Models\User::where('email', \$email)->first();
    if (\$user) {
        echo '✅ ' . \$email . PHP_EOL;
        echo '   - ID: ' . \$user->id . PHP_EOL;
        echo '   - Roles: ' . \$user->roles->pluck('name')->implode(', ') . PHP_EOL;
        echo '   - Email Verified: ' . (\$user->email_verified_at ? 'YES' : 'NO') . PHP_EOL;
        try {
            \$panel = Filament\Facades\Filament::getPanel('admin');
            echo '   - canAccessPanel: ' . (\$user->canAccessPanel(\$panel) ? 'YES' : 'NO') . PHP_EOL;
        } catch (Exception \$e) {
            echo '   - canAccessPanel: ERROR - ' . \$e->getMessage() . PHP_EOL;
        }
    } else {
        echo '❌ ' . \$email . ' - NOT FOUND' . PHP_EOL;
    }
    echo PHP_EOL;
}
"

echo ""
echo "3️⃣ Checking Shield configuration:"
php artisan tinker --execute="
echo 'Shield super_admin enabled: ' . (config('filament-shield.super_admin.enabled') ? 'YES' : 'NO') . PHP_EOL;
echo 'Shield super_admin name: ' . config('filament-shield.super_admin.name') . PHP_EOL;
echo 'Shield panel_user enabled: ' . (config('filament-shield.panel_user.enabled') ? 'YES' : 'NO') . PHP_EOL;
"

echo ""
echo "4️⃣ Checking if sessions table exists:"
php artisan tinker --execute="
try {
    \$count = DB::table('sessions')->count();
    echo 'Sessions table: EXISTS (rows: ' . \$count . ')' . PHP_EOL;
} catch (Exception \$e) {
    echo 'Sessions table: ERROR - ' . \$e->getMessage() . PHP_EOL;
}
"

echo ""
echo "5️⃣ Checking Filament panel configuration:"
php artisan tinker --execute="
try {
    \$panel = Filament\Facades\Filament::getPanel('admin');
    echo 'Panel ID: ' . \$panel->getId() . PHP_EOL;
    echo 'Panel Path: ' . \$panel->getPath() . PHP_EOL;
    echo 'Auth Guard: ' . \$panel->getAuthGuard() . PHP_EOL;
} catch (Exception \$e) {
    echo 'Panel Error: ' . \$e->getMessage() . PHP_EOL;
}
"

echo ""
echo "✅ Diagnosis complete!"
