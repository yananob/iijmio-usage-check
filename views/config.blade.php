<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IIJmio Usage Checker Config</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Add focus ring smooth transitions */
        input, select, button {
            transition: all 0.2s ease-in-out;
        }
    </style>
</head>
<body class="h-full text-slate-800 font-sans antialiased">
    <div class="min-h-screen py-10 px-4 sm:px-6 lg:px-8 flex flex-col justify-between">
        <div class="max-w-4xl w-full mx-auto bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden mb-12">
            <!-- Modern Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-8 sm:px-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">IIJmio Usage Checker</h1>
                    <p class="text-blue-100 mt-1 sm:mt-2 text-sm font-medium">Firestore Configuration & Management Dashboard</p>
                </div>
                <div class="flex flex-wrap gap-2 items-start md:items-center">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-sm border border-white/10">
                        <span class="w-2 h-2 rounded-full bg-blue-300 animate-pulse"></span>
                        {{ $collectionName }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold
                          @if($appEnv === 'production') bg-red-500/25 text-red-100 border border-red-500/30
                          @elseif($appEnv === 'test') bg-amber-500/25 text-amber-100 border border-amber-500/30
                          @else bg-slate-500/25 text-slate-100 border border-slate-500/30
                          @endif backdrop-blur-sm uppercase">
                        {{ $appEnv }}
                    </span>
                </div>
            </div>

            <!-- Toast / Success Messages -->
            @if($message)
                <div class="p-6 pb-0">
                    <div class="flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="text-sm font-medium">{{ $message }}</div>
                    </div>
                </div>
            @endif

            <!-- Preview Result Block -->
            @if($previewMessage)
                <div class="p-6 pb-0">
                    <div class="bg-slate-900 rounded-xl border border-slate-800 shadow-2xl overflow-hidden">
                        <div class="bg-slate-800/80 px-4 py-3 border-b border-slate-700/50 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                <span class="text-slate-300 text-xs font-mono ml-2">Preview Result</span>
                            </div>
                            <span class="text-slate-400 text-xs font-mono uppercase tracking-wider">Simulation</span>
                        </div>
                        <pre class="p-5 overflow-x-auto text-emerald-400 font-mono text-sm leading-relaxed whitespace-pre-wrap select-all">{{ $previewMessage }}</pre>
                    </div>
                </div>
            @endif

            <!-- Form Content -->
            <div class="p-6 sm:p-10 space-y-8">
                <form method="POST" id="config-form" class="space-y-8">
                    <input type="hidden" name="action" id="form-action" value="save">

                    <!-- Section: IIJmio Credentials -->
                    <div class="bg-slate-50/50 p-6 rounded-xl border border-slate-100 space-y-6">
                        <div class="border-b border-slate-200/60 pb-3 flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <h2 class="text-lg font-extrabold text-slate-800">IIJmio Settings</h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="mio_id" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Mio ID</label>
                                <input type="text" id="mio_id" name="iijmio[mio_id]" value="{{ $config['iijmio']['mio_id'] ?? '' }}" required placeholder="MA1234567"
                                       class="block w-full rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm py-2.5 px-3.5 border transition">
                            </div>
                            <div>
                                <label for="password" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Password</label>
                                <input type="password" id="password" name="iijmio[password]" value="{{ $config['iijmio']['password'] ?? '' }}" required
                                       class="block w-full rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm py-2.5 px-3.5 border transition">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Users -->
                    <div class="bg-slate-50/50 p-6 rounded-xl border border-slate-100 space-y-6">
                        <div class="border-b border-slate-200/60 pb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <h2 class="text-lg font-extrabold text-slate-800">Users Config</h2>
                            </div>
                            <button type="button" id="add-user" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg transition active:scale-95">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                Add User
                            </button>
                        </div>

                        <div class="overflow-hidden border border-slate-200/80 rounded-xl shadow-sm bg-white">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200" id="users-table">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">HDO Code (e.g. hdo12345678)</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Name</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Plan Data Volume (GB)</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="users-body" class="divide-y divide-slate-200">
                                    @php $userIndex = 0; @endphp
                                    @if(isset($config['iijmio']['users']) && is_array($config['iijmio']['users']))
                                        @foreach($config['iijmio']['users'] as $code => $user)
                                        @php
                                            $name = is_array($user) ? ($user['name'] ?? '') : (is_object($user) ? ($user->name ?? '') : $user);
                                            $vol = is_array($user) ? ($user['plan_data_volume'] ?? '') : (is_object($user) ? ($user->plan_data_volume ?? '') : '');
                                        @endphp
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="px-4 py-3.5 whitespace-nowrap">
                                                <input type="text" name="iijmio[users][{{ $userIndex }}][code]" value="{{ $code }}" required placeholder="hdo12345678"
                                                       class="block w-full rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm py-2 px-3 border transition">
                                            </td>
                                            <td class="px-4 py-3.5 whitespace-nowrap">
                                                <input type="text" name="iijmio[users][{{ $userIndex }}][name]" value="{{ $name }}" required placeholder="Name"
                                                       class="block w-full rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm py-2 px-3 border transition">
                                            </td>
                                            <td class="px-4 py-3.5 whitespace-nowrap">
                                                <select name="iijmio[users][{{ $userIndex }}][plan_data_volume]" required
                                                        class="block w-full rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm py-2 px-3 border transition">
                                                    <option value="" disabled {{ $vol === '' ? 'selected' : '' }}>Select...</option>
                                                    @php
                                                        $options = [2, 5, 10, 15];
                                                        if ($vol !== '' && !in_array((int)$vol, $options)) {
                                                            $options[] = (float)$vol;
                                                            sort($options);
                                                        }
                                                    @endphp
                                                    @foreach($options as $v)
                                                        <option value="{{ $v }}" {{ (float)$vol === (float)$v ? 'selected' : '' }}>{{ $v }} GB</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-right">
                                                <button type="button" onclick="removeRow(this)"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors active:scale-95">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                        @php $userIndex++; @endphp
                                        @endforeach
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Alert Settings -->
                    <div class="bg-slate-50/50 p-6 rounded-xl border border-slate-100 space-y-6">
                        <div class="border-b border-slate-200/60 pb-3 flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <h2 class="text-lg font-extrabold text-slate-800">Alert Settings</h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="alert_bot" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Bot Name</label>
                                <input type="text" id="alert_bot" name="alert[bot]" value="{{ $config['alert']['bot'] ?? '' }}" required
                                       class="block w-full rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm py-2.5 px-3.5 border transition">
                            </div>
                            <div>
                                <label for="alert_target" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Target Name</label>
                                <input type="text" id="alert_target" name="alert[target]" value="{{ $config['alert']['target'] ?? '' }}" required
                                       class="block w-full rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm py-2.5 px-3.5 border transition">
                            </div>
                            <div>
                                <label for="alert_days" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Send usage each N days</label>
                                <input type="number" id="alert_days" name="alert[send_usage_each_n_days]" value="{{ $config['alert']['send_usage_each_n_days'] ?? '' }}" required
                                       class="block w-full rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm py-2.5 px-3.5 border transition">
                            </div>
                        </div>
                    </div>

                    <!-- Button Group -->
                    <div class="flex flex-col sm:flex-row items-center justify-end gap-4 border-t border-slate-100 pt-6">
                        <button type="submit" onclick="setAction('preview')"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-bold rounded-xl shadow-sm transition active:scale-95">
                            <svg class="w-5 h-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Preview Report
                        </button>
                        <button type="submit" onclick="setAction('save')"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold rounded-xl shadow-md hover:shadow-lg transition active:scale-95">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                            Save Config
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="text-center text-xs text-slate-400 font-medium pb-4">
            IIJmio Usage Checker &bull; Environment: <span class="px-2 py-0.5 rounded bg-slate-200 text-slate-600 font-mono">{{ $appEnv }}</span>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 hidden transition-all duration-300">
        <div class="bg-white p-8 rounded-2xl shadow-2xl flex flex-col items-center gap-5 max-w-sm mx-4 border border-slate-100 text-center">
            <!-- Modern Animated CSS spinner -->
            <div class="relative w-14 h-14">
                <div class="absolute inset-0 rounded-full border-4 border-slate-100"></div>
                <div class="absolute inset-0 rounded-full border-4 border-indigo-600 border-t-transparent animate-spin"></div>
            </div>
            <div>
                <h3 class="text-lg font-extrabold text-slate-800" id="loading-title">処理中</h3>
                <p class="text-sm text-slate-500 mt-1.5 leading-relaxed" id="loading-desc">しばらくお待ちください。この処理には数十秒かかる場合があります。</p>
            </div>
        </div>
    </div>

    <script>
        let userIndex = {{ $userIndex }};
        document.getElementById('add-user').addEventListener('click', function() {
            const tbody = document.getElementById('users-body');
            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-50/50 transition-colors";
            tr.innerHTML = `
                <td class="px-4 py-3 whitespace-nowrap">
                    <input type="text" name="iijmio[users][${userIndex}][code]" value="" required placeholder="hdo12345678"
                           class="block w-full rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm py-2 px-3 border transition">
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <input type="text" name="iijmio[users][${userIndex}][name]" value="" required placeholder="Name"
                           class="block w-full rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm py-2 px-3 border transition">
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <select name="iijmio[users][${userIndex}][plan_data_volume]" required
                            class="block w-full rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm py-2 px-3 border transition">
                        <option value="" disabled selected>Select...</option>
                        <option value="2">2 GB</option>
                        <option value="5">5 GB</option>
                        <option value="10">10 GB</option>
                        <option value="15">15 GB</option>
                    </select>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right">
                    <button type="button" onclick="removeRow(this)"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors active:scale-95">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Remove
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            userIndex++;
        });

        function removeRow(btn) {
            btn.closest('tr').remove();
        }

        function setAction(action) {
            document.getElementById('form-action').value = action;

            const titleEl = document.getElementById('loading-title');
            const descEl = document.getElementById('loading-desc');
            if (action === 'preview') {
                titleEl.textContent = 'プレビュー生成中';
                descEl.textContent = 'IIJmioからデータ利用履歴を取得しています。これには数秒から数十秒かかる場合があります。';
            } else {
                titleEl.textContent = '設定保存中';
                descEl.textContent = '最新の設定をFirestoreに保存しています。しばらくお待ちください。';
            }
        }

        document.getElementById('config-form').addEventListener('submit', function() {
            // Show loading overlay
            document.getElementById('loading-overlay').classList.remove('hidden');

            // Disable all buttons to prevent duplicate clicks
            const buttons = this.querySelectorAll('button[type="submit"]');
            buttons.forEach(btn => {
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            });
        });
    </script>
</body>
</html>
