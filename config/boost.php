<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Excluded Guidelines
    |--------------------------------------------------------------------------
    |
    | This app is not deployed with Laravel Cloud. Drop the Cloud deployment
    | guideline so agents are not steered toward it.
    |
    */

    'guidelines' => [
        'exclude' => [
            'deployments',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Paths
    |--------------------------------------------------------------------------
    |
    | Grok reads project skills from .agents/skills. Keep Boost's copies there
    | so the repo does not grow a second skills tree under .grok/skills.
    | Project MCP stays at .grok/config.toml, which is the file Grok reads.
    |
    */

    'agents' => [
        'grok_build' => [
            'skills_path' => '.agents/skills',
        ],
    ],

];
