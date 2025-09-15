<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Resources\Pages\ListRecords\Tab;
class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
   public function getTabs(): array
   {
    return [
      "All" => Tab::make(),
      "Published" => Tab::make()->modifyQueryUsing(function ( $query) {
        $query->where('published', true);
      }),
      "Unpublished" => Tab::make()->modifyQueryUsing(function ( $query) {
        $query->where('published', false);
      }),
    ];
   }
}
