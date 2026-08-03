<?php

namespace App\Services\Conversations;

use App\Domain\Messaging\DataObjects\ContactIdentity;
use App\Models\ChannelConnection;
use App\Models\Contact;
use App\Models\ContactChannel;
use App\Support\Messaging\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;

class ContactResolver
{
    public function resolve(ChannelConnection $connection, ContactIdentity $identity): ContactChannel
    {
        $channel = ContactChannel::query()
            ->where('channel_connection_id', $connection->id)
            ->whereIn('identifier', $this->identifiers($identity))
            ->first();

        if ($channel !== null) {
            $this->refresh($channel, $identity);

            return $channel;
        }

        $contact = $this->findExistingContact($identity) ?? Contact::create([
            'tenant_id' => $connection->tenant_id,
            'name' => $identity->name ?? $identity->phone ?? $identity->identifier,
            'phone' => $identity->phone,
            'email' => $identity->email,
            'avatar_url' => $identity->avatarUrl,
        ]);

        return ContactChannel::create([
            'tenant_id' => $connection->tenant_id,
            'contact_id' => $contact->id,
            'channel_connection_id' => $connection->id,
            'channel' => $connection->channel,
            'identifier' => $identity->identifier,
            'display_name' => $identity->name,
            'avatar_url' => $identity->avatarUrl,
            'is_group' => $identity->isGroup,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function identifiers(ContactIdentity $identity): array
    {
        if ($identity->isGroup) {
            return [$identity->identifier];
        }

        $suffix = str_contains($identity->identifier, '@')
            ? '@'.str($identity->identifier)->after('@')->value()
            : '';

        $variants = PhoneNumber::variants(str($identity->identifier)->before('@')->value());

        return $variants === []
            ? [$identity->identifier]
            : array_map(fn (string $digits): string => $digits.$suffix, $variants);
    }

    private function findExistingContact(ContactIdentity $identity): ?Contact
    {
        if ($identity->isGroup) {
            return null;
        }

        if (blank($identity->phone) && blank($identity->email)) {
            return null;
        }

        $phones = PhoneNumber::variants($identity->phone);

        return Contact::query()
            ->where(function (Builder $query) use ($identity, $phones): void {
                $query->when($phones !== [], fn (Builder $query) => $query->orWhereIn('phone', $phones))
                    ->when(filled($identity->email), fn (Builder $query) => $query->orWhere('email', $identity->email));
            })
            ->first();
    }

    private function refresh(ContactChannel $channel, ContactIdentity $identity): void
    {
        $changes = array_filter([
            'display_name' => $identity->name,
            'avatar_url' => $identity->avatarUrl,
        ]);

        if ($changes !== [] && $changes != $channel->only(array_keys($changes))) {
            $channel->update($changes);
        }
    }
}
