<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\Warehouse;
use App\Services\ReportService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class StockCard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string $view = 'filament.pages.stock-card';

    protected static ?string $navigationLabel = 'كارت الصنف';

    protected static ?string $title = 'كارت الصنف';

    protected static ?string $navigationGroup = 'الإدارة المالية';

    protected static ?int $navigationSort = 6;

    public static function shouldRegisterNavigation(): bool
    {
        return false; // Hidden - accessible via ReportsHub
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_StockCard') ?? false;
    }

    public ?array $data = [];

    public $reportData = null;

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth(),
            'to_date' => now()->endOfMonth(),
            'warehouse_id' => 'all',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معايير التقرير')
                    ->description('اختر المنتج والمخزن والفترة الزمنية لعرض حركة المخزون')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('المنتج')
                            ->options(Product::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->placeholder('اختر المنتج'),
                        Forms\Components\Select::make('warehouse_id')
                            ->label('المخزن')
                            ->options(['all' => 'جميع المخازن'] + Warehouse::pluck('name', 'id')->toArray())
                            ->default('all')
                            ->required(),
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
                    ->columns(4),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            // A4 Print Action
            Action::make('print_a4')
                ->label('طباعة (A4)')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->url(fn () => $this->reportData
                    ? route('reports.stock-card.print', [
                        'product_id' => $this->data['product_id'],
                        'warehouse_id' => $this->data['warehouse_id'],
                        'from_date' => $this->data['from_date'],
                        'to_date' => $this->data['to_date'],
                        'format' => 'a4',
                    ])
                    : null
                )
                ->openUrlInNewTab()
                ->visible(fn () => $this->reportData !== null),

            // Thermal Print Action
            Action::make('print_thermal')
                ->label('طباعة (حراري)')
                ->icon('heroicon-o-receipt-percent')
                ->color('success')
                ->url(fn () => $this->reportData
                    ? route('reports.stock-card.print', [
                        'product_id' => $this->data['product_id'],
                        'warehouse_id' => $this->data['warehouse_id'],
                        'from_date' => $this->data['from_date'],
                        'to_date' => $this->data['to_date'],
                        'format' => 'thermal',
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

        $this->reportData = $service->getStockCard(
            $this->data['product_id'],
            $this->data['warehouse_id'],
            $this->data['from_date'],
            $this->data['to_date']
        );
    }

    public function getReportDataProperty(): ?array
    {
        return $this->reportData;
    }
}
