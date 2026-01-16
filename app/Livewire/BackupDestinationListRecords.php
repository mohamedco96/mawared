<?php

namespace App\Livewire;

use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords as BaseBackupDestinationListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\Models\BackupDestination;
use App\Jobs\RestoreBackupJob;
use Spatie\Backup\BackupDestination\BackupDestination as SpatieBackupDestination;
use Spatie\Backup\BackupDestination\Backup;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class BackupDestinationListRecords extends BaseBackupDestinationListRecords
{
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->actions([
                Tables\Actions\Action::make('restore')
                    ->label('استعادة')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(function (): bool {
                        $user = Auth::user();
                        return $user && $user->can('restore-backup');
                    })
                    ->requiresConfirmation()
                    ->modalHeading('استعادة النسخة الاحتياطية')
                    ->modalDescription('اختر ما تريد استعادته من هذه النسخة الاحتياطية. سيتم استبدال الملفات الحالية.')
                    ->modalIcon('heroicon-o-arrow-path')
                    ->form([
                        Radio::make('restore_type')
                            ->label('ماذا تريد أن تستعيد؟')
                            ->options([
                                'storage' => 'ملفات التخزين فقط (الموصى به)',
                                'database' => 'قاعدة البيانات فقط',
                                'both' => 'كلاهما (ملفات التخزين وقاعدة البيانات)',
                            ])
                            ->default('storage')
                            ->required()
                            ->helperText('ملفات التخزين: تشمل الصور، المستندات، والملفات المرفوعة من المستخدمين. لا تؤثر على منطق التطبيق.'),
                    ])
                    ->action(function (BackupDestination $record, array $data) {
                        $restoreType = $data['restore_type'] ?? 'storage';

                        RestoreBackupJob::dispatch(
                            backupPath: $record->path,
                            disk: $record->disk,
                            restoreDatabase: in_array($restoreType, ['database', 'both']),
                            restoreStorage: in_array($restoreType, ['storage', 'both']),
                            userId: auth()->id()
                        )->onQueue('default')->afterResponse();

                        Notification::make()
                            ->title('تم بدء عملية الاستعادة')
                            ->body('سيتم إشعارك عند اكتمال العملية.')
                            ->info()
                            ->send();
                    }),

                Tables\Actions\Action::make('download')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.actions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(function (): bool {
                        $user = Auth::user();
                        return $user && $user->can('download-backup');
                    })
                    ->action(fn (BackupDestination $record) => Storage::disk($record->disk)->download($record->path)),

                Tables\Actions\Action::make('delete')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->visible(function (): bool {
                        $user = Auth::user();
                        return $user && $user->can('delete-backup');
                    })
                    ->requiresConfirmation()
                    ->color('danger')
                    ->modalIcon('heroicon-o-trash')
                    ->action(function (BackupDestination $record) {
                        $backup = SpatieBackupDestination::create($record->disk, config('backup.backup.name'))
                            ->backups()
                            ->first(function (Backup $backup) use ($record) {
                                return $backup->path() === $record->path;
                            });

                        if ($backup) {
                            $backup->delete();

                            Notification::make()
                                ->title(__('filament-spatie-backup::backup.pages.backups.messages.backup_delete_success'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('النسخة الاحتياطية غير موجودة')
                                ->body('لم يتم العثور على النسخة الاحتياطية المطلوبة.')
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
