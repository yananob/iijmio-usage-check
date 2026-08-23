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
    <div class="min-h-screen py-3 sm:py-6 px-3 sm:px-6 lg:px-8 flex flex-col justify-between">
        <div class="max-w-4xl w-full mx-auto bg-white rounded-2xl shadow-md border border-slate-100 overflow-hidden mb-4">
            <!-- Navigation Header -->
            @include('nav')

            <div class="p-4 sm:p-6 space-y-5">
                <!-- Loading Skeleton -->
                <div id="loading-skeleton" class="space-y-4 animate-pulse">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="h-20 bg-slate-100 rounded-xl"></div>
                        <div class="h-20 bg-slate-100 rounded-xl"></div>
                        <div class="h-20 bg-slate-100 rounded-xl"></div>
                        <div class="h-20 bg-slate-100 rounded-xl"></div>
                    </div>
                    <div class="h-60 bg-slate-100 rounded-2xl"></div>
                    <div class="h-40 bg-slate-900/10 rounded-2xl"></div>
                </div>

                <!-- Error State -->
                <div id="error-container" class="hidden p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <h3 class="font-bold text-sm">データの読み込みに失敗しました</h3>
                            <p class="text-xs mt-0.5" id="error-message">通信エラーが発生しました。</p>
                        </div>
                    </div>
                </div>

                <!-- Content Container (Hidden until loaded) -->
                <div id="content-container" class="hidden space-y-5">
                    <!-- Key Metric Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="bg-slate-50 border border-slate-100 p-3 rounded-xl">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">今月合計使用量</span>
                            <span class="text-xl sm:text-2xl font-black text-slate-800 mt-0.5 block" id="card-this-month-total">-- GB</span>
                        </div>
                        <div class="bg-slate-50 border border-slate-100 p-3 rounded-xl">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">契約容量合計</span>
                            <span class="text-xl sm:text-2xl font-black text-indigo-600 mt-0.5 block" id="card-plan-data">-- GB</span>
                        </div>
                        <div class="bg-slate-50 border border-slate-100 p-3 rounded-xl">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">クーポン残量</span>
                            <span class="text-xl sm:text-2xl font-black text-emerald-600 mt-0.5 block" id="card-left-data">-- GB</span>
                        </div>
                        <div class="bg-slate-50 border border-slate-100 p-3 rounded-xl" id="card-surplus-box">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">過不足予定</span>
                            <span class="text-xl sm:text-2xl font-black mt-0.5 block" id="card-surplus">-- GB</span>
                        </div>
                    </div>

                    <!-- Usage & Prediction Chart Section -->
                    <div class="bg-slate-50/60 p-4 sm:p-5 rounded-2xl border border-slate-100 space-y-4">
                        <div class="border-b border-slate-200/60 pb-2.5 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                </svg>
                                <h2 class="text-base font-extrabold text-slate-800">全体使用量 ＆ 月末着地点予測</h2>
                            </div>
                        </div>

                        <div class="relative h-64 sm:h-72 flex justify-center items-center">
                            <canvas id="landingChart"></canvas>
                        </div>
                    </div>

                    <!-- User Breakdown Table -->
                    <div class="bg-slate-50/60 p-4 sm:p-5 rounded-2xl border border-slate-100 space-y-3">
                        <h3 class="text-sm font-extrabold text-slate-800">ユーザー別利用詳細</h3>
                        <div class="overflow-x-auto border border-slate-200/80 rounded-xl bg-white shadow-sm">
                            <table class="min-w-full divide-y divide-slate-200 text-xs sm:text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th scope="col" class="px-3.5 py-2.5 text-left font-bold text-slate-500">ユーザー名</th>
                                        <th scope="col" class="px-3.5 py-2.5 text-right font-bold text-slate-500">当月使用量</th>
                                        <th scope="col" class="px-3.5 py-2.5 text-right font-bold text-slate-500">本日増加量</th>
                                        <th scope="col" class="px-3.5 py-2.5 text-right font-bold text-slate-500">月末予測</th>
                                        <th scope="col" class="px-3.5 py-2.5 text-right font-bold text-slate-500">契約容量</th>
                                    </tr>
                                </thead>
                                <tbody id="user-breakdown-body" class="divide-y divide-slate-200">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- LINE Notification Report -->
                    <div class="bg-slate-900 rounded-2xl border border-slate-800 shadow-lg overflow-hidden">
                        <div class="bg-slate-800/80 px-4 py-2.5 border-b border-slate-700/50 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                <span class="text-slate-200 text-xs font-bold">LINE通知レポートの内容</span>
                            </div>
                            <span class="text-slate-400 text-[10px] font-mono uppercase tracking-wider">Report</span>
                        </div>
                        <pre id="line-report-text" class="p-4 overflow-x-auto text-emerald-400 font-mono text-xs leading-relaxed whitespace-pre-wrap select-all"></pre>
                    </div>
                </div>
            </div>
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
                surplusEl.className = 'text-xl sm:text-2xl font-black text-emerald-600 mt-0.5 block';
            } else {
                surplusEl.className = 'text-xl sm:text-2xl font-black text-rose-600 mt-0.5 block';
            }

            // User breakdown table
            const tbody = document.getElementById('user-breakdown-body');
            tbody.innerHTML = '';
            if (data.users && data.users.length > 0) {
                data.users.forEach(u => {
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-slate-50/50 transition-colors';
                    tr.innerHTML = `
                        <td class="px-3.5 py-2.5 font-semibold text-slate-800">${u.name}</td>
                        <td class="px-3.5 py-2.5 text-right text-slate-700">${u.currentUsage} GB</td>
                        <td class="px-3.5 py-2.5 text-right text-slate-500">+${u.dailyUsage} GB</td>
                        <td class="px-3.5 py-2.5 text-right font-bold text-indigo-600">${u.estimatedUserUsage} GB</td>
                        <td class="px-3.5 py-2.5 text-right text-slate-500">${u.planDataVolume} GB</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            // LINE Report
            document.getElementById('line-report-text').textContent = data.message || '';

            // Render Landing Prediction Chart
            const ctx = document.getElementById('landingChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['契約容量', '今月の使用予定', '過不足予定'],
                    datasets: [
                        {
                            label: '契約容量',
                            data: [data.planDataVolume || 0, 0, 0],
                            backgroundColor: '#6366f1',
                            stack: 'plan',
                            borderRadius: 6
                        },
                        {
                            label: '当月使用量',
                            data: [0, data.thisMonthTotalUsage || 0, 0],
                            backgroundColor: '#3b82f6',
                            stack: 'estimate',
                            borderRadius: 0
                        },
                        {
                            label: '残り消費予定',
                            data: [0, data.remainingConsumption || 0, 0],
                            backgroundColor: '#a855f7',
                            stack: 'estimate',
                            borderRadius: 6
                        },
                        {
                            label: '過不足予定',
                            data: [0, 0, shortageOrSurplus],
                            backgroundColor: shortageOrSurplus >= 0 ? '#10b981' : '#f43f5e',
                            stack: 'surplus',
                            borderRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        title: {
                            display: true,
                            text: '月末着地点予測 (GB)',
                            font: { size: 14, weight: 'bold' }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
