<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IIJmio Usage Checker - 月別グラフ</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="h-full text-slate-800 font-sans antialiased">
    <div class="min-h-screen py-10 px-4 sm:px-6 lg:px-8 flex flex-col justify-between">
        <div class="max-w-4xl w-full mx-auto bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden mb-12">
            <!-- Navigation Header -->
            @include('nav')

            <div class="p-6 sm:p-10 space-y-8">
                <!-- Transition Banner to Daily View -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 bg-indigo-50 border border-indigo-100 rounded-xl">
                    <div class="text-sm font-medium text-indigo-900">
                        月ごとの個人別利用量グラフを表示しています。日ごとの詳細を見るには日別グラフへお進みください。
                    </div>
                    <a href="?page=daily" class="shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-lg transition active:scale-95 shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                        </svg>
                        日別グラフ画面へ
                    </a>
                </div>

                <!-- Loading Skeleton -->
                <div id="loading-skeleton" class="space-y-6 animate-pulse">
                    <div class="h-8 bg-slate-200 rounded-lg w-1/3"></div>
                    <div class="h-80 bg-slate-100 rounded-2xl"></div>
                </div>

                <!-- Error Container -->
                <div id="error-container" class="hidden p-6 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl">
                    <div class="flex items-center gap-3">
                        <svg class="w-6 h-6 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <h3 class="font-bold text-base">履歴データの読み込みに失敗しました</h3>
                            <p class="text-sm mt-1" id="error-message">通信エラーが発生しました。</p>
                        </div>
                    </div>
                </div>

                <!-- Content Container -->
                <div id="content-container" class="hidden space-y-8">
                    <div class="bg-slate-50/60 p-6 rounded-2xl border border-slate-100 space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-3">
                            <h2 class="text-lg font-extrabold text-slate-800">月別・個人別使用量 (GB)</h2>
                        </div>
                        <div class="relative h-80 sm:h-96">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>

                    <!-- History Table -->
                    <div class="bg-slate-50/60 p-6 rounded-2xl border border-slate-100 space-y-4">
                        <h3 class="text-md font-extrabold text-slate-800">月別使用量 集計テーブル</h3>
                        <div class="overflow-x-auto border border-slate-200/80 rounded-xl bg-white shadow-sm">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50" id="monthly-table-head">
                                </thead>
                                <tbody id="monthly-table-body" class="divide-y divide-slate-200">
                                </tbody>
                            </table>
                        </div>
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
            currentParams.set('action', 'api_history');
            const apiUrl = '?' + currentParams.toString();

            fetch(apiUrl)
                .then(res => {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(data => {
                    renderMonthlyChart(data);
                })
                .catch(err => {
                    document.getElementById('loading-skeleton').classList.add('hidden');
                    document.getElementById('error-container').classList.remove('hidden');
                    document.getElementById('error-message').textContent = err.message || '読み込み失敗';
                });
        });

        function renderMonthlyChart(data) {
            document.getElementById('loading-skeleton').classList.add('hidden');
            document.getElementById('content-container').classList.remove('hidden');

            const users = data.users || {};
            const userKeys = Object.keys(users);
            const monthlyData = data.monthly || [];

            const labels = monthlyData.map(d => d.month);

            const colors = ['#6366f1', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4'];

            const datasets = userKeys.map((key, idx) => {
                return {
                    label: users[key] || key,
                    data: monthlyData.map(d => d.usages[key] || 0),
                    backgroundColor: colors[idx % colors.length],
                    borderRadius: 4
                };
            });

            const ctx = document.getElementById('monthlyChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        title: { display: true, text: '月別データ使用量 (GB)', font: { size: 14, weight: 'bold' } }
                    },
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true, beginAtZero: true }
                    }
                }
            });

            // Table rendering
            const thead = document.getElementById('monthly-table-head');
            let headHtml = `<tr><th class="px-4 py-3 text-left font-bold text-slate-500">年月</th>`;
            userKeys.forEach(k => {
                headHtml += `<th class="px-4 py-3 text-right font-bold text-slate-500">${users[k]}</th>`;
            });
            headHtml += `<th class="px-4 py-3 text-right font-bold text-slate-700">合計</th></tr>`;
            thead.innerHTML = headHtml;

            const tbody = document.getElementById('monthly-table-body');
            tbody.innerHTML = '';
            monthlyData.slice().reverse().forEach(row => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50/50 transition-colors';
                let rowHtml = `<td class="px-4 py-3 font-semibold text-slate-800">${row.month}</td>`;
                userKeys.forEach(k => {
                    rowHtml += `<td class="px-4 py-3 text-right text-slate-600">${row.usages[k] ?? 0} GB</td>`;
                });
                rowHtml += `<td class="px-4 py-3 text-right font-bold text-indigo-600">${row.total} GB</td>`;
                tr.innerHTML = rowHtml;
                tbody.appendChild(tr);
            });
        }
    </script>
</body>
</html>
