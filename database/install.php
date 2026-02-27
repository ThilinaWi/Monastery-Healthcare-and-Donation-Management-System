<?php
/**
 * Database Installation Script
 * Monastery Healthcare and Donation Management System
 * 
 * This script sets up the database and creates all required tables.
 * Run this file once to initialize the database.
 */

// Define constant to allow database access
define('INCLUDED', true);

// Start output buffering for better error handling
ob_start();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Installation - Monastery System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 50px 0;
        }
        .install-container {
            max-width: 800px;
        }
        .step {
            display: none;
        }
        .step.active {
            display: block;
        }
        .log {
            background: #1e1e1e;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            padding: 20px;
            border-radius: 10px;
            max-height: 400px;
            overflow-y: auto;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
    </style>
</head>
<body>
    <div class="container install-container">
        <!-- Header -->
        <div class="text-center mb-5">
            <h1 class="text-white mb-3">
                <i class="fas fa-lotus me-3"></i>
                Database Installation
            </h1>
            <p class="lead text-white">Monastery Healthcare & Donation Management System</p>
        </div>

        <?php
        // Check if this is a POST request (installation attempt)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            // Get form data
            $db_host = $_POST['db_host'] ?? 'localhost';
            $db_username = $_POST['db_username'] ?? 'root';
            $db_password = $_POST['db_password'] ?? '';
            $db_name = $_POST['db_name'] ?? 'monastery_system';
            $admin_username = $_POST['admin_username'] ?? 'admin';
            $admin_email = $_POST['admin_email'] ?? 'admin@monastery.com';
            $admin_password = $_POST['admin_password'] ?? 'admin123';
            $admin_fullname = $_POST['admin_fullname'] ?? 'System Administrator';

            echo '<div class="card shadow-lg">';
            echo '<div class="card-header bg-primary text-white">';
            echo '<h3 class="card-title mb-0"><i class="fas fa-cogs me-2"></i>Installation Progress</h3>';
            echo '</div>';
            echo '<div class="card-body">';
            echo '<div class="log" id="installLog">';

            $success = true;
            
            try {
                // Step 1: Test database connection
                echo "<div class='info'>[INFO] Testing database connection...</div>";
                
                $dsn = "mysql:host={$db_host};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_username, $db_password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                
                echo "<div class='success'>[SUCCESS] Database connection established!</div>";
                
                // Step 2: Create database
                echo "<div class='info'>[INFO] Creating database '{$db_name}'...</div>";
                
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$db_name}`");
                
                echo "<div class='success'>[SUCCESS] Database '{$db_name}' created/selected!</div>";
                
                // Step 3: Read and execute SQL schema
                echo "<div class='info'>[INFO] Reading database schema...</div>";
                
                $sql_file = __DIR__ . '/schema.sql';
                if (!file_exists($sql_file)) {
                    throw new Exception("Schema file not found: {$sql_file}");
                }
                
                $sql_content = file_get_contents($sql_file);
                
                // Remove comments and split by semicolons
                $sql_content = preg_replace('/--.*$/m', '', $sql_content);
                $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
                
                $statements = array_filter(array_map('trim', explode(';', $sql_content)));
                
                echo "<div class='success'>[SUCCESS] Schema loaded! Found " . count($statements) . " statements.</div>";
                
                // Step 4: Execute SQL statements
                echo "<div class='info'>[INFO] Executing database schema...</div>";
                
                $executed = 0;
                foreach ($statements as $statement) {
                    if (empty($statement) || strtoupper(substr($statement, 0, 3)) === 'USE') {
                        continue;
                    }
                    
                    try {
                        $pdo->exec($statement);
                        $executed++;
                        
                        // Show progress for major operations
                        if (stripos($statement, 'CREATE TABLE') !== false) {
                            preg_match('/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`?(\w+)`?/i', $statement, $matches);
                            $table_name = $matches[1] ?? 'unknown';
                            echo "<div class='info'>  → Created table: {$table_name}</div>";
                        }
                        
                    } catch (PDOException $e) {
                        // Ignore "already exists" errors for tables
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            throw $e;
                        }
                    }
                }
                
                echo "<div class='success'>[SUCCESS] Executed {$executed} SQL statements!</div>";
                
                // Step 5: Create admin user
                echo "<div class='info'>[INFO] Creating admin user...</div>";
                
                $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                
                // Check if admin user already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ? OR email = ?");
                $stmt->execute([$admin_username, $admin_email]);
                $admin_exists = $stmt->fetchColumn() > 0;
                
                if (!$admin_exists) {
                    $stmt = $pdo->prepare("INSERT INTO admins (username, email, password, full_name, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$admin_username, $admin_email, $hashed_password, $admin_fullname]);
                    echo "<div class='success'>[SUCCESS] Admin user created successfully!</div>";
                } else {
                    echo "<div class='warning'>[WARNING] Admin user already exists, skipped creation.</div>";
                }
                
                // Step 6: Verify installation
                echo "<div class='info'>[INFO] Verifying installation...</div>";
                
                $tables = ['admins', 'monks', 'doctors', 'donators', 'rooms', 'appointments', 
                          'medical_records', 'donation_categories', 'donations', 'expenses'];
                
                foreach ($tables as $table) {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                    $count = $stmt->fetchColumn();
                    echo "<div class='info'>  → Table '{$table}': {$count} records</div>";
                }
                
                // Step 7: Update config file
                echo "<div class='info'>[INFO] Updating configuration file...</div>";
                
                $config_file = __DIR__ . '/../includes/config.php';
                if (file_exists($config_file)) {
                    $config_content = file_get_contents($config_file);
                    
                    // Update database settings
                    $config_content = preg_replace("/define\('DB_HOST',\s*'[^']*'\);/", "define('DB_HOST', '{$db_host}');", $config_content);
                    $config_content = preg_replace("/define\('DB_USERNAME',\s*'[^']*'\);/", "define('DB_USERNAME', '{$db_username}');", $config_content);
                    $config_content = preg_replace("/define\('DB_PASSWORD',\s*'[^']*'\);/", "define('DB_PASSWORD', '{$db_password}');", $config_content);
                    $config_content = preg_replace("/define\('DB_NAME',\s*'[^']*'\);/", "define('DB_NAME', '{$db_name}');", $config_content);
                    
                    file_put_contents($config_file, $config_content);
                    echo "<div class='success'>[SUCCESS] Configuration file updated!</div>";
                } else {
                    echo "<div class='warning'>[WARNING] Configuration file not found, please update manually.</div>";
                }
                
                echo "<div class='success'><strong>[INSTALLATION COMPLETE!]</strong></div>";
                echo "<div class='info'>You can now access the system with:</div>";
                echo "<div class='info'>Username: {$admin_username}</div>";
                echo "<div class='info'>Password: {$admin_password}</div>";
                
            } catch (Exception $e) {
                $success = false;
                echo "<div class='error'>[ERROR] Installation failed: " . htmlspecialchars($e->getMessage()) . "</div>";
                echo "<div class='error'>Please check your database settings and try again.</div>";
            }

            echo '</div>'; // Close log div
            echo '</div>'; // Close card-body
            echo '<div class="card-footer">';
            
            if ($success) {
                echo '<div class="d-grid">';
                echo '<a href="../index.php" class="btn btn-success btn-lg">';
                echo '<i class="fas fa-home me-2"></i>Go to System Homepage';
                echo '</a>';
                echo '</div>';
            } else {
                echo '<div class="d-grid">';
                echo '<button onclick="location.reload();" class="btn btn-danger btn-lg">';
                echo '<i class="fas fa-redo me-2"></i>Try Again';
                echo '</button>';
                echo '</div>';
            }
            
            echo '</div>'; // Close card-footer
            echo '</div>'; // Close card

        } else {
            // Show installation form
            ?>
            
            <!-- Installation Form -->
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-database me-2"></i>
                        Database Configuration
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <!-- Database Settings -->
                            <div class="col-md-6">
                                <h5 class="mb-3"><i class="fas fa-server me-2"></i>Database Settings</h5>
                                
                                <div class="mb-3">
                                    <label for="db_host" class="form-label">Database Host</label>
                                    <input type="text" class="form-control" id="db_host" name="db_host" 
                                           value="localhost" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_username" class="form-label">Database Username</label>
                                    <input type="text" class="form-control" id="db_username" name="db_username" 
                                           value="root" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_password" class="form-label">Database Password</label>
                                    <input type="password" class="form-control" id="db_password" name="db_password" 
                                           placeholder="Leave empty if no password">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_name" class="form-label">Database Name</label>
                                    <input type="text" class="form-control" id="db_name" name="db_name" 
                                           value="monastery_system" required>
                                </div>
                            </div>
                            
                            <!-- Admin Account Settings -->
                            <div class="col-md-6">
                                <h5 class="mb-3"><i class="fas fa-user-shield me-2"></i>Admin Account</h5>
                                
                                <div class="mb-3">
                                    <label for="admin_username" class="form-label">Admin Username</label>
                                    <input type="text" class="form-control" id="admin_username" name="admin_username" 
                                           value="admin" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="admin_email" class="form-label">Admin Email</label>
                                    <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                           value="admin@monastery.com" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="admin_password" class="form-label">Admin Password</label>
                                    <input type="password" class="form-control" id="admin_password" name="admin_password" 
                                           value="admin123" required>
                                    <div class="form-text">Default: admin123 (change after installation)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="admin_fullname" class="form-label">Admin Full Name</label>
                                    <input type="text" class="form-control" id="admin_fullname" name="admin_fullname" 
                                           value="System Administrator" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Before proceeding:</strong>
                            <ul class="mb-0">
                                <li>Make sure MySQL server is running</li>
                                <li>Verify database credentials are correct</li>
                                <li>Ensure the database user has CREATE privileges</li>
                                <li>Backup any existing data if reinstalling</li>
                            </ul>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-rocket me-2"></i>
                                Install Database
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php
        }
        ?>
        
        <!-- Footer -->
        <div class="text-center mt-4">
            <p class="text-white">
                <i class="fas fa-lotus me-2"></i>
                Monastery Healthcare & Donation Management System
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-scroll log to bottom
        function scrollLogToBottom() {
            const log = document.getElementById('installLog');
            if (log) {
                log.scrollTop = log.scrollHeight;
            }
        }
        
        // Scroll to bottom when new content is added
        const observer = new MutationObserver(scrollLogToBottom);
        const log = document.getElementById('installLog');
        if (log) {
            observer.observe(log, { childList: true, subtree: true });
        }
    </script>
</body>
</html>