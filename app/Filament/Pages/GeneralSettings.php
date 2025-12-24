<?php

namespace App\Filament\Pages;

use App\Models\GeneralSetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GeneralSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.general-settings';

    protected static ?string $navigationLabel = 'الإعدادات العامة';

    protected static ?string $title = 'الإعدادات العامة';

    protected static ?string $navigationGroup = 'الإدارة';

    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'fixed_assets_value' => GeneralSetting::getValue('fixed_assets_value', '0'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('البيانات المالية الثابتة')
                    ->description('إعدادات الأصول الثابتة')
                    ->schema([
                        Forms\Components\TextInput::make('fixed_assets_value')
                            ->label('أصول ثابتة')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->prefix('ج.م')
                            ->step(0.01)
                            ->helperText('مثل: الأثاث، المعدات، وغيرها'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        GeneralSetting::setValue('fixed_assets_value', $data['fixed_assets_value'] ?? '0');

        Notification::make()
            ->success()
            ->title('تم حفظ الإعدادات بنجاح')
            ->send();
    }
}

