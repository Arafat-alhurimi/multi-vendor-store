<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleActive')
                ->label(fn (): string => $this->getRecord()->is_active ? 'إلغاء التفعيل' : 'تفعيل الحساب')
                ->color(fn (): string => $this->getRecord()->is_active ? 'danger' : 'success')
                ->icon(fn (): string => $this->getRecord()->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                ->action(function (): void {
                    $this->toggleActivation();
                })
                ->requiresConfirmation(),
        ];
    }

    protected function toggleActivation(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof User) {
            return;
        }

        if ($record->is_active) {
            $record->update(['is_active' => false]);

            Notification::make()
                ->title('تم إلغاء التفعيل')
                ->body('تم إلغاء تفعيل الحساب بنجاح.')
                ->warning()
                ->send();

            return;
        }

        if (! $record->otp_verified_at) {
            Notification::make()
                ->title('لا يمكن التفعيل')
                ->body('يجب أن يتم التحقق من OTP أولاً.')
                ->danger()
                ->send();

            return;
        }

        if ($record->role === 'vendor') {
            $store = $record->store;

            if (! $store) {
                Notification::make()
                    ->title('لا يمكن التفعيل')
                    ->body('لا يوجد متجر مرتبط بهذا البائع.')
                    ->danger()
                    ->send();

                return;
            }

            if (! $store->logo) {
                Notification::make()
                    ->title('لا يمكن التفعيل')
                    ->body('يجب إضافة صورة/شعار المتجر قبل التفعيل.')
                    ->danger()
                    ->send();

                return;
            }

            $store->update(['is_active' => true]);
        }

        $record->update(['is_active' => true]);

        Notification::make()
            ->title('تم التفعيل')
            ->body('تم تفعيل الحساب بنجاح.')
            ->success()
            ->send();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('name')->label('الاسم'),
                TextEntry::make('email')->label('البريد الإلكتروني')->placeholder('-'),
                TextEntry::make('phone')->label('رقم الهاتف')->placeholder('-'),
                TextEntry::make('role')->label('الدور'),
                TextEntry::make('otp_verified_at')
                    ->label('حالة التحقق')
                    ->formatStateUsing(fn ($state): string => $state ? 'تم التحقق' : 'غير متحقق'),
                TextEntry::make('is_active')
                    ->label('الحالة')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'نشط' : 'غير نشط'),
                TextEntry::make('store.name')->label('المتجر')->placeholder('لا يوجد متجر'),
                TextEntry::make('vendorFinancialDetail.kuraimi_account_number')
                    ->label('رقم حساب الكريمي')
                    ->placeholder('-'),
                TextEntry::make('vendorFinancialDetail.kuraimi_account_name')
                    ->label('اسم حساب الكريمي')
                    ->placeholder('-'),
                TextEntry::make('vendorFinancialDetail.jeeb_id')
                    ->label('معرّف جيب')
                    ->placeholder('-'),
                TextEntry::make('vendorFinancialDetail.jeeb_name')
                    ->label('اسم حساب جيب')
                    ->placeholder('-'),
                TextEntry::make('vendorFinancialDetail.card_image')
                    ->label('صورة البطاقة (أمام)')
                    ->placeholder('-'),
                TextEntry::make('vendorFinancialDetail.back_card_image')
                    ->label('صورة البطاقة (خلف)')
                    ->placeholder('-'),
                TextEntry::make('vendorFinancialDetail.total_commission_owed')
                    ->label('إجمالي العمولات المستحقة')
                    ->placeholder('-'),
            ])
            ->columns(2);
    }
}
