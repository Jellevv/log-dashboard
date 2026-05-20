<?php

return [

    'laravel-test' => [
        'label' => 'Laravel Test',
        'log_path' => '/mnt/projects/laravel-test/storage/logs',
    ],

    'llm-project' => [
        'label' => 'LLM project',
        'log_path' => '/mnt/llm-craft-project/storage/logs',
    ],

    'ssh-project' => [
        'type' => 'ssh',
        'label' => 'SSH Project',
        'host' => '192.168.1.10',
        'user' => 'deploy',
        'password' => 'ssh-password',
        'path' => '/var/www/app/storage/logs',
    ],

];

