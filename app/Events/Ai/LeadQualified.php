<?php

namespace App\Events\Ai;

use App\Models\AiObjective;
use App\Models\Conversation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadQualified
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public Conversation $conversation,
        public AiObjective $objective,
        public array $data = [],
    ) {}
}
