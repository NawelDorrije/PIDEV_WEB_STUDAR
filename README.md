
# StuDar

StuDar est une plateforme développée dans le cadre du projet PIDEV 3A. Elle vise à simplifier la vie des étudiants en leur offrant une solution tout-en-un pour :

- Trouver un logement étudiant sans frais d’agence,
- Acheter et vendre des meubles d’occasion entre étudiants,
- Gérer le transport de leurs meubles à moindre coût,
- Communiquer directement avec les propriétaires via une messagerie intégrée.

Ce projet favorise des échanges directs et économiques entre étudiants et propriétaires, sans intermédiaires coûteux.

## Description du projet

L'objectif principal de StuDar est de répondre aux besoins réels des étudiants dans la gestion de leur logement et de leur ameublement, tout en réduisant les coûts et en encourageant les pratiques écoresponsables.

### Fonctionnalités principales

- Recherche de logements étudiants sans frais d’agence
- Publication et consultation d'annonces pour meubles d’occasion
- Gestion simplifiée du transport de mobilier
- Messagerie interne entre étudiants et propriétaires
- Interface utilisateur intuitive et responsive

## Technologies utilisées

- **Backend** : Symfony
- **Frontend** : Symfony (Twig)
- **Base de données** : MySQL via XAMPP
- **Gestionnaire de dépendances PHP** : Composer
- **Gestionnaire de packages frontend** : npm
- **Serveur Web** : Symfony Local Server

## Installation

1. **Cloner le repository** :
   ```bash
   git clone https://github.com/ton-utilisateur/studar.git
   cd studar
   ```

2. **Installer les dépendances PHP** :
   ```bash
   composer install
   ```

3. **Installer les dépendances front-end** :
   ```bash
   npm install
   ```

4. **Configurer la base de données** :
   - Ouvrir le fichier `.env`
   - Modifier la ligne :
     ```env
     DATABASE_URL="mysql://root:@127.0.0.1:3306/studar"
     ```

5. **Créer la base de données (si elle n'existe pas)** :
   ```bash
   php bin/console doctrine:database:create
   ```

6. **Exécuter les migrations** :
   ```bash
   php bin/console doctrine:migrations:migrate
   ```

7. **Lancer le serveur Symfony** :
   ```bash
   symfony server:start
   ```

8. *(Optionnel)* Lancer le serveur WebSocket si nécessaire :
   ```bash
   php bin/websocket-server.php
   ```

## Utilisation

Une fois le projet installé et le serveur lancé, accédez à l'application via :

[http://localhost:8000](http://localhost:8000)

## Contributions

Nous remercions tous ceux qui ont contribué à ce projet !

### Contributeurs

Les personnes suivantes ont contribué à ce projet en ajoutant des fonctionnalités, en corrigeant des bugs ou en améliorant la documentation :


- [Nour Mougou](https://github.com/nourmougou) –  Gestion des utilisateurs
- [Nawel Dorrije](https://github.com/NawelDorrije) – Gestion des meubles
- [Oumeyma Tibaoui](https://github.com/oumeymatibaoui) – Gestion des logements
- [Khlass Melek](https://github.com/Khlass-Melek) – Gestion des transports
- [Nour Choc](https://github.com/noor1510) –  Gestion des rendez-vous
- [Alaa eddine zamouri](https://github.com/Aloulouzamouri) – Gestion des réclamations
Encadre par : 
- [Ghada Ben Khlifa ](https://github.com/BenKhalifaGHADA) – Supervision du projet StuDar, accompagnement technique et méthodologique pour garantir la qualité et la cohérence du développement.


### Comment contribuer ?

1. **Fork le projet** : Allez sur la page GitHub du projet et cliquez sur le bouton **Fork** pour créer une copie du projet dans votre compte GitHub.

2. **Clonez votre fork** :
   ```bash
   git clone https://github.com/votre-utilisateur/studar.git
   cd studar
   ```

3. **Créez une branche** :
   ```bash
   git checkout -b nouvelle-fonctionnalite
   ```

4. **Faites vos modifications et commitez-les** :
   ```bash
   git commit -m "Ajout d'une nouvelle fonctionnalité"
   ```

5. **Poussez votre branche** :
   ```bash
   git push origin nouvelle-fonctionnalite
   ```

6. **Ouvrez une Pull Request** sur GitHub.

## Licence

Ce projet est ouvert à tous pour un usage non commercial dans un cadre académique. Pour une utilisation professionnelle ou commerciale, merci de contacter les auteurs du projet.
