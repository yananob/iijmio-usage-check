<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IIJmio Usage Checker - メイン</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        input, select, button { transition: all 0.2s ease-in-out; }
    </style>
</head>
<body class="h-full text-slate-800 font-sans antialiased">
    <div class="min-h-screen py-10 px-4 sm:px-6 lg:px-8 flex flex-col justify-between">
        <div class="max-w-4xl w-full mx-auto bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden mb-12">
            <!-- Navigation Header -->
            @include('nav')

            <div class="p-6 sm:p-10 space-y-8">
                <!-- Loading Skeleton -->
                <div id="loading-skeleton" class="space-y-6 animate-pulse">
                    <div class="h-8 bg-slate-200 rounded-lg w-1/3"></div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="h-24 bg-slate-100 rounded-xl"></div>
                        <div class="h-24 bg-slate-100 rounded-xl"></div>
                        <div class="h-24 bg-slate-100 rounded-xl"></div>
                        <div class="h-24 bg-slate-100 rounded-xl"></div>
                    </div>
                    <div class="h-64 bg-slate-100 rounded-2xl"></div>
                    <div class="h-48 bg-slate-900/10 rounded-2xl"></div>
                </div>

                <!-- Error State -->
                <div id="error-container" class="hidden p-6 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl">
                    <div class="flex items-center gap-3">
                        <svg class="w-6 h-6 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <h3 class="font-bold text-base">データの読み込みに失敗しました</h3>
                            <p class="text-sm mt-1" id="error-message">通信エラーが発生しました。</p>
                        </div>
                    </div>
                </div>

                <!-- Content Container (Hidden until loaded) -->
                <div id="content-container" class="hidden space-y-8">
                    <!-- Key Metric Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-slate-50 border border-slate-100 p-4 rounded-xl">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">今月合計使用量</span>
                            <span class="text-2xl font-black text-slate-800 mt-1 block" id="card-this-month-total">-- GB</span>
                        </div>
                        <div class="bg-slate-50 border border-slate-100 p-4 rounded-xl">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">契約容量合計</span>
                            <span class="text-2xl font-black text-indigo-600 mt-1 block" id="card-plan-data">-- GB</span>
                        </div>
                        <div class="bg-slate-50 border border-slate-100 p-4 rounded-xl">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">クーポン残量</span>
                            <span class="text-2xl font-black text-emerald-600 mt-1 block" id="card-left-data">-- GB</span>
                        </div>
                        <div class="bg-slate-50 border border-slate-100 p-4 rounded-xl" id="card-surplus-box">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">過不足予定</span>
                            <span class="text-2xl font-black mt-1 block" id="card-surplus">-- GB</span>
                        </div>
                    </div>

                    <!-- Usage & Prediction Chart Section -->
                    <div class="bg-slate-50/60 p-6 rounded-2xl border border-slate-100 space-y-6">
                        <div class="border-b border-slate-200/60 pb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                                </svg>
                                <h2 class="text-lg font-extrabold text-slate-800">全体使用量 ＆ 過不足予測</h2>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                            <div class="relative h-64 flex justify-center items-center">
                                <canvas id="usageChart"></canvas>
                            </div>
                            <div class="relative h-64 flex justify-center items-center">
                                <canvas id="surplusChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- User Breakdown Table -->
                    <div class="bg-slate-50/60 p-6 rounded-2xl border border-slate-100 space-y-4">
                        <h3 class="text-md font-extrabold text-slate-800">ユーザー別利用詳細</h3>
                        <div class="overflow-x-auto border border-slate-200/80 rounded-xl bg-white shadow-sm">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left font-bold text-slate-500">ユーザー名</th>
                                        <th scope="col" class="px-4 py-3 text-right font-bold text-slate-500">当月使用量</th>
                                        <th scope="col" class="px-4 py-3 text-right font-bold text-slate-500">本日増加量</th>
                                        <th scope="col" class="px-4 py-3 text-right font-bold text-slate-500">月末予測</th>
                                        <th scope="col" class="px-4 py-3 text-right font-bold text-slate-500">契約容量</th>
                                    </tr>
                                </thead>
                                <tbody id="user-breakdown-body" class="divide-y divide-slate-200">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- LINE Notification Report -->
                    <div class="bg-slate-900 rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
                        <div class="bg-slate-800/80 px-5 py-3.5 border-b border-slate-700/50 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                                <span class="text-slate-200 text-sm font-bold">LINE通知レポートの内容</span>
                            </div>
                            <span class="text-slate-400 text-xs font-mono uppercase tracking-wider">Report</span>
                        </div>
                        <pre id="line-report-text" class="p-5 overflow-x-auto text-emerald-400 font-mono text-sm leading-relaxed whitespace-pre-wrap select-all"></pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center text-xs text-slate-400 font-medium pb-4">
            IIJmio Usage Checker &bull; Environment: <span class="px-2 py-0.5 rounded bg-slate-200 text-slate-600 font-mono">{{ $appEnv }}</span>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const currentParams = new URLSearchParams(window.location.search);
            currentParams.set('action', 'api_usage');
            const apiUrl = '?' + currentParams.toString();

            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP status ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    renderDashboard(data);
                })
                .catch(err => {
                    document.getElementById('loading-skeleton').classList.add('hidden');
                    document.getElementById('error-container').classList.remove('hidden');
                    document.getElementById('error-message').textContent = err.message || 'データ取得エラー';
                });
        });

        function renderDashboard(data) {
            document.getElementById('loading-skeleton').classList.add('hidden');
            document.getElementById('content-container').classList.remove('hidden');

            document.getElementById('card-this-month-total').textContent = (data.thisMonthTotalUsage || 0) + ' GB';
            document.getElementById('card-plan-data').textContent = (data.planDataVolume || 0) + ' GB';
            document.getElementById('card-left-data').textContent = (data.totalRemainingDataVolume || 0) + ' GB';

            const shortageOrSurplus = data.shortageOrSurplus || 0;
            const surplusEl = document.getElementById('card-surplus');
            surplusEl.textContent = (shortageOrSurplus >= 0 ? '+' : '') + shortageOrSurplus + ' GB';
            if (shortageOrSurplus >= 0) {
                surplusEl.className = 'text-2xl font-black text-emerald-600 mt-1 block';
            } else {
                surplusEl.className = 'text-2xl font-black text-rose-600 mt-1 block';
            }

            // User breakdown table
            const tbody = document.getElementById('user-breakdown-body');
            tbody.innerHTML = '';
            if (data.users && data.users.length > 0) {
                data.users.forEach(u => {
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-slate-50/50 transition-colors';
                    tr.innerHTML = `
                        <td class="px-4 py-3 font-semibold text-slate-800">${u.name}</td>
                        <td class="px-4 py-3 text-right text-slate-700">${u.currentUsage} GB</td>
                        <td class="px-4 py-3 text-right text-slate-500">+${u.dailyUsage} GB</td>
                        <td class="px-4 py-3 text-right font-bold text-indigo-600">${u.estimatedUserUsage} GB</td>
                        <td class="px-4 py-3 text-right text-slate-500">${u.planDataVolume} GB</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            // LINE Report
            document.getElementById('line-report-text').textContent = data.message || '';

            // Render Chart 1: Total Usage vs Capacity
            const ctx1 = document.getElementById('usageChart').getContext('2d');
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: ['当月使用量', '残りクーポン容量'],
                    datasets: [{
                        data: [data.thisMonthTotalUsage || 0, data.totalRemainingDataVolume || 0],
                        backgroundColor: ['#6366f1', '#10b981'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        title: { display: true, text: '使用量と残量 (GB)', font: { size: 14, weight: 'bold' } }
                    }
                }
            });

            // Render Chart 2: End-of-month prediction & Surplus/Shortage
            const ctx2 = document.getElementById('surplusChart').getContext('2d');
            new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: ['月末予測使用量', '残り消費予定', '過不足予定'],
                    datasets: [{
                        label: 'GB',
                        data: [data.estimateUsage || 0, data.remainingConsumption || 0, shortageOrSurplus],
                        backgroundColor: [
                            '#8b5cf6',
                            '#f59e0b',
                            shortageOrSurplus >= 0 ? '#10b981' : '#f43f5e'
                        ],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: '予測 & 過不足予定 (GB)', font: { size: 14, weight: 'bold' } }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
    </script>
</body>
</html>
