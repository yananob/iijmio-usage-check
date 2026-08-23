<!-- Navigation Tabs -->
<div class="bg-slate-800 border-b border-slate-700/80 px-3 sm:px-6">
    <nav class="flex flex-nowrap overflow-x-auto whitespace-nowrap space-x-1 sm:space-x-4 text-sm font-bold" aria-label="Tabs">
        <a href="?page=main" class="inline-flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-sm transition-colors shrink-0 {{ ($currentPage ?? 'main') === 'main' ? 'border-indigo-400 text-indigo-300 font-extrabold' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-500' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V10M9 21h6" />
            </svg>
            メイン
        </a>
        <a href="?page=daily" class="inline-flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-sm transition-colors shrink-0 {{ ($currentPage ?? '') === 'daily' ? 'border-indigo-400 text-indigo-300 font-extrabold' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-500' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            日別
        </a>
        <a href="?page=monthly" class="inline-flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-sm transition-colors shrink-0 {{ ($currentPage ?? '') === 'monthly' ? 'border-indigo-400 text-indigo-300 font-extrabold' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-500' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            月別
        </a>
        <a href="?page=config" class="inline-flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-sm transition-colors shrink-0 {{ ($currentPage ?? '') === 'config' ? 'border-indigo-400 text-indigo-300 font-extrabold' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-500' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            設定
        </a>
    </nav>
</div>
