<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Filament\Resources\PostResource\RelationManagers;
use App\Models\Post;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Models\Category;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'cat posts';
    public static function form(Form $form): Form
    {

        return $form
            ->schema([
                Tabs::make('create post')->tabs([
                    Tab::make('content')
                    ->schema([
                        Section::make()
                        ->schema([
                            TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                    
                        ]),
                      
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->required(),
                            Select::make('authors')
                            ->relationship('authors', 'name')
                            ->multiple()
                            ->searchable()
                            ->required(),
                    ]),
                    Tab::make('body')
                    ->schema([
                        Section::make()
                        ->schema([
                            TextInput::make('body')
                            ->required()
                            ->maxLength(255),
                        ]),
                    ]),
                    

                ])->columnSpan('full'),
            //     Section::make()
            //     ->schema([
            //         TextInput::make('title')
            //         ->required()
            //         ->maxLength(255),
            //     TextInput::make('body')
            //         ->required()
            //         ->maxLength(255),
            //     ]),
              
            //     Select::make('category_id')
            //         ->relationship('category', 'name')
            //         ->searchable()
            //         ->required(),
            //         Select::make('authors')
            //         ->relationship('authors', 'name')
            //         ->multiple()
            //         ->searchable()
            //         ->required(),
            // ])->columns(1);
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                ->searchable(),
                TextColumn::make('body')
                ->searchable(),
                TextColumn::make('category.name')
                ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AuthorsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
