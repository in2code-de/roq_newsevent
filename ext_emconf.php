<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'News event',
    'description' => 'Event extension based on the versatile news system. Supplies additional event functionality to news records.',
    'category' => 'plugin',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'author' => 'ROQUIN B.V., Oliver Eglseder',
    'author_email' => 'extensions@roquin.nl, oliver.eglseder@in2code.de',
    'author_company' => 'ROQUIN B.V., in2code GmbH',
    'version' => '4.0.2',
    'constraints' => [
        'depends' => [
            'typo3' => '7.6.11-8.7.99',
            'news' => '5.0.0-6.0.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
