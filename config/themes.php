<?php
/**
 * Thèmes de veille et mots-clés associés (FR + EN).
 * Un article reçoit tous les thèmes dont un mot-clé apparaît
 * dans son titre ou son résumé. Ajoute librement des mots-clés.
 * Les mots-clés sont cherchés en mots entiers (un « s » final est toléré) :
 * « ia » trouve « l'IA » mais pas « média ».
 */
return [
    'ia' => [
        'label' => 'IA', 'color' => '#7c3aed',
        'keywords' => ['intelligence artificielle', ' ia ', 'ia générative', 'chatgpt', 'openai', 'anthropic', 'claude', 'gemini', 'mistral', 'llm', 'gpt', 'machine learning', 'deep learning', 'artificial intelligence', 'genai', 'agent ia', 'ai agent', 'copilot', 'modèle de langage'],
    ],
    'data' => [
        'label' => 'Data', 'color' => '#0891b2',
        'keywords' => ['data', 'données', 'big data', 'analytics', 'dataviz', 'tableau de bord', 'dashboard', 'power bi', 'sql', 'data center', 'datacenter', 'base de données', 'database'],
    ],
    'web' => [
        'label' => 'Web & dev', 'color' => '#ea580c',
        'keywords' => ['javascript', 'typescript', 'react', 'vue.js', 'vuejs', 'angular', 'css', 'html', 'php', 'python', 'framework', 'développeur', 'developer', 'frontend', 'backend', 'api', 'navigateur', 'browser', 'chrome', 'firefox', 'accessibilité', 'accessibility', 'ux', 'ui', 'wordpress', 'github', 'open source', 'webdesign', 'site web'],
    ],
    'product' => [
        'label' => 'Product & projet', 'color' => '#db2777',
        'keywords' => ['product owner', 'product manager', 'gestion de projet', 'project management', 'agile', 'scrum', 'kanban', 'roadmap', 'sprint', 'jira', 'notion', 'trello', 'méthodologie', 'transformation numérique', 'transformation digitale', 'startup', 'produit'],
    ],
    'automatisation' => [
        'label' => 'Automatisation', 'color' => '#16a34a',
        'keywords' => ['automatisation', 'automation', 'no-code', 'low-code', 'nocode', 'lowcode', 'zapier', 'make.com', 'n8n', 'workflow', 'rpa', 'robot', 'bot'],
    ],
    'cyber' => [
        'label' => 'Cyber', 'color' => '#dc2626',
        'keywords' => ['cyber', 'cyberattaque', 'piratage', 'pirate', 'hacker', 'ransomware', 'rançongiciel', 'phishing', 'hameçonnage', 'malware', 'vulnérabilité', 'vulnerability', 'faille', 'cve','fuite de données', 'data breach', 'breach', 'sécurité', 'security', 'anssi', 'exploit', 'zero-day'],
    ],
    'regulation' => [
        'label' => 'Régulation', 'color' => '#ca8a04',
        'keywords' => ['rgpd', 'gdpr', 'cnil', 'ai act', 'dma', 'dsa', 'nis2', 'règlement', 'regulation', 'loi', 'law', 'commission européenne', 'european commission', 'amende', 'antitrust', 'conformité', 'compliance', 'souveraineté'],
    ],
];
