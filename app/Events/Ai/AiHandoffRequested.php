<?php

namespace App\Events\Ai;

use App\Models\AiObjective;
use App\Models\Conversation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiHandoffRequested
{
    use Dispatchable, SerializesModels;

    /**
     * @param  bool  $announcedByAi  Marca o encaminhamento que a própria IA decidiu,
     */
    public function __construct(
        public Conversation $conversation,
        public AiObjective $objective,
        public ?string $reason = null,
        public bool $announcedByAi = false,
    ) {}
}
