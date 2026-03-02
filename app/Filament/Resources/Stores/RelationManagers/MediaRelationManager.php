<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use Illuminate\Database\Eloquent\Collection;
use Filament\Resources\RelationManagers\RelationManager;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'وسائط المتجر';

    protected string $view = 'filament.resources.stores.relation-managers.media-gallery';

    public function getMediaItems(): Collection
    {
        return $this->getOwnerRecord()
            ->media()
            ->latest('id')
            ->get();
    }
}
