<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    public function getHeading(): string
    {
        return 'Acceso Administrador';
    }

    public function getSubHeading(): string
    {
        return '⚠️ SISTEMA PRIVADO - Acceso Restringido';
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent()
                            ->label('Correo Electrónico'),
                        $this->getPasswordFormComponent()
                            ->label('Contraseña'),
                        $this->getRememberFormComponent()
                            ->label('Recordarme'),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Correo Electrónico')
            ->email()
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['style' => 'font-size: 1rem;'])
            ->placeholder('admin@nicagsm.com');
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Contraseña')
            ->password()
            ->required()
            ->extraInputAttributes(['style' => 'font-size: 1rem;'])
            ->placeholder('Ingrese su contraseña');
    }
}
