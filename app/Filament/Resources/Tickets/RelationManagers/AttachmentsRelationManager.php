<?php

namespace App\Filament\Resources\Tickets\RelationManagers;

use App\Support\Jalali;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('tickets.attachments');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label(__('common.attachment'))
                ->disk('local')
                ->directory('ticket-attachments')
                ->maxSize(20 * 1024)   // ۲۰ مگابایت
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->columns([
                TextColumn::make('original_name')
                    ->label(__('common.attachment')),

                TextColumn::make('size')
                    ->label(__('common.quantity'))
                    ->formatStateUsing(fn ($record) => $record->humanSize()),

                TextColumn::make('user.name')
                    ->label(__('common.user')),

                TextColumn::make('created_at')
                    ->label(__('common.created_at'))
                    ->formatStateUsing(fn ($state) => Jalali::formatDateTime($state)),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data) {
                        $data['user_id']       = auth()->id();
                        $data['original_name'] = basename($data['path']);
                        $data['mime']          = \Illuminate\Support\Facades\Storage::disk('local')->mimeType($data['path']);
                        $data['size']          = \Illuminate\Support\Facades\Storage::disk('local')->size($data['path']);

                        return $data;
                    }),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('common.empty_state'));
    }
}
