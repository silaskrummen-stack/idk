<?php

return [
    [
        'title' => 'LAN-Party Moderation',
        'featured' => true,
        'category' => 'privat',
        'date' => '10/2026',
        'thumbnail' => '/pictures/projects/lan.jpg',
        'description' => 'Ein interaktiver Punktestand für meine LAN-Partys: zwei Teams, Spiele, Quiz und Abstimmungen live auf dem großen Bildschirm, moderiert über ein Admin-Panel, mitgespielt am Handy.',
        // Eigene Seite statt Modal, siehe ProjectDashboard/Controller
        'route' => 'project_lan_party',
    ],
    [
        'title' => 'CIV-Abend Spielleitung',
        'featured' => true,
        'category' => 'privat',
        'date' => '09/2026',
        'thumbnail' => '/pictures/projects/civ.jpg',
        'description' => 'Mein erster Versuch mit Vibecoding: ein Live-Punktestand für den Strategieklassiker Sid Meier’s Civilization VI mit acht Spielern.',
        // Mehrfachklick auf die Karte: 3× öffnet den Bildschirm, 5× die Spielleitung (siehe CivController)
        'clickLinks' => [3 => '/portfolio/civ/display', 5 => '/portfolio/civ/admin'],
        // Eigene Seite statt Modal, siehe ProjectDashboard/Controller
        'route' => 'project_civ_abend',
    ],
    [
        'title' => 'krummen.de',
        'featured' => true,
        'category' => 'produktiv',
        'date' => '09/2026',
        'thumbnail' => '/pictures/projects/krummen.jpg',
        'description' => 'Die neue Website für Eventservice & Fotostudio Krummen in Lingen. In zwei Wochen vor der Hochzeitsmesse mit WordPress bei IONOS umgesetzt.',
        // Eigene Seite statt Modal, siehe ProjectDashboard/Controller
        'route' => 'project_krummen',
    ],
    [
        'title' => 'LoRaWAN',
        'category' => 'produktiv',
        'date' => '07/2026',
        'thumbnail' => '/pictures/projects/lorawan.png',
        'description' => 'Einführung von LoRaWAN in unser Produktportfolio: von der Technologie-Evaluierung über Hardwaretests bis zur Anbindung der Sensordaten und einer Kampagne für unsere Kunden.',
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
                'text' => 'Dabei ging es nicht nur um die eigentliche Kommunikation zwischen Geräten und Netzwerk, sondern um das gesamte Ökosystem, von den Endgeräten über Gateways und Network Server bis hin zur Integration in unsere bestehenden Systeme.',
            ],
            [
                'type' => 'heading',
                'text' => 'Warum LoRaWAN',
            ],
            [
                'type' => 'text',
                'text' => 'LoRaWAN überträgt sehr kleine Datenmengen über große Entfernungen und braucht dafür kaum Energie. Ein Sensor, der alle paar Minuten einen Zählerstand, einen Füllstand oder das Raumklima meldet, läuft so jahrelang mit einer Batterie. Gerade im ländlichen Emsland, wo Mobilfunk nicht überall zuverlässig ist, und in großen Gebäuden ohne Netzwerkanschluss an jeder Ecke ist das ein echter Vorteil.',
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
                'type' => 'text',
                'text' => 'Jeder Hersteller kodiert seine Messwerte anders. Für die getesteten Sensoren habe ich deshalb Payload-Decoder geschrieben, die die Rohdaten in einheitliche Messwerte übersetzen. Ein eigener Endpunkt nimmt die Daten vom Network Server per Webhook entgegen, prüft sie und ordnet sie den passenden Geräten und Kunden im CRM zu.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/lorawan.png',
                'alt' => 'LoRaWAN-Projekt',
            ],
            [
                'type' => 'heading',
                'text' => 'Erkenntnisse aus den Tests',
            ],
            [
                'type' => 'text',
                'text' => 'Die Praxis sah teilweise anders aus als das Datenblatt. In Gebäuden mit Stahlbeton sinkt die Reichweite deutlich, und die Platzierung des Gateways war oft wichtiger als die Wahl des Sensors. Außerdem muss man bei jedem Gerät zwischen Sendeintervall und Batterielaufzeit abwägen. Weil das Frequenzband in Europa nur eine begrenzte Sendezeit erlaubt, sind Nachrichten zurück an die Geräte knapp und müssen gut geplant werden.',
            ],
            [
                'type' => 'heading',
                'text' => 'Strategie & Kampagne',
            ],
            [
                'type' => 'text',
                'text' => 'Neben der Technik habe ich die Strategie mitentwickelt, mit der wir LoRaWAN unseren Kunden anbieten: welche Anwendungsfälle wir zuerst angehen, welche Hardware wir empfehlen und wie wir die Lösung erklären, ohne dass Kunden sich mit Funktechnik beschäftigen müssen. Daraus ist eine Kampagne mit Infomaterial und konkreten Beispielen aus der Praxis entstanden.',
            ],
            [
                'type' => 'text',
                'text' => 'Der erste interne Anwendungsfall war die Raumverwaltung mit E-Ink-Displays, die ohne jede Verkabelung an den Türen hängen.',
            ],
        ],
    ],
    [
        'title' => 'Raumverwaltung',
        'category' => 'produktiv',
        'date' => '06/2026',
        'thumbnail' => '/pictures/projects/rooms.jpg',
        'description' => 'Ein Raumverwaltungssystem mit E-Ink-Displays an den Türen, die per LoRaWAN ohne Verkabelung den aktuellen Buchungsstatus aus Microsoft Teams anzeigen.',
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
                'text' => 'Warum E-Ink',
            ],
            [
                'type' => 'text',
                'text' => 'E-Ink-Displays brauchen nur Strom, wenn sich ihr Inhalt ändert, und sind auch ohne Hintergrundbeleuchtung gut lesbar. Zusammen mit LoRaWAN halten die Batterien dadurch viele Monate. Weil LoRaWAN nur wenige Bytes an ein Gerät schicken kann, sende ich keine fertigen Bilder, sondern einen kompakten Datensatz mit Status, Zeitfenster und Ansprechpartner. Das Display füllt damit eine feste Vorlage und wird nur aktualisiert, wenn sich wirklich etwas ändert.',
            ],
            [
                'type' => 'heading',
                'text' => 'Automatisierte Synchronisation',
            ],
            [
                'type' => 'text',
                'text' => 'Ein selbst entwickelter Job ruft regelmäßig die Raumbuchungen aus Microsoft Teams über die Microsoft Graph API ab und synchronisiert sie mit der Raumverwaltung. Änderungen an bestehenden Buchungen werden dadurch automatisch auf den Displays berücksichtigt.',
            ],
            [
                'type' => 'text',
                'text' => 'Die eigentlichen Stolpersteine waren dabei nicht die normalen Termine, sondern abgesagte Besprechungen, verschobene Einzeltermine aus Serien und Buchungen, die kurzfristig verlängert werden. Für diese Fälle gleicht der Job nicht nur neue Termine ab, sondern auch, was seit dem letzten Lauf weggefallen ist.',
            ],
            [
                'type' => 'heading',
                'text' => 'Manuelle Buchungen',
            ],
            [
                'type' => 'text',
                'text' => 'Zusätzlich zur Synchronisation mit Microsoft Teams habe ich eine manuelle Buchungsfunktion direkt im CRM integriert. Dadurch können Räume auch für Anwendungsfälle reserviert werden, die nicht über die normale Kalenderverwaltung abgebildet werden. Überschneidungen mit bestehenden Buchungen werden vor dem Speichern geprüft.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/rooms.png',
                'alt' => 'Raumverwaltung',
            ],
            [
                'type' => 'heading',
                'text' => 'Ergebnis',
            ],
            [
                'type' => 'text',
                'text' => 'Ausgedruckte Raumpläne an den Türen sind überflüssig geworden. Wer über den Flur geht, sieht sofort, ob ein Raum frei ist und an wen man sich wenden kann, und spontane Besprechungen landen nicht mehr in bereits gebuchten Räumen.',
            ],
        ],
    ],
    [
        'title' => 'Klimasensor',
        'category' => 'privat',
        'date' => '04/2026',
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
        'date' => '04/2026',
        'thumbnail' => '/pictures/projects/rage.jpg',
        'description' => 'Ein Unity-Rageroom-Konzept, in dem sich Objekte per Hand oder mit Werkzeugen zerstören lassen. Der Fokus liegt auf taktilem und akustischem Feedback.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Der VR-Rageroom ist ein Konzept in Unity: ein Raum voller Geschirr, Flaschen, Möbel und alter Elektronik, den man in VR mit bloßen Händen oder mit Werkzeugen wie Hammer und Baseballschläger auseinandernehmen darf. Das Ziel war nicht möglichst viel Inhalt, sondern dass sich jeder einzelne Schlag richtig anfühlt.',
            ],
            [
                'type' => 'heading',
                'text' => 'Zerstörung',
            ],
            [
                'type' => 'text',
                'text' => 'Jedes zerstörbare Objekt liegt zusätzlich als vorab zerlegte Version mit einzelnen Bruchstücken vor. Trifft ein Schlag stark genug, wird das ganze Objekt gegen seine Bruchstücke ausgetauscht, die die Wucht des Treffers übernehmen. Ob etwas bricht, hängt von der Geschwindigkeit und dem Gewicht des Werkzeugs ab, sodass ein Hammer anders wirkt als die flache Hand.',
            ],
            [
                'type' => 'heading',
                'text' => 'Taktiles und akustisches Feedback',
            ],
            [
                'type' => 'text',
                'text' => 'Die Controller vibrieren abhängig von der Stärke des Aufpralls, und jedes Material hat eigene Geräusche für Glas, Holz, Keramik und Metall. Damit zwanzig zerbrochene Teller nicht zwanzigmal gleich klingen, variieren Tonhöhe und Lautstärke bei jedem Treffer leicht. Gerade dieses Zusammenspiel aus Vibration und Klang macht den Unterschied zwischen einem Effekt und einem Gefühl.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/rage.png',
                'alt' => 'VR-Rageroom in Unity',
            ],
            [
                'type' => 'heading',
                'text' => 'Herausforderungen',
            ],
            [
                'type' => 'text',
                'text' => 'In VR gibt es keine echten Widerstände. Damit Werkzeuge trotzdem Gewicht haben, folgen sie der Hand über physikalische Gelenke, statt direkt an ihr zu kleben. Ein schwerer Hammer schwingt so etwas nach. Außerdem braucht VR eine konstant hohe Bildrate, sonst wird einem schnell schlecht. Bruchstücke verschwinden deshalb nach einiger Zeit wieder, damit die Physik auch nach einer ausgiebigen Zerstörungsorgie flüssig bleibt.',
            ],
            [
                'type' => 'heading',
                'text' => 'Fazit',
            ],
            [
                'type' => 'text',
                'text' => 'Das Projekt ist bisher ein Konzept, hat mir aber gezeigt, wie sehr das Spielgefühl an kleinen Details hängt: einem kurzen Ruckeln im Controller, einem leicht anderen Klang oder ein paar Millisekunden Verzögerung.',
            ],
        ],
    ],
    [
        'title' => 'Godot 2D Planeten-Sumo',
        'category' => 'privat',
        'date' => '03/2026',
        'thumbnail' => '/pictures/projects/2d.png',
        'description' => 'In meiner Freizeit entwickle ich ein 2D-Spiel in Godot, bei dem man abstrakte Planeten in einem Sonnensystem steuert und versucht, die Gegner aus dem Sonnensystem zu knocken.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Planeten-Sumo ist ein lokales Mehrspieler-Spiel in Godot. Jeder Spieler steuert einen abstrakten Planeten in einem Sonnensystem und versucht, die anderen aus dem System zu stoßen. Wer als Letzter übrig bleibt, gewinnt die Runde.',
            ],
            [
                'type' => 'heading',
                'text' => 'Gravitation als Spielmechanik',
            ],
            [
                'type' => 'text',
                'text' => 'Die Sonne in der Mitte zieht alle Planeten an, und man kann nie einfach stehen bleiben. Wer nicht aufpasst, wird in eine enge Umlaufbahn gezogen oder schießt aus dem System hinaus. Bei Zusammenstößen zählen Masse und Geschwindigkeit: Ein schneller kleiner Planet kann einen großen aus der Bahn werfen, wenn er im richtigen Moment trifft.',
            ],
            [
                'type' => 'heading',
                'text' => 'Steuerung und Fähigkeiten',
            ],
            [
                'type' => 'text',
                'text' => 'Neben der normalen Steuerung hat jeder Planet einen kräftigen Schub mit Abklingzeit und kann sich kurzzeitig schwerer machen, um einen Treffer abzufangen. Dazu kommen Ereignisse wie Kometen, die quer durch das System fliegen, und ein Asteroidengürtel, der Planeten abbremst.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/2d.png',
                'alt' => 'Planeten-Sumo in Godot',
            ],
            [
                'type' => 'heading',
                'text' => 'Technische Umsetzung',
            ],
            [
                'type' => 'text',
                'text' => 'Das Spiel ist in Godot 4 mit GDScript umgesetzt. Die Planeten sind Physikkörper, auf die in jedem Physik-Schritt eine selbst berechnete Anziehungskraft der Sonne wirkt. Bis zu vier Spieler spielen gleichzeitig an einem Bildschirm, jeder mit eigenem Controller.',
            ],
            [
                'type' => 'heading',
                'text' => 'Stand',
            ],
            [
                'type' => 'text',
                'text' => 'Planeten-Sumo ist ein Herzensprojekt, an dem ich immer wieder weiterarbeite. Getestet wird auf unseren LAN-Partys, wo sich schnell zeigt, welche Mechanik Spaß macht und welche nur frustriert.',
            ],
        ],
    ],
    [
        'title' => 'Webinar',
        'category' => 'produktiv',
        'date' => '02/2026',
        'thumbnail' => '/pictures/projects/webinar.png',
        'description' => 'Ein System, um Anmeldungen, Anwesenheit und Engagement von Webinaren direkt im CRM auszuwerten.',
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
                'text' => 'Technische Umsetzung',
            ],
            [
                'type' => 'text',
                'text' => 'Die Anbindung authentifiziert sich per OAuth 2.0 und importiert Webinare, Anmeldungen und Teilnahmen in regelmäßigen Abständen. Dabei musste ich die Paginierung und die Limits der API berücksichtigen, damit auch große Webinare vollständig und ohne abgebrochene Importe übernommen werden. Ein erneuter Import erzeugt keine doppelten Datensätze, sondern aktualisiert die vorhandenen.',
            ],
            [
                'type' => 'heading',
                'text' => 'Datenverknüpfung',
            ],
            [
                'type' => 'text',
                'text' => 'Ein wichtiger Bestandteil war die Zuordnung der Webinarteilnehmer zu unseren CRM-Stammdaten. Teilnehmer werden zuerst über ihre E-Mail-Adresse einem Ansprechpartner zugeordnet, ansonsten über die Domain einem Unternehmen. Was sich nicht eindeutig zuordnen lässt, landet in einer Liste zur manuellen Prüfung, statt falsch verknüpft zu werden.',
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
            [
                'type' => 'heading',
                'text' => 'Ergebnis',
            ],
            [
                'type' => 'text',
                'text' => 'Der Vertrieb sieht jetzt direkt beim Kontakt, an welchen Webinaren jemand teilgenommen hat und wie lange. Besonders wertvoll ist die Liste derjenigen, die sich angemeldet haben, aber nicht dabei waren: Sie bekommen gezielt die Aufzeichnung und ein Gesprächsangebot. Übernommen werden dabei nur die Daten, die für diese Auswertungen wirklich gebraucht werden.',
            ],
        ],
    ],
    [
        'title' => 'Werbekampagnen Leads Auswertung',
        'category' => 'produktiv',
        'date' => '02/2026',
        'thumbnail' => '/pictures/projects/leads.png',
        'description' => 'Eine Grundlage zur internen Auswertung von Leads und Werbekampagnen.',
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
        'date' => '10/2025',
        'thumbnail' => '/pictures/projects/fleet.png',
        'description' => 'Mittlerweile inaktive Software für einen lokalen mobilen Gastronomie-Dienst. Kern war eine vereinfachte Kommunikation zwischen Planer und Fahrer.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Für einen lokalen mobilen Gastronomie-Dienst habe ich eine Software entwickelt, die die Planung der Fahrzeuge und die Kommunikation zwischen Planer und Fahrern vereinfacht. Vorher lief vieles über Anrufe und Chatnachrichten, und wichtige Informationen gingen zwischen den Einsätzen verloren.',
            ],
            [
                'type' => 'heading',
                'text' => 'Planung und Touren',
            ],
            [
                'type' => 'text',
                'text' => 'Der Planer legt Touren an und bestimmt Fahrzeug, Fahrer, Einsatzort, Zeiten und was geladen werden muss. Die Fahrer sehen auf dem Handy nur ihre eigenen Touren mit allen Informationen, die sie unterwegs brauchen, inklusive Adresse und Ansprechpartner vor Ort.',
            ],
            [
                'type' => 'heading',
                'text' => 'Kommunikation unterwegs',
            ],
            [
                'type' => 'text',
                'text' => 'Mit einem Tipp melden die Fahrer ihren Status: losgefahren, angekommen, fertig oder ein Problem. Notizen und Fotos, etwa von einer schwierigen Zufahrt, bleiben an der Tour hängen und stehen beim nächsten Einsatz am selben Ort wieder zur Verfügung. Der Planer sieht so jederzeit, wo welches Fahrzeug gerade ist, ohne anrufen zu müssen.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/fleet.png',
                'alt' => 'Flottenmanagement-Software',
            ],
            [
                'type' => 'heading',
                'text' => 'Technische Umsetzung',
            ],
            [
                'type' => 'text',
                'text' => 'Die Software ist eine für das Handy optimierte Webanwendung, die ohne App-Store und Installation funktioniert. Gerade für die Fahrer war mir wichtig, dass alles mit großen Schaltflächen und wenigen Schritten bedienbar ist, denn unterwegs hat niemand Zeit, sich durch Menüs zu klicken.',
            ],
            [
                'type' => 'heading',
                'text' => 'Fazit',
            ],
            [
                'type' => 'text',
                'text' => 'Die Software ist inzwischen nicht mehr im Einsatz. Für mich war sie trotzdem ein wichtiges Projekt, weil ich zum ersten Mal komplett für Menschen entwickelt habe, die mit Software eigentlich nichts zu tun haben wollen, und dabei viel über einfache Bedienung gelernt habe.',
            ],
        ],
    ],
    [
        'title' => 'Finanzbuchhaltung-Anbindung',
        'category' => 'produktiv',
        'date' => '01/2026',
        'thumbnail' => '/pictures/projects/select.png',
        'description' => 'Für Wartungsverträge und Finanzbuchhaltung verwenden wir Selectline. Ich musste die Selectline-Datenbank enger in unser CRM integrieren.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Wartungsverträge und Finanzbuchhaltung laufen bei uns über Selectline, Kundendaten und Vertrieb über unser CRM. Wer mit einem Kunden telefonierte, wusste deshalb oft nicht, ob noch Rechnungen offen sind oder ein Wartungsvertrag bald ausläuft. Meine Aufgabe war es, die Selectline-Datenbank enger in unser CRM zu integrieren.',
            ],
            [
                'type' => 'heading',
                'text' => 'Umsetzung',
            ],
            [
                'type' => 'text',
                'text' => 'Selectline speichert seine Daten in einer SQL-Server-Datenbank. Über einen eigenen Datenbankbenutzer mit reinen Leserechten fragt das CRM Verträge, Laufzeiten und offene Posten ab und ordnet sie über die Kundennummer den passenden Kunden zu. Im CRM erscheinen diese Informationen direkt beim Kunden.',
            ],
            [
                'type' => 'text',
                'text' => 'In die Buchhaltung schreibt das CRM bewusst nicht zurück. Änderungen an Verträgen und Rechnungen passieren weiterhin in Selectline, damit die Buchhaltung die einzige verbindliche Quelle bleibt und keine zwei Systeme gegeneinander arbeiten.',
            ],
            [
                'type' => 'heading',
                'text' => 'Performance',
            ],
            [
                'type' => 'text',
                'text' => 'Direkte Abfragen bei jedem Seitenaufruf hätten die Buchhaltung unnötig belastet. Stammdaten wie Verträge werden deshalb regelmäßig synchronisiert, nur offene Posten werden beim Öffnen eines Kunden live nachgeladen, weil sie sich am häufigsten ändern.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/select.png',
                'alt' => 'Selectline-Anbindung im CRM',
            ],
            [
                'type' => 'heading',
                'text' => 'Ergebnis',
            ],
            [
                'type' => 'text',
                'text' => 'Der Vertrieb sieht vor jedem Gespräch, ob es offene Rechnungen gibt oder ein Vertrag ausläuft. Auslaufende Wartungsverträge erscheinen außerdem rechtzeitig als Erinnerung im CRM, sodass Verlängerungen nicht mehr durchrutschen.',
            ],
        ],
    ],
    [
        'title' => 'Kanban-App',
        'category' => 'privat',
        'date' => '11/2025',
        'thumbnail' => '/pictures/projects/kanban.png',
        'description' => 'Ein kleiner Versuch, eine eigene Kanban-App für ein fiktives Unternehmen zu entwickeln.',
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
        'date' => '09/2025',
        'thumbnail' => '/pictures/projects/dg.jpg',
        'description' => 'Als Dungeon Master ist es mir wichtig, meinen Spielern eine lebendige Geschichte zu bieten. Dafür habe ich ein Infotainment-System mit Licht, Musik und Notizen gebaut.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Als Dungeon Master jongliere ich am Spieltisch mit vielen Dingen gleichzeitig: Notizen, Musik, Karten, Regeln und der Frage, was die Spieler eigentlich schon wissen. Jedes Mal, wenn ich nach dem passenden Lied oder der richtigen Notiz suchen musste, ging ein Stück Atmosphäre verloren. Deshalb habe ich ein System gebaut, das all das über eine einzige Oberfläche steuert.',
            ],
            [
                'type' => 'heading',
                'text' => 'Szenen',
            ],
            [
                'type' => 'text',
                'text' => 'Herzstück sind Szenen wie Taverne, Kampf oder Gewölbe. Jede Szene bündelt eine Playlist, eine Lichtstimmung und die passenden Notizen. Ein Tipp auf dem Tablet wechselt alles gleichzeitig: Die Musik blendet über, die LED-Streifen am Tisch wechseln Farbe und Helligkeit, und ich habe sofort die Informationen vor mir, die ich gerade brauche.',
            ],
            [
                'type' => 'heading',
                'text' => 'Nonlineare Geschichte',
            ],
            [
                'type' => 'text',
                'text' => 'Meine Spieler entscheiden selbst, wohin sie gehen und mit wem sie reden. Ein lineares Skript funktioniert dafür nicht. Meine Notizen sind deshalb als Netz aufgebaut: Personen, Orte und offene Handlungsstränge sind miteinander verknüpft, und ich markiere, was die Gruppe bereits herausgefunden hat. So bleibt die Welt auch über viele Abende hinweg stimmig.',
            ],
            [
                'type' => 'heading',
                'text' => 'Spieleranzeige',
            ],
            [
                'type' => 'text',
                'text' => 'Ein zweiter Bildschirm am Tisch zeigt den Spielern Karten, Bilder von Personen und Fundstücke, aber erst, wenn ich sie freigebe. Was die Gruppe noch nicht entdeckt hat, bleibt verborgen.',
            ],
            [
                'type' => 'heading',
                'text' => 'Technische Umsetzung',
            ],
            [
                'type' => 'text',
                'text' => 'Das System ist eine kleine Webanwendung, die lokal auf meinem Laptop läuft und im Browser bedient wird. Dadurch funktioniert sie auf dem Tablet genauso wie auf dem Laptop, auch ohne Internet. Das Licht kommt von LED-Streifen mit WLED, die sich über das lokale Netzwerk ansteuern lassen.',
            ],
            [
                'type' => 'heading',
                'text' => 'Fazit',
            ],
            [
                'type' => 'text',
                'text' => 'Meine Spieler erinnern sich vor allem an die Momente, in denen Licht, Musik und Geschichte zusammenpassen. Gleichzeitig ist meine Vorbereitung strukturierter geworden, weil jede Szene schon vor dem Abend vollständig steht.',
            ],
        ],
    ],
    [
        'title' => 'Adress-Auswertungstool',
        'category' => 'produktiv',
        'date' => '08/2025',
        'thumbnail' => '/pictures/projects/auswertung.png',
        'description' => 'Damit der Vertrieb eigenständig Auswertungen zu Adressdaten erstellen kann, habe ich ein Tool mit konfigurierbaren Filtern und frei wählbaren Ausgabeformaten entwickelt.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Der Vertrieb brauchte regelmäßig Auswertungen zu Adressdaten, zum Beispiel alle Kunden einer Region mit einem bestimmten Produkt oder alle Interessenten, die seit einem Jahr nicht mehr kontaktiert wurden. Bisher landete jede dieser Anfragen in der Entwicklung, und bis zur fertigen Liste vergingen oft Tage.',
            ],
            [
                'type' => 'heading',
                'text' => 'Konfigurierbare Filter',
            ],
            [
                'type' => 'text',
                'text' => 'Mit dem Tool stellt sich der Vertrieb seine Auswertungen selbst zusammen. Filter wie Postleitzahlbereich, Branche, Produkte, Vertragsstatus oder letzter Kontakt lassen sich frei kombinieren. Häufig genutzte Auswertungen können als Vorlage gespeichert und jederzeit mit aktuellen Daten neu erzeugt werden.',
            ],
            [
                'type' => 'heading',
                'text' => 'Ausgabeformate',
            ],
            [
                'type' => 'text',
                'text' => 'Welche Spalten im Ergebnis stehen, ist frei wählbar. Ausgegeben wird als Excel-Datei, als CSV für andere Systeme oder als druckfertige Liste. So passt das Ergebnis direkt zu dem, was als Nächstes damit passieren soll, ob Telefonliste, Serienbrief oder Import in ein anderes Werkzeug.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/auswertung.png',
                'alt' => 'Adress-Auswertungstool',
            ],
            [
                'type' => 'heading',
                'text' => 'Sicherheit und Datenschutz',
            ],
            [
                'type' => 'text',
                'text' => 'Das Tool erzeugt alle Abfragen selbst aus den gewählten Filtern, freie Datenbankabfragen sind nicht möglich. Jeder sieht nur die Felder, die seine Rolle auch sonst sehen darf, und jeder Export wird protokolliert.',
            ],
            [
                'type' => 'heading',
                'text' => 'Ergebnis',
            ],
            [
                'type' => 'text',
                'text' => 'Der Vertrieb ist bei Auswertungen jetzt unabhängig, und die Entwicklung hat wieder mehr Zeit für ihre eigentlichen Aufgaben.',
            ],
        ],
    ],
    [
        'title' => 'GitLab-Anbindung',
        'category' => 'produktiv',
        'date' => '01/2026',
        'thumbnail' => '/pictures/projects/gitlab.png',
        'description' => 'Tickets im Kanban-Board ändern ihren Status automatisch anhand der Merge Requests in GitLab.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Unsere Entwicklungsaufgaben verwalten wir in einem Kanban-Board im CRM, den Code in GitLab. Nach jedem Merge Request musste das passende Ticket von Hand weitergeschoben werden. Das wurde regelmäßig vergessen, und das Board zeigte nicht mehr, woran gerade wirklich gearbeitet wird.',
            ],
            [
                'type' => 'heading',
                'text' => 'Umsetzung',
            ],
            [
                'type' => 'text',
                'text' => 'GitLab meldet jede Änderung an einem Merge Request per Webhook an einen eigenen Endpunkt im CRM. Der Endpunkt prüft zuerst das geheime Token des Webhooks, damit niemand von außen Tickets verschieben kann. Danach sucht er die Ticketnummer im Branch-Namen oder im Titel des Merge Requests.',
            ],
            [
                'type' => 'text',
                'text' => 'Aus dem Zustand des Merge Requests ergibt sich der neue Ticketstatus: Wird ein Merge Request eröffnet, wandert das Ticket in den Review. Wird er gemergt, landet es im Test. Wird er ohne Merge geschlossen, geht es zurück in die Bearbeitung. Merge Requests im Entwurfsstatus werden ignoriert.',
            ],
            [
                'type' => 'heading',
                'text' => 'Robustheit',
            ],
            [
                'type' => 'text',
                'text' => 'Webhooks können doppelt oder in falscher Reihenfolge ankommen. Ein Ticket wird deshalb nur nach vorne verschoben, außer ein Merge Request wurde ausdrücklich geschlossen. Jede automatische Änderung steht mit einem Link zum Merge Request in der Ticket-Historie, damit nachvollziehbar bleibt, warum sich ein Status geändert hat.',
            ],
            [
                'type' => 'heading',
                'text' => 'Ergebnis',
            ],
            [
                'type' => 'text',
                'text' => 'Das Board ist jetzt aktuell, ohne dass jemand daran denken muss. Wer wissen will, wie weit eine Aufgabe ist, muss nicht mehr nachfragen, sondern sieht es direkt im Ticket.',
            ],
        ],
    ],
    [
        'title' => 'Adress-Cluster',
        'category' => 'produktiv',
        'date' => '02/2026',
        'thumbnail' => '/pictures/projects/cluster.png',
        'description' => 'Ein Tool zur Verwaltung von Beziehungen zwischen Adressen und Netzen.',
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
                    'alt' => 'Adressbeziehungen im CRM',
                ],
                [
                    'type' => 'text',
                    'text' => 'Besonders wertvoll ist das für Unternehmensgruppen mit vielen Tochtergesellschaften.',
                ],
                [
                    'type' => 'text',
                    'text' => 'Darauf aufbauend habe ich ein System für konsistente Preiskommunikation geschaffen, damit unterschiedliche Adressen desselben Clusters nicht versehentlich unterschiedliche Preise erhalten.',
                ],
                [
                    'type' => 'image',
                    'src' => '/pictures/projects/sonderkonditionsverwaltung.PNG',
                    'alt' => 'Verwaltung von Sonderkonditionen',
                ],
        ],
    ],
    [
        'title' => 'GitLab Pipeline',
        'category' => 'privat',
        'date' => '03/2026',
        'thumbnail' => '/pictures/projects/cicd.png',
        'description' => 'Eine eigene Pipeline von der Entwicklung bis zum Deployment.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Für meine Kanban-App wollte ich nicht nur Code schreiben, sondern den kompletten Weg bis zur laufenden Anwendung selbst aufbauen. Daraus ist eine eigene GitLab-Pipeline entstanden, die jede Änderung automatisch baut, testet und bereitstellt.',
            ],
            [
                'type' => 'heading',
                'text' => 'Aufbau der Pipeline',
            ],
            [
                'type' => 'text',
                'text' => 'Die Pipeline läuft in vier Stufen. Zuerst wird ein Docker-Image der Anwendung gebaut und in der Container Registry von GitLab abgelegt. Danach laufen die Tests in genau diesem Image, zusammen mit einer eigenen Testdatenbank, die für jeden Durchlauf frisch erzeugt wird. Anschließend prüft eine statische Codeanalyse typische Fehler und den Codestil. Erst wenn alles grün ist, folgt das Deployment.',
            ],
            [
                'type' => 'heading',
                'text' => 'Halbautomatisches Deployment',
            ],
            [
                'type' => 'text',
                'text' => 'Jede Änderung auf dem Hauptzweig landet automatisch auf einer Testumgebung. Der Schritt in die Produktion ist bewusst ein Knopfdruck in GitLab, damit ich vorher selbst noch einmal draufschauen kann. Jedes Image trägt den Commit-Hash als Tag. Ein Rollback bedeutet deshalb nur, das vorherige Image erneut auszurollen.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/cicd.png',
                'alt' => 'GitLab-Pipeline der Kanban-App',
            ],
            [
                'type' => 'heading',
                'text' => 'Zugangsdaten',
            ],
            [
                'type' => 'text',
                'text' => 'Die Kanban-App spricht mit der OpenAI-API. Der API-Schlüssel und die Datenbankpasswörter liegen deshalb nie im Repository, sondern als geschützte und maskierte Variablen in GitLab. Sie tauchen weder im Code noch in den Logs der Pipeline auf.',
            ],
            [
                'type' => 'heading',
                'text' => 'Was ich gelernt habe',
            ],
            [
                'type' => 'text',
                'text' => 'Eine Pipeline ist nur so gut wie ihr Feedback. Durch Caching der Abhängigkeiten und eine sinnvolle Reihenfolge der Stufen bricht sie bei Fehlern früh ab und ist insgesamt deutlich schneller geworden. Die Erfahrungen daraus fließen heute in meine Arbeit mit GitLab im Beruf ein.',
            ],
        ],
    ],
    [
        'title' => 'Passwort-Handling',
        'category' => 'produktiv',
        'date' => '05/2025',
        'thumbnail' => '/pictures/projects/key.jpg',
        'description' => 'Ich habe den bestehenden Passwort-Reiter bei Geocapture um ein paar Funktionen erweitert. Sicherheit war hier besonders wichtig.',
        'content' => [
            [
                'type' => 'heading',
                'text' => 'Projektüberblick',
            ],
            [
                'type' => 'text',
                'text' => 'Im CRM bei Geocapture gibt es einen Reiter, in dem Zugangsdaten zu Kundensystemen und Geräten hinterlegt sind. Ich habe diesen Bereich um mehrere Funktionen erweitert. Weil dort sensible Daten liegen, stand Sicherheit bei jeder Entscheidung an erster Stelle.',
            ],
            [
                'type' => 'heading',
                'text' => 'Neue Funktionen',
            ],
            [
                'type' => 'text',
                'text' => 'Passwörter werden nicht mehr direkt angezeigt, sondern erst auf Klick und nur für kurze Zeit. Sie lassen sich mit einem Klick kopieren, ohne sie überhaupt sichtbar zu machen. Ein integrierter Generator erzeugt starke Passwörter direkt beim Anlegen eines neuen Zugangs.',
            ],
            [
                'type' => 'heading',
                'text' => 'Sicherheit',
            ],
            [
                'type' => 'text',
                'text' => 'Die Passwörter liegen verschlüsselt in der Datenbank und werden erst entschlüsselt, wenn jemand sie gezielt abruft. Bis dahin stehen sie auch nicht im Quelltext der Seite. Wer welches Passwort wann angesehen oder kopiert hat, wird protokolliert, und der Zugriff richtet sich nach den Rollen im CRM. In Logs oder Fehlermeldungen tauchen Passwörter nie auf.',
            ],
            [
                'type' => 'image',
                'src' => '/pictures/projects/key.png',
                'alt' => 'Passwortverwaltung im CRM',
            ],
            [
                'type' => 'heading',
                'text' => 'Ergebnis',
            ],
            [
                'type' => 'text',
                'text' => 'Zugangsdaten müssen nicht mehr in Excel-Listen oder Notizen gepflegt werden, und im Ernstfall ist nachvollziehbar, wer auf welche Daten zugegriffen hat.',
            ],
        ],
    ],
];
