<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Parcel Box Dashboard - IoT System</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #ff97cbff 0%, #ff98d8ff 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans Thai', sans-serif;
            padding: 20px;
            min-height: 100vh;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-bar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-title {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .header-subtitle {
            font-size: 14px;
            color: #718096;
            font-weight: 500;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .status-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .status-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4299e1, #667eea);
        }

        .status-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }

        .card-icon.distance { background: linear-gradient(45deg, #4299e1, #667eea); }
        .card-icon.motion { background: linear-gradient(45deg, #38b2ac, #4299e1); }
        .card-icon.status { background: linear-gradient(45deg, #ed8936, #f56500); }
        .card-icon.network { background: linear-gradient(45deg, #38a169, #48bb78); }

        .card-title {
            font-size: 16px;
            color: #2d3748;
            font-weight: 600;
        }

        .card-value {
            font-size: 28px;
            font-weight: 800;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .card-status {
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }

        .status-online { background: #c6f6d5; color: #22543d; }
        .status-offline { background: #fed7d7; color: #742a2a; }
        .status-active { background: #bee3f8; color: #2a4365; }

        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        .chart-section, .control-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .chart-title, .control-title, .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .chart-container {
            width: 100%;
            height: 350px;
            position: relative;
        }

        .control-section .status-display {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .status-item {
            background: rgba(247, 250, 252, 0.8);
            border: 2px solid rgba(226, 232, 240, 0.8);
            border-radius: 15px;
            padding: 15px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .status-item.active {
            background: rgba(236, 253, 245, 0.9);
            border-color: #38a169;
            transform: scale(1.02);
            box-shadow: 0 4px 15px rgba(56, 161, 105, 0.2);
        }

        .status-left {
            display: flex;
            align-items: center;
        }

        .status-indicator {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            margin-right: 12px;
            flex-shrink: 0;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }

        .status-indicator.green { 
            background: linear-gradient(45deg, #38a169, #48bb78);
            box-shadow: 0 0 10px rgba(56, 161, 105, 0.5);
        }
        .status-indicator.yellow { 
            background: linear-gradient(45deg, #d69e2e, #ed8936);
            box-shadow: 0 0 10px rgba(214, 158, 46, 0.5);
        }
        .status-indicator.red { 
            background: linear-gradient(45deg, #e53e3e, #f56565);
            box-shadow: 0 0 10px rgba(229, 62, 62, 0.5);
        }

        .status-badge {
            background: linear-gradient(45deg, #38a169, #48bb78);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.inactive {
            background: linear-gradient(45deg, #e53e3e, #f56565);
        }

        /* Filter Section */
        .filter-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
        }

        .filter-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 6px;
        }

        .filter-input, .filter-select {
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #4299e1, #667eea);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 153, 225, 0.4);
        }

        .btn-success {
            background: linear-gradient(45deg, #38a169, #48bb78);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(56, 161, 105, 0.4);
        }

        .btn-danger {
            background: linear-gradient(45deg, #e53e3e, #f56565);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(229, 62, 62, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #718096, #a0aec0);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(113, 128, 150, 0.4);
        }

        /* History Section */
        .bottom-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .table-wrapper {
            overflow-x: auto;
            margin-top: 15px;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th,
        .history-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }

        .history-table th {
            background: rgba(237, 242, 247, 0.8);
            font-weight: 600;
            color: #2d3748;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .history-table td {
            color: #4a5568;
            font-size: 14px;
        }

        .history-table tr:hover {
            background: rgba(237, 242, 247, 0.5);
        }

        .result-count {
            font-size: 14px;
            color: #718096;
            margin-top: 15px;
            font-weight: 500;
        }

        .update-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(45deg, #38a169, #48bb78);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 12px;
            font-weight: 600;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .update-indicator.updating {
            background: linear-gradient(45deg, #d69e2e, #ed8936);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }

        @media (max-width: 1200px) {
            .main-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body { padding: 10px; }
            .status-grid {
                grid-template-columns: 1fr;
            }
            .filter-controls {
                grid-template-columns: 1fr;
            }
            .chart-container { height: 250px; }
        }
    </style>
</head>
<body>
    <div class="update-indicator" id="updateIndicator">กำลังโหลดข้อมูล...</div>
    
    <div class="dashboard-container">
        <!-- Header -->
        <div class="header-bar">
            <div class="header-title">ระบบแจ้งเตือนการรับ-ส่งพัสดุอัจฉริยะ</div>
            <div class="header-subtitle">Internet of Things (IoT) Smart Parcel Box System</div>
        </div>

        <!-- Status Cards -->
        <div class="status-grid">
            <div class="status-card">
                <div class="card-header">
                    <div class="card-icon distance">📏</div>
                    <div class="card-title">ความจุในกล่อง</div>
                </div>
                <div class="card-value" id="distance-display">-- ซม.</div>
                <div class="card-status status-active" id="distance-status">กำลังตรวจสอบ</div>
            </div>

            <div class="status-card">
                <div class="card-header">
                    <div class="card-icon motion">🚶</div>
                    <div class="card-title">การตรวจจับการเคลื่อนไหว</div>
                </div>
                <div class="card-value" id="motion-status">ไม่พบ</div>
                <div class="card-status status-offline" id="motion-badge">ไม่ใช้งาน</div>
            </div>

            <div class="status-card">
                <div class="card-header">
                    <div class="card-icon status">📦</div>
                    <div class="card-title">สถานะกล่องพัสดุ</div>
                </div>
                <div class="card-value" id="box-status">ปิด</div>
                <div class="card-status status-online" id="box-badge">พร้อมใช้งาน</div>
            </div>

            <div class="status-card">
                <div class="card-header">
                    <div class="card-icon network">📡</div>
                    <div class="card-title">การเชื่อมต่อเครือข่าย</div>
                </div>
                <div class="card-value" id="network-status">ออนไลน์</div>
                <div class="card-status status-online" id="network-badge">เชื่อมต่อแล้ว</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="chart-section">
                <div class="chart-title">📊 แผนภูมิข้อมูลเซ็นเซอร์แบบเรียลไทม์</div>
                <div class="chart-container">
                    <canvas id="sensorChart"></canvas>
                </div>
            </div>

            <div class="control-section">
                <div class="control-title">🎛️ การควบคุมรีเลย์</div>
                <div class="status-display">
                    <div class="status-item active" id="relay-ready">
                        <div class="status-left">
                            <div class="status-indicator green"></div>
                            <div class="status-label">ส่งพัสดุได้เลย</div>
                        </div>
                        <div class="status-badge">สถานะรีเลย์ : ทำงาน</div>
                    </div>
                    <div class="status-item" id="relay-medium">
                        <div class="status-left">
                            <div class="status-indicator yellow"></div>
                            <div class="status-label">พัสดุค่อนข้างเยอะ</div>
                        </div>
                        <div class="status-badge inactive">สถานะรีเลย์ : ไม่ทำงาน</div>
                    </div>
                    <div class="status-item" id="relay-full">
                        <div class="status-left">
                            <div class="status-indicator red"></div>
                            <div class="status-label">พัสดุเต็มแล้ว</div>
                        </div>
                        <div class="status-badge inactive">สถานะรีเลย์ : ไม่ทำงาน</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-header">
                <div class="filter-title">🔍 กรองข้อมูลประวัติการใช้งาน</div>
            </div>
            
            <div class="filter-controls">
                <div class="filter-group">
                    <label class="filter-label">วันที่เริ่มต้น</label>
                    <input type="date" id="filterStartDate" class="filter-input">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">วันที่สิ้นสุด</label>
                    <input type="date" id="filterEndDate" class="filter-input">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">โหมดการทำงาน</label>
                    <select id="filterMode" class="filter-select">
                        <option value="">ทั้งหมด</option>
                        <option value="online">ออนไลน์</option>
                        <option value="offline">ออฟไลน์</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">การดำเนินการ</label>
                    <select id="filterAction" class="filter-select">
                        <option value="">ทั้งหมด</option>
                        <option value="OPEN_ONLINE">เปิดกล่อง (ออนไลน์)</option>
                        <option value="OPEN_OFFLINE">เปิดกล่อง (ออฟไลน์)</option>
                        <option value="AUTO_CLOSE">ปิดอัตโนมัติ</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions">
                <button class="btn btn-primary" onclick="applyFilters()">
                    🔍 กรองข้อมูล
                </button>
                <button class="btn btn-secondary" onclick="resetFilters()">
                    🔄 ล้างตัวกรอง
                </button>
                <button class="btn btn-success" onclick="exportToExcel()">
                    📊 ดาวน์โหลด Excel
                </button>
                <button class="btn btn-danger" onclick="exportToPDF()">
                    📄 ดาวน์โหลด PDF
                </button>
            </div>
        </div>

        <!-- Bottom Section -->
        <div class="bottom-section">
            <div class="section-title">📋 ประวัติการใช้งานระบบ</div>
            <div class="table-wrapper">
                <table class="history-table" id="historyTable">
                    <thead>
                        <tr>
                            <th>เวลา</th>
                            <th>การดำเนินการ</th>
                            <th>โหมด</th>
                            <th>ระยะทาง</th>
                            <th>การเคลื่อนไหว</th>
                            <th>ระยะเวลา</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #718096;">
                                กำลังโหลดข้อมูล...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="result-count" id="resultCount">แสดง 0 รายการ</div>
        </div>
    </div>

    <script>
        let sensorChart;
        let distanceHistory = [];
        let motionHistory = [];
        let timeLabels = [];
        let allOperations = []; // เก็บข้อมูลทั้งหมด
        let filteredOperations = []; // เก็บข้อมูลที่กรองแล้ว

        const API_BASE_URL = 'dashboard_api.php';

        document.addEventListener('DOMContentLoaded', function() {
            initializeChart();
            setDefaultDateFilter();
            startDataUpdates();
        });

        function setDefaultDateFilter() {
            const today = new Date();
            const sevenDaysAgo = new Date(today);
            sevenDaysAgo.setDate(today.getDate() - 7);
            
            document.getElementById('filterEndDate').valueAsDate = today;
            document.getElementById('filterStartDate').valueAsDate = sevenDaysAgo;
        }

        function initializeChart() {
            const ctx = document.getElementById('sensorChart').getContext('2d');
            
            sensorChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: timeLabels,
                    datasets: [{
                        label: 'ระยะทาง (ซม.)',
                        data: distanceHistory,
                        borderColor: 'rgba(66, 153, 225, 1)',
                        backgroundColor: 'rgba(66, 153, 225, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'การเคลื่อนไหว',
                        data: motionHistory,
                        borderColor: 'rgba(56, 178, 172, 1)',
                        backgroundColor: 'rgba(56, 178, 172, 0.1)',
                        tension: 0.4,
                        fill: false,
                        stepped: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 50
                        }
                    }
                }
            });
        }

        function startDataUpdates() {
            fetchLatestData();
            fetchOperationHistory();
            setInterval(fetchLatestData, 2000);
            setInterval(fetchOperationHistory, 10000);
        }

        async function fetchLatestData() {
            try {
                const response = await fetch(`${API_BASE_URL}?action=latest_data`);
                const data = await response.json();
                
                if (data && data.distance_cm !== undefined) {
                    updateSensorData(data);
                }
            } catch (error) {
                console.error('Error:', error);
                useDemoData();
            }
        }

        async function fetchOperationHistory() {
            try {
                const response = await fetch(`${API_BASE_URL}?action=operation_history`);
                const data = await response.json();
                
                if (Array.isArray(data)) {
                    allOperations = data;
                    applyFilters();
                }
            } catch (error) {
                console.error('Error:', error);
                // สร้างข้อมูลตัวอย่าง
                createDemoHistory();
            }
        }

        function createDemoHistory() {
            allOperations = [];
            const operations = ['OPEN_ONLINE', 'OPEN_OFFLINE', 'AUTO_CLOSE'];
            
            for (let i = 0; i < 20; i++) {
                const date = new Date();
                date.setHours(date.getHours() - i);
                
                allOperations.push({
                    timestamp: date.toISOString(),
                    operation_type: operations[Math.floor(Math.random() * operations.length)],
                    internet_mode: Math.random() > 0.3,
                    distance_at_operation: 20 + Math.random() * 20,
                    motion_detected: Math.random() > 0.5,
                    open_duration_seconds: Math.floor(Math.random() * 10)
                });
            }
            
            applyFilters();
        }

        function applyFilters() {
            const startDate = document.getElementById('filterStartDate').value;
            const endDate = document.getElementById('filterEndDate').value;
            const mode = document.getElementById('filterMode').value;
            const action = document.getElementById('filterAction').value;

            filteredOperations = allOperations.filter(op => {
                const opDate = new Date(op.timestamp);
                
                // กรองตามวันที่
                if (startDate && opDate < new Date(startDate)) return false;
                if (endDate) {
                    const endDateTime = new Date(endDate);
                    endDateTime.setHours(23, 59, 59);
                    if (opDate > endDateTime) return false;
                }
                
                // กรองตามโหมด
                if (mode === 'online' && !op.internet_mode) return false;
                if (mode === 'offline' && op.internet_mode) return false;
                
                // กรองตามการดำเนินการ
                if (action && op.operation_type !== action) return false;
                
                return true;
            });

            updateHistoryTable(filteredOperations);
        }

        function resetFilters() {
            setDefaultDateFilter();
            document.getElementById('filterMode').value = '';
            document.getElementById('filterAction').value = '';
            applyFilters();
        }

        function updateHistoryTable(operations) {
            const tbody = document.querySelector('#historyTable tbody');
            
            if (operations.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px; color: #718096;">
                            ไม่พบข้อมูลที่ตรงกับเงื่อนไขการกรอง
                        </td>
                    </tr>
                `;
                document.getElementById('resultCount').textContent = 'แสดง 0 รายการ';
                return;
            }

            tbody.innerHTML = operations.map(op => {
                const date = new Date(op.timestamp);
                const timeString = date.toLocaleString('th-TH');
                
                const operationText = {
                    'OPEN_ONLINE': 'เปิดกล่อง (ออนไลน์)',
                    'OPEN_OFFLINE': 'เปิดกล่อง (ออฟไลน์)',
                    'AUTO_CLOSE': 'ปิดอัตโนมัติ'
                }[op.operation_type] || op.operation_type;

                const modeText = op.internet_mode ? 'ออนไลน์' : 'ออฟไลน์';
                const motionText = op.motion_detected ? 'ตรวจพบ' : 'ไม่พบ';
                const durationText = op.open_duration_seconds ? `${op.open_duration_seconds} วินาที` : '-';

                return `
                    <tr>
                        <td>${timeString}</td>
                        <td>${operationText}</td>
                        <td><span class="card-status ${op.internet_mode ? 'status-online' : 'status-offline'}">${modeText}</span></td>
                        <td>${op.distance_at_operation ? op.distance_at_operation.toFixed(1) + ' ซม.' : '-'}</td>
                        <td>${motionText}</td>
                        <td>${durationText}</td>
                    </tr>
                `;
            }).join('');
            
            document.getElementById('resultCount').textContent = `แสดง ${operations.length} รายการ`;
        }

        function exportToExcel() {
            if (filteredOperations.length === 0) {
                alert('ไม่มีข้อมูลที่จะส่งออก');
                return;
            }

            // เตรียมข้อมูลสำหรับ Excel
            const excelData = filteredOperations.map(op => {
                const date = new Date(op.timestamp);
                const operationText = {
                    'OPEN_ONLINE': 'เปิดกล่อง (ออนไลน์)',
                    'OPEN_OFFLINE': 'เปิดกล่อง (ออฟไลน์)',
                    'AUTO_CLOSE': 'ปิดอัตโนมัติ'
                }[op.operation_type] || op.operation_type;

                return {
                    'เวลา': date.toLocaleString('th-TH'),
                    'การดำเนินการ': operationText,
                    'โหมด': op.internet_mode ? 'ออนไลน์' : 'ออฟไลน์',
                    'ระยะทาง (ซม.)': op.distance_at_operation ? op.distance_at_operation.toFixed(1) : '-',
                    'การเคลื่อนไหว': op.motion_detected ? 'ตรวจพบ' : 'ไม่พบ',
                    'ระยะเวลา (วินาที)': op.open_duration_seconds || '-'
                };
            });

            // สร้าง worksheet
            const ws = XLSX.utils.json_to_sheet(excelData);
            
            // กำหนดความกว้างของคอลัมน์
            ws['!cols'] = [
                { wch: 20 }, // เวลา
                { wch: 25 }, // การดำเนินการ
                { wch: 12 }, // โหมด
                { wch: 15 }, // ระยะทาง
                { wch: 15 }, // การเคลื่อนไหว
                { wch: 15 }  // ระยะเวลา
            ];

            // สร้าง workbook
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'ประวัติการใช้งาน');

            // ดาวน์โหลดไฟล์
            const fileName = `Smart_Parcel_Box_Report_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(wb, fileName);
            
            alert(`ดาวน์โหลดไฟล์ ${fileName} สำเร็จ!`);
        }

        function exportToPDF() {
            if (filteredOperations.length === 0) {
                alert('ไม่มีข้อมูลที่จะส่งออก');
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // เพิ่มฟอนต์ไทย (ใช้ฟอนต์ default ของ jsPDF ที่รองรับภาษาไทย)
            doc.setFont("helvetica");
            
            // หัวเอกสาร
            doc.setFontSize(16);
            doc.text('Smart Parcel Box - Report', 105, 15, { align: 'center' });
            
            doc.setFontSize(10);
            doc.text(`Generated: ${new Date().toLocaleString('th-TH')}`, 105, 22, { align: 'center' });
            doc.text(`Total Records: ${filteredOperations.length}`, 105, 28, { align: 'center' });

            // เตรียมข้อมูลตาราง
            let yPos = 40;
            const lineHeight = 7;
            const pageHeight = 280;

            doc.setFontSize(9);
            
            // หัวตาราง
            doc.setFont("helvetica", "bold");
            doc.text('Time', 10, yPos);
            doc.text('Operation', 50, yPos);
            doc.text('Mode', 100, yPos);
            doc.text('Distance', 130, yPos);
            doc.text('Motion', 160, yPos);
            doc.text('Duration', 185, yPos);
            
            yPos += lineHeight;
            doc.line(10, yPos - 2, 200, yPos - 2);
            
            doc.setFont("helvetica", "normal");

            // ข้อมูล
            filteredOperations.forEach((op, index) => {
                if (yPos > pageHeight) {
                    doc.addPage();
                    yPos = 20;
                }

                const date = new Date(op.timestamp);
                const timeStr = date.toLocaleTimeString('th-TH');
                
                const operationMap = {
                    'OPEN_ONLINE': 'Open (Online)',
                    'OPEN_OFFLINE': 'Open (Offline)',
                    'AUTO_CLOSE': 'Auto Close'
                };
                
                doc.text(timeStr, 10, yPos);
                doc.text(operationMap[op.operation_type] || op.operation_type, 50, yPos);
                doc.text(op.internet_mode ? 'Online' : 'Offline', 100, yPos);
                doc.text(op.distance_at_operation ? op.distance_at_operation.toFixed(1) + ' cm' : '-', 130, yPos);
                doc.text(op.motion_detected ? 'Yes' : 'No', 160, yPos);
                doc.text(op.open_duration_seconds ? op.open_duration_seconds + ' s' : '-', 185, yPos);
                
                yPos += lineHeight;
            });

            // บันทึกไฟล์
            const fileName = `Smart_Parcel_Box_Report_${new Date().toISOString().split('T')[0]}.pdf`;
            doc.save(fileName);
            
            alert(`ดาวน์โหลดไฟล์ ${fileName} สำเร็จ!`);
        }

        function updateSensorData(data) {
            document.getElementById('distance-display').textContent = data.distance_cm.toFixed(1) + ' ซม.';
            
            const motionElement = document.getElementById('motion-status');
            const motionBadgeElement = document.getElementById('motion-badge');
            
            if (data.pir_motion) {
                motionElement.textContent = 'ตรวจพบ';
                motionBadgeElement.textContent = 'ใช้งานอยู่';
                motionBadgeElement.className = 'card-status status-online';
            } else {
                motionElement.textContent = 'ไม่พบ';
                motionBadgeElement.textContent = 'ไม่ใช้งาน';
                motionBadgeElement.className = 'card-status status-offline';
            }

            const boxStatusElement = document.getElementById('box-status');
            const boxBadgeElement = document.getElementById('box-badge');
            
            if (data.box_status === 'OPEN') {
                boxStatusElement.textContent = 'เปิด';
                boxBadgeElement.textContent = 'กำลังใช้งาน';
                boxBadgeElement.className = 'card-status status-active';
            } else {
                boxStatusElement.textContent = 'ปิด';
                boxBadgeElement.textContent = 'พร้อมใช้งาน';
                boxBadgeElement.className = 'card-status status-online';
            }

            updateRelayStatus(data.lamp_status, data.distance_cm);
            updateChartData(data.distance_cm, data.pir_motion ? 1 : 0);
        }

        function updateRelayStatus(lampStatus, distance) {
            const relayItems = ['relay-ready', 'relay-medium', 'relay-full'];
            relayItems.forEach(id => {
                const element = document.getElementById(id);
                element.classList.remove('active');
                const badge = element.querySelector('.status-badge');
                badge.textContent = 'สถานะรีเลย์ : ไม่ทำงาน';
                badge.className = 'status-badge inactive';
            });

            let activeRelay = null;
            if (distance <= 10) {
                activeRelay = 'relay-full';
            } else if (distance <= 20) {
                activeRelay = 'relay-medium';
            } else if (distance <= 40) {
                activeRelay = 'relay-ready';
            }

            if (activeRelay) {
                const element = document.getElementById(activeRelay);
                element.classList.add('active');
                const badge = element.querySelector('.status-badge');
                badge.textContent = 'สถานะรีเลย์ : ทำงาน';
                badge.className = 'status-badge';
            }
        }

        function updateChartData(distance, motion) {
            const now = new Date();
            const timeLabel = now.toLocaleTimeString('th-TH', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });

            distanceHistory.push(distance);
            motionHistory.push(motion);
            timeLabels.push(timeLabel);

            if (distanceHistory.length > 20) {
                distanceHistory.shift();
                motionHistory.shift();
                timeLabels.shift();
            }

            sensorChart.data.labels = timeLabels;
            sensorChart.data.datasets[0].data = distanceHistory;
            sensorChart.data.datasets[1].data = motionHistory;
            sensorChart.update('none');
        }

        function useDemoData() {
            const demoData = {
                distance_cm: 20 + Math.random() * 10,
                pir_motion: Math.random() > 0.7,
                box_status: 'CLOSED',
                lamp_status: 'GREEN ON'
            };
            updateSensorData(demoData);
        }

        // เริ่มต้นระบบด้วยข้อมูลตัวอย่าง
        createDemoHistory();
    </script>
</body>
</html>