# 🔐 User Credentials - HomeGoodsSeeder

## 👤 Admin User Created

The HomeGoodsSeeder now creates a specific admin user with the following credentials:

---

## 📋 Login Credentials

| Field | Value |
|-------|-------|
| **Name** | Mohamed Ibrahim |
| **Email** | admin@test.com |
| **Password** | 12345678 |

---

## ✅ Verification

### **User Details:**
- ✅ Name: Mohamed Ibrahim
- ✅ Email: admin@test.com
- ✅ Password: 12345678 (verified with Hash::check)
- ✅ Created by seeder
- ✅ Used as creator for all invoices and expenses

### **User Activity (After Seeding):**
- **Invoices Created**: 4 (2 purchase + 2 sales)
- **Expenses Created**: 1 (Store rent)
- **Sales Returns**: 1

---

## 🔑 How to Login

### **Web Interface:**
1. Navigate to: `/admin/login`
2. Enter email: `admin@test.com`
3. Enter password: `12345678`
4. Click Login

### **Console Test:**
```bash
php artisan tinker --execute="
\$user = App\Models\User::where('email', 'admin@test.com')->first();
echo 'Email: ' . \$user->email . PHP_EOL;
echo 'Password Check: ' . (Hash::check('12345678', \$user->password) ? 'VALID' : 'INVALID');
"
```

---

## 🏢 Related Data

### **Same Person, Different Roles:**

1. **System User** (for login & transaction tracking):
   - Name: Mohamed Ibrahim
   - Email: admin@test.com
   - Purpose: Admin access, creates invoices/expenses

2. **Shareholder Partner** (for equity tracking):
   - Name: Mohamed Ibrahim - Business Owner
   - Type: shareholder
   - Balance: +1,000,000 EGP
   - Purpose: Owner's equity, capital contributions

**Note:** These are intentionally separate records:
- **User table** → Authentication & transaction creator
- **Partners table** → Financial partner, shareholder equity

---

## 🔄 Seeder Behavior

### **User Creation Logic:**
```php
$this->user = User::where('email', 'admin@test.com')->first();
if (!$this->user) {
    $this->user = User::create([
        'name' => 'Mohamed Ibrahim',
        'email' => 'admin@test.com',
        'password' => bcrypt('12345678'),
    ]);
}
```

### **Features:**
- ✅ Checks if user exists (by email)
- ✅ Creates only if not found
- ✅ Uses bcrypt for password hashing
- ✅ Idempotent (safe to run multiple times)

---

## 📊 Console Output

When running the seeder:

```
┌─────────────────────────────────────────────────────────────┐
│ 1️⃣  FOUNDATION SETUP                                         │
└─────────────────────────────────────────────────────────────┘
  ✓ Created User: Mohamed Ibrahim (admin@test.com)
  ✓ Created Main Warehouse
  ✓ Created Main Treasury
  ✓ Created Piece Unit
```

---

## 🛡️ Security Notes

### **Production Recommendations:**

⚠️ **IMPORTANT**: These credentials are for **development/testing only**!

For production:
1. Change the password immediately
2. Use a strong, unique password
3. Enable 2FA if available
4. Use environment variables for sensitive data
5. Never commit credentials to git

### **Development Usage:**
- ✅ Perfect for local development
- ✅ Easy to remember
- ✅ Consistent across team members
- ✅ Simple for testing

---

## 🔧 How to Change Credentials

### **In the Seeder:**
Edit [HomeGoodsSeeder.php](HomeGoodsSeeder.php):

```php
$this->user = User::create([
    'name' => 'Your Name',           // Change here
    'email' => 'your@email.com',     // Change here
    'password' => bcrypt('yourpass'), // Change here
]);
```

### **Via Tinker:**
```bash
php artisan tinker --execute="
\$user = App\Models\User::where('email', 'admin@test.com')->first();
\$user->password = bcrypt('new-password');
\$user->save();
echo 'Password updated!';
"
```

### **Via Migration/Seeder:**
Create a dedicated `AdminUserSeeder.php`:

```php
User::updateOrCreate(
    ['email' => 'admin@test.com'],
    [
        'name' => 'Mohamed Ibrahim',
        'password' => bcrypt('12345678'),
    ]
);
```

---

## ✅ Verification Checklist

After running the seeder:

- [ ] User exists in database
- [ ] Email is `admin@test.com`
- [ ] Password `12345678` works for login
- [ ] User created 4 invoices
- [ ] User created 1 expense
- [ ] User is separate from shareholder partner

---

## 🚀 Quick Start

```bash
# 1. Run seeder
php artisan migrate:fresh --seed --seeder=HomeGoodsSeeder

# 2. Login with:
Email: admin@test.com
Password: 12345678

# 3. Start working!
```

---

**Created**: 2025-12-28
**Last Updated**: 2025-12-28
**Status**: ✅ Active
**Environment**: Development/Testing
