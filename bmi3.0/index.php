<?php
// ══════════════════════════════════════════════════════════════════
//  ALL-IN-ONE BMI CALCULATOR (LIQUID GLASS EDITION) ── index.php
//  Everything in one file: PHP + MySQL + HTML + CSS + JavaScript
//  How to run: Put this file in  C:\xampp\htdocs\bmi\
//              Open:  http://localhost/bmi/index.php
// ══════════════════════════════════════════════════════════════════

// ── STEP 4 : DATABASE SETTINGS ────────────────────────────────────
$DB_HOST = 'localhost';   // MySQL server (always localhost in XAMPP)
$DB_NAME = 'bmi_app';    // database name (auto-created below)
$DB_USER = 'root';        // MySQL username
$DB_PASS = '';            // MySQL password — blank for XAMPP default

// ── STEP 3 & 4 : CONNECT + AUTO-CREATE DATABASE & TABLE ──────────
function connectDB($host, $name, $user, $pass)
{
    // Connect without a database name first (so we can CREATE it)
    $pdo = new PDO("mysql:host=$host;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create the database if it doesn't already exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name`");

    // Now select that database
    $pdo->exec("USE `$name`");

    // Create the table — IF NOT EXISTS means it's safe to run every time
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bmi_records (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(100) NOT NULL,
            height     FLOAT        NOT NULL,
            weight     FLOAT        NOT NULL,
            age        INT          DEFAULT 25,
            bmi        FLOAT        NOT NULL,
            category   VARCHAR(50)  NOT NULL,
            created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        )
    ");
    return $pdo;
}

