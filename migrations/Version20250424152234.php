<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250424152234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message DROP FOREIGN KEY message_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message DROP FOREIGN KEY message_ibfk_2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation DROP FOREIGN KEY reclamation_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation DROP FOREIGN KEY fk_reclamation_logement
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE message
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE reclamation
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE transport
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE utilisateurs
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE voiture
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commandes DROP FOREIGN KEY commandes_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commandes DROP FOREIGN KEY commandes_ibfk_2
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX commandes_ibfk_2 ON commandes
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commandes DROP FOREIGN KEY commandes_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commandes CHANGE date_commande date_commande DATETIME NOT NULL, CHANGE statut statut VARCHAR(20) DEFAULT 'EN_ATTENTE' NOT NULL, CHANGE methode_paiement methode_paiement VARCHAR(50) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commandes ADD CONSTRAINT FK_35D4282C2FBB81F FOREIGN KEY (id_panier) REFERENCES paniers (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX commandes_ibfk_1 ON commandes
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_35D4282C2FBB81F ON commandes (id_panier)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commandes ADD CONSTRAINT commandes_ibfk_1 FOREIGN KEY (id_panier) REFERENCES paniers (id) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE image_logement DROP FOREIGN KEY image_logement_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE image_logement DROP FOREIGN KEY image_logement_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE image_logement ADD CONSTRAINT FK_7F0DCAFF58ABF955 FOREIGN KEY (logement_id) REFERENCES logement (id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX image_logement_ibfk_1 ON image_logement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7F0DCAFF58ABF955 ON image_logement (logement_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE image_logement ADD CONSTRAINT image_logement_ibfk_1 FOREIGN KEY (logement_id) REFERENCES logement (id) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lignes_panier DROP FOREIGN KEY lignes_panier_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lignes_panier DROP FOREIGN KEY lignes_panier_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lignes_panier DROP FOREIGN KEY lignes_panier_ibfk_2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lignes_panier ADD CONSTRAINT FK_ECBFA3512FBB81F FOREIGN KEY (id_panier) REFERENCES paniers (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX lignes_panier_ibfk_1 ON lignes_panier
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_ECBFA3512FBB81F ON lignes_panier (id_panier)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX lignes_panier_ibfk_2 ON lignes_panier
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_ECBFA351916F0E6B ON lignes_panier (id_meuble)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lignes_panier ADD CONSTRAINT lignes_panier_ibfk_1 FOREIGN KEY (id_panier) REFERENCES paniers (id) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lignes_panier ADD CONSTRAINT lignes_panier_ibfk_2 FOREIGN KEY (id_meuble) REFERENCES meubles (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement CHANGE nbrChambre nbrChambre INT NOT NULL, CHANGE description description VARCHAR(255) NOT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE utilisateur_cin utilisateur_cin VARCHAR(8) DEFAULT NULL, CHANGE localisation localisation POINT NOT NULL COMMENT '(DC2Type:point)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement ADD CONSTRAINT FK_F0FD445726A98CA9 FOREIGN KEY (utilisateur_cin) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fk_utilisateur ON logement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F0FD445726A98CA9 ON logement (utilisateur_cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement_options DROP FOREIGN KEY logement_options_ibfk_2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement_options DROP FOREIGN KEY logement_options_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement_options DROP FOREIGN KEY logement_options_ibfk_2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement_options ADD CONSTRAINT FK_AAE2EAD5A026A8C2 FOREIGN KEY (id_logement) REFERENCES logement (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement_options ADD CONSTRAINT FK_AAE2EAD57CB1B55D FOREIGN KEY (id_option) REFERENCES options (id_option)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX logement_options_ibfk_2 ON logement_options
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_AAE2EAD57CB1B55D ON logement_options (id_option)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement_options ADD CONSTRAINT logement_options_ibfk_2 FOREIGN KEY (id_option) REFERENCES options (id_option) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE meubles DROP FOREIGN KEY fk_meubles_utilisateur
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_cin_vendeur ON meubles
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE meubles CHANGE description description LONGTEXT DEFAULT NULL, CHANGE statut statut VARCHAR(50) NOT NULL, CHANGE categorie categorie VARCHAR(50) NOT NULL, CHANGE date_enregistrement date_enregistrement DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE options CHANGE id_option id_option INT AUTO_INCREMENT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paniers DROP FOREIGN KEY fk_paniers_utilisateur
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fk_paniers_utilisateur ON paniers
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paniers CHANGE statut statut VARCHAR(20) DEFAULT 'EN_COURS' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous DROP FOREIGN KEY fk_rendez_vous_cinEtudiant
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous DROP FOREIGN KEY fk_rendez_vous_cinProprietaire
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fk_logement_id ON rendez_vous
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous DROP FOREIGN KEY fk_rendez_vous_cinEtudiant
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous DROP FOREIGN KEY fk_rendez_vous_cinProprietaire
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous CHANGE status status ENUM('confirmée', 'en_attente', 'refusée') DEFAULT 'en_attente'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A58090EBB FOREIGN KEY (cinProprietaire) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0AF6C8AB85 FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fk_cinproprietaire ON rendez_vous
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_65E8AA0A58090EBB ON rendez_vous (cinProprietaire)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fk_cinetudiant ON rendez_vous
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_65E8AA0AF6C8AB85 ON rendez_vous (cinEtudiant)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous ADD CONSTRAINT fk_rendez_vous_cinEtudiant FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous ADD CONSTRAINT fk_rendez_vous_cinProprietaire FOREIGN KEY (cinProprietaire) REFERENCES utilisateur (cin) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fk_logement_id ON reservation_logement
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_logement DROP FOREIGN KEY fk_reservation_logement_cinEtudiant
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_logement DROP FOREIGN KEY fk_reservation_logement_cinProprietaire
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_logement CHANGE status status ENUM('confirmée', 'en_attente', 'refusée') DEFAULT 'en_attente', CHANGE cinEtudiant cinEtudiant VARCHAR(8) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fk_reservation_logement_cinproprietaire ON reservation_logement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6D48A64358090EBB ON reservation_logement (cinProprietaire)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fk_reservation_logement_cinetudiant ON reservation_logement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6D48A643F6C8AB85 ON reservation_logement (cinEtudiant)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_logement ADD CONSTRAINT fk_reservation_logement_cinEtudiant FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_logement ADD CONSTRAINT fk_reservation_logement_cinProprietaire FOREIGN KEY (cinProprietaire) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport DROP FOREIGN KEY fk_reservation_transport_cinTransporteur
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport DROP FOREIGN KEY fk_reservation_transport_cinEtudiant
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport CHANGE status status ENUM('confirmée', 'en_attente', 'refusée') DEFAULT 'en_attente'
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fk_reservation_transport_cinetudiant ON reservation_transport
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7CEC40B1F6C8AB85 ON reservation_transport (cinEtudiant)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fk_reservation_transport_cintransporteur ON reservation_transport
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7CEC40B193BCDE18 ON reservation_transport (cinTransporteur)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport ADD CONSTRAINT fk_reservation_transport_cinTransporteur FOREIGN KEY (cinTransporteur) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport ADD CONSTRAINT fk_reservation_transport_cinEtudiant FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX email ON utilisateur
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE utilisateur ADD preferred_zones JSON DEFAULT NULL COMMENT '(DC2Type:json)', ADD preferred_times JSON DEFAULT NULL COMMENT '(DC2Type:json)', CHANGE role role VARCHAR(20) NOT NULL, CHANGE blocked blocked TINYINT(1) NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, senderCin VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, receiverCin VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, content TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX senderCin (senderCin), INDEX receiverCin (receiverCin), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE reclamation (cin VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, idReclamation INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, idLogement INT DEFAULT NULL, INDEX fk_reclamation_logement (idLogement), INDEX cin (cin), PRIMARY KEY(idReclamation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE transport (id INT AUTO_INCREMENT NOT NULL, id_voiture INT NOT NULL, cin VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, reservation_id INT NOT NULL, trajet_en_km DOUBLE PRECISION NOT NULL, tarif DOUBLE PRECISION NOT NULL, status VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, timestamp DATETIME DEFAULT 'current_timestamp(6)' NOT NULL, INDEX fk_cin (cin), INDEX fk_id_voiture (id_voiture), INDEX fk_reservation_id (reservation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE utilisateurs (cin VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, nom VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, prenom VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, email VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, mdp VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, numTel VARCHAR(15) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, role VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, UNIQUE INDEX email (email), PRIMARY KEY(cin)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE voiture (id_voiture INT AUTO_INCREMENT NOT NULL, cin VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, model VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, num_serie VARCHAR(12) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, photo VARCHAR(200) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, disponibilite VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, timestamp DATETIME DEFAULT 'current_timestamp(6)' NOT NULL, INDEX fk_cin_v (cin), PRIMARY KEY(id_voiture)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message ADD CONSTRAINT message_ibfk_1 FOREIGN KEY (senderCin) REFERENCES utilisateur (cin) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message ADD CONSTRAINT message_ibfk_2 FOREIGN KEY (receiverCin) REFERENCES utilisateur (cin) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation ADD CONSTRAINT reclamation_ibfk_1 FOREIGN KEY (cin) REFERENCES utilisateur (cin) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reclamation ADD CONSTRAINT fk_reclamation_logement FOREIGN KEY (idLogement) REFERENCES logement (id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE messenger_messages
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commandes DROP FOREIGN KEY FK_35D4282C2FBB81F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commandes DROP FOREIGN KEY FK_35D4282C2FBB81F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commandes CHANGE date_commande date_commande DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE statut statut VARCHAR(255) DEFAULT 'EN_ATTENTE' NOT NULL, CHANGE methode_paiement methode_paiement VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commandes ADD CONSTRAINT commandes_ibfk_1 FOREIGN KEY (id_panier) REFERENCES paniers (id) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commandes ADD CONSTRAINT commandes_ibfk_2 FOREIGN KEY (cin_acheteur) REFERENCES utilisateur (cin) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX commandes_ibfk_2 ON commandes (cin_acheteur)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_35d4282c2fbb81f ON commandes
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX commandes_ibfk_1 ON commandes (id_panier)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commandes ADD CONSTRAINT FK_35D4282C2FBB81F FOREIGN KEY (id_panier) REFERENCES paniers (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE image_logement DROP FOREIGN KEY FK_7F0DCAFF58ABF955
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE image_logement DROP FOREIGN KEY FK_7F0DCAFF58ABF955
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE image_logement ADD CONSTRAINT image_logement_ibfk_1 FOREIGN KEY (logement_id) REFERENCES logement (id) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_7f0dcaff58abf955 ON image_logement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX image_logement_ibfk_1 ON image_logement (logement_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE image_logement ADD CONSTRAINT FK_7F0DCAFF58ABF955 FOREIGN KEY (logement_id) REFERENCES logement (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lignes_panier DROP FOREIGN KEY FK_ECBFA3512FBB81F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lignes_panier DROP FOREIGN KEY FK_ECBFA3512FBB81F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lignes_panier DROP FOREIGN KEY FK_ECBFA351916F0E6B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lignes_panier ADD CONSTRAINT lignes_panier_ibfk_1 FOREIGN KEY (id_panier) REFERENCES paniers (id) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_ecbfa3512fbb81f ON lignes_panier
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX lignes_panier_ibfk_1 ON lignes_panier (id_panier)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_ecbfa351916f0e6b ON lignes_panier
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX lignes_panier_ibfk_2 ON lignes_panier (id_meuble)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lignes_panier ADD CONSTRAINT FK_ECBFA3512FBB81F FOREIGN KEY (id_panier) REFERENCES paniers (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lignes_panier ADD CONSTRAINT FK_ECBFA351916F0E6B FOREIGN KEY (id_meuble) REFERENCES meubles (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement DROP FOREIGN KEY FK_F0FD445726A98CA9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement DROP FOREIGN KEY FK_F0FD445726A98CA9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement CHANGE utilisateur_cin utilisateur_cin VARCHAR(8) NOT NULL, CHANGE nbrChambre nbrChambre VARCHAR(255) NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE type type VARCHAR(100) DEFAULT NULL, CHANGE localisation localisation POINT DEFAULT NULL COMMENT '(DC2Type:point)'
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_f0fd445726a98ca9 ON logement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fk_utilisateur ON logement (utilisateur_cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement ADD CONSTRAINT FK_F0FD445726A98CA9 FOREIGN KEY (utilisateur_cin) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement_options DROP FOREIGN KEY FK_AAE2EAD5A026A8C2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement_options DROP FOREIGN KEY FK_AAE2EAD57CB1B55D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement_options DROP FOREIGN KEY FK_AAE2EAD57CB1B55D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement_options ADD CONSTRAINT logement_options_ibfk_2 FOREIGN KEY (id_option) REFERENCES options (id_option) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement_options ADD CONSTRAINT logement_options_ibfk_1 FOREIGN KEY (id_logement) REFERENCES logement (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_aae2ead57cb1b55d ON logement_options
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX logement_options_ibfk_2 ON logement_options (id_option)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE logement_options ADD CONSTRAINT FK_AAE2EAD57CB1B55D FOREIGN KEY (id_option) REFERENCES options (id_option)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE meubles CHANGE description description TEXT DEFAULT NULL, CHANGE statut statut VARCHAR(255) NOT NULL, CHANGE categorie categorie VARCHAR(255) NOT NULL, CHANGE date_enregistrement date_enregistrement DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE meubles ADD CONSTRAINT fk_meubles_utilisateur FOREIGN KEY (cin_vendeur) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_cin_vendeur ON meubles (cin_vendeur)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE options CHANGE id_option id_option INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paniers CHANGE statut statut VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paniers ADD CONSTRAINT fk_paniers_utilisateur FOREIGN KEY (cin_acheteur) REFERENCES utilisateur (cin) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fk_paniers_utilisateur ON paniers (cin_acheteur)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A58090EBB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0AF6C8AB85
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A58090EBB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0AF6C8AB85
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous CHANGE status status VARCHAR(255) DEFAULT 'en_attente' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous ADD CONSTRAINT fk_rendez_vous_cinEtudiant FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous ADD CONSTRAINT fk_rendez_vous_cinProprietaire FOREIGN KEY (cinProprietaire) REFERENCES utilisateur (cin) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fk_logement_id ON rendez_vous (idLogement)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_65e8aa0a58090ebb ON rendez_vous
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fk_cinProprietaire ON rendez_vous (cinProprietaire)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_65e8aa0af6c8ab85 ON rendez_vous
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fk_cinEtudiant ON rendez_vous (cinEtudiant)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A58090EBB FOREIGN KEY (cinProprietaire) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0AF6C8AB85 FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_logement DROP FOREIGN KEY FK_6D48A64358090EBB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_logement DROP FOREIGN KEY FK_6D48A643F6C8AB85
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_logement CHANGE status status VARCHAR(255) DEFAULT 'en_attente' NOT NULL, CHANGE cinEtudiant cinEtudiant VARCHAR(20) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fk_logement_id ON reservation_logement (idLogement)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_6d48a643f6c8ab85 ON reservation_logement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fk_reservation_logement_cinEtudiant ON reservation_logement (cinEtudiant)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_6d48a64358090ebb ON reservation_logement
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fk_reservation_logement_cinProprietaire ON reservation_logement (cinProprietaire)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_logement ADD CONSTRAINT FK_6D48A64358090EBB FOREIGN KEY (cinProprietaire) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_logement ADD CONSTRAINT FK_6D48A643F6C8AB85 FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport DROP FOREIGN KEY FK_7CEC40B1F6C8AB85
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport DROP FOREIGN KEY FK_7CEC40B193BCDE18
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport CHANGE status status VARCHAR(255) DEFAULT 'en_attente'
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_7cec40b193bcde18 ON reservation_transport
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fk_reservation_transport_cinTransporteur ON reservation_transport (cinTransporteur)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_7cec40b1f6c8ab85 ON reservation_transport
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fk_reservation_transport_cinEtudiant ON reservation_transport (cinEtudiant)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport ADD CONSTRAINT FK_7CEC40B1F6C8AB85 FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport ADD CONSTRAINT FK_7CEC40B193BCDE18 FOREIGN KEY (cinTransporteur) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE utilisateur DROP preferred_zones, DROP preferred_times, CHANGE role role VARCHAR(255) NOT NULL, CHANGE blocked blocked TINYINT(1) DEFAULT 0, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX email ON utilisateur (email)
        SQL);
    }
}
