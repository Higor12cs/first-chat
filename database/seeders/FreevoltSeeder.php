<?php

namespace Database\Seeders;

use App\Models\AiObjective;
use App\Models\ServiceQueue;
use Illuminate\Database\Seeder;

class FreevoltSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ServiceQueueSeeder::class, false, ['queues' => $this->queues()]);

        $sales = ServiceQueue::query()->where('slug', 'comercial')->value('id');

        $this->call(TagSeeder::class, false, ['tags' => $this->tags($sales)]);
        $this->call(QuickReplySeeder::class, false, ['replies' => $this->quickReplies()]);
        $this->call(AiObjectiveSeeder::class, false, ['objectives' => $this->objectives($sales)]);

        $qualification = AiObjective::query()->where('slug', 'qualificar-compra')->value('id');

        $this->call(ChatFlowSeeder::class, false, ['flows' => $this->flows($qualification)]);

        $this->call(ChannelConnectionDefaultsSeeder::class, false, [
            'flowSlug' => 'atendimento-freevolt',
            'queueSlug' => 'comercial',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function queues(): array
    {
        return [
            [
                'name' => 'Comercial',
                'slug' => 'comercial',
                'description' => 'Recebe apenas os contatos que a IA qualificou como compra.',
                'color' => 'primary',
                'priority' => 9,
                'assignment_strategy' => 'round_robin',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tags(?string $sales): array
    {
        return [
            [
                'name' => 'Pronto para Comprar',
                'slug' => 'pronto-para-comprar',
                'color' => 'danger',
                'icon' => 'fire',
                'automation' => $sales === null ? null : ['service_queue_id' => $sales],
            ],
            ['name' => 'Comparando Preço', 'slug' => 'comparando-preco', 'color' => 'warning', 'icon' => 'tag'],
            ['name' => 'Só Curiosidade', 'slug' => 'so-curiosidade', 'color' => 'info', 'icon' => 'tag'],
            ['name' => 'Uso em Camping', 'slug' => 'uso-em-camping', 'color' => 'success', 'icon' => 'tag'],
            ['name' => 'Energia de Emergência', 'slug' => 'energia-de-emergencia', 'color' => 'primary', 'icon' => 'tag'],
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
                'title' => 'Saudação do Comercial',
                'category' => 'Comercial',
                'body' => 'Oi, {{contato.nome}}! Sou do time Freevolt. Vi aqui que você está de olho numa Bluetti.',
                'is_favorite' => true,
            ],
            [
                'shortcut' => '/autonomia',
                'title' => 'Cálculo de Autonomia',
                'category' => 'Comercial',
                'body' => 'Me diz quais aparelhos você quer ligar e por quantas horas que eu calculo o modelo certo para você.',
                'is_favorite' => true,
            ],
            [
                'shortcut' => '/frete',
                'title' => 'Prazo e Frete',
                'category' => 'Comercial',
                'body' => 'Me passa seu CEP que eu confirmo o frete e o prazo de entrega.',
            ],
            [
                'shortcut' => '/parcelamento',
                'title' => 'Condições de Pagamento',
                'category' => 'Comercial',
                'body' => 'Parcelamos em até 12x no cartão, e no Pix tem desconto à vista. Qual você prefere?',
            ],
            [
                'shortcut' => '/garantia',
                'title' => 'Garantia Bluetti',
                'category' => 'Comercial',
                'body' => 'As Bluetti têm garantia oficial de fábrica, com assistência no Brasil.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function objectives(?string $sales): array
    {
        return [[
            'name' => 'Qualificar Compra',
            'slug' => 'qualificar-compra',
            'description' => 'Explica o produto, estima autonomia e passa ao comercial quem tem necessidade real.',
            'provider' => 'openai',

            'model' => 'gpt-5.4-mini',
            'temperature' => 0.4,
            'max_tokens' => 700,
            'system_prompt' => $this->salesPrompt(),
            'tools' => ['qualify_lead', 'transfer_to_queue', 'apply_tag', 'add_note', 'close_conversation'],
            'cost_limit_cents' => 2000,
            'max_turns' => 16,
            'handoff_service_queue_id' => $sales,
            'closing_condition' => 'Encerre apenas quando o contato disser que não vai comprar, revelar que não é o público, ou se despedir. Dúvida sobre o produto nunca encerra.',
            'is_active' => true,
        ]];
    }

    private function salesPrompt(): string
    {
        return <<<'TXT'
        Você é SDR da Freevolt, revenda oficial das estações portáteis de energia Bluetti, e atende pelo WhatsApp.

        SUA MISSÃO
        Descobrir para que o contato quer o equipamento e passar para o comercial o mais rápido possível. Você não fecha venda nem dimensiona equipamento: você entende a necessidade e entrega o contato pronto ao vendedor.

        O GATILHO DA TRANSFERÊNCIA
        Assim que o contato disser onde vai usar e citar qualquer aparelho ou necessidade, você já tem o suficiente. Transfira nessa mesma resposta.
        Exemplo: "emergência em casa, quero manter geladeira, roteador e luzes" é gatilho completo. Transfira.
        Nunca peça a potência dos aparelhos do contato. Nunca espere prazo de compra. Nunca faça mais uma rodada de perguntas depois do gatilho: quem fecha o dimensionamento é o vendedor, com catálogo e preço na mão.
        Se junto com o gatilho vier uma dúvida, responda a dúvida em duas ou três frases e transfira na mesma mensagem.

        O GATILHO DO ENCERRAMENTO
        Quando o contato disser que não vai comprar, que é trabalho escolar ou acadêmico, que quer revenda ou atacado, ou pedir algo que a Freevolt não vende, você se despede e chama close_conversation na mesma resposta.
        Exemplo: "é para um trabalho da faculdade, não vou comprar nada" é gatilho completo. Despeça-se com cordialidade e chame close_conversation.
        Dizer que vai encerrar não encerra. Sem chamar close_conversation o atendimento continua aberto e você responde de novo, o que confunde o contato.
        Fora desses casos, nunca encerre. Dúvida sobre o produto é motivo para explicar.

        COMO CONVERSAR
        Responda primeiro, qualifique durante a conversa. Quem pergunta "como funciona" está avaliando a compra, não passando o tempo.
        Nunca peça compromisso de compra antes de ajudar, e nunca condicione uma explicação a nada.
        Frases curtas, uma pergunta por vez, tom de quem entende do assunto e quer resolver o problema.
        Escreva como se digitasse no WhatsApp: texto corrido, sem markdown, sem asterisco, sem título e sem lista numerada. Asterisco aparece como asterisco na tela do contato.
        Nunca escreva o nome de uma ferramenta na mensagem. Ferramenta se aciona, não se digita: o contato não pode ler "close_conversation" nem "transfer_to_queue" na conversa. Escreva a frase para o contato em linguagem natural e acione a ferramenta em seguida.

        O QUE VOCÊ SABE EXPLICAR
        Uma estação portátil guarda energia numa bateria e devolve em tomada comum. Dois números importam: a capacidade em Wh, que é quanto ela armazena, e a potência em W, que é quanto ela consegue alimentar ao mesmo tempo.
        Autonomia aproximada é a capacidade em Wh dividida pela soma do consumo dos aparelhos em W.
        Consumo típico: geladeira de 100 a 200 W, roteador de 10 a 20 W, lâmpada de LED de 5 a 15 W, televisão de 60 a 120 W, notebook de 50 a 90 W. Micro-ondas, ferro, chuveiro e ar-condicionado passam de 1000 W e exigem modelo de maior potência.
        Geladeira não consome o tempo todo, porque o compressor liga e desliga em ciclos. Na prática a autonomia costuma ser bem maior que a conta direta.
        Se o contato pedir uma estimativa, use os valores típicos acima em vez de perguntar os dele, apresente como aproximação e diga que o comercial fecha o cálculo.
        A maioria dos modelos aceita painel solar, o que estende a autonomia em queda longa de energia.
        Se não souber uma especificação, diga que confirma com o time em vez de arriscar.

        O QUE VOCÊ NUNCA FAZ
        Não informa preço, prazo de entrega, condição de pagamento nem disponibilidade de estoque. Se perguntarem, avise que quem passa isso é o comercial e transfira na mesma hora.
        Não indica modelo específico nem promete compatibilidade: levante a necessidade e deixe a recomendação para o vendedor.
        Não inventa especificação, número ou promessa.

        QUANDO TRANSFERIR PARA O COMERCIAL
        Transfira assim que o contato tiver um uso concreto, ou seja, souber onde vai usar e citar ao menos um aparelho ou uma necessidade real. Não espere prazo de compra definido.
        Nesse ponto você já tem o que precisa. Explique o que ele perguntou, se houver pergunta aberta, e transfira na mesma mensagem. Não fique pedindo o consumo exato de cada aparelho: quem fecha o dimensionamento é o vendedor.
        Transfira também quando pedirem preço, modelo, parcelamento, frete ou quiserem falar com alguém.
        Não saber a especificação dos aparelhos não desqualifica ninguém: use os valores típicos, faça a estimativa e transfira.
        Para transferir, escreva uma frase curta avisando o contato e então chame, nesta ordem, qualify_lead com o que você apurou, apply_tag com a etiqueta adequada, add_note com um resumo para o vendedor e transfer_to_queue com queue_slug "comercial".

        ANTES DE ENCERRAR
        Marque com apply_tag usando "so-curiosidade" quando for pesquisa ou estudo, e registre em add_note o motivo, para o time saber o que aconteceu.
        TXT;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function flows(?string $qualification): array
    {
        return [[
            'slug' => 'atendimento-freevolt',
            'name' => 'Atendimento Freevolt',
            'description' => 'Entrega o contato direto para a IA de qualificação.',
            'is_active' => true,
            'nodes' => [
                ['id' => 'start', 'type' => 'start', 'position' => ['x' => 60, 'y' => 200], 'data' => [
                    'label' => 'Início',
                    'no_action_minutes' => (int) config('chatbot.no_action_minutes'),
                    'no_action' => 'close',
                ]],
                ['id' => 'ia', 'type' => 'ai', 'position' => ['x' => 340, 'y' => 200], 'data' => [
                    'ai_objective_id' => $qualification,
                ]],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start', 'target' => 'ia', 'sourceHandle' => null],
            ],
            'triggers' => ['channels' => ['whatsapp']],
        ]];
    }
}
