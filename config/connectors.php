<?php

use App\Domain\Messaging\Connectors\Uazapi\UazapiConnector;
use App\Domain\Messaging\Enums\Channel;
use App\Domain\Messaging\Enums\MessageType;

return [

    'webhook_secret' => env('CONNECTOR_WEBHOOK_SECRET'),

    /**
     * O provedor precisa alcançar a aplicação de fora, tanto para entregar o
     * callback quanto para buscar o anexo que vamos enviar. Em desenvolvimento
     * o APP_URL aponta para localhost, então aqui entra o endereço do túnel.
     */
    'public_url' => env('CONNECTOR_PUBLIC_URL'),

    'instance_prefix' => env('CONNECTOR_INSTANCE_PREFIX', 'first-chat'),

    'provisioning' => [
        Channel::WhatsApp->value => 'uazapi',
    ],

    'tenant_channels' => [
        Channel::WhatsApp->value,
    ],

    'drivers' => [

        'uazapi' => [
            'label' => 'Uazapi',
            'channel' => Channel::WhatsApp,
            'class' => UazapiConnector::class,
            'capabilities' => [
                'message_types' => [
                    MessageType::Text,
                    MessageType::Image,
                    MessageType::Audio,
                    MessageType::Video,
                    MessageType::Document,
                    MessageType::Sticker,
                    MessageType::Location,
                    MessageType::Interactive,
                ],
                'media' => true,
                'interactive_buttons' => true,
                'typing_indicator' => true,
                'read_receipts' => true,
                'message_deletion' => true,
                'groups' => true,
                'session' => true,
                'pairing_code' => true,
            ],
            'credentials' => [
                'base_url' => env('UAZAPI_BASE_URL', 'https://free.uazapi.com'),
                'admin_token' => env('UAZAPI_ADMIN_TOKEN'),
            ],
        ],

    ],

];
