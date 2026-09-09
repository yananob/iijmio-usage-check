<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IIJmio Usage Checker - 日別グラフ</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="h-full text-slate-800 font-sans antialiased">
    <div class="min-h-screen py-3 sm:py-6 px-3 sm:px-6 lg:px-8 flex flex-col justify-between">
        <div class="max-w-4xl w-full mx-auto bg-white rounded-2xl shadow-md border border-slate-100 overflow-hidden mb-4">
            <!-- Navigation Header -->
            @include('nav')

            <div class="p-4 sm:p-6 space-y-5">
                <!-- Transition Banner to Monthly View -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3.5 bg-indigo-50 border border-indigo-100 rounded-xl">
                    <div class="text-xs sm:text-sm font-medium text-indigo-900">
                        日ごとの個人別利用量グラフを表示しています。月ごとの推移を見るには月別画面へお進みください。
                    </div>
                    <a href="?page=monthly" class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-lg transition active:scale-95 shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                        月別画面へ
                    </a>
                </div>

                <!-- Loading Skeleton -->
                <div id="loading-skeleton" class="space-y-4 animate-pulse">
                    <div class="h-8 bg-slate-200 rounded-lg w-1/3"></div>
                    <div class="h-80 bg-slate-100 rounded-2xl"></div>
                </div>

                <!-- Error Container -->
                <div id="error-container" class="hidden p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <h3 class="font-bold text-sm">履歴データの読み込みに失敗しました</h3>
                            <p class="text-xs mt-0.5" id="error-message">通信エラーが発生しました。</p>
                        </div>
                    </div>
                </div>

                <!-- Content Container -->
                <div id="content-container" class="hidden space-y-5">
                    <div class="bg-slate-50/60 p-4 sm:p-5 rounded-2xl border border-slate-100 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200/60 pb-3 gap-3">
                            <div class="flex items-center gap-3 flex-wrap">
                                <h2 class="text-base font-extrabold text-slate-800" id="chart-title">日別・個人別使用量 (GB)</h2>
                                <!-- Month Navigation -->
                                <div class="flex items-center gap-1 bg-slate-200/80 p-1 rounded-xl">
                                    <button id="btn-prev-month" onclick="changeMonth(-1)" class="p-1 text-slate-600 hover:text-slate-900 hover:bg-white rounded-lg transition active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed" title="前月">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                        </svg>
                                    </button>
                                    <span id="current-month-label" class="text-xs font-bold text-slate-800 font-mono px-1.5 min-w-[70px] text-center">--</span>
                                    <button id="btn-next-month" onclick="changeMonth(1)" class="p-1 text-slate-600 hover:text-slate-900 hover:bg-white rounded-lg transition active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed" title="次月">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <!-- Toggle Button Group -->
                            <div class="inline-flex p-1 bg-slate-200/80 rounded-xl space-x-1 shrink-0 self-start sm:self-auto">
                                <button id="btn-mode-daily" onclick="switchMode('daily')" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-indigo-600 text-white shadow-sm">
                                    日ごと
                                </button>
                                <button id="btn-mode-cumulative" onclick="switchMode('cumulative')" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-slate-600 hover:text-slate-900">
                                    累積
                                </button>
                            </div>
                        </div>
                        <div class="relative h-72 sm:h-80">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>

                    <!-- History Table -->
                    <div class="bg-slate-50/60 p-4 sm:p-5 rounded-2xl border border-slate-100 space-y-3">
                        <h3 class="text-sm font-extrabold text-slate-800" id="table-title">日別使用量 履歴テーブル</h3>
                        <div class="overflow-x-auto border border-slate-200/80 rounded-xl bg-white shadow-sm">
                            <table class="min-w-full divide-y divide-slate-200 text-xs sm:text-sm">
                                <thead class="bg-slate-50" id="daily-table-head">
                                </thead>
                                <tbody id="daily-table-body" class="divide-y divide-slate-200">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentMode = 'daily'; // 'daily' or 'cumulative'
        let selectedMonth = null;  // 'YYYY-MM'
        let historyDataGlobal = null;
        let usageDataGlobal = null;
        let dailyChartInstance = null;

        function getTodayJst() {
            const now = new Date();
            const jstFormatter = new Intl.DateTimeFormat('en-CA', {
                timeZone: 'Asia/Tokyo',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            });
            const parts = jstFormatter.formatToParts(now);
            const y = parts.find(p => p.type === 'year').value;
            const m = parts.find(p => p.type === 'month').value;
            const d = parts.find(p => p.type === 'day').value;
            return {
                yearMonth: `${y}-${m}`,
                year: parseInt(y, 10),
                month: parseInt(m, 10),
                day: parseInt(d, 10),
                dateStr: `${y}-${m}-${d}`
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const currentParams = new URLSearchParams(window.location.search);
            const monthParam = currentParams.get('month');
            const todayJst = getTodayJst();

            if (monthParam && /^\d{4}-\d{2}$/.test(monthParam)) {
                selectedMonth = monthParam;
            } else {
                selectedMonth = todayJst.yearMonth;
            }

            const historyParams = new URLSearchParams(currentParams);
            historyParams.set('action', 'api_history');
            const historyUrl = '?' + historyParams.toString();

            const usageParams = new URLSearchParams(currentParams);
            usageParams.set('action', 'api_usage');
            const usageUrl = '?' + usageParams.toString();

            Promise.all([
                fetch(historyUrl).then(res => {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                }),
                fetch(usageUrl).then(res => {
                    if (!res.ok) return null;
                    return res.json();
                }).catch(() => null)
            ])
            .then(([historyData, usageData]) => {
                historyDataGlobal = historyData;
                usageDataGlobal = usageData;
                renderDashboard();
            })
            .catch(err => {
                document.getElementById('loading-skeleton').classList.add('hidden');
                document.getElementById('error-container').classList.remove('hidden');
                document.getElementById('error-message').textContent = err.message || '読み込み失敗';
            });
        });

        function switchMode(mode) {
            currentMode = mode;
            const btnDaily = document.getElementById('btn-mode-daily');
            const btnCumulative = document.getElementById('btn-mode-cumulative');

            if (mode === 'daily') {
                btnDaily.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-indigo-600 text-white shadow-sm';
                btnCumulative.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-slate-600 hover:text-slate-900';
            } else {
                btnCumulative.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-indigo-600 text-white shadow-sm';
                btnDaily.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-slate-600 hover:text-slate-900';
            }

            renderDashboard();
        }

        function changeMonth(offset) {
            if (!selectedMonth) return;
            let [y, m] = selectedMonth.split('-').map(Number);
            m += offset;
            if (m > 12) {
                y += 1;
                m = 1;
            } else if (m < 1) {
                y -= 1;
                m = 12;
            }
            selectedMonth = `${y}-${m < 10 ? '0' + m : m}`;

            const currentParams = new URLSearchParams(window.location.search);
            currentParams.set('month', selectedMonth);
            window.history.replaceState({}, '', '?' + currentParams.toString());

            renderDashboard();
        }

        function renderDashboard() {
            if (!historyDataGlobal) return;

            document.getElementById('loading-skeleton').classList.add('hidden');
            document.getElementById('content-container').classList.remove('hidden');

            const todayJst = getTodayJst();
            if (!selectedMonth) {
                selectedMonth = todayJst.yearMonth;
            }

            // Update Month header UI
            const monthLabel = document.getElementById('current-month-label');
            if (monthLabel) {
                const [y, m] = selectedMonth.split('-');
                monthLabel.textContent = `${y}年${m}月`;
            }

            const btnNextMonth = document.getElementById('btn-next-month');
            if (btnNextMonth) {
                btnNextMonth.disabled = (selectedMonth >= todayJst.yearMonth);
            }

            const users = historyDataGlobal.users || {};
            const userKeys = Object.keys(users);

            // Determine date range for selectedMonth (1日〜月末)
            const [sYear, sMonth] = selectedMonth.split('-').map(Number);
            const daysInMonth = new Date(sYear, sMonth, 0).getDate();

            const monthDates = [];
            for (let d = 1; d <= daysInMonth; d++) {
                const dStr = d < 10 ? '0' + d : '' + d;
                monthDates.push(`${selectedMonth}-${dStr}`);
            }

            const dailyMap = {};
            (historyDataGlobal.daily || []).forEach(d => {
                dailyMap[d.date] = d;
            });

            const monthData = monthDates.map(dateStr => {
                if (dailyMap[dateStr]) {
                    return dailyMap[dateStr];
                }
                return {
                    date: dateStr,
                    usages: {},
                    total: null,
                    cumulativeUsages: {},
                    cumulativeTotal: null
                };
            });

            const colors = ['#6366f1', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4'];

            if (dailyChartInstance) {
                dailyChartInstance.destroy();
            }

            const ctx = document.getElementById('dailyChart').getContext('2d');

            if (currentMode === 'daily') {
                document.getElementById('chart-title').textContent = '日別・個人別使用量 (GB)';
                document.getElementById('table-title').textContent = '日別使用量 履歴テーブル';

                const labels = monthData.map(d => d.date);
                const datasets = userKeys.map((key, idx) => ({
                    type: 'bar',
                    label: users[key] || key,
                    data: monthData.map(d => dailyMap[d.date] ? (d.usages[key] ?? 0) : null),
                    backgroundColor: colors[idx % colors.length],
                    borderRadius: 4,
                    stack: 'dailyStack'
                }));

                dailyChartInstance = new Chart(ctx, {
                    data: { labels: labels, datasets: datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            title: { display: true, text: `${selectedMonth} 日別データ使用量 (GB)`, font: { size: 14, weight: 'bold' } }
                        },
                        scales: {
                            x: { stacked: true },
                            y: { stacked: true, beginAtZero: true }
                        }
                    }
                });
            } else {
                // Cumulative Mode
                document.getElementById('chart-title').textContent = '累積使用量 ＆ 月末予測 (GB)';
                document.getElementById('table-title').textContent = '累積使用量 履歴テーブル';

                const labels = monthData.map(d => d.date);

                const datasets = userKeys.map((key, idx) => ({
                    type: 'bar',
                    label: users[key] || key,
                    data: monthData.map(d => dailyMap[d.date] ? ((d.cumulativeUsages ? d.cumulativeUsages[key] : null) ?? d.usages[key] ?? 0) : null),
                    backgroundColor: colors[idx % colors.length],
                    borderRadius: 4,
                    stack: 'cumStack'
                }));

                // Add End-of-Month prediction trendline only for current month
                if (selectedMonth === todayJst.yearMonth && usageDataGlobal && usageDataGlobal.estimateUsage && monthData.length > 0) {
                    const totalDays = monthData.length;
                    const estimateTotal = usageDataGlobal.estimateUsage;
                    const trendLineData = monthData.map((d, index) => {
                        const dayNum = index + 1;
                        return Math.round((estimateTotal / totalDays) * dayNum * 100) / 100;
                    });

                    datasets.push({
                        type: 'line',
                        label: `月末予測トレンド (${usageDataGlobal.estimateUsage} GB)`,
                        data: trendLineData,
                        borderColor: '#f59e0b',
                        backgroundColor: '#f59e0b',
                        borderWidth: 2.5,
                        borderDash: [6, 6],
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        fill: false,
                        tension: 0,
                        spanGaps: true
                    });
                }

                dailyChartInstance = new Chart(ctx, {
                    data: { labels: labels, datasets: datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            title: { display: true, text: `${selectedMonth} 累積データ使用量 (GB)`, font: { size: 14, weight: 'bold' } }
                        },
                        scales: {
                            x: { stacked: true },
                            y: { stacked: true, beginAtZero: true }
                        }
                    }
                });
            }

            // Render Table (filtering out future dates with no history)
            const tableData = monthData.filter(d => dailyMap[d.date] || d.date <= todayJst.dateStr);

            const thead = document.getElementById('daily-table-head');
            let headHtml = `<tr><th class="px-3.5 py-2.5 text-left font-bold text-slate-500">日付</th>`;
            userKeys.forEach(k => {
                headHtml += `<th class="px-3.5 py-2.5 text-right font-bold text-slate-500">${users[k]}</th>`;
            });
            headHtml += `<th class="px-3.5 py-2.5 text-right font-bold text-slate-700">合計</th></tr>`;
            thead.innerHTML = headHtml;

            const tbody = document.getElementById('daily-table-body');
            tbody.innerHTML = '';

            tableData.slice().reverse().forEach(row => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50/50 transition-colors';
                let rowHtml = `<td class="px-3.5 py-2.5 font-semibold text-slate-800">${row.date}</td>`;
                userKeys.forEach(k => {
                    const val = currentMode === 'daily'
                        ? (row.usages[k] ?? 0)
                        : ((row.cumulativeUsages ? row.cumulativeUsages[k] : null) ?? row.usages[k] ?? 0);
                    rowHtml += `<td class="px-3.5 py-2.5 text-right text-slate-600">${val} GB</td>`;
                });
                const totalVal = currentMode === 'daily'
                    ? (row.total ?? 0)
                    : (row.cumulativeTotal ?? row.total ?? 0);
                rowHtml += `<td class="px-3.5 py-2.5 text-right font-bold text-indigo-600">${totalVal} GB</td>`;
                tr.innerHTML = rowHtml;
                tbody.appendChild(tr);
            });
        }
    </script>
</body>
</html>
