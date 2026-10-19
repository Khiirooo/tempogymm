# Royal TempoGym Jette — Site réel (PHP pour OVH)

Cette branche contient **la version serveur préparée pour OVH**. Elle a passé la recette PHP locale ; elle n'a pas encore été installée ni vérifiée sur l'hébergement du club, dont les accès ne sont pas encore disponibles. Elle inclut :

- Site public et boutique avec sélection des groupes.
- Gestion des commandes et des stocks centralisée sur l’hébergement.
- Panneau d’administration sécurisé par connexion PHP.
- Espace entraîneurs (connexion séparée, droits limités).
- Page « Site en développement » activable dans l’administration et mot de passe visiteur modifiable.
- Carte Google Maps intégrée.

## IMPORTANT : GitHub n’héberge pas ce site dynamique

Ne pas activer **GitHub Pages** pour ce dépôt : GitHub Pages n’exécute pas PHP. Le code est stocké sur GitHub, et **le site fonctionne sur un hébergement OVH compatible PHP 8.1+ / Apache**, sous un domaine/sous-domaine choisi et autorisé par le club.

## Mise en production (résumé)

1. Avec l’autorisation du client, réserver un nouveau sous-domaine/dossier web (ex. `preview` ou `test`) chez OVH, puis sauvegarder le site déjà en ligne.
2. Vérifier que le dossier web de destination est vide et que PHP, HTTPS et `.htaccess` fonctionnent.
3. Copier les fichiers du site dans le répertoire web de destination (souvent `www`), y compris `.htaccess`, `assets/`, `api/`, les images et documents. Ne pas transférer `.git/` ni `tests/`. Ne pas mettre un sous-dossier supplémentaire entre le domaine et `index.php`.
4. Charger `https://votre-domaine/installation.php` : une clé aléatoire propre au serveur est générée sous **`tempogym_prive/installation_key.txt`**, **à côté** de `www`, et **n’est jamais publiée sur GitHub**. Lire la clé avec l’accès FTP du propriétaire OVH.
5. Coller cette clé dans l’installation et choisir **trois mots de passe différents de 14 à 128 caractères** : administrateur, entraîneurs et visiteurs de la préversion. Aucun mot de passe visiteur commun n'est fourni. La clé est supprimée après installation. Transmettre les accès par un canal sécurisé, jamais dans GitHub.
6. Garder l'écran de développement activé pendant la recette. Pour une installation déjà existante, modifier l'ancien mot de passe visiteur dans **Accès au site** : une mise à jour du code ne remplace pas les mots de passe du serveur.
7. Configurer les groupes, les produits, les stocks réels, les coordonnées et les documents légaux avec le club, puis tester une commande complète et son apparition côté administration.

## GitHub → OVH

OVH propose une association GitHub pour déployer depuis une branche. Consultez la documentation officielle : https://docs.ovhcloud.com/fr/guides/web-cloud/web-hosting/git-integration-webhosting

Pour une première installation, le transfert FTP des fichiers vers un **nouveau** répertoire de site est aussi possible. **Ne pas écraser le site actuel sans autorisation et sauvegarde.**

### Stockage des données

L’application crée **`tempogym_prive/data.json`** et **`tempogym_prive/config.json`** à côté du dossier public, sur OVH. Aucune commande ni aucun mot de passe de production n’est conservé dans GitHub. Prévoir des sauvegardes, des permissions adaptées et une politique de conservation des données personnelles.

Le stockage doit rester **hors de toutes les racines web**, y compris celles des autres domaines OVH. La variable d'environnement serveur facultative `TG_PRIVATE_DIR` accepte un chemin absolu vers un dossier privé dont le parent existe. Sans cette variable, le chemin historique reste celui décrit ci-dessus. Utiliser des dossiers privés différents pour la préversion et la production ; ne pas partager leurs commandes. Une mise à jour du code conserve ce dossier et ses données. Sauvegarder puis vérifier une restauration avant ouverture.

### Recette reproductible

Avec PHP 8.1+ et Python 3 disponibles, lancer `python3 tests/php_smoke.py --php php` sous Linux. Le test démarre un serveur PHP local à quatre workers, utilise un stockage et des mots de passe fictifs temporaires, puis les supprime. Les **31 contrôles** portent sur l'installation, les sessions, les droits, deux commandes simultanées sur une seule unité, le stock, les annulations, les ressources partagées, le CSV et l'anonymisation. La syntaxe PHP et JavaScript a aussi été vérifiée le 19 octobre 2026. Apache, HTTPS, les permissions et les cookies Secure restent à contrôler sur OVH.

Les fiches entraîneurs utilisent `assets/coach-carousel.js` : clic, clavier et glissement sont gérés ensemble. Les articles à taille unique peuvent être demandés sans sélection de taille artificielle. Les médias et PDF du club utilisent les fichiers locaux du projet ; ne pas omettre ces dossiers lors du transfert. Les URL déjà enregistrées dans des données serveur existantes doivent être mises à jour séparément si nécessaire.

### Limitations avant ouverture publique

- Le site envoie des *demandes* de commande : pas de paiement ni de courriel automatique inclus.
- Les champs légaux préremplis doivent être complétés/validés par le club.
- Les images, les polices et la carte utilisent des ressources externes ; leur affichage et la notice de confidentialité doivent être vérifiés.
- Les essais automatisés locaux ne remplacent pas les tests d’intégration sur le véritable hébergement OVH et sur mobile.
- Pour présenter au client avant accord, utiliser un sous-domaine de test **autorisé**, pas le domaine public existant.

## Utilisation dans le dépôt `Khiirooo/tempogymm`

Cette archive est conçue pour être **ajoutée à la branche `nouveau-site-v5`**. Ne pas modifier `main` : il contient actuellement le site public.

- La branche possède déjà les dossiers `image`, `floatingimage`, `logo`, `document` et le fichier `CNAME` : **conserver ces éléments**.
- À la racine de la branche `nouveau-site-v5`, remplacer `index.html` et `README.md`, ajouter les fichiers PHP, le dossier `api` et les fichiers cachés `.htaccess` et `.gitignore`.
- Les ressources externes figurant dans `index.html` peuvent dépendre des fichiers de la branche `main`. Ne les supprimer ni de `main` ni du dépôt avant migration.
- L'en-tête conserve son texte, sans image de logo ajoutée au menu. Le bouton de navigation est « S’inscrire ».
- **Ne jamais changer la source GitHub Pages de `main` vers `nouveau-site-v5`.** GitHub Pages ne sait pas exécuter PHP et le domaine public serait affecté.
- Pour tester réellement les comptes, les commandes et le stock, déployer la branche sur un hébergement PHP / Apache avec un sous-domaine de test et procéder à l'installation.

## Coordonnées et contenus à valider avec le club

L'identité, le siège et le numéro BCE ont été renseignés d'après les [statuts publiés par le club](https://www.tempogymjette.be/wp-content/uploads/2024/11/Statuts-corriges.pdf) et ses [coordonnées officielles](https://www.tempogymjette.be/). Le régime TVA, les conditions de vente/retour, les durées de conservation, les tarifs, les horaires et les âges restent à valider par le club. Les champs préparatoires ne constituent pas une validation juridique. Aucun paiement en ligne ni courriel automatique n'a été ajouté.