// ── STEP 5 & 6 : HANDLE JAVASCRIPT API CALLS ─────────────────────
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    try {
        $pdo = connectDB($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'DB: ' . $e->getMessage()]);
        exit;
    }

    // ── STEP 5 : Save a BMI result sent by JavaScript ──
    if ($_GET['action'] === 'save') {
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || !isset($d['name'], $d['bmi'], $d['category'])) {
            echo json_encode(['error' => 'Missing fields']);
            exit;
        }

        // Server-side validation: alphabets and spaces only
        if (!preg_match("/^[a-zA-Z\s]+$/", $d['name'])) {
            echo json_encode(['error' => 'Name must contain letters only']);
            exit;
        }

        $s = $pdo->prepare(
            "INSERT INTO bmi_records (name, height, weight, age, bmi, category)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $s->execute([$d['name'], $d['height'], $d['weight'], $d['age'] ?? 25, $d['bmi'], $d['category']]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    // ── STEP 6 : Return last 5 records for a person ──
    if ($_GET['action'] === 'history') {
        $name = $_GET['name'] ?? '';
        if (empty($name)) {
            echo json_encode([]);
            exit;
        }
        $s = $pdo->prepare(
            "SELECT * FROM bmi_records WHERE name = ? ORDER BY created_at DESC LIMIT 5"
        );
        $s->execute([$name]);
        echo json_encode($s->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    exit;
}

$dbError = null;
try {
    connectDB($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS);
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>BMI Calculator - Liquid Glass Edition</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet" />

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Nunito', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #1f4068, #162447, #1b1b2f);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            gap: 24px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Liquid Glass Background Blobs */
        .liquid-blob {
            position: absolute;
            filter: blur(80px);
            z-index: -1;
            opacity: 0.6;
            animation: floatBlob 10s ease-in-out infinite alternate;
        }
        .blob-1 {
            top: -10%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: rgba(102, 126, 234, 0.6);
            border-radius: 50%;
        }
        .blob-2 {
            bottom: -10%;
            right: -10%;
            width: 450px;
            height: 450px;
            background: rgba(246, 79, 89, 0.5);
            border-radius: 50%;
            animation-delay: -5s;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes floatBlob {
            0% { transform: translate(0px, 0px) scale(1); }
            100% { transform: translate(30px, 50px) scale(1.1); }
        }

        .db-error-banner {
            width: 100%;
            max-width: 460px;
            background: rgba(248, 215, 218, 0.85);
            backdrop-filter: blur(10px);
            color: #721c24;
            border-radius: 16px;
            padding: 18px 22px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .db-error-banner small {
            font-weight: 600;
            opacity: 0.8;
        }

        /* Liquid Glass Card Style */
        .card, .history-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-top: 1px solid rgba(255, 255, 255, 0.4);
            border-left: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 30px;
            padding: 40px 36px;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3), inset 0 0 0 1px rgba(255, 255, 255, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 28px;
        }

        .header .emoji {
            font-size: 52px;
            display: block;
            margin-bottom: 8px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
        }

        .header h1 {
            font-size: 28px;
            font-weight: 900;
            color: #ffffff;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            margin-top: 4px;
            font-weight: 600;
        }

        .field {
            margin-bottom: 16px;
            position: relative;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 7px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .field input {
            width: 100%;
            padding: 13px 18px;
            background: rgba(255, 255, 255, 0.08);
            border: 2px solid rgba(255, 255, 255, 0.15);
            border-radius: 14px;
            font-family: 'Nunito', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            outline: none;
            transition: all 0.3s ease;
        }

        .field input:focus {
            border-color: rgba(255, 255, 255, 0.6);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
        }

        .field input.input-error {
            border-color: #ff6b6b;
            background-color: rgba(255, 107, 107, 0.15);
        }

        .field input::placeholder {
            color: rgba(255, 255, 255, 0.35);
            font-weight: 600;
        }

        .field-warning {
            color: #ff8787;
            font-size: 12px;
            font-weight: 700;
            margin-top: 5px;
            display: none;
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.1));
            backdrop-filter: blur(10px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 14px;
            font-family: 'Nunito', sans-serif;
            font-size: 17px;
            font-weight: 800;
            cursor: pointer;
            margin-top: 6px;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .btn:hover {
            transform: translateY(-3px);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.4), rgba(255, 255, 255, 0.2));
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.7);
        }

        .btn:active {
            transform: translateY(0);
        }

        .error-msg {
            color: #ff8787;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            margin-top: 10px;
            display: none;
        }

        .saving-msg {
            color: #a5b4fc;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            margin-top: 10px;
            display: none;
        }

        .result-box {
            margin-top: 24px;
            border-radius: 20px;
            padding: 26px;
            text-align: center;
            display: none;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: popIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes popIn {
            from {
                opacity: 0;
                transform: scale(0.85);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .result-box.underweight { background: rgba(255, 243, 205, 0.85); }
        .result-box.normal { background: rgba(212, 237, 218, 0.85); }
        .result-box.overweight { background: rgba(253, 232, 216, 0.85); }
        .result-box.obese { background: rgba(248, 215, 218, 0.85); }

        .bmi-number {
            font-size: 60px;
            font-weight: 900;
            line-height: 1;
        }

        .underweight .bmi-number { color: #856404; }
        .normal .bmi-number { color: #155724; }
        .overweight .bmi-number { color: #c0560a; }
        .obese .bmi-number { color: #721c24; }

        .category {
            font-size: 18px;
            font-weight: 800;
            margin-top: 6px;
            margin-bottom: 16px;
        }

        .underweight .category { color: #856404; }
        .normal .category { color: #155724; }
        .overweight .category { color: #c0560a; }
        .obese .category { color: #721c24; }

        .scale-bar {
            height: 10px;
            border-radius: 10px;
            background: linear-gradient(to right, #74b9ff, #55efc4, #fdcb6e, #e17055);
            margin-bottom: 8px;
            position: relative;
        }

        .scale-marker {
            width: 18px;
            height: 18px;
            background: white;
            border: 3px solid #333;
            border-radius: 50%;
            position: absolute;
            top: 50%;
            transform: translate(-50%, -50%);
            transition: left 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }

        .scale-labels {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #555;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .suggestions h3 {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #333;
            margin-bottom: 10px;
        }

        .suggestion-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #222;
            text-align: left;
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .suggestion-item .icon {
            font-size: 17px;
            flex-shrink: 0;
        }

        .suggestion-content {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .suggestion-source {
            font-size: 11px;
            font-weight: 700;
            color: #553c9a;
            opacity: 0.9;
        }

        .history-card h2 {
            font-size: 20px;
            font-weight: 900;
            color: #ffffff;
            margin-bottom: 20px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .graph-section {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 22px;
        }

        .graph-title {
            font-size: 13px;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 14px;
        }

        .bar-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .bar-label {
            font-size: 12px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.8);
            width: 54px;
            flex-shrink: 0;
            text-align: right;
        }

        .bar-track {
            flex: 1;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            height: 28px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .bar-fill {
            height: 100%;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            width: 0%;
            transition: width 0.7s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .bar-fill .bar-val {
            font-size: 12px;
            font-weight: 800;
            color: white;
        }

        .bar-fill.underweight { background: linear-gradient(90deg, #f9ca24, #f0932b); }
        .bar-fill.normal { background: linear-gradient(90deg, #6ab04c, #badc58); }
        .bar-fill.overweight { background: linear-gradient(90deg, #e67e22, #f39c12); }
        .bar-fill.obese { background: linear-gradient(90deg, #e74c3c, #c0392b); }
        .bar-fill.prev { opacity: 0.45; }

        .change-badge {
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 8px;
        }

        .log-title {
            font-size: 13px;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .log-entry {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 11px 14px;
            border-radius: 12px;
            margin-bottom: 8px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .log-entry .log-name {
            font-size: 14px;
            font-weight: 800;
            color: #ffffff;
        }

        .log-entry .log-date {
            font-size: 11px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.5);
        }

        .log-entry .log-bmi {
            font-size: 16px;
            font-weight: 900;
            color: #ffffff;
        }

        .cat-pill {
            font-size: 11px;
            font-weight: 700;
            border-radius: 8px;
            padding: 3px 8px;
        }

        .cat-pill.underweight { background: #fff3cd; color: #856404; }
        .cat-pill.normal { background: #d4edda; color: #155724; }
        .cat-pill.overweight { background: #fde8d8; color: #c0560a; }
        .cat-pill.obese { background: #f8d7da; color: #721c24; }
    </style>
</head>

<body>

    <!-- Liquid Glass Animated Background Blobs -->
    <div class="liquid-blob blob-1"></div>
    <div class="liquid-blob blob-2"></div>

    <?php if ($dbError): ?>
        <div class="db-error-banner">
            ❌ <strong>Database Error:</strong> <?= htmlspecialchars($dbError) ?><br>
            <small>Make sure XAMPP is running and MySQL is started.</small>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="header">
            <span class="emoji">⚖️</span>
            <h1>BMI Calculator</h1>
            <p>Check your Body Mass Index instantly</p>
        </div>

        <div class="field">
            <label>Your Name</label>
            <input type="text" id="name" placeholder="e.g. Tushar" oninput="validateNameRealtime()" />
            <div class="field-warning" id="name-warning">⚠️ Name must contain only alphabets and spaces.</div>
        </div>
        <div class="field">
            <label>Height (cm)</label>
            <input type="number" id="height" placeholder="e.g. 170" min="1" />
        </div>
        <div class="field">
            <label>Weight (kg)</label>
            <input type="number" id="weight" placeholder="e.g. 65" min="1" />
        </div>
        <div class="field">
            <label>Age (years)</label>
            <input type="number" id="age" placeholder="e.g. 22" min="1" max="120" />
        </div>

        <button class="btn" onclick="calculateBMI()">Calculate My BMI 🚀</button>

        <p class="error-msg" id="error-msg">⚠️ Please fill in all fields correctly!</p>
        <p class="saving-msg" id="saving-msg">⏳ Saving to server...</p>

        <!-- Result box -->
        <div class="result-box" id="result-box">
            <div class="bmi-number" id="bmi-number">--</div>
            <div class="category" id="bmi-category">--</div>

            <div class="scale-bar">
                <div class="scale-marker" id="scale-marker"></div>
            </div>
            <div class="scale-labels">
                <span>Under&shy;weight<br>&lt;18.5</span>
                <span>Normal<br>18.5–24.9</span>
                <span>Over&shy;weight<br>25–29.9</span>
                <span>Obese<br>≥30</span>
            </div>

            <div class="suggestions">
                <h3>💡 Health Tips For You</h3>
                <div id="suggestions-list"></div>
            </div>
        </div>
    </div>

    <div class="history-card" id="history-card" style="display:none">
        <h2>📊 BMI History &amp; Comparison</h2>
        <div class="graph-section">
            <div class="graph-title">📈 Previous vs Current BMI</div>
            <div id="bar-graph"></div>
            <div class="change-badge" id="change-badge"></div>
        </div>
        <div class="log-title">📋 All Saved Records</div>
        <div id="history-log"></div>
    </div>

    <script>
        // ─────────────────────────────────
        //  NAME VALIDATION
        // ─────────────────────────────────
        function validateNameRealtime() {
            let nameInput = document.getElementById('name');
            let warning = document.getElementById('name-warning');
            let val = nameInput.value;
            let lettersOnlyRegex = /^[a-zA-Z\s]*$/;

            if (!lettersOnlyRegex.test(val)) {
                warning.style.display = 'block';
                nameInput.classList.add('input-error');
                return false;
            } else {
                warning.style.display = 'none';
                nameInput.classList.remove('input-error');
                return true;
            }
        }

        // ─────────────────────────────────
        //  CALCULATE BMI
        // ─────────────────────────────────
        function calculateBMI() {
            let nameInput = document.getElementById('name');
            let name = nameInput.value.trim();
            let height = parseFloat(document.getElementById('height').value);
            let weight = parseFloat(document.getElementById('weight').value);
            let age = parseFloat(document.getElementById('age').value) || 25;

            let lettersOnlyRegex = /^[a-zA-Z\s]+$/;

            if (!name || !lettersOnlyRegex.test(name)) {
                document.getElementById('name-warning').style.display = 'block';
                nameInput.classList.add('input-error');
                document.getElementById('result-box').style.display = 'none';
                return;
            } else {
                document.getElementById('name-warning').style.display = 'none';
                nameInput.classList.remove('input-error');
            }

            if (!height || !weight || height <= 0 || weight <= 0) {
                document.getElementById('error-msg').style.display = 'block';
                document.getElementById('result-box').style.display = 'none';
                return;
            }
            document.getElementById('error-msg').style.display = 'none';

            let heightM = height / 100;
            let bmi = weight / (heightM * heightM);
            bmi = Math.round(bmi * 10) / 10;

            let cat = getCategory(bmi);
            showResult(bmi, cat, age);
            saveToServer({
                name,
                height,
                weight,
                age,
                bmi,
                category: cat.css
            });
        }

        function getCategory(bmi) {
            if (bmi < 18.5) return { label: 'Underweight', css: 'underweight', emoji: '🌱' };
            if (bmi < 25) return { label: 'Normal Weight', css: 'normal', emoji: '✅' };
            if (bmi < 30) return { label: 'Overweight', css: 'overweight', emoji: '⚠️' };
            return { label: 'Obese', css: 'obese', emoji: '🔴' };
        }

        function showResult(bmi, cat, age) {
            let box = document.getElementById('result-box');
            box.className = 'result-box ' + cat.css;
            box.style.display = 'block';

            document.getElementById('bmi-number').textContent = bmi;
            document.getElementById('bmi-category').textContent = cat.emoji + ' ' + cat.label;

            let pct = Math.min(Math.max(((bmi - 10) / 30) * 100, 2), 98);
            document.getElementById('scale-marker').style.left = pct + '%';

            let tips = getSuggestions(cat.css, age);
            document.getElementById('suggestions-list').innerHTML = tips.map(function(t) {
                return `
                  <div class="suggestion-item">
                    <span class="icon">${t.icon}</span>
                    <div class="suggestion-content">
                      <span>${t.text}</span>
                      <span class="suggestion-source">📚 Source: ${t.source}</span>
                    </div>
                  </div>
                `;
            }).join('');
        }

        function saveToServer(data) {
            document.getElementById('saving-msg').style.display = 'block';

            fetch('index.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(result => {
                document.getElementById('saving-msg').style.display = 'none';
                if (result.success) {
                    loadHistory(data.name);
                } else {
                    console.error('Save error:', result.error);
                }
            })
            .catch(err => {
                document.getElementById('saving-msg').style.display = 'none';
                alert('Server error! Make sure XAMPP is running.\n\n' + err);
            });
        }

        function loadHistory(name) {
            fetch('index.php?action=history&name=' + encodeURIComponent(name))
                .then(res => res.json())
                .then(records => renderHistory(records))
                .catch(err => console.error('History error:', err));
        }

        function renderHistory(records) {
            let card = document.getElementById('history-card');
            if (records.length === 0) {
                card.style.display = 'none';
                return;
            }
            card.style.display = 'block';

            renderBarGraph(records);

            let html = '';
            records.forEach(function(r) {
                html +=
                    '<div class="log-entry">' +
                    '<div>' +
                    '<div class="log-name">' + r.name + '</div>' +
                    '<div class="log-date">' + formatDate(r.created_at) + '</div>' +
                    '</div>' +
                    '<div style="display:flex;align-items:center;gap:8px">' +
                    '<span class="log-bmi">' + r.bmi + '</span>' +
                    '<span class="cat-pill ' + r.category + '">' + r.category + '</span>' +
                    '</div>' +
                    '</div>';
            });
            document.getElementById('history-log').innerHTML = html;
        }

        function renderBarGraph(records) {
            let show = records.slice(0, 5).reverse();
            let maxBMI = 40;
            let html = '';

            show.forEach(function(r, i) {
                let isCurrent = (i === show.length - 1);
                let isPrev = (i === show.length - 2);

                let label;
                if (isCurrent) label = 'Now';
                else if (isPrev) label = 'Prev';
                else label = formatDate(r.created_at).split(' ').slice(0, 2).join(' ');

                let widthPct = Math.min((r.bmi / maxBMI) * 100, 100);
                let prevClass = isCurrent ? '' : ' prev';

                html +=
                    '<div class="bar-row">' +
                    '<div class="bar-label">' + label + '</div>' +
                    '<div class="bar-track">' +
                    '<div class="bar-fill ' + r.category + prevClass + '" ' +
                    'style="width:0%" data-width="' + widthPct + '">' +
                    '<span class="bar-val">' + r.bmi + '</span>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            });
            document.getElementById('bar-graph').innerHTML = html;

            setTimeout(function() {
                document.querySelectorAll('.bar-fill').forEach(function(el) {
                    el.style.width = el.getAttribute('data-width') + '%';
                });
            }, 50);

            let badge = document.getElementById('change-badge');
            if (show.length >= 2) {
                let prev = parseFloat(show[show.length - 2].bmi);
                let curr = parseFloat(show[show.length - 1].bmi);
                let diff = Math.round((curr - prev) * 10) / 10;
                let icon = diff < 0 ? '📉' : diff > 0 ? '📈' : '➡️';
                let msg = diff < 0 ? diff + ' — Great progress!' : diff > 0 ? '+' + diff + ' since last time' : 'No change since last time';
                badge.innerHTML = '<span style="font-size:20px">' + icon + '</span> ' + msg;
            } else {
                badge.innerHTML = '📌 First entry saved — keep checking in!';
            }
        }

        function formatDate(str) {
            let d = new Date(str);
            return d.toLocaleDateString('en-IN', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            });
        }

        function getSuggestions(cat, age) {
            let tips = {
                underweight: [
                    { icon: '🍳', text: 'Eat calorie-dense foods: eggs, nuts, avocados, whole grains.', source: 'NHS Guidelines' },
                    { icon: '🏋️', text: 'Strength training 3×/week builds muscle, not just fat.', source: 'ACSM' },
                    { icon: '🥛', text: 'Add protein shakes or smoothies between meals.', source: 'Academy of Nutrition' },
                ],
                normal: [
                    { icon: '🏃', text: 'Keep up 150 mins of moderate exercise per week — great job!', source: 'WHO' },
                    { icon: '🥗', text: 'Balanced diet: fruits, vegetables, lean protein.', source: 'Harvard Health' },
                    { icon: '📊', text: 'Recheck your BMI every 3–6 months.', source: 'CDC' },
                ],
                overweight: [
                    { icon: '🚶', text: '30-minute brisk walk every day — small steps matter!', source: 'AHA' },
                    { icon: '🍽️', text: 'Reduce portions; cut sugary drinks and processed snacks.', source: 'CDC Guidelines' },
                    { icon: '📝', text: 'Food journal for 1 week — awareness is the first step.', source: 'NHS' },
                ],
                obese: [
                    { icon: '👨‍⚕️', text: 'Consult a doctor/dietitian for a safe, personalised plan.', source: 'WHO' },
                    { icon: '🏊', text: 'Swimming or cycling — low-impact, easier on joints.', source: 'Arthritis Foundation' },
                    { icon: '🚫', text: 'Cut sugary drinks — a huge hidden calorie source.', source: 'Harvard Public Health' },
                ],
            };

            let ageTip = age < 18 ? 
                { icon: '🧒', text: 'As a teen, focus on healthy habits. Enjoy sports and play!', source: 'AAP' } :
                age >= 60 ? 
                { icon: '🦴', text: 'Include calcium-rich foods and balance exercises for bone health.', source: 'NOF' } : 
                { icon: '🔋', text: 'Consistency beats perfection — build small habits every day.', source: 'CDC' };

            let generalTip = { icon: '💧', text: 'Drink at least 8 glasses of water daily.', source: 'U.S. National Academies' };

            return [ageTip, ...tips[cat], generalTip];
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') calculateBMI();
        });
    </script>

</body>

</html>