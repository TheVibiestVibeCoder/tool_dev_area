<?php
/**
 * Clear PHP OpCache
 * Visit this page once to clear PHP's cache, then delete this file
 */

// Security: Only allow from localhost or if a secret key is provided
$secret = 'clear_cache_now_2024'; // Change this if you want
$isAllowed = (
    $_SERVER['REMOTE_ADDR'] === '127.0.0.1' ||
    $_SERVER['REMOTE_ADDR'] === '::1' ||
    (isset($_GET['key']) && $_GET['key'] === $secret)
);

if (!$isAllowed) {
    die('Access denied. Add ?key=' . $secret . ' to the URL.');
}

echo "<h1>PHP Cache Clearing Tool</h1>";
echo "<p>Clearing PHP cache...</p>";

$results = [];

// Clear OpCache
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        $results[] = "✅ OpCache cleared successfully";
    } else {
        $results[] = "❌ Failed to clear OpCache";
    }
} else {
    $results[] = "ℹ️ OpCache not available or not enabled";
}

// Clear APCu cache if available
if (function_exists('apcu_clear_cache')) {
    if (apcu_clear_cache()) {
        $results[] = "✅ APCu cache cleared successfully";
    } else {
        $results[] = "❌ Failed to clear APCu cache";
    }
} else {
    $results[] = "ℹ️ APCu not available";
}

// Clear realpath cache
clearstatcache(true);
$results[] = "✅ Realpath cache cleared";

echo "<ul>";
foreach ($results as $result) {
    echo "<li>" . htmlspecialchars($result) . "</li>";
}
echo "</ul>";

echo "<hr>";
echo "<h2>What to do now:</h2>";
echo "<ol>";
echo "<li>Try accessing <a href='dev_login.php'>dev_login.php</a> again</li>";
echo "<li>If it works, <strong>delete this file (clear_cache.php)</strong> for security</li>";
echo "<li>If it still doesn't work, wait 5 minutes for cache to expire naturally</li>";
echo "</ol>";

echo "<hr>";
echo "<p><small>Note: This file should be deleted after use for security reasons.</small></p>";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cache Cleared</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 { color: #333; }
        ul {
            background: white;
            padding: 20px;
            border-radius: 5px;
            list-style: none;
        }
        li {
            padding: 10px;
            margin: 5px 0;
            border-left: 4px solid #4CAF50;
            background: #f9f9f9;
        }
        a {
            color: #1976D2;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
</body>
</html>
