# wp-agenda-partage
Extension WordPress d'un Agenda Partagé
https://agenda-partage.fr

- Saisie libre des évènements par les visiteurs.
	- L'objectif est de rendre l'utilisation la plus simple possible pour tous les niveaux d'utilisateurs.
	
	- Sans compte, le rédacteur doit confirmer la validation de l'évènement par email. Sinon, l'évènement n'est pas public et reste dans le statut "en attente de lecture".

	- Un code secret est associé à chaque évènement permettant la modification de celui-ci.

	- Le rédacteur peut modifier son évènement durant toute la journée, avec la même IP, même navigateur. (à vérifier)
	
	- Les visiteurs connectés avec un compte abonné n'ont pas besoin de validation par mail après la rédaction d'un nouvel évènement.

	- Les évènements peuvent être synchronisés avec d'autres agendas partagés via l'envoi automatique d'un e-mail en arrière-plan.
 
- Téléchargement d'un fichier .ics des évènements

- Saisie libre des covoiturages par les visiteurs.
	- L'objectif est de rendre l'utilisation la plus simple possible pour tous les niveaux d'utilisateurs.
	
	- Les covoiturages sont publics.

	- Un code secret est associé à chaque évènement permettant la modification de celui-ci via l'adresse e-mail associée.

	- Le rédacteur peut modifier son évènement durant toute la journée, avec la même IP, même navigateur. (à vérifier)

- Gestion de page comme forum
  
	- L'ajout de message (commentaire) dans un forum peut s'effectuer par le formulaire original "Laisser un commentaire", par un formulaire WPCF7 ou par l'intégration d'e-mails d'un compte e-mail défini.

	- Un code secret est associé à chaque message permettant la modification de celui-ci via l'adresse e-mail associée.

	- Les droits de publication sont : public, validation par e-mail, utilisateur connecté, utilisateur adhérent.


- Gestion de lettres-info contenant la liste des évènements, des covoiturages ou des messages.
	
- Module de traçage des emails sortant du site.

- Représentation de l'agenda dans le code php du plugin : pas de template utilisable (sic)
- Pas de paramétrage possible des champs wpcf7 hors du code php

- Plugins obligatoires :
	- WP Contact Form 7
	
- Plugins conseillés
	- Akismet Anti-Spam
	- WP Mail Smtp - SMTP7
	- AIOS ou tout autre extension de sécurité
