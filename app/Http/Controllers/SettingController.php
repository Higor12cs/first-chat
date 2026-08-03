<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantContext;
use App\Http\Resources\CardResource;
use App\Models\Card;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function edit(Request $request): Response
    {
        $tenant = $this->context->get();

        return Inertia::render('Settings/Edit', [
            'tenant' => [
                'name' => $tenant->name,
                'document' => $tenant->document,
                'timezone' => $tenant->timezone,
                'settings' => $tenant->settings ?? [],
                'max_connections' => $tenant->limit('max_connections'),
            ],
            'timezones' => ['America/Sao_Paulo', 'America/Manaus', 'America/Belem', 'America/Fortaleza', 'UTC'],
            'cards' => CardResource::collection(Card::query()->active()->orderBy('name')->get()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $card = Rule::exists('cards', 'id')->where('tenant_id', app(TenantContext::class)->id());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:30'],
            'timezone' => ['required', 'string', 'timezone'],
            'settings' => ['nullable', 'array'],
            'settings.sign_messages' => ['boolean'],
            'settings.auto_close_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'settings.greeting' => ['nullable', 'string', 'max:1000'],
            'settings.after_hours_enabled' => ['boolean'],
            'settings.after_hours_card_id' => ['nullable', 'uuid', $card],
            'settings.business_hours' => ['nullable', 'array'],
            'settings.business_hours.*' => ['array', 'min:1'],
            'settings.business_hours.*.*.start' => ['required', 'date_format:H:i'],
            'settings.business_hours.*.*.end' => ['required', 'date_format:H:i', 'after:settings.business_hours.*.*.start'],
            'settings.business_exceptions' => ['nullable', 'array'],
            'settings.business_exceptions.*.type' => ['required', 'in:holiday,exception'],
            'settings.business_exceptions.*.name' => ['required', 'string', 'max:120'],
            'settings.business_exceptions.*.starts_on' => ['required', 'date_format:Y-m-d'],
            'settings.business_exceptions.*.ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:settings.business_exceptions.*.starts_on'],
            'settings.business_exceptions.*.start' => ['nullable', 'required_if:settings.business_exceptions.*.type,exception', 'date_format:H:i'],
            'settings.business_exceptions.*.end' => ['nullable', 'required_if:settings.business_exceptions.*.type,exception', 'date_format:H:i', 'after:settings.business_exceptions.*.start'],
            'settings.business_exceptions.*.card_id' => ['nullable', 'uuid', $card],
        ], [], [
            'settings.business_hours.*.*.start' => 'início',
            'settings.business_hours.*.*.end' => 'fim',
            'settings.business_exceptions.*.name' => 'nome',
            'settings.business_exceptions.*.starts_on' => 'data inicial',
            'settings.business_exceptions.*.ends_on' => 'data final',
            'settings.business_exceptions.*.start' => 'hora inicial',
            'settings.business_exceptions.*.end' => 'hora final',
        ]);

        $this->context->get()->update($validated);

        return back()->with('success', 'Configurações salvas.');
    }
}
