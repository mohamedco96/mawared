<?php

namespace App\Filament\Pages;

use App\Models\Partner;
use App\Services\ReportService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class PartnerStatement extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.partner-statement';

    protected static ?string $navigationLabel = 'كشف حساب عميل';

    protected static ?string $title = 'كشف حساب عميل';

    protected static ?string $navigationGroup = 'الإدارة المالية';

    protected static ?int $navigationSort = 5;

    public ?array $data = [];

    public $reportData = null;

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
                Forms\Components\Section::make('معايير التقرير')
                    ->description('اختر العميل والفترة الزمنية لعرض كشف الحساب')
                    ->schema([
                        Forms\Components\Select::make('partner_id')
                            ->label('العميل')
                            ->options(Partner::where('type', 'customer')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->placeholder('اختر العميل'),
                        Forms\Components\DatePicker::make('from_date')
                            ->label('من تاريخ')
                            ->required()
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('to_date')
                            ->label('إلى تاريخ')
                            ->required()
                            ->default(now()->endOfMonth())
                            ->afterOrEqual('from_date'),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('طباعة PDF')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn () => $this->reportData
                    ? route('reports.partner-statement.print', [
                        'partner_id' => $this->data['partner_id'],
                        'from_date' => $this->data['from_date'],
                        'to_date' => $this->data['to_date'],
                    ])
                    : null
                )
                ->openUrlInNewTab()
                ->visible(fn () => $this->reportData !== null),
        ];
    }

    public function generateReport(): void
    {
        $this->validate();

        $service = app(ReportService::class);

        $this->reportData = $service->getPartnerStatement(
            $this->data['partner_id'],
            $this->data['from_date'],
            $this->data['to_date']
        );
    }

    public function getReportDataProperty(): ?array
    {
        return $this->reportData;
    }
}
