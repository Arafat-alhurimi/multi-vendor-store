<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\Stores\StoreResource;
use App\Models\Comment;
use App\Models\Product;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openTarget')
                ->label('فتح العنصر المبلغ عنه')
                ->url(function (): ?string {
                    $reportable = $this->getRecord()->reportable;

                    if ($reportable instanceof Product) {
                        return ProductResource::getUrl('view', ['record' => $reportable]);
                    }

                    if ($reportable instanceof Store) {
                        return StoreResource::getUrl('view', ['record' => $reportable]);
                    }

                    if ($reportable instanceof Comment) {
                        $commentable = $reportable->commentable;

                        if ($commentable instanceof Product) {
                            return ProductResource::getUrl('view', ['record' => $commentable]);
                        }

                        if ($commentable instanceof Store) {
                            return StoreResource::getUrl('view', ['record' => $commentable]);
                        }
                    }

                    return null;
                })
                ->openUrlInNewTab(),
            \Filament\Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('user.name')->label('المبلغ'),
                TextEntry::make('reason')->label('سبب البلاغ')->columnSpanFull(),
                TextEntry::make('reportable_type')
                    ->label('نوع العنصر')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        Product::class => 'منتج',
                        Store::class => 'متجر',
                        Comment::class => 'تعليق',
                        default => 'غير معروف',
                    }),
                TextEntry::make('created_at')->label('تاريخ البلاغ')->since(),
            ])
            ->columns(2);
    }
}
