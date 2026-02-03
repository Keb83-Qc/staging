<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Tabs;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Section;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Liste des conseillers';
    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getNavigationGroup(): ?string
    {
        return 'Gestion Conseillers';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // --- 1. IDENTITÉ ---
                Section::make('Identité')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('Prénom')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($set, $state, $get) {
                                // On génère le slug si le prénom change ET qu'on a un nom
                                if ($get('last_name')) {
                                    $set('slug', Str::slug($state . '-' . $get('last_name')));
                                }
                            }),

                        Forms\Components\TextInput::make('last_name')
                            ->label('Nom')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($set, $state, $get) {
                                // On génère le slug si le nom change ET qu'on a un prénom
                                if ($get('first_name')) {
                                    $set('slug', Str::slug($get('first_name') . '-' . $state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Permalien (URL)')
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->unique(ignoreRecord: true),
                    ]),

                Forms\Components\Grid::make(3)
                    ->schema([
                        // --- 2. GAUCHE : BIOGRAPHIE (2/3) ---
                        Forms\Components\Group::make()->columnSpan(2)->schema([
                            Tabs::make('Biographie')
                                ->tabs([
                                    Tabs\Tab::make('Français')->icon('heroicon-m-language')
                                        ->schema([
                                            Forms\Components\RichEditor::make('bio_fr')
                                                ->label('Biographie (FR)')
                                                ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'h2', 'h3', 'undo', 'redo']),
                                        ]),
                                    Tabs\Tab::make('Anglais')->icon('heroicon-m-globe-alt')
                                        ->schema([
                                            Forms\Components\RichEditor::make('bio_en')
                                                ->label('Biography (EN)')
                                                ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'h2', 'h3', 'undo', 'redo']),
                                        ]),
                                ]),
                        ]),

                        // --- 3. DROITE : PHOTO & INFOS (1/3) ---
                        Forms\Components\Group::make()->columnSpan(1)->schema([

                            Section::make('Photo de Profil')
                                ->schema([
                                    // APERÇU MAGIQUE (Utilise l'accesseur image_url du modèle)
                                    Forms\Components\Placeholder::make('avatar_preview')
                                        ->label('Aperçu')
                                        ->content(fn($record) => $record && $record->image_url
                                            ? new \Illuminate\Support\HtmlString("<img src='{$record->image_url}' style='width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 3px solid #ddd; margin: 0 auto; display: block;'>")
                                            : 'Aucune image'),

                                    // UPLOAD
                                    Forms\Components\FileUpload::make('image')
                                        ->label('Changer la photo')
                                        ->image()
                                        ->avatar() // Mode rond dans l'upload
                                        ->imageEditor()
                                        ->directory('team') // Dossier storage/app/public/team
                                        ->getUploadedFileNameForStorageUsing(function ($file, $get) {
                                            $name = $get('slug') ?? Str::slug($get('first_name') . '-' . $get('last_name'));
                                            return $name . '-' . time() . '.' . $file->getClientOriginalExtension();
                                        })
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Connexion')
                                ->schema([
                                    Forms\Components\TextInput::make('email')
                                        ->email()
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->prefixIcon('heroicon-m-envelope'),

                                    Forms\Components\TextInput::make('password')
                                        ->label('Mot de passe')
                                        ->password()
                                        ->dehydrateStateUsing(fn($state) => Hash::make($state))
                                        ->dehydrated(fn($state) => filled($state)) // Important : ne met à jour que si rempli
                                        ->required(fn(string $context): bool => $context === 'create'),

                                    // Sélection du Rôle (Relation)
                                    Forms\Components\Select::make('role_id')
                                        ->relationship('role', 'name')
                                        ->label('Rôle')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                ]),
                        ]),
                    ]),

                // --- 4. DETAILS SUPPLÉMENTAIRES (Replié) ---
                Section::make('Détails & Configuration')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('phone')->label('Téléphone')->prefixIcon('heroicon-m-phone'),
                            Forms\Components\TextInput::make('city')->label('Ville')->prefixIcon('heroicon-m-map-pin'),
                            Forms\Components\TextInput::make('position')->label('Ordre d\'affichage')->numeric()->default(999),

                            Forms\Components\Select::make('languages')
                                ->label('Langues parlées')
                                ->multiple()
                                ->options([
                                    'fr' => 'Français',
                                    'en' => 'Anglais',
                                    'es' => 'Espagnol'
                                ])
                                ->searchable(),

                            Forms\Components\TextInput::make('booking_url')->label('Lien Calendly/Booking')->url()->prefixIcon('heroicon-m-calendar')->columnSpan(2),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // 1. ICI ON CACHE LE ROBOT (Modifiez l'ID ou le nom selon votre base de données)
            ->modifyQueryUsing(
                fn($query) => $query
                    ->where('id', '!=', 1) // Souvent le Super Admin/Robot est l'ID 1
                    ->where('first_name', '!=', 'System') // Ou on filtre par nom
                    ->where('first_name', '!=', 'Robot')
            )
            ->columns([
                // Image
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Photo')
                    ->circular()
                    ->defaultImageUrl(asset('assets/img/agent-default.jpg')),

                // Nom
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nom Complet')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name'])
                    ->weight('bold'),

                // 2. ICI ON AFFICHE LE TITRE
                // Assurez-vous que votre table 'team_titles' a bien une colonne 'name' ou 'title_name_fr'
                Tables\Columns\TextColumn::make('title.name')
                    ->label('Titre du poste')
                    ->badge() // Joli badge coloré
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                // Rôle
                Tables\Columns\TextColumn::make('role.name')
                    ->label('Rôle Système')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'conseiller' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(), // On peut le cacher si on veut juste voir le Titre pro

                // Ville
                Tables\Columns\TextColumn::make('city')
                    ->label('Ville')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('position', 'asc')
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
