<?php

return [
    'abandonedcart' => [
        [
            'key'    => 'abandonedcart.settings',
            'name'   => 'Abandoned Cart Settings',
            'sort'   => 1,
            'fields' => [
                [
                    'name'  => 'enable',
                    'title' => 'Enable Abandoned Cart Emails',
                    'type'  => 'boolean',
                ],
                [
                    'name'       => 'abandoned_time',
                    'title'      => 'Time to Consider Cart Abandoned (in hours)',
                    'type'       => 'text',
                    'validation' => 'numeric|min:1',
                ],
                [
                    'name'  => 'email_subject',
                    'title' => 'Email Subject',
                    'type'  => 'text',
                ],
                [
                    'name'  => 'email_content',
                    'title' => 'Email Content',
                    'type'  => 'textarea',
                ],
            ],
        ],
    ],
];
