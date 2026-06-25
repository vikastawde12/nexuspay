<?php
// Database Check
require_once 'config.php';

echo "<h1>🔍 NexusPay Database Check</h1>";

$db = getDB();
if (!$db) {
    die("❌ Database connection failed! Please check config.php");
}

echo "<p style='color:green;'>✅ Database connected successfully!</p>";

// Check tables
$tables = $db->query("SHOW TABLES");
echo "<h2>📋 Tables:</h2>";
echo "<ul>";
$count = 0;
while ($row = $tables->fetch(PDO::FETCH_NUM)) {
    echo "<li>✅ " . $row[0] . "</li>";
    $count++;
}
echo "</ul>";
echo "<p>Total: <strong>$count</strong> tables found.</p>";

if ($count == 0) {
    echo "<p style='color:red;'>❌ No tables found! Please create tables.</p>";
}

// Check settings
echo "<h2>⚙️ Settings:</h2>";
$settings = $db->query("SELECT * FROM settings");
if ($settings->rowCount() > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Key</th><th>Value</th></tr>";
    while ($row = $settings->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>" . $row['setting_key'] . "</td><td>" . $row['setting_value'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:orange;'>⚠️ No settings found!</p>";
}

// Check warnings
echo "<h2>⚠️ Warnings:</h2>";
try {
    $warnings = $db->query("SHOW WARNINGS");
    if ($warnings->rowCount() > 0) {
        while ($row = $warnings->fetch(PDO::FETCH_ASSOC)) {
            echo "<p style='color:orange;'>⚠️ " . $row['Level'] . " - " . $row['Code'] . " - " . $row['Message'] . "</p>";
        }
    } else {
        echo "<p style='color:green;'>✅ No warnings found!</p>";
    }
} catch (Exception $e) {
    echo "<p>ℹ️ " . $e->getMessage() . "</p>";
}

// Check errors
echo "<h2>❌ Errors:</h2>";
try {
    $errors = $db->query("SHOW ERRORS");
    if ($errors->rowCount() > 0) {
        while ($row = $errors->fetch(PDO::FETCH_ASSOC)) {
            echo "<p style='color:red;'>❌ " . $row['Level'] . " - " . $row['Code'] . " - " . $row['Message'] . "</p>";
        }
    } else {
        echo "<p style='color:green;'>✅ No errors found!</p>";
    }
} catch (Exception $e) {
    echo "<p>ℹ️ " . $e->getMessage() . "</p>";
}

// PHP Info
echo "<h2>🔧 Server Info:</h2>";
echo "<ul>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>MySQL Version: " . $db->getAttribute(PDO::ATTR_SERVER_VERSION) . "</li>";
echo "<li>Server IP: " . $_SERVER['SERVER_ADDR'] . "</li>";
echo "</ul>";

// Memory
echo "<h2>📊 Memory:</h2>";
echo "<ul>";
echo "<li>Current: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB</li>";
echo "<li>Peak: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB</li>";
echo "</ul>";

// Links
echo "<hr>";
echo "<p>";
echo "<a href='index.php'>🏠 Home</a> | ";
echo "<a href='admin.php'>🔐 Admin</a> | ";
echo "<a href='testdb.php'>🧪 Test DB</a>";
echo "</p>";
?>
