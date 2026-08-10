Nouveau site

- OVH Cloud, hébergement edvariables.ovh, Multisite
	- Ajouter un domaine enregistré chez OVH : agenda-partage.fr
	- Domaine : nord-ardeche
	- Décocher "Créer également le sous domaine www.nord-arceche.agenda-partage.fr"
	- Dossier racine : users/agenda-partage
	- Activer le firewall
	- Logs séparés : agenda-partage.fr
- OVH Cloud, hébergement edvariables.ovh, Informations générales
	- Peut-être attendre 2H...
	- Cliquer sur ... à droite de "Certificat SSL"
	- Regénérer le certificat SSL
- agenda-partage.fr Admin réseau, Mes Sites
	https://agenda-partage.fr/wp-admin/network/sites.php
	Actions, Ajouter un domaine ou un sous-domaine : nord-ardeche.agenda-partage.fr
- Modifier le site nord-ardeche.agenda-partage.fr, Réglages, Général
	https://nord-ardeche.agenda-partage.fr/wp-admin/options-general.php
	- Slogan : Pour une démocratie participative et citoyenne
	- Icone du site
	- Adresse e-mail d’administration : nord-ardeche@agenda-partage.fr
- Réglages, Commentaires
	- Décocher Autoriser les commentaires sur les nouvelles publications
- Appareance, Thêmes
	- Activer "Agenda partagé"
- Appareance, Personnaliser
	- Média de l'en-tête
- Réglages, Askimet
	- Configurer
- Réglages, SMTP7
	- Configurer
- Menu Agenda partagé
	https://nord-ardeche.agenda-partage.fr/wp-admin/admin.php?page=agendapartage
	- Onglet "Initialisation du site"
	- Cocher "Je confirme l'importation des données manquantes ici depuis le site suivant :", Pays de Saint-Félicien
	- Enregistrer et attendre même si rien ne bouge tout de suite
	- Lire et suivre "Pour configurer un nouveau site, il faut : "
- Menu Agenda partagé
	- onglet Divers
		- Affichage du menu "Se connecter"
	- onglet Evènements
		- Les nouveaux évènements doivent être validés par email

- Menu WP Security
	- User Security
		- Disable users enumeration
		- Disable application password
	- Firewall
		- Disallow unauthorized REST requests
	- Brute force
		- Autorisez le renommage de la page des réglages de la page de connexion
		+ URL de page de connexion : connexion
	
- OVH Cloud, E-mails, agenda-partage.fr
	- Ajouter une redirection nord-ardeche@agenda-partage.fr
	- Ajouter une adresse publier.nord-ardeche@agenda-partage.fr
	- Ajouter une redirection info-partage.nord-ardeche@agenda-partage.fr vers publier.nord-ardeche@agenda-partage.fr
	