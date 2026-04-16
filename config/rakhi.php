<?php

return [
    'trial_days'                  => 7,
    'max_chat_messages_trial'     => 50,
    'default_language'            => 'en',
    'pinecone_index'              => env('PINECONE_INDEX', 'rakhi-ai'),
    'pinecone_host'               => env('PINECONE_HOST', ''),
    'pinecone_dimension'          => 768,
    'safety_keywords'             => [
        'chest pain', 'heart attack', 'unconscious',
        'suicide', 'bleeding', 'emergency',
        'can\'t breathe', 'severe pain',
    ],
    'supported_llm_providers'     => ['gemini', 'chatgpt'],
    'default_llm'                 => 'gemini',
    'voice_languages'             => ['en-IN', 'hi-IN', 'ta-IN', 'te-IN'],
    'max_voice_duration_seconds'  => 300,
    'plan_generation_queue'       => 'plans',

    'pinecone_namespaces' => [
        'coach-diabetes',
        'coach-diet-nutrition',
        'coach-fitness',
        'coach-pcos-thyroid',
        'coach-mental-wellness',
        'coach-sleep',
        'coach-weight-loss',
        'coach-pregnancy',
        'coach-postpartum',
        'coach-energy',
        'coach-stress',
        'coach-habit',
    ],
];
