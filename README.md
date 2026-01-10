# Infraprotect Live Situation Room

A real-time collaborative workshop tool designed for collecting, moderating, and displaying ideas/strategies in live workshop settings with 50+ concurrent participants.

## 🎯 Overview

This tool provides a complete solution for interactive workshops where participants submit ideas via their mobile devices, while moderators control what appears on a shared display in real-time. Built with robust concurrency handling to support large groups without data conflicts.

## ✨ Key Features

### 📊 Live Dashboard (`index.php`)
- **Real-time Updates**: Automatically refreshes every 2 seconds
- **Categorized Display**: Ideas organized in customizable columns
- **Focus Mode**: Spotlight specific entries on all screens
- **QR Code Access**: Easy mobile participation via scan-to-join
- **Theme Toggle**: Light/dark mode support
- **Visibility Control**: Show/hide entries based on moderation
- **Mobile Responsive**: Adapts from large displays to smartphones

### 📝 Public Input Form (`eingabe.php`)
- **Category Selection**: Choose from configurable categories
- **Guided Questions**: Dynamic "Leitfragen" (guiding questions) per category
- **Character Limit**: 500 character maximum with live counter
- **Duplicate Submission Prevention**: Client-side protection
- **Success Feedback**: Clear confirmation messages
- **Mobile-First Design**: Optimized for smartphone input

### 🎛️ Admin Moderation Panel (`admin.php`)
- **Password Protected**: Session-based authentication
- **Mass Control**: Bulk show/hide operations by category or globally
- **Individual Moderation**: Toggle visibility per entry
- **Focus Control**: Highlight specific entries on all displays
- **Live Editing**: Modify entry text in real-time
- **Category Moving**: Reassign entries to different categories
- **Entry Deletion**: Remove inappropriate or duplicate content
- **PDF Export**: Generate formatted reports for documentation
- **Real-time Feed**: Live view of all submissions as they arrive

### ⚙️ Customization Panel (`customize.php`)
- **Header Customization**: Set workshop title with HTML support
- **Category Management**: Add, edit, remove categories dynamically
- **Guiding Questions**: Configure category-specific prompts
- **Icons & Labels**: Customize visual appearance
- **Live Preview**: Changes reflect immediately across all components

## 🏗️ Architecture

### Technical Stack
- **Backend**: PHP 7.4+
- **Storage**: JSON files (no database required)
- **Frontend**: Vanilla JavaScript (no frameworks)
- **Styling**: CSS3 with CSS Variables
- **Fonts**: Google Fonts (Montserrat, Roboto)

### File Structure
```
.
├── index.php                    # Main live dashboard (public)
├── eingabe.php                  # Input form (public)
├── admin.php                    # Moderation panel (protected)
├── customize.php                # Configuration panel (protected)
├── file_handling_robust.php     # Robust file operations library
├── config.json                  # Configuration (categories, questions)
├── daten.json                   # Data storage (submissions)
└── backups/                     # Auto-generated backups (created automatically)
```

### Data Flow
```
┌─────────────────┐
│ Participants    │
│ (Mobile/Desktop)│
└────────┬────────┘
         │ Submit via eingabe.php
         ▼
┌─────────────────────────────────┐
│  file_handling_robust.php       │
│  (Atomic Operations + Locks)    │
└────────┬────────────────────────┘
         │ JSON Storage
         ▼
    ┌────────────┐
    │ daten.json │
    └────┬───────┘
         │ Read Every 2s
         ▼
┌─────────────────┬──────────────┐
│  index.php      │  admin.php   │
│  (Live Display) │  (Moderation)│
└─────────────────┴──────────────┘
```

## 🔒 Concurrency & Data Safety

### Atomic Operations
The system uses **file locking** to prevent race conditions when multiple users submit simultaneously:

- **LOCK_EX** (Exclusive Lock): Write operations
- **LOCK_SH** (Shared Lock): Read operations
- **Automatic Retry Logic**: Up to 10 attempts with exponential backoff
- **Transaction Safety**: Read-Modify-Write happens atomically

