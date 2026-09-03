<?php

return [
    [
        'title' => 'LoRaWAN',
        'category' => 'produktiv',
        'thumbnail' => '/pictures/projects/lorawan.png',
        'description' => 'Entwicklung einer LoRaWAN Kampagne und allgemeinen Strategie.',
        'content' => [
                [
                    'type' => 'heading',
                    'text' => 'Projektüberblick',
                ],
                [
                    'type' => 'text',
                    'text' => 'LoRaWAN war eines der größeren Projekte, bei denen ich mich zunächst intensiv mit der Technologie auseinandergesetzt und anschließend die Einführung in unser Produktportfolio begleitet habe.',
                ],
                [
                    'type' => 'text',
                    'text' => 'Dabei ging es nicht nur um die eigentliche Kommunikation zwischen Geräten und Netzwerk, sondern um das gesamte Ökosystem – von den Endgeräten über Gateways und Network Server bis hin zur Integration in unsere bestehenden Systeme.',
                ],
                [
                    'type' => 'heading',
                    'text' => 'Technische Umsetzung',
                ],
                [
                    'type' => 'text',
                    'text' => 'Ein Schwerpunkt lag auf der Frage, wie sich LoRaWAN sinnvoll in unsere bestehende Infrastruktur integrieren lässt. Dazu gehörten unter anderem Tests mit verschiedenen Sensoren, die Auswahl geeigneter Hardware und die Anbindung an unsere Backend-Systeme.',
                ],
                [
                    'type' => 'image',
                    'src' => '/pictures/projects/lorawan.png',
                    'alt' => 'LoRaWAN Projekt',
                ],
        ],
    ],
    [
        'title' => 'Raumverwaltung',
        'category' => 'produktiv',
        'thumbnail' => '/pictures/projects/rooms.png',
        'description' => 'Ein Raumverwaltungsystem gekoppelt mit E-Ink Displays.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Für die Raumverwaltung im Gebäude habe ich eine Lösung entwickelt, die E-Ink-Displays mit LoRaWAN nutzt. Dadurch konnten die Displays ohne zusätzliche Netzwerk- oder Stromverkabelung an den jeweiligen Räumen angebracht werden.',
            ],
            [
                'type' => 'text',
                'text' => 'Die Displays zeigen den aktuellen Buchungsstatus eines Raumes an. Ist der Raum nicht gebucht, werden zusätzlich die für den Raum zuständigen Ansprechpartner angezeigt.',
            ],
            [
                'type' => 'heading',
                'text' => 'Automatisierte Synchronisation',
            ],
            [
                'type' => 'text',
                'text' => 'Ein selbst entwickelter Job ruft regelmäßig die Raumbuchungen aus Microsoft Teams ab und synchronisiert diese mit der Raumverwaltung. Änderungen an bestehenden Buchungen werden dadurch automatisch auf den Displays berücksichtigt.',
            ],
            [
                'type' => 'heading',
                'text' => 'Manuelle Buchungen',
            ],
            [
                'type' => 'text',
                'text' => 'Zusätzlich zur Synchronisation mit Microsoft Teams habe ich eine manuelle Buchungsfunktion direkt im CRM integriert. Dadurch können Räume auch für Anwendungsfälle reserviert werden, die nicht über die normale Kalenderverwaltung abgebildet werden.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/rooms.png',
                'alt' => 'Raumverwaltung',
            ],
        ],
    ],
    [
        'title' => 'Klimasensor',
        'category' => 'privat',
        'thumbnail' => '/pictures/projects/klima.png',
        'description' => 'Ein Klimasensor mit Serveranbindung und historischer Datenspeicherung + GPS Ortung.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Der Klimasensor ist ein privates Hardware- und Softwareprojekt, mit dem ich mich mit der kontinuierlichen Erfassung und Verarbeitung von Umweltdaten beschäftigt habe.',
            ],
            [
                'type' => 'text',
                'text' => 'Der Sensor erfasst verschiedene Luftwerte und überträgt die Messdaten regelmäßig an einen eigenen Server. Dabei war mir besonders wichtig, die gesamte Kette selbst umzusetzen – von der Messung über die Übertragung bis zur Speicherung und Darstellung.',
            ],
            [
                'type' => 'heading',
                'text' => 'Server & Auswertung',
            ],
            [
                'type' => 'text',
                'text' => 'Die eingehenden Messwerte werden auf meinem eigenen, mit nginx betriebenen Server verarbeitet und gespeichert. Über ein Webinterface können sowohl die aktuellen Werte als auch historische Messverläufe betrachtet werden.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/klima.png',
                'alt' => 'Klimasensor',
            ],
        ],
    ],
    [
        'title' => 'Unity VR Rageroom',
        'category' => 'privat',
        'thumbnail' => '/pictures/projects/rage.png',
        'description' => 'Ein Unity Rageroom-Konzept, in dem sich Objekte per Hand oder mit Werkzeugen zerstören lassen. Fokus auf taktilem- und Audio-Feedback',
        'content' => [],
    ],
    [
        'title' => 'Godot 2D Planeten-Sumo',
        'category' => 'privat',
        'thumbnail' => '/pictures/projects/2d.png',
        'description' => 'In meiner Freizeit entwickle ich ein 2D-Spiel in Godot, bei dem man abstrakte Planeten in einem Sonnensystem steuert und versucht, die Gegner aus dem Sonnensystem zu knocken.',
        'content' => [],
    ],
    [
        'title' => 'Webinar',
        'category' => 'produktiv',
        'thumbnail' => '/pictures/projects/webinar.png',
        'description' => 'Ein System um Interaktionen, Anwesenheit und Engagement von Webinaren zu analysieren.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Für die Verwaltung unserer Webinare habe ich die GoTo-Webinar-API in unser CRM integriert. Ziel war es, Webinarinformationen nicht mehr getrennt von unseren bestehenden Kundendaten verwalten zu müssen.',
            ],
            [
                'type' => 'text',
                'text' => 'Geplante und bereits durchgeführte Webinare können zentral abgerufen und ausgewertet werden. Dadurch stehen sowohl die Veranstaltungsdaten als auch die zugehörigen Teilnehmer- und Anmeldedaten innerhalb unserer bestehenden Systeme zur Verfügung.',
            ],
            [
                'type' => 'heading',
                'text' => 'Datenverknüpfung',
            ],
            [
                'type' => 'text',
                'text' => 'Ein wichtiger Bestandteil war die Zuordnung der Webinarteilnehmer zu unseren CRM-Stammdaten. Dadurch konnten Informationen aus unterschiedlichen Quellen miteinander verbunden und für weitere Auswertungen genutzt werden.',
            ],
            [
                'type' => 'heading',
                'text' => 'Auswertungen',
            ],
            [
                'type' => 'text',
                'text' => 'Auf Basis der zusammengeführten Daten lassen sich unter anderem Branchen, Ansprechpartner und Teilnahmeverhalten analysieren. Auch Antworten aus Webinarumfragen können in die Auswertung einbezogen werden.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/webinar.png',
                'alt' => 'Webinar-Verwaltung',
            ],
        ],
    ],
    [
        'title' => 'Werbekampagnen Leads Auswertung',
        'category' => 'produktiv',
        'thumbnail' => '/pictures/projects/leads.png',
        'description' => 'Eine Grundlage zur internen Auswertung von Leads und Werbekampgnen',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Für die Auswertung unserer Website-Anfragen habe ich ein System entwickelt, das eingehende Leads automatisch analysiert und den jeweiligen Marketingaktivitäten zuordnet.',
            ],
            [
                'type' => 'text',
                'text' => 'Dabei werden unter anderem die Herkunft der Anfrage, verwendete Kampagneninformationen und weitere Daten ausgewertet. So lässt sich nachvollziehen, über welche Kanäle und Kampagnen neue Anfragen entstehen.',
            ],
            [
                'type' => 'heading',
                'text' => 'Traffic & Kampagnen',
            ],
            [
                'type' => 'text',
                'text' => 'Ein wesentlicher Bestandteil ist die Unterscheidung zwischen organischem und bezahltem Traffic. Bezahlte Leads können anschließend konkreten Werbekampagnen zugeordnet werden.',
            ],
            [
                'type' => 'text',
                'text' => 'Die Kampagnen selbst können ebenfalls zentral verwaltet werden. Dadurch stehen Kampagneninformationen und die daraus entstandenen Leads in einer gemeinsamen Auswertung zur Verfügung.',
            ],
            [
                'type' => 'heading',
                'text' => 'Technische Umsetzung',
            ],
            [
                'type' => 'text',
                'text' => 'Die Anwendung wurde mit Angular umgesetzt und als eigenständige Oberfläche für die Analyse und Verwaltung der Kampagnendaten entwickelt.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/leads.png',
                'alt' => 'Lead-Auswertung',
            ],
        ],
    ],
    [
        'title' => 'Flottenmanagement-Software',
        'category' => 'privat',
        'thumbnail' => '/pictures/projects/fleet.png',
        'description' => 'Mittlerweile inkative Software für einen lokalen Eventservice-Dienst. Kern war eine vereinfachte Kommunikation zwischen Planer und Fahrer',
        'content' => [],
    ],
    [
        'title' => 'Finanzbuchhaltung-Anbindung',
        'category' => 'produktiv',
        'thumbnail' => '/pictures/projects/select.png',
        'description' => 'Für Wartungsverträge und Finanzbuchhaltung verwenden wir Selectline. Ich musste die Selectline-Datenbank enger in unser CRM integrieren.',
        'content' => [],
    ],
    [
        'title' => 'Kanban-App',
        'category' => 'privat',
        'thumbnail' => '/pictures/projects/kanban.png',
        'description' => 'Ein kleiner Versuch eine eigene kleine Kanban App für eine fiktives Unternehmen zu entwickeln.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Die Kanban-App ist aus dem Wunsch entstanden, die Möglichkeiten einer OpenAI-Anbindung praktisch auszuprobieren. Statt nur eine einzelne API-Funktion zu testen, habe ich die Anbindung direkt in eine vollständige Anwendung integriert.',
            ],
            [
                'type' => 'text',
                'text' => 'Tickets können über Prompts erzeugt und anschließend wie gewohnt in einem Kanban-Board organisiert werden. Dabei können unter anderem Titel, Beschreibung und weitere Ticketinformationen automatisch aus einer Anfrage erzeugt werden.',
            ],
            [
                'type' => 'heading',
                'text' => 'Benutzer & Rollen',
            ],
            [
                'type' => 'text',
                'text' => 'Die Anwendung besitzt ein Rollenmodell mit Benutzern, Managern und Administratoren. Manager können zusätzlich Projekte und weitere Einstellungen verwalten, während Administratoren die übergeordneten Bereiche der Anwendung kontrollieren können.',
            ],
            [
                'type' => 'heading',
                'text' => 'CI/CD',
            ],
            [
                'type' => 'text',
                'text' => 'Das Projekt habe ich außerdem genutzt, um eine halbautomatische CI/CD-Pipeline aufzubauen und verschiedene Möglichkeiten für automatisierte Builds, Tests und Deployments auszuprobieren.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/kanban.png',
                'alt' => 'Kanban-App',
            ],
        ],
    ],
    [
        'title' => 'Tabletop Infotainment-System',
        'category' => 'privat',
        'thumbnail' => '/pictures/projects/dg.png',
        'description' => 'Als "Dungeon Master ist es wichtig, meinen Spielern eine narrative anzubieten. Dafür habe ich ein Infotainment System mit  Licht Musik und Notizen erschaffen.',
        'content' => [],
    ],
    [
        'title' => 'Adress-Auswertungstool',
        'category' => 'produktiv',
        'thumbnail' => '/pictures/projects/auswertung.png',
        'description' => 'Damit der Vertrieb eigenständig Auswertungen zu Adressdaten erstellen kann, habe ich ein Tool mit konfigurierbaren Filtern und frei wählbaren Ausgabeformaten entwickelt.',
        'content' => [],
    ],
    [
        'title' => 'GitLab-Anbindung',
        'category' => 'produktiv',
        'thumbnail' => '/pictures/projects/gitlab.png',
        'description' => 'Tickets im Kanban-Board sollten basierend auf GitLab-Merge-Requests automatisch ihren Status ändern.',
        'content' => [],
    ],
    [
        'title' => 'Adress-Cluster',
        'category' => 'produktiv',
        'thumbnail' => '/pictures/projects/cluster.png',
        'description' => 'Ein Tool zur Management von Beziehungen zwischen Adressen und Netzen',
        'content' => [
                [
                    'type' => 'heading',
                    'text' => 'Projektüberblick',
                ],
                [
                    'type' => 'text',
                    'text' => 'Ich habe ein Feature im CRM entwickelt, das Beziehungen zwischen Adressen abbildet.',
                ],
                [
                    'type' => 'image',
                    'src' => '/pictures/projects/adressverwaltung.PNG',
                    'alt' => 'Addressen',
                ],
                [
                    'type' => 'text',
                    'text' => 'Besonders wertvoll für Unternehmensgruppen mit vielen Tochtergesellschaften. Darauf aufbauend habe ich ein System für konsistente Preiskommunikation geschaffen, damit unterschiedliche Adressen desselben Clusters nicht versehentlich unterschiedliche Preise erhalten.',
                ],
                [
                    'type' => 'text',
                    'text' => 'Darauf aufbauend habe ich ein System für konsistente Preiskommunikation geschaffen, damit unterschiedliche Adressen desselben Clusters nicht versehentlich unterschiedliche Preise erhalten.',
                ],
                [
                    'type' => 'image',
                    'src' => '/pictures/projects/sonderkonditionsverwaltung.PNG',
                    'alt' => 'LoRaWAN Projekt',
                ],
        ],
    ],
    [
        'title' => 'GitLab Pipeline',
        'category' => 'privat',
        'thumbnail' => '/pictures/projects/cicd.png',
        'description' => 'Eine eigene Pipeline von Development bis Deployment',
        'content' => [],
    ],
    [
        'title' => 'Passwort-Handling',
        'category' => 'produktiv',
        'thumbnail' => '/pictures/projects/key.png',
        'description' => 'Ich habe den bestehenden Passwort-Reiter um ein paar Funktionen erweitert. Sicherheit war hier besonders wichtig.',
        'content' => [],
    ],
];
