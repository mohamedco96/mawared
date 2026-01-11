<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

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