### Auto-Backup System
- Automatic backups created on every write operation
- Timestamps: `daten_backup_YYYY-MM-DD_HH-MM-SS.json`
- Retention: Keeps last 10 backups (configurable)
- Location: `backups/` directory (auto-created)

### Error Handling
- JSON validation on every read/write
- Fallback to empty arrays on corruption
- Comprehensive error logging to `error.log`
- Graceful degradation on file access issues

## 🚀 Installation & Setup

### Requirements
- PHP 7.4 or higher
- Web server (Apache, Nginx, etc.)
- Write permissions for JSON files and backup directory

### Quick Start

1. **Upload Files**
   ```bash
   # Upload all PHP files to your web directory
   ```

2. **Set Permissions**
   ```bash
   chmod 666 daten.json config.json
   chmod 755 .
   ```

3. **Access the Tool**
   - Dashboard: `http://yourserver.com/index.php`
   - Input Form: `http://yourserver.com/eingabe.php`
   - Admin Panel: `http://yourserver.com/admin.php`

4. **Default Admin Password**
   ```
   Password: workshop2025
   ```
   ⚠️ Change this in `admin.php` line 8:
   ```php
   $admin_passwort = "your_secure_password";
   ```

### Initial Configuration

1. **Login to Admin Panel** (`admin.php`)
2. **Click "Anpassen"** to access customization
3. **Configure Categories**:
   - Set category keys (lowercase, no spaces)
   - Add display names and icons
   - Define guiding questions (one per line)
4. **Set Workshop Title** (supports HTML like `<br>`)
5. **Save Changes**

## 📱 Usage Guide

### For Participants

1. **Access via QR Code**: Scan code displayed on dashboard
2. **Select Category**: Choose from dropdown menu
3. **Read Guiding Questions**: Automatically displayed after selection
4. **Enter Your Idea**: Max 500 characters
5. **Submit**: Confirmation shown on success

### For Moderators

**Basic Workflow:**
1. Login to admin panel
2. Monitor incoming submissions in real-time
3. Review and approve entries (toggle visibility)
4. Use focus mode to highlight important ideas
5. Export PDF summary at end of workshop

**Mass Operations:**
- **ALL LIVE**: Show all entries across all categories
- **ALL HIDE**: Hide all entries across all categories
- **Sector Control**: Show/hide entire categories at once

**Individual Actions:**
- **EDIT**: Modify entry text
- **FOCUS**: Spotlight on all displays
- **HIDE/GO LIVE**: Toggle visibility
- **DELETE**: Remove entry permanently

## 🎨 Customization

### Color Scheme
Edit CSS variables in any PHP file's `<style>` section:

```css
:root {
    --ip-blue: #00658b;      /* Primary brand color */
    --ip-dark: #32373c;      /* Text color */
    --ip-grey-bg: #f4f4f4;   /* Background */
    --accent-success: #00d084; /* Success actions */
    --accent-danger: #cf2e2e;  /* Dangerous actions */
}
```

### Categories Structure
Edit `config.json` or use the customization panel:

```json
{
    "header_title": "Your Workshop Title<br>Second Line",
    "categories": [
        {
            "key": "category_key",
            "name": "DISPLAY NAME",
            "abbreviation": "ABB",
            "icon": "📚",
            "display_name": "📚 Full Display Name",
            "leitfragen": [
                "Question 1?",
                "Question 2?"
            ]
        }
    ]
}
```

### Adding New Features
The modular architecture allows easy extension:
- Add new admin actions in `admin.php`
- Extend atomic operations in `file_handling_robust.php`
- Customize frontend behavior in JavaScript sections

## 🔧 Configuration Options

### Admin Password
```php
// admin.php line 8
$admin_passwort = "workshop2025";
```

### Polling Interval
```javascript
// index.php line 701
setInterval(updateBoard, 2000); // 2 seconds

// admin.php line 697
refreshInterval = setInterval(updateAdminBoard, 2000);
```

### Backup Retention
```php
// file_handling_robust.php line 195
createAutoBackup($file); // Default keeps 10 backups

// To change:
createAutoBackup($file, 20); // Keep 20 backups
```

