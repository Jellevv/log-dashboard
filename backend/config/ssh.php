<?php

return [
    'key_path'   => storage_path('app/ssh/id_ed25519'),
    'passphrase' => env('SSH_KEY_PASSPHRASE', null),
];
