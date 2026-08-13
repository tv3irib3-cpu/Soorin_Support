<?php

namespace App\Filament\Resources\Tickets\RelationManagers;

use App\Models\Ticket;
use App\Support\Jalali;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * گفتگوی تیکت — پاسخ عمومی و یادداشت داخلی.
 *
 * یادداشت داخلی (is_internal) هرگز به مشتری نشان داده نمی‌شود؛ این
 * RelationManager فقط داخل پنل مدیریت است، پس نمایش آن اینجا مشکلی ندارد.
 */
class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('tickets.conversation');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->label(__('tickets.reply'))
                ->placeholder(__('tickets.reply_placeholder'))
                ->required()
                ->rows(4)
                ->columnSpanFull(),

            Toggle::make('is_internal')
                ->label(__('tickets.internal_note'))
                ->helperText(__('tickets.internal_note_hint'))
                ->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var Ticket $ticket */
        $ticket = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('common.user'))
                    ->placeholder(__('customers.label')),

                TextColumn::make('body')
                    ->label(__('tickets.reply'))
                    ->wrap()
                    ->limit(200),

                IconColumn::make('is_internal')
                    ->label(__('tickets.internal_note'))
                    ->boolean()
                    ->trueColor('warning'),

                TextColumn::make('created_at')
                    ->label(__('common.created_at'))
                    ->formatStateUsing(fn ($state) => Jalali::formatDateTime($state)),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('tickets.reply'))
                    ->disabled($ticket->is_locked)
                    ->mutateDataUsing(function (array $data) {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->defaultSort('created_at')
            ->emptyStateHeading(__('tickets.no_messages'));
    }
}
