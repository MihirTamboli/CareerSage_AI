<?php
/**
 * CareerSage AI Database Setup
 * Imports schema.sql and seed.sql into careersage_ai
 */

$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'careersage_ai';

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h2>CareerSage AI - Database Setup</h2>";
    $fresh = isset($_GET['fresh']) && $_GET['fresh'] === '1';
    if ($fresh) {
        $pdo->exec("DROP DATABASE IF EXISTS `$dbname`");
        echo "🧹 Fresh rebuild requested. Existing database dropped.<br>";
    } else {
        echo "ℹ️ Tip: add <code>?fresh=1</code> to the URL to rebuild from scratch.<br>";
    }
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }

    $schemaSql = file_get_contents($schemaFile);
    $schemaStatements = array_filter(array_map('trim', explode(';', $schemaSql)));
    foreach ($schemaStatements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    echo "✅ Schema imported successfully<br>";

    $careerCount = $pdo->query("SELECT COUNT(*) FROM career_master")->fetchColumn();
    $mcqCount = $pdo->query("SELECT COUNT(*) FROM mcq_questions")->fetchColumn();
    if ((int)$careerCount === 0 || (int)$mcqCount === 0) {
        $seedFile = __DIR__ . '/seed.sql';
        if (!file_exists($seedFile)) {
            throw new Exception("Seed file not found: $seedFile");
        }
        $seedSql = file_get_contents($seedFile);
        $seedStatements = array_filter(array_map('trim', explode(';', $seedSql)));
        foreach ($seedStatements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        echo "✅ Seed data imported successfully<br>";
    } else {
        echo "ℹ️ Seed data skipped (already present)<br>";
    }

    echo "<h3>✅ Database setup complete</h3>";
    echo "<p>Go to <a href=\"../frontend/index.html\">CareerSage AI</a></p>";
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage();
} catch (Exception $e) {
    echo "❌ Setup Error: " . $e->getMessage();
}
