<?php

namespace App\Filament\Resources\MessageResource\Pages;

use App\Filament\Resources\MessageResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions\Action;

class CreateMessage extends CreateRecord
{
    protected static string $resource = MessageResource::class;

    // 1. Renommer le bouton de validation principal
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Envoyer');
    }

    // 2. Supprimer le bouton "Créer & ajouter un autre"
    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->hidden(); // Cache le bouton complètement
    }

    // 3. Renommer le bouton Annuler en Fermer
    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Fermer');
    }

    // 4. Redirection automatique vers la boîte de réception après l'envoi
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Message envoyé avec succès !';
    }
}
