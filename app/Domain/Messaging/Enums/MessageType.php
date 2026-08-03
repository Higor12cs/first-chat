<?php

namespace App\Domain\Messaging\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case Document = 'document';
    case Sticker = 'sticker';
    case Location = 'location';
    case Contact = 'contact';
    case Template = 'template';
    case Interactive = 'interactive';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texto',
            self::Image => 'Imagem',
            self::Audio => 'Áudio',
            self::Video => 'Vídeo',
            self::Document => 'Documento',
            self::Sticker => 'Figurinha',
            self::Location => 'Localização',
            self::Contact => 'Contato',
            self::Template => 'Modelo',
            self::Interactive => 'Interativo',
            self::System => 'Sistema',
        };
    }

    public function isMedia(): bool
    {
        return in_array($this, [self::Image, self::Audio, self::Video, self::Document, self::Sticker], true);
    }
}
