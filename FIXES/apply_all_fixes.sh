#!/bin/bash
# ============================================
# WORKSHOP TOOL - CRITICAL SECURITY FIXES
# Auto-apply script for the 3 critical issues
# ============================================

set -e  # Exit on error

echo "🔒 Workshop Tool - Critical Security Fixes"
echo "==========================================="
echo ""

# Check if we're in the right directory
if [ ! -f "customize.php" ]; then
    echo "❌ ERROR: customize.php not found!"
    echo "Please run this script from the workshop tool root directory"
    exit 1
fi

echo "📂 Current directory: $(pwd)"
echo ""

# Backup existing files
echo "📦 Creating backups..."
BACKUP_DIR="backups/security_fix_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

if [ -f ".htaccess" ]; then
    cp .htaccess "$BACKUP_DIR/.htaccess.backup"
    echo "  ✓ Backed up .htaccess"
fi

cp security_helpers.php "$BACKUP_DIR/security_helpers.php.backup"
echo "  ✓ Backed up security_helpers.php"

cp user_auth.php "$BACKUP_DIR/user_auth.php.backup"
echo "  ✓ Backed up user_auth.php"

cp customize.php "$BACKUP_DIR/customize.php.backup"
echo "  ✓ Backed up customize.php"

echo "  📦 Backups saved to: $BACKUP_DIR"
echo ""

# Fix 1: Create .htaccess
echo "🔧 Fix 1/3: Creating .htaccess file..."
if [ -f ".htaccess" ]; then
    echo "  ⚠️  .htaccess already exists - backing up and replacing"
fi

cp .htaccess_SECURITY_FIX .htaccess
chmod 644 .htaccess
echo "  ✅ .htaccess created and permissions set"
echo ""

# Fix 2: Update security_helpers.php
echo "🔧 Fix 2/3: Fixing rate limiting in security_helpers.php..."
echo "  ⚠️  MANUAL ACTION REQUIRED"
echo "  Please replace the checkPublicRateLimit() function (lines 120-153)"
echo "  with the version in: FIXES/security_helpers_FIXED.php"
echo "  (This requires manual editing to ensure we don't break your customizations)"
echo ""

# Fix 3: Update user_auth.php
echo "🔧 Fix 3/3: Fixing rate limiting in user_auth.php..."
echo "  ⚠️  MANUAL ACTION REQUIRED"
echo "  Please replace the checkRateLimit() function (lines 573-626)"
echo "  with the version in: FIXES/user_auth_FIXED.php"
echo "  (This requires manual editing to ensure we don't break your customizations)"
echo ""

# Fix 4: CSRF Protection
echo "🔧 Fix 4/3 (BONUS): CSRF Protection..."
echo "  ⚠️  MANUAL ACTION REQUIRED"
echo "  Please follow the guide in: FIXES/csrf_protection_guide.md"
echo "  Changes needed in customize.php:"
echo "    1. Add <?= getCSRFField() ?> to form (line 447)"
echo "    2. Add CSRF validation to POST handler (line 35)"
echo ""

# Set proper file permissions
echo "🔐 Setting file permissions..."
chmod 600 .env 2>/dev/null || echo "  ⚠️  .env file not found (create it!)"
chmod 640 *.json 2>/dev/null || true
chmod 750 data/ 2>/dev/null || true
chmod 644 *.php 2>/dev/null || true
echo "  ✅ File permissions updated"
echo ""

# Create test script
echo "🧪 Creating test script..."
cat > test_security_fixes.sh << 'TESTEOF'
#!/bin/bash
echo "Testing Security Fixes"
echo "======================"
echo ""

echo "Test 1: Check .htaccess blocks users.json"
if [ -f "users.json" ]; then
    echo "  Testing: curl -I http://localhost/$(pwd)/users.json"
    echo "  Expected: 403 Forbidden"
    echo "  Run manually after deploying to server"
else
    echo "  ⚠️  users.json not found - will be created on first registration"
fi
echo ""

echo "Test 2: Check .htaccess blocks .env"
if [ -f ".env" ]; then
    echo "  Testing: curl -I http://localhost/$(pwd)/.env"
    echo "  Expected: 403 Forbidden"
    echo "  Run manually after deploying to server"
else
    echo "  ⚠️  .env not found - create it before going live!"
fi
echo ""

echo "Test 3: CSRF Protection"
echo "  1. Open customize.php in browser"
echo "  2. Open DevTools (F12) → Elements"
echo "  3. Find <input type='hidden' name='csrf_token' value='...'>"
echo "  4. If present: ✅ PASS"
echo "  5. If missing: ❌ FAIL - add getCSRFField() to form"
echo ""

echo "Run these tests after deploying to your server!"
TESTEOF
chmod +x test_security_fixes.sh
echo "  ✅ Created test_security_fixes.sh"
echo ""

# Summary
echo "✅ AUTOMATED FIXES COMPLETE!"
echo ""
echo "📋 REMAINING MANUAL ACTIONS:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1. Edit security_helpers.php (10 min)"
echo "   Replace checkPublicRateLimit() with version from FIXES/security_helpers_FIXED.php"
echo ""
echo "2. Edit user_auth.php (10 min)"
echo "   Replace checkRateLimit() with version from FIXES/user_auth_FIXED.php"
echo ""
echo "3. Edit customize.php (5 min)"
echo "   Follow FIXES/csrf_protection_guide.md to add CSRF protection"
echo ""
echo "4. Test your fixes"
echo "   Run: ./test_security_fixes.sh"
echo "   Test CSRF manually using the guide"
echo ""
echo "5. Create .env file if missing"
echo "   Copy from .env.example and add your Stripe keys"
echo ""
echo "📚 Documentation:"
echo "   - Full review: SECURITY_REVIEW_MVP_LAUNCH.md"
echo "   - CSRF guide: FIXES/csrf_protection_guide.md"
echo ""
echo "⏱️  Total time remaining: ~25 minutes"
echo ""
echo "🚀 After completing these steps, you'll be ready to launch!"
