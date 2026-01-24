<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    public function getTabs(): array
    {
        $productModel = $this->getModel();

        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge($productModel::count()),
            'in_stock' => Tab::make('متوفر')
                ->icon('heroicon-m-check-circle')
                ->badgeColor('success')
                ->badge($productModel::query()->whereHas('stockMovements', function ($q) {
                    $q->select('product_id')
                        ->selectRaw('SUM(quantity) as total_stock')
                        ->groupBy('product_id')
                        ->havingRaw('SUM(quantity) > 0');
                })->count())
                ->modifyQueryUsing(fn ($query) => $query->whereHas('stockMovements', function ($q) {
                    $q->select('product_id')
                        ->selectRaw('SUM(quantity) as total_stock')
                        ->groupBy('product_id')
                        ->havingRaw('SUM(quantity) > 0');
                })),
            'low_stock' => Tab::make('مخزون منخفض')
                ->icon('heroicon-m-exclamation-triangle')
                ->badgeColor('warning')
                ->badge($productModel::query()->whereHas('stockMovements', function ($q) {
                    $q->select('product_id')
                        ->selectRaw('SUM(quantity) as total_stock')
                        ->groupBy('product_id')
                        ->havingRaw('SUM(quantity) <= products.min_stock AND SUM(quantity) > 0');
                })->count())
                ->modifyQueryUsing(fn ($query) => $query->whereHas('stockMovements', function ($q) {
                    $q->select('product_id')
                        ->selectRaw('SUM(quantity) as total_stock')
                        ->groupBy('product_id')
                        ->havingRaw('SUM(quantity) <= products.min_stock AND SUM(quantity) > 0');
                })),
            'out_of_stock' => Tab::make('نفذ من المخزون')
                ->icon('heroicon-m-x-circle')
                ->badgeColor('danger')
                ->badge($productModel::query()->whereHas('stockMovements', function ($q) {
                    $q->select('product_id')
                        ->selectRaw('SUM(quantity) as total_stock')
                        ->groupBy('product_id')
                        ->havingRaw('SUM(quantity) <= 0');
                })->count())
                ->modifyQueryUsing(fn ($query) => $query->whereHas('stockMovements', function ($q) {
                    $q->select('product_id')
                        ->selectRaw('SUM(quantity) as total_stock')
                        ->groupBy('product_id')
                        ->havingRaw('SUM(quantity) <= 0');
                })),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ProductResource\Widgets\ProductStatsOverview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_showroom_qr')
                ->label('رموز QR للكتالوج')
                ->icon('heroicon-o-qr-code')
                ->color('warning')
                ->modalHeading('رموز QR للكتالوج الرقمي')
                ->modalDescription('اختر نوع الكتالوج وقم بتحميل رمز QR المناسب')
                ->modalWidth('3xl')
                ->modalContent(function () {
                    $retailUrl = route('showroom.retail');
                    $wholesaleUrl = route('showroom.wholesale');

                    // Generate QR codes as SVG
                    $retailQr = QrCode::size(300)
                        ->margin(2)
                        ->generate($retailUrl);

                    $wholesaleQr = QrCode::size(300)
                        ->margin(2)
                        ->generate($wholesaleUrl);

                    return view('filament.pages.showroom-qr-modal', [
                        'retailQr' => $retailQr,
                        'wholesaleQr' => $wholesaleQr,
                        'retailUrl' => $retailUrl,
                        'wholesaleUrl' => $wholesaleUrl,
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('إغلاق'),

            Actions\Action::make('showroom_retail')
                ->label('كتالوج قطاعي')
                ->icon('heroicon-o-shopping-bag')
                ->color('info')
                ->url(route('showroom.retail'), shouldOpenInNewTab: true),

            Actions\Action::make('showroom_wholesale')
                ->label('كتالوج الجملة')
                ->icon('heroicon-o-building-storefront')
                ->color('success')
                ->url(route('showroom.wholesale'), shouldOpenInNewTab: true),

            Actions\CreateAction::make(),
        ];
    }
}
