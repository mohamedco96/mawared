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
            'initial_capital' => GeneralSetting::getValue('initial_capital', '0'),
            'fixed_assets_value' => GeneralSetting::getValue('fixed_assets_value', '0'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('البيانات المالية الثابتة')
                    ->description('إعدادات رأس المال والأصول الثابتة')
                    ->schema([
                        Forms\Components\TextInput::make('initial_capital')
                            ->label('رأس المال')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->prefix('ر.س')
                            ->step(0.01),
                        Forms\Components\TextInput::make('fixed_assets_value')
                            ->label('أصول ثابتة')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->prefix('ر.س')
                            ->step(0.01)
                            ->helperText('مثل: الأثاث، المعدات، وغيرها'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        GeneralSetting::setValue('initial_capital', $data['initial_capital'] ?? '0');
        GeneralSetting::setValue('fixed_assets_value', $data['fixed_assets_value'] ?? '0');

        Notification::make()
            ->success()
            ->title('تم حفظ الإعدادات بنجاح')
            ->send();
    }
}

