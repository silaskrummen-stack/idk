<?php

return [
    [
        'title' => 'LAN-Party Moderation',
        'category' => 'privat',
        'thumbnail' => '/pictures/projects/lan.jpg',
        'description' => 'Ein interaktiver Punktestand für meine LAN-Partys: zwei Teams, Spiele, Quiz und Abstimmungen live auf dem großen Bildschirm – moderiert über ein Admin-Panel, mitgespielt am Handy.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Ich veranstalte regelmäßig LAN-Partys und wollte die Punktzahl dabei interaktiver gestalten. Zwei Teams treten den ganzen Abend in verschiedenen Spielen gegeneinander an, und wer am Ende die meisten Punkte hat, gewinnt.',
            ],
            [
                'type' => 'text',
                'text' => 'Ein Bildschirm ist für alle auf der LAN-Party einsehbar und zeigt Punktestand, das aktuelle Spiel, Timer und Durchsagen. Währenddessen bedient eine andere Person das Admin-Panel und moderiert darüber den Abend: Punkte vergeben, Spiele starten, Ergebnisse eintragen.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/lan-board.jpg',
                'alt' => 'Punktestand der beiden Teams mit dem nächsten Spiel',
            ],
            [
                'type' => 'heading',
                'text' => 'Mitmachen am Handy',
            ],
            [
                'type' => 'text',
                'text' => 'Weil alles online läuft, kann die Verwaltung jede Person mit Zugang übernehmen, von jedem Gerät aus. Die Gäste scannen einen QR-Code auf dem Bildschirm und stimmen direkt am Handy ab, ganz ohne App oder Login.',
            ],
            [
                'type' => 'text',
                'text' => 'So entstanden mehrere Runden: ein Trivia-Quiz, in dem jedes Team eine Antwort einloggt, „Guess the Game“, bei dem Spiele anhand von Bildern erraten werden und auch Tippfehler oder andere Schreibweisen zählen, „Guess the Price“ mit Eingaben wie „430 Mio“, eine Award-Verleihung per Abstimmung und ein Glücksrad, das aus jedem Team zufällig einen Spieler zieht.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/lan-quiz.jpg',
                'alt' => 'Trivia-Quiz mit vier Antworten und QR-Code zum Mitraten',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/lan-price.jpg',
                'alt' => 'Guess the Price: Auflösung mit den Tipps beider Teams',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/lan-wheel.jpg',
                'alt' => 'Zwei Glücksräder, die pro Team einen Spieler auswählen',
            ],
            [
                'type' => 'heading',
                'text' => 'Admin-Panel',
            ],
            [
                'type' => 'text',
                'text' => 'Das Admin-Panel ist für das Handy optimiert. Dort steuert man, was auf dem Bildschirm zu sehen ist, sieht die Antworten der Teams schon vor der Auflösung und kann Ergebnisse jederzeit korrigieren. Die Punkte passen sich dabei automatisch an.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/lan-admin.jpg',
                'alt' => 'Admin-Panel während einer laufenden Quizfrage',
            ],
            [
                'type' => 'heading',
                'text' => 'Technische Umsetzung',
            ],
            [
                'type' => 'text',
                'text' => 'Die Anwendung ist Teil dieses Symfony-Projekts (PHP 8.3), das Frontend ist in Vanilla JavaScript und CSS umgesetzt. Der Spielstand liegt als JSON-Datei mit Datei-Lock vor, Bildschirm, Admin-Panel und Handys synchronisieren sich per Polling. Zeitabläufe wie Countdowns und Auflösungen steuert der Server, damit alle Geräte dasselbe zeigen. Lösungen und Bilder liefert er erst aus, wenn sie aufgedeckt werden, damit niemand vorher nachschauen kann.',
            ],
            [
                'type' => 'heading',
                'text' => 'Zusammenarbeit mit Claude Code',
            ],
            [
                'type' => 'text',
                'text' => 'Das Projekt ist einer meiner ersten Tests mit Claude Code (Opus 5.5) in einem Symfony-Framework. Es war eine gute Symbiose: Ich habe die technischen Anforderungen, das Styling, die Infrastruktur, das Shipping und die Moderation übernommen, während sich Claude Code um die Umsetzung gekümmert hat.',
            ],
            [
                'type' => 'heading',
                'text' => 'Fazit',
            ],
            [
                'type' => 'text',
                'text' => 'Die LAN-Party war ein Erfolg, und auch viele meiner nicht-technischen Freunde waren davon beeindruckt.',
            ],
        ],
    ],
    [
        'title' => 'CIV-Abend Spielleitung',
        'category' => 'privat',
        'thumbnail' => '/pictures/projects/civ.jpg',
        'description' => 'Mein erster Versuch mit Vibecoding: ein Live-Punktestand für der Strategieklassiker Sid Meier's Civilization VI mit acht Spielern.',
        // Mehrfachklick auf die Karte: 3× öffnet den Bildschirm, 5× die Spielleitung (siehe CivController)
        'clickLinks' => [3 => '/portfolio/civ/display', 5 => '/portfolio/civ/admin'],
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Für einen Civilization-VI-Abend mit acht Spielern habe ich eine Anzeige gebaut, die den ganzen Abend auf einem großen Bildschirm läuft: Punktestand aller Spieler, die aktuelle Ära mit ihrem Gipfel und die Regeln, die in dieser Ära gelten. Gesteuert wird alles über ein separates Admin-Panel, das für das Handy optimiert ist.',
            ],
            [
                'type' => 'text',
                'text' => 'Das Projekt war mein erster Versuch mit Vibecoding. Weil ich es wirklich stresstesten wollte, habe ich kein Framework vorgegeben und allgemein sehr hands-off gearbeitet. Meine Vorgaben beschränkten sich auf das Styling, den Docker-Container und die Infrastruktur sowie eine Erläuterung, der Regeln von CIV, den Rest habe ich der KI überlassen.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/civ-start.jpg',
                'alt' => 'Startbildschirm des CIV-Abends mit Regeln und den acht Reichen',
            ],
            [
                'type' => 'heading',
                'text' => 'Ären, Gipfel & Regeln',
            ],
            [
                'type' => 'text',
                'text' => 'Das Spiel läuft über neun Ären. Zu jeder neuen Ära treffen sich alle Reiche zu einem Gipfel mit eigenen Regeln, die auf dem Bildschirm stehen. Kurz nach jedem Ära-Wechsel erscheint automatisch eine Gipfelaktion groß auf dem Bildschirm, zum Beispiel eine Vorstellungsrunde der Völker oder eine Abstimmung.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/civ-board.jpg',
                'alt' => 'Punktestand und Gipfelregeln',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/civ-gipfelaktion.jpg',
                'alt' => 'Gipfelaktion „Vorstellung“',
            ],
            [
                'type' => 'heading',
                'text' => 'Events & Inszenierung',
            ],
            [
                'type' => 'text',
                'text' => 'Über Knöpfe im Admin-Panel lassen sich Ereignisse auslösen: Kriegsalarm, Friedensschluss, öffentliche Denunziationen, ABC-Alarm, der Weltkongress oder Errungenschaften wie die erste Weltumrundung, der erste Milliardär oder der erste Geheimagent. Jedes Event hat eine eigene Animation.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/civ-events-diplomatie.jpg',
                'alt' => 'Events: Kriegsalarm, Frieden, Denunziation und ABC-Alarm',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/civ-events-errungenschaften.jpg',
                'alt' => 'Events: Erste Weltumrundung, Milliardär, Geheimagent und Weltkongress',
            ],
            [
                'type' => 'heading',
                'text' => 'Watcher-Mod: das Spiel meldet sich selbst',
            ],
            [
                'type' => 'text',
                'text' => 'Ich konnte sogar einen Watcher-Mod umsetzen, der die Logs des laufenden Spiels liest und die wichtigen Ereignisse an die Anzeige meldet, vor allem Kriege, Friedensschlüsse, Spionage und Missionen. Ein kleines Skript neben Civilization VI schickt sie an den Server, abgesichert über einen Schlüssel aus dem Admin-Panel. So muss die Spielleitung nicht jedes Ereignis selbst mitbekommen und auslösen.',
            ],
            [
                'type' => 'heading',
                'text' => 'Abstimmen per QR-Code',
            ],
            [
                'type' => 'text',
                'text' => 'Abstimmungen laufen über die Handys der Spieler: Auf dem Bildschirm erscheint ein QR-Code, der direkt zur Abstimmungsseite führt, ganz ohne Login. Möglich sind geheime und öffentliche Abstimmungen sowie ein anonymer Briefkasten. Geantwortet wird mit Ja und Nein, mit den Namen der Mitspieler oder mit eigenen Antwortmöglichkeiten. Bei öffentlichen Abstimmungen zeigt der Bildschirm am Ende, wer wofür gestimmt hat. Ein analoges Voting-System brauchten wir dadurch nicht mehr.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/civ-vote.jpg',
                'alt' => 'Öffentliche Abstimmung mit QR-Code',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/civ-vote-result.jpg',
                'alt' => 'Ergebnis einer öffentlichen Abstimmung',
            ],
            [
                'type' => 'heading',
                'text' => 'Spielleitung',
            ],
            [
                'type' => 'text',
                'text' => 'Im Admin-Panel lassen sich Punkte, Ären, Gipfelregeln und Gipfelaktionen live ändern, ohne dass auf dem Bildschirm etwas davon zu sehen ist. Notizen sieht nur die Spielleitung, und Events lösen sich mit einem Tipp aus.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/civ-admin-events.jpg',
                'alt' => 'Admin-Panel: Events, An/Aus-Anzeigen und erste Errungenschaften',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/civ-admin-gipfel.jpg',
                'alt' => 'Admin-Panel: Gipfel, Regeln und Gipfelaktionen bearbeiten',
            ],
            [
                'type' => 'heading',
                'text' => 'Technische Umsetzung',
            ],
            [
                'type' => 'text',
                'text' => 'Die Anwendung läuft als eigener Docker-Container mit PHP 8.3 und Apache, das Frontend ist ohne Framework in Vanilla JavaScript und CSS umgesetzt. Der Spielstand liegt als JSON-Datei mit Datei-Lock vor, Bildschirm und Admin-Panel synchronisieren sich per Polling.',
            ],
            [
                'type' => 'text',
                'text' => 'Online hängt der Container hinter demselben nginx wie dieses Portfolio. Der Zugriff auf die beiden Subdomains wird per auth_request gegen den Symfony-Login dieser Seite geprüft, der Bildschirm und die Spielleitung haben dabei getrennte Rollen.',
            ],
            [
                'type' => 'heading',
                'text' => 'Erfahrungen mit Vibecoding',
            ],
            [
                'type' => 'text',
                'text' => 'Von den Ergebnissen war ich sehr überrascht: Die KI hat die Spiellogik gut erfasst und von sich aus umfassende Fallbacks eingebaut, etwa eingebaute Töne, wenn keine Audiodatei vorhanden ist. Die Wartbarkeit ließ dagegen deutlich zu wünschen übrig. Ohne vorgegebene Struktur ist der Code schnell gewachsen und schwer zu überblicken.',
            ],
            [
                'type' => 'heading',
                'text' => 'Fazit',
            ],
            [
                'type' => 'text',
                'text' => 'Der Abend war sehr cool. Durch das System wurde die Kommunikation zwischen den Spielern gefördert, und die Punkte waren für alle übersichtlicher. Mir hat das Ganze auch viel Spaß gemacht, weil ich mich in meiner Rolle als Storyteller und Moderator sehr wohlgefühlt habe.',
            ],
        ],
    ],
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
        'thumbnail' => '/pictures/projects/rooms.jpg',
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
        'thumbnail' => '/pictures/projects/klima.jpg',
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
        'thumbnail' => '/pictures/projects/rage.jpg',
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
        'thumbnail' => '/pictures/projects/dg.jpg',
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
        'thumbnail' => '/pictures/projects/key.jpg',
        'description' => 'Ich habe den bestehenden Passwort-Reiter um ein paar Funktionen erweitert. Sicherheit war hier besonders wichtig.',
        'content' => [],
    ],
];
