<?php

namespace Database\Seeders;

use App\Models\ServiceQueue;
use Illuminate\Database\Seeder;

class ConsysSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ServiceQueueSeeder::class, false, ['queues' => $this->queues()]);

        $support = ServiceQueue::query()->where('slug', 'suporte')->value('id');
        $billing = ServiceQueue::query()->where('slug', 'financeiro')->value('id');

        $this->call(TagSeeder::class, false, ['tags' => $this->tags($support)]);
        $this->call(QuickReplySeeder::class, false, ['replies' => $this->quickReplies()]);
        $this->call(ChatFlowSeeder::class, false, ['flows' => $this->flows($support, $billing)]);

        $this->call(ChannelConnectionDefaultsSeeder::class, false, [
            'flowSlug' => 'triagem-consys',
            'queueSlug' => 'suporte',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function queues(): array
    {
        return [
            [
                'name' => 'Suporte',
                'slug' => 'suporte',
                'description' => 'Erros, dúvidas de uso e chamados do ERP.',
                'color' => 'info',
                'priority' => 9,
                'assignment_strategy' => 'least_busy',
            ],
            [
                'name' => 'Financeiro',
                'slug' => 'financeiro',
                'description' => 'Boletos, notas fiscais e renovação de licença.',
                'color' => 'success',
                'priority' => 6,
                'assignment_strategy' => 'manual',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tags(?string $support): array
    {
        return [
            [
                'name' => 'Erro no Sistema',
                'slug' => 'erro-no-sistema',
                'color' => 'danger',
                'icon' => 'alert',
                'automation' => $support === null ? null : ['service_queue_id' => $support],
            ],
            ['name' => 'Dúvida de Uso', 'slug' => 'duvida-de-uso', 'color' => 'info', 'icon' => 'tag'],
            ['name' => 'Módulo Fiscal', 'slug' => 'modulo-fiscal', 'color' => 'warning', 'icon' => 'tag'],
            ['name' => 'Aguardando Cliente', 'slug' => 'aguardando-cliente', 'color' => 'warning', 'icon' => 'tag'],
            ['name' => 'Resolvido', 'slug' => 'resolvido', 'color' => 'success', 'icon' => 'check'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function quickReplies(): array
    {
        return [
            [
                'shortcut' => '/ola',
                'title' => 'Saudação Inicial',
                'category' => 'Saudações',
                'body' => 'Olá, {{contato.nome}}! Aqui é o suporte Consys. Como posso ajudar?',
                'is_favorite' => true,
            ],
            [
                'shortcut' => '/acesso',
                'title' => 'Pedido de Acesso',
                'category' => 'Suporte',
                'body' => 'Para eu reproduzir o problema, me envie o CNPJ da empresa e o usuário que apresentou o erro.',
                'is_favorite' => true,
            ],
            [
                'shortcut' => '/print',
                'title' => 'Pedido de Print',
                'category' => 'Suporte',
                'body' => 'Consegue me mandar um print da tela com a mensagem de erro? Ajuda a identificar o módulo.',
            ],
            [
                'shortcut' => '/versao',
                'title' => 'Versão do ERP',
                'category' => 'Suporte',
                'body' => 'Qual versão do ERP está instalada? Ela aparece no rodapé da tela inicial.',
            ],
            [
                'shortcut' => '/boleto',
                'title' => 'Segunda Via',
                'category' => 'Financeiro',
                'body' => 'Consigo gerar a segunda via para você. Confirma o CNPJ e a competência da cobrança?',
            ],
            [
                'shortcut' => '/encerrar',
                'title' => 'Encerramento',
                'category' => 'Suporte',
                'body' => 'Posso ajudar em mais alguma coisa? Se estiver tudo certo, encerro o chamado por aqui.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function flows(?string $support, ?string $billing): array
    {
        return [[
            'slug' => 'triagem-consys',
            'name' => 'Triagem Consys',
            'description' => 'Recebe o cliente, pergunta o motivo do contato e encaminha para o setor.',
            'is_active' => true,
            'nodes' => [
                ['id' => 'start', 'type' => 'start', 'position' => ['x' => 60, 'y' => 200], 'data' => [
                    'label' => 'Início',
                    'no_action_minutes' => (int) config('chatbot.no_action_minutes'),
                    'no_action' => 'close',
                ]],
                ['id' => 'welcome', 'type' => 'message', 'position' => ['x' => 300, 'y' => 200], 'data' => [
                    'text' => 'Olá, {{contato.nome}}! Aqui é a Consys, do ERP que você usa.',
                ]],
                ['id' => 'menu', 'type' => 'menu', 'position' => ['x' => 560, 'y' => 200], 'data' => [
                    'text' => 'O que você precisa hoje?',
                    'options' => [
                        ['id' => 'suporte', 'label' => 'Erro ou dúvida no sistema'],
                        ['id' => 'financeiro', 'label' => 'Boleto, nota fiscal ou licença'],
                        ['id' => 'encerrar', 'label' => 'Nada por enquanto'],
                    ],
                ]],
                ['id' => 'setor-suporte', 'type' => 'queue', 'position' => ['x' => 840, 'y' => 100], 'data' => [
                    'label' => 'Suporte',
                    'service_queue_id' => $support,
                ]],
                ['id' => 'setor-financeiro', 'type' => 'queue', 'position' => ['x' => 840, 'y' => 280], 'data' => [
                    'label' => 'Financeiro',
                    'service_queue_id' => $billing,
                ]],
                ['id' => 'finalizar', 'type' => 'close', 'position' => ['x' => 840, 'y' => 440], 'data' => [
                    'label' => 'Finalizar',
                    'text' => 'Tudo bem! Quando precisar é só chamar por aqui.',
                    'reason' => 'Encerrado pelo chatbot.',
                ]],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start', 'target' => 'welcome', 'sourceHandle' => null],
                ['id' => 'e2', 'source' => 'welcome', 'target' => 'menu', 'sourceHandle' => null],
                ['id' => 'e3', 'source' => 'menu', 'target' => 'setor-suporte', 'sourceHandle' => 'suporte'],
                ['id' => 'e4', 'source' => 'menu', 'target' => 'setor-financeiro', 'sourceHandle' => 'financeiro'],
                ['id' => 'e5', 'source' => 'menu', 'target' => 'finalizar', 'sourceHandle' => 'encerrar'],
            ],
            'triggers' => ['channels' => ['whatsapp']],
        ]];
    }
}
