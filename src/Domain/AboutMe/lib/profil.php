<?php

/*
 * Daten für die Seite „Über mich“ – und gleichzeitig Wissen für den Portfolio-Assistenten.
 * Eine Quelle für beides: Was hier geändert wird, ist auf der Website und im Chatbot aktuell.
 *
 * Bei stations[].link ist href ein Routenname (wird im Template mit path() aufgelöst).
 */

return [
    'skill_groups' => [
        'Backend' => ['PHP' => 3, 'Java' => 1, 'C#' => 1],
        'Frontend' => ['Java/-TypeScript' => 3, 'HTML / CSS' => 2, 'Twig / React' => 2],
        'Datenhandling' => ['MySQL' => 3, 'GraphQL' => 1, 'SPSS' => 1],
        'Sonstiges' => ['Microsoft Office' => 3, 'Docker' => 2, 'GitLab & CI/CD' => 2],
    ],
    'skill_projects' => [
        'PHP' => ['LAN-Party Moderation', 'Raumverwaltung', 'Webinar', 'Finanzbuchhaltung-Anbindung'],
        'Twig / React' => ['LAN-Party Moderation', 'Webinar', 'Werbekampagnen Leads Auswertung'],
        'MySQL' => ['Adress-Auswertungstool', 'Adress-Cluster', 'Finanzbuchhaltung-Anbindung'],
        'GitLab & CI/CD' => ['Kanban-App', 'GitLab Pipeline', 'GitLab-Anbindung'],
    ],
    'strengths' => [
        ['name' => 'Hilfsbereitschaft', 'proof' => 'Zwei Wochen vor der Hochzeitsmesse habe ich für einen Lingener Betrieb kurzfristig die neue Website übernommen.', 'project' => 'krummen.de'],
        ['name' => 'Analytisches Denken', 'proof' => 'Bei der Raumverwaltung lagen die Stolpersteine nicht in normalen Terminen, sondern in Absagen, verschobenen Serienterminen und kurzfristigen Verlängerungen.', 'project' => 'Raumverwaltung'],
        ['name' => 'Flexibilität', 'proof' => 'Nach sechs Semestern Wirtschaftsinformatik bin ich in eine praxisorientierte Ausbildung gewechselt und habe dort genau den Einstieg gefunden, den ich gesucht hatte.', 'project' => null],
        ['name' => 'Kundenorientierung', 'proof' => 'Mit dem Adress-Auswertungstool erstellt der Vertrieb seine Auswertungen selbst, statt tagelang auf die Entwicklung zu warten.', 'project' => 'Adress-Auswertungstool'],
        ['name' => 'Kreativität', 'proof' => 'Für meine Tabletop-Abende habe ich ein System gebaut, das Licht, Musik und Notizen über eine einzige Oberfläche steuert.', 'project' => 'Tabletop Infotainment-System'],
        ['name' => 'Lernbereitschaft', 'proof' => 'LoRaWAN habe ich mir von Grund auf erarbeitet: von der Technologie-Evaluierung über Hardwaretests bis zur Einführung ins Produktportfolio.', 'project' => 'LoRaWAN'],
        ['name' => 'Kommunikation', 'proof' => 'Für LoRaWAN habe ich mitentwickelt, wie wir die Lösung unseren Kunden erklären, ohne dass sie sich mit Funktechnik beschäftigen müssen.', 'project' => 'LoRaWAN'],
    ],
    'stations' => [
        ['year' => '2013', 'label' => 'Realschule', 'title' => 'Antoniusschule Thuine',
            'text' => ['Aufgewachsen bin ich in Lingen (Ems), wo auch meine Begeisterung für Technik entstanden ist. Angefangen hat alles mit kleinen Bastelprojekten und dem Interesse daran, zu verstehen, wie Computer und Software funktionieren. Mit der Zeit wurden daraus immer anspruchsvollere Projekte, die meinen beruflichen Weg geprägt haben.'],
            'facts' => ['Qualifizierter Realschulabschluss', 'Ø 2,4']],
        ['year' => '2018', 'label' => 'Praktikum', 'title' => 'Bockmeyer Zaun & Tor Systeme',
            'text' => ['Ein Praktikum im Einzelhandel und Landschaftsbau. In dem Jahr habe ich mich als wahrer Jack of All Trades entwickelt. Folgende Dinge gehörten zum Ende meines Praktikums zu meinen täglichen Aufgaben: ERP-Datenpflege, Posteingang, Preispflege, Einkauf, Lagerverwaltung, Kommissionierung von Waren, Inventur, Telefonzentrale, Tech-Support und Botengänge'],
            'facts' => ['960 Stunden']],
        ['year' => '2019', 'label' => 'Fachabitur', 'title' => 'BBS Lingen Wirtschaft',
            'text' => ['Nach meinem Realschulabschluss entschied ich mich für ein Fachabitur mit Schwerpunkt Informatik. Dort konnte ich meine ersten fundierten Kenntnisse in Softwareentwicklung und IT vertiefen. Dort wurde mir klar, dass ich meine berufliche Zukunft im Bereich der Softwareentwicklung sehe und bei der Digitalisierung im Emsland mitwirken wollte.'],
            'facts' => ['Schwerpunkt Informatik', 'Ø 2,3']],
        ['year' => '2020', 'label' => 'Studium', 'title' => 'Hochschule Osnabrück',
            'text' => ['Im Anschluss begann ich ein Studium der Wirtschaftsinformatik an der Hochschule Osnabrück. Während der sechs Semester konnte ich einen Großteil der Module erfolgreich absolvieren und sowohl technische als auch betriebswirtschaftliche Grundlagen aufbauen. Aus privaten Gründen entschied ich mich dazu, das Studium vorzeitig zu beenden und einen praxisorientierten Weg einzuschlagen.'],
            'facts' => ['Wirtschaftsinformatik', '6 Semester', '75 % der Leistungspunkte']],
        ['year' => '2024', 'label' => 'Ausbildung', 'title' => 'Geocapture GmbH',
            'text' => ['Mit der Ausbildung zum Fachinformatiker für Anwendungsentwicklung bei der Geocapture GmbH fand ich genau den Einstieg, den ich gesucht hatte. Dort arbeitete ich an realen Kundenprojekten und konnte meine Kenntnisse in der Webentwicklung, Datenbanken und modernen Entwicklungsprozessen kontinuierlich erweitern.'],
            'facts' => ['Fachinformatiker Anwendungsentwicklung', 'Prüfung: 2', 'Berufsschule Ø 1,8']],
        ['year' => 'Heute', 'label' => 'Heute', 'title' => 'Full-Stack Web-Developer',
            'text' => ['Nun bin ich ein ausgelernter Entwickler, der gespannt ist, was die Zukunft so bietet.'],
            'link' => ['href' => 'project_dashboard', 'label' => 'Woran ich seitdem gearbeitet habe →']],
    ],
    'hobbies' => [
        ['icon' => 'tabletop', 'title' => 'Tabletop-RPG', 'text' => 'Ich leite regelmäßig Tabletop-Abende mit Freunden als Dungeon Master, inklusive eigenem Infotainment-Setup mit Musik, Licht und Notizen zur Story sowie einer nonlinearen Erzählung.'],
        ['icon' => 'gamedev', 'title' => 'Godot & Gamedev', 'text' => 'Wenn ich mal nicht selber Videospiele spiele, entwickle ich auch mal an einem eigenen 2D-Spiel. Dies ist ein persönliches Herzensprojekt, an dem ich ab und zu weiterarbeite.'],
        ['icon' => 'kanu', 'title' => 'Kanu fahren', 'text' => 'Falls mal gutes Wetter ist, nehme ich oft mein Kanu für Touren an der Ems oder der Hase raus. Das ist auch oft verbunden mit Campen und Wandern.'],
        ['icon' => 'gaming', 'title' => 'Gaming', 'text' => 'Ich bin im Herzen noch ein Gamer und Videospiele waren mein erster Kontakt zur Informatik. Heute veranstalte ich noch regelmäßig LAN-Partys.'],
        ['icon' => 'bouldern', 'title' => 'Bouldern', 'text' => 'Seit der Eröffnung der Boulderhalle in Lingen versuche ich mindestens einmal im Monat eine neue Wand zu meistern oder meinen persönlichen Rekord zu brechen.'],
        ['icon' => 'video', 'title' => 'Videobearbeitung', 'text' => 'Entstand durch das Schneiden von LoL-Highlights und entwickelte sich zu einem Hobby, bei dem ich auch kreative Projekte für Bekannte unterstütze.'],
    ],
];
