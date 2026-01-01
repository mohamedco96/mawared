<?php

namespace App\Filament\Pages;

use App\Services\FinancialReportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ProfitLossReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static string $view = 'filament.pages.financial-report';

    protected static ?string $navigationLabel = 'المركز المالي وقائمة الدخل';

    protected static ?string $title = 'المركز المالي وقائمة الدخل';

    protected static ?string $navigationGroup = 'الإدارة المالية';

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_ProfitLossReport') ?? false;
    }

    public ?array $data = [];

    public $from_date;

    public $to_date;

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth(),
            'to_date' => now()->endOfMonth(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('فترة التقرير')
                    ->schema([
                        Forms\Components\DatePicker::make('from_date')
                            ->label('من تاريخ')
                            ->required()
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('to_date')
                            ->label('إلى تاريخ')
                            ->required()
                            ->default(now()->endOfMonth()),
                    ])
                    ->columns(2),
            ]);
    }

    public $reportData = null;

    public function generateReport(): void
    {
        $data = $this->form->getState();
        $service = app(FinancialReportService::class);
        $this->reportData = $service->generateReport($data['from_date'], $data['to_date']);
    }

    public function getReportDataProperty(): ?array
    {
        return $this->reportData;
    }
}
