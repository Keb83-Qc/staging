<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageResource\Pages;
use App\Models\Message;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action; // Import important
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Messagerie';

    public static function getNavigationGroup(): ?string
    {
        return 'Espace Conseiller';
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-navigation.sort.' . static::class);
    }

    // --- 1. Badge de notification (Compteur rouge) ---
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('receiver_id', Auth::id())
            ->where('is_read', false)
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger'; // Rouge
    }

    // --- 2. Filtrer pour ne voir que SES messages reçus ---
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('receiver_id', Auth::id())
            ->orderBy('created_at', 'desc');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('sender_id')
                    ->default(Auth::id()),

                Forms\Components\Select::make('receiver_id')
                    ->label('Destinataire')
                    ->options(function () {
                        return User::where('id', '!=', Auth::id())
                            ->get()
                            ->mapWithKeys(function ($user) {
                                return [$user->id => $user->first_name . ' ' . $user->last_name];
                            });
                    })
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('subject')
                    ->label('Sujet')
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\RichEditor::make('body')
                    ->label('Message')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sender.first_name')
                    ->label('De')
                    ->formatStateUsing(fn($record) => $record->sender ? $record->sender->first_name . ' ' . $record->sender->last_name : 'Système')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable('first_name')
                    ->weight('bold')
                    ->icon('heroicon-m-user-circle'),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Sujet')
                    ->searchable()
                    ->limit(50)
                    ->weight(fn(Message $record): string => $record->is_read ? 'normal' : 'extra-bold')
                    ->color(fn(Message $record): string => $record->is_read ? 'gray' : 'primary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Reçu le')
                    ->dateTime('d M Y à H:i')
                    ->sortable(),
            ])
            ->actions([
                // --- ACTION 1 : LIRE (Version corrigée "Action Standard") ---
                // On utilise Action::make au lieu de ViewAction::make pour éviter le bug du rafraichissement
                Tables\Actions\Action::make('read')
                    ->label('Lire')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Lecture du message')
                    ->modalSubmitAction(false) // On cache le bouton "Confirmer" inutile
                    ->modalCancelAction(fn() => \Filament\Actions\StaticAction::make('close')->label('Fermer')) // Bouton Fermer simple
                    ->form([
                        Forms\Components\TextInput::make('subject')->label('Sujet')->disabled(),
                        Forms\Components\RichEditor::make('body')->label('Message')->disabled(),
                    ])
                    // On prépare les données à l'ouverture
                    ->mountUsing(function (Message $record, Forms\ComponentContainer $form) {
                        // Marquer comme lu
                        if (!$record->is_read) {
                            $record->update(['is_read' => true]);
                        }
                        // Remplir le formulaire
                        $form->fill([
                            'subject' => $record->subject,
                            'body' => $record->body,
                        ]);
                    })
                    // LES BOUTONS VALIDER / REFUSER
                    ->modalFooterActions(fn(Message $record, $action): array => [

                        // BOUTON VALIDER
                        Tables\Actions\Action::make('accept_inside')
                            ->label('Valider le candidat')
                            ->color('success')
                            ->icon('heroicon-o-check')
                            ->visible(fn() => isset($record->data['action_type']) && $record->data['action_type'] === 'registration_request')
                            ->requiresConfirmation()
                            ->modalHeading('Confirmer la validation')
                            ->action(function () use ($record, $action) {
                                $record->refresh();
                                if (!isset($record->data['action_type'])) return;

                                $applicant = User::find($record->data['applicant_id'] ?? null);

                                if ($applicant) {
                                    // --- DÉBUT DE LA MODIFICATION ---

                                    // Option 1 (Recommandée) : On cherche l'ID du rôle "conseiller" dynamiquement
                                    // Cela évite les erreurs si les IDs changent un jour.
                                    $roleConseiller = \App\Models\Role::where('name', 'conseiller')->first();

                                    // Si on trouve le rôle, on prend son ID. Sinon, on met 3 par défaut (à vérifier dans votre table 'roles')
                                    $targetRoleId = $roleConseiller ? $roleConseiller->id : 3;

                                    // On applique le rôle
                                    $applicant->update(['role_id' => $targetRoleId]);

                                    // --- FIN DE LA MODIFICATION ---

                                    $record->update([
                                        'body' => $record->body . "<br><br><strong>[TRAITÉ : VALIDÉ]</strong>",
                                        'data' => null,
                                        'is_read' => true
                                    ]);

                                    Notification::make()->success()->title('Candidat validé en tant que Conseiller !')->send();
                                } else {
                                    Notification::make()->danger()->title('Utilisateur introuvable.')->send();
                                }

                                $action->cancel();
                            }),

                        // BOUTON REFUSER
                        Tables\Actions\Action::make('reject_inside')
                            ->label('Refuser')
                            ->color('danger')
                            ->icon('heroicon-o-x-mark')
                            ->visible(fn() => isset($record->data['action_type']) && $record->data['action_type'] === 'registration_request')
                            ->requiresConfirmation()
                            ->modalHeading('Confirmer le refus')
                            ->action(function () use ($record, $action) {
                                $record->refresh();
                                if (!isset($record->data['action_type'])) return;

                                $applicant = User::find($record->data['applicant_id'] ?? null);
                                if ($applicant) {
                                    $applicant->delete();

                                    $record->update([
                                        'body' => $record->body . "<br><br><strong>[TRAITÉ : REFUSÉ]</strong>",
                                        'data' => null,
                                        'is_read' => true
                                    ]);

                                    Notification::make()->success()->title('Candidat refusé.')->send();
                                } else {
                                    $record->update(['data' => null]);
                                    Notification::make()->warning()->title('Déjà supprimé.')->send();
                                }

                                // FORCE LA FERMETURE DE LA MODALE
                                $action->cancel();
                            }),
                    ]),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessages::route('/'),
            'create' => Pages\CreateMessage::route('/create'),
        ];
    }
}
