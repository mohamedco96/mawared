<?php

namespace App\Filament\Resources\TreasuryResource\Pages;

use App\Filament\Resources\TreasuryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTreasury extends EditRecord
{
    protected static string $resource = TreasuryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action) {
                    if ($this->getRecord()->hasAssociatedRecords()) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('لا يمكن حذف الخزينة')
                            ->body('لا يمكن حذف الخزينة لوجود معاملات مالية أو مصروفات أو إيرادات أو أصول ثابتة مرتبطة بها.')
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}