### Character Limit
```php
// eingabe.php line 48
if (strlen($idee) > 500) {
    // Change 500 to your desired limit
}
```

## 🐛 Troubleshooting

### "Write Failed" Errors
```bash
# Check file permissions
chmod 666 daten.json config.json
chmod 777 backups/
```

### Data Not Updating
- Check browser console for JavaScript errors
- Verify PHP error log for server-side issues
- Ensure JSON files are valid (use online validator)
- Clear browser cache

### Lock Timeout Issues
If experiencing lock timeouts with very high traffic:
```php
// Increase retry attempts in file_handling_robust.php
function atomicAddEntry($file, $newEntry, $maxRetries = 20, $retryDelay = 150000)
```

### PDF Export Not Working
- Ensure browser pop-up blocker allows new windows
- Check `admin.php?mode=pdf` directly in browser
- Verify session is active (logged in)

## 📊 Data Structure

### Entry Format (`daten.json`)
```json
[
    {
        "id": "1234_69614124d420f5.10201804",
        "thema": "category_key",
        "text": "User submitted text",
        "zeit": 1767981348,
        "visible": true,
        "focus": false
    }
]
```

### Field Descriptions
- `id`: Unique identifier (timestamp + random)
- `thema`: Category key (matches config.json)
- `text`: HTML-escaped user input
- `zeit`: Unix timestamp
- `visible`: Boolean (shown on dashboard)
- `focus`: Boolean (spotlighted on all displays)

## 🔐 Security Considerations

### Current Implementation
- Session-based admin authentication
- HTML entity escaping on user input
- No SQL injection risk (no database)
- File locking prevents concurrent write conflicts

### Recommended Enhancements
```php
// 1. Move password to separate config file
// 2. Add password hashing
$admin_passwort = password_hash("workshop2025", PASSWORD_DEFAULT);

// 3. Add CSRF protection
// 4. Implement rate limiting
// 5. Add input sanitization beyond htmlspecialchars
```

### Production Deployment
- [ ] Change default admin password
- [ ] Enable HTTPS
- [ ] Set restrictive file permissions
- [ ] Regular backup exports
- [ ] Monitor error.log for issues
- [ ] Consider adding .htaccess protection

## 📈 Performance

### Tested Capacity
- **50+ concurrent users**: Validated with atomic operations
- **Submission rate**: Handles rapid consecutive submissions
- **Data size**: Efficient up to 1000+ entries
- **Auto-refresh**: Minimal server load with 2s polling

### Optimization Tips
1. **Increase polling interval** for very large displays:
   ```javascript
   setInterval(updateBoard, 5000); // 5 seconds instead of 2
   ```

2. **Implement pagination** for 500+ entries in admin panel

3. **Use opcode cache** (OPcache) for PHP performance

4. **Enable gzip compression** in web server config

## 🎓 Use Cases

Perfect for:
- ✅ Interactive workshops and conferences
- ✅ Brainstorming sessions
- ✅ Town hall meetings
- ✅ Educational classroom activities
- ✅ Strategy development sessions
- ✅ Feedback collection events
- ✅ Innovation labs and hackathons

## 📄 License

This tool was developed for Infraprotect workshops. Ensure you have appropriate rights before deploying or modifying.

## 🤝 Support & Contribution

### Reporting Issues
Check `error.log` file for detailed error messages

### Feature Requests
Consider the modular architecture when planning extensions:
- Frontend changes: Modify respective PHP files
- Backend logic: Extend `file_handling_robust.php`
- Data structure: Update JSON schemas

## 📞 Technical Details

### Browser Compatibility
- Chrome/Edge: ✅ Full support
- Firefox: ✅ Full support
- Safari: ✅ Full support
- Mobile browsers: ✅ Optimized

### Server Requirements
- PHP 7.4+ with file locking support
- `flock()` function enabled
- `json_encode/decode` available
- Write access to working directory

### JavaScript Dependencies
- QRCode.js (CDN: cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js)

---

**Built with 💙 for collaborative workshops**

Version: 1.0 | Last Updated: 2025
