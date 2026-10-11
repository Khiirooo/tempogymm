# Royal TempoGym Jette — Site réel (PHP pour OVH)

Ce dépôt contient **le site de production** et non la démonstration locale. Il inclut :

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
3. Copier *tous les fichiers de cette racine* dans le répertoire web de destination (souvent `www`), y compris `.htaccess`. Ne pas mettre un sous-dossier supplémentaire entre le domaine et `index.php`.
4. Charger `https://votre-domaine/installation.php` : une clé aléatoire propre au serveur est générée sous **`tempogym_prive/installation_key.txt`**, **à côté** de `www`, et **n’est jamais publiée sur GitHub**. Lire la clé avec l’accès FTP du propriétaire OVH.
5. Coller cette clé dans l’installation et créer deux mots de passe forts, différents, pour l’administrateur et les entraîneurs. La clé est supprimée après installation.
6. Le mot de passe visiteur initial de l’écran de développement est `tempogym2026`. Connectez-vous en administrateur et remplacez-le dans **Accès au site** avant de diffuser le lien.
7. Configurer les groupes, les produits, les stocks réels, les coordonnées et les documents légaux avec le club, puis tester une commande complète et son apparition côté administration.

## GitHub → OVH

OVH propose une association GitHub pour déployer depuis une branche. Consultez la documentation officielle : https://docs.ovhcloud.com/fr/guides/web-cloud/web-hosting/git-integration-webhosting

Pour une première installation, le transfert FTP des fichiers vers un **nouveau** répertoire de site est aussi possible. **Ne pas écraser le site actuel sans autorisation et sauvegarde.**

### Stockage des données

L’application crée **`tempogym_prive/data.json`** et **`tempogym_prive/config.json`** à côté du dossier public, sur OVH. Aucune commande ni aucun mot de passe de production n’est conservé dans GitHub. Prévoir des sauvegardes, des permissions adaptées et une politique de conservation des données personnelles.

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
- Le logo de navigation pointe désormais vers `floatingimage/Logofloating3D.png`, qui existe déjà dans le dépôt.
- **Ne jamais changer la source GitHub Pages de `main` vers `nouveau-site-v5`.** GitHub Pages ne sait pas exécuter PHP et le domaine public serait affecté.
- Pour tester réellement les comptes, les commandes et le stock, déployer la branche sur un hébergement PHP / Apache avec un sous-domaine de test et procéder à l'installation.
