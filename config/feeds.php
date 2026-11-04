<?php
/**
 * Sources RSS ajoutées à la création de la base.
 * Ensuite, tu gères tes sources depuis la page /sources du site
 * (ajout, désactivation, suppression), sans toucher à ce fichier.
 *
 * Ces adresses n'ont pas pu être testées avant la mise en ligne :
 * la page /sources affiche l'état de chaque flux après la première
 * récupération (OK ou erreur).
 */
return [
    // Actualité tech générale (FR)
    ['name' => 'Le Monde Informatique', 'url' => 'https://www.lemondeinformatique.fr/flux-rss/thematique/toutes-les-actualites/rss.xml'],
    ['name' => 'Numerama',              'url' => 'https://www.numerama.com/feed/'],
    ['name' => 'Siècle Digital',        'url' => 'https://siecledigital.fr/feed/'],
    ['name' => 'Blog du Modérateur',    'url' => 'https://www.blogdumoderateur.com/feed/'],
    ['name' => 'LeMagIT',               'url' => 'https://www.lemagit.fr/rss/ContentSyndication.xml'],
    ['name' => 'Korben',                'url' => 'https://korben.info/feed'],

    // Web & dev
    ['name' => 'Alsacréations',         'url' => 'https://www.alsacreations.com/rss/actualites.xml'],
    ['name' => 'Smashing Magazine',     'url' => 'https://www.smashingmagazine.com/feed/'],

    // Cybersécurité & régulation (sources officielles FR)
    ['name' => 'CERT-FR (ANSSI)',       'url' => 'https://www.cert.ssi.gouv.fr/feed/'],
    ['name' => 'CNIL',                  'url' => 'https://www.cnil.fr/fr/rss.xml'],

    // International
    ['name' => 'The Verge',             'url' => 'https://www.theverge.com/rss/index.xml'],
    ['name' => 'Hacker News (une)',     'url' => 'https://hnrss.org/frontpage'],
];
