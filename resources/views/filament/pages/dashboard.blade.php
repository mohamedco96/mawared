<x-filament-panels::page>
    <style>
        /* Custom styles to ensure correct rendering without relying on build process */
        .dashboard-gradient-banner {
            background: linear-gradient(to right, #6366f1, #9333ea);
        }

        /* Quick Access Card Colors */
        .qa-icon-sales {
            background-color: #dbeafe;
            color: #2563eb;
        }

        .qa-border-sales:hover {
            border-color: #3b82f6;
        }

        .qa-icon-purchase {
            background-color: #ffedd5;
            color: #ea580c;
        }

        .qa-border-purchase:hover {
            border-color: #f97316;
        }

        .qa-icon-treasury {
            background-color: #f3e8ff;
            color: #9333ea;
        }

        .qa-border-treasury:hover {
            border-color: #a855f7;
        }

        .qa-icon-customer {
            background-color: #d1fae5;
            color: #059669;
        }

        .qa-border-customer:hover {
            border-color: #10b981;
        }

        /* Dark Mode Overrides */
        .dark .qa-icon-sales {
            background-color: rgba(30, 58, 138, 0.5);
            color: #60a5fa;
        }

        .dark .qa-border-sales:hover {
            border-color: #3b82f6;
        }

        .dark .qa-icon-purchase {
            background-color: rgba(124, 45, 18, 0.5);
            color: #fb923c;
        }

        .dark .qa-border-purchase:hover {
            border-color: #f97316;
        }

        .dark .qa-icon-treasury {
            background-color: rgba(88, 28, 135, 0.5);
            color: #c084fc;
        }

        .dark .qa-border-treasury:hover {
            border-color: #a855f7;
        }

        .dark .qa-icon-customer {
            background-color: rgba(6, 78, 59, 0.5);
            color: #34d399;
        }

        .dark .qa-border-customer:hover {
            border-color: #10b981;
        }
    </style>

    {{-- Welcome Banner --}}
    <div
        class="dashboard-gradient-banner relative overflow-hidden rounded-xl px-8 py-12 md:px-10 md:py-16 text-white shadow-lg mb-6">
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="text-center md:text-right flex-1">
                <h1 class="text-3xl font-bold mb-3" style="color: #ffffff;">
                    مرحباً بك، {{ auth()->user()->name }} 👋
                </h1>
                <p class="text-lg opacity-90" style="color: #e0e7ff;">
                    إليك نظرة عامة على أداء النظام اليوم.
                </p>
            </div>

            <div class="flex flex-row gap-4 rtl:flex-row-reverse">
                <div class="rounded-lg px-6 py-3 ml-6 border border-white/20 text-center min-w-[120px]"
                    style="background-color: rgba(255, 255, 255, 0.1); backdrop-filter: blur(4px);">
                    <span class="text-sm block mb-1 opacity-80" style="color: #eef2ff;">التاريخ</span>
                    <div class="font-bold text-lg" style="color: #ffffff;">{{ now()->format('Y-m-d') }}</div>
                </div>
                <div class="rounded-lg px-6 py-3 border border-white/20 text-center min-w-[120px]"
                    style="background-color: rgba(255, 255, 255, 0.1); backdrop-filter: blur(4px);">
                    <span class="text-sm block mb-1 opacity-80" style="color: #eef2ff;">الوقت</span>
                    <div class="font-bold text-lg" style="color: #ffffff;">{{ now()->format('h:i A') }}</div>
                </div>
            </div>
        </div>

        {{-- Decorative Circles --}}
        <div class="absolute -right-10 -top-10 h-64 w-64 rounded-full blur-3xl"
            style="background-color: rgba(255, 255, 255, 0.1);"></div>
        <div class="absolute -left-10 -bottom-10 h-64 w-64 rounded-full blur-3xl"
            style="background-color: rgba(49, 46, 129, 0.2);"></div>
    </div>

    {{-- Quick Access Widget (Manually placed to ensure full width) --}}
    @livewire(\App\Filament\Widgets\QuickAccessWidget::class)

    {{-- Widgets Section --}}
    <x-filament-widgets::widgets :widgets="$this->getVisibleWidgets()" :columns="$this->getColumns()" />
</x-filament-panels::page>
