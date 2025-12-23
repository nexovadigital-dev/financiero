<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class EditProfile extends BaseEditProfile
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nombre Completo')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Ingrese su nombre completo'),

                TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->placeholder('correo@ejemplo.com')
                    ->validationMessages([
                        'unique' => 'Este correo ya está en uso.',
                    ]),

                TextInput::make('password')
                    ->label('Nueva Contraseña')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context) => $context === 'create')
                    ->confirmed()
                    ->rule(Password::default())
                    ->placeholder('Dejar en blanco para no cambiar')
                    ->helperText('Mínimo 8 caracteres. Deje en blanco si no desea cambiar la contraseña.'),

                TextInput::make('password_confirmation')
                    ->label('Confirmar Nueva Contraseña')
                    ->password()
                    ->dehydrated(false)
                    ->placeholder('Confirme su nueva contraseña')
                    ->requiredWith('password'),
            ]);
    }

    public function getHeading(): string
    {
        return 'Mi Perfil';
    }

    public function getSubheading(): string | null
    {
        return 'Gestione su información personal y credenciales de acceso';
    }
}
