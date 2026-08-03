<?php

return [

    'no_action_minutes' => (int) env('CHATBOT_NO_ACTION_MINUTES', 15),

    'no_action' => env('CHATBOT_NO_ACTION', 'close'),

    'no_action_message' => env(
        'CHATBOT_NO_ACTION_MESSAGE',
        'Encerrei este atendimento por falta de resposta. É só mandar uma mensagem para começarmos de novo.',
    ),

];
