<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250408232856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lignes_panier DROP FOREIGN KEY lignes_panier_ibfk_1');
        $this->addSql('ALTER TABLE lignes_panier DROP FOREIGN KEY lignes_panier_ibfk_2');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY fk_reclamation_logement');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY reclamation_ibfk_1');
        $this->addSql('ALTER TABLE reservation_logement DROP FOREIGN KEY fk_reservation_logement_cinEtudiant');
        $this->addSql('ALTER TABLE reservation_logement DROP FOREIGN KEY fk_reservation_logement_cinProprietaire');
        $this->addSql('ALTER TABLE meubles DROP FOREIGN KEY fk_meubles_utilisateur');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY message_ibfk_1');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY message_ibfk_2');
        $this->addSql('ALTER TABLE paniers DROP FOREIGN KEY fk_paniers_utilisateur');
        $this->addSql('ALTER TABLE reservation_transport DROP FOREIGN KEY fk_reservation_transport_cinEtudiant');
        $this->addSql('ALTER TABLE reservation_transport DROP FOREIGN KEY fk_reservation_transport_cinTransporteur');
        $this->addSql('DROP TABLE lignes_panier');
        $this->addSql('DROP TABLE reclamation');
        $this->addSql('DROP TABLE reservation_logement');
        $this->addSql('DROP TABLE rendez_vous');
        $this->addSql('DROP TABLE meubles');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE paniers');
        $this->addSql('DROP TABLE reservation_transport');
        $this->addSql('DROP TABLE utilisateurs');
        $this->addSql('ALTER TABLE image_logement RENAME INDEX logement_id TO IDX_7F0DCAFF58ABF955');
        $this->addSql('ALTER TABLE logement ADD nbrChambre INT NOT NULL, CHANGE adresse adresse VARCHAR(255) DEFAULT NULL, CHANGE description description VARCHAR(255) NOT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(255) NOT NULL, CHANGE utilisateur_cin utilisateur_cin VARCHAR(8) DEFAULT NULL, CHANGE localisation localisation POINT NOT NULL COMMENT \'(DC2Type:point)\'');
        $this->addSql('ALTER TABLE logement ADD CONSTRAINT FK_F0FD445726A98CA9 FOREIGN KEY (utilisateur_cin) REFERENCES utilisateur (cin)');
        $this->addSql('ALTER TABLE logement RENAME INDEX fk_utilisateur TO IDX_F0FD445726A98CA9');
        $this->addSql('ALTER TABLE logement_options DROP FOREIGN KEY logement_options_ibfk_1');
        $this->addSql('ALTER TABLE logement_options DROP FOREIGN KEY logement_options_ibfk_2');
        $this->addSql('ALTER TABLE logement_options ADD CONSTRAINT FK_AAE2EAD5A026A8C2 FOREIGN KEY (id_logement) REFERENCES logement (id)');
        $this->addSql('ALTER TABLE logement_options ADD CONSTRAINT FK_AAE2EAD57CB1B55D FOREIGN KEY (id_option) REFERENCES options (id_option)');
        $this->addSql('ALTER TABLE logement_options RENAME INDEX logement_options_ibfk_2 TO IDX_AAE2EAD57CB1B55D');
        $this->addSql('ALTER TABLE options CHANGE id_option id_option INT AUTO_INCREMENT NOT NULL');
        $this->addSql('DROP INDEX email ON utilisateur');
        $this->addSql('ALTER TABLE utilisateur ADD blocked TINYINT(1) NOT NULL, ADD created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE role role VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lignes_panier (id INT AUTO_INCREMENT NOT NULL, id_panier INT NOT NULL, id_meuble INT NOT NULL, INDEX id_panier (id_panier), INDEX lignes_panier_ibfk_2 (id_meuble), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE reclamation (cin VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, idReclamation INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, idLogement INT DEFAULT NULL, INDEX cin (cin), INDEX fk_reclamation_logement (idLogement), PRIMARY KEY(idReclamation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE reservation_logement (id INT AUTO_INCREMENT NOT NULL, dateDebut DATE DEFAULT NULL, dateFin DATE DEFAULT NULL, status VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'en_attente\' COLLATE `utf8mb4_general_ci`, cinEtudiant VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, cinProprietaire VARCHAR(8) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX fk_reservation_logement_cinEtudiant (cinEtudiant), INDEX fk_reservation_logement_cinProprietaire (cinProprietaire), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE rendez_vous (id INT AUTO_INCREMENT NOT NULL, date DATE DEFAULT NULL, heure VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, status VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'en_attente\' COLLATE `utf8mb4_general_ci`, cinProprietaire VARCHAR(8) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, cinEtudiant VARCHAR(8) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, idLogement INT DEFAULT NULL, INDEX fk_cinEtudiant (cinEtudiant), INDEX fk_cinProprietaire (cinProprietaire), INDEX fk_logement_id (idLogement), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE meubles (id INT AUTO_INCREMENT NOT NULL, cin_vendeur VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, prix NUMERIC(10, 2) NOT NULL, statut VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, categorie VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_enregistrement DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, image VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX idx_cin_vendeur (cin_vendeur), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, senderCin VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, receiverCin VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, content TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX senderCin (senderCin), INDEX receiverCin (receiverCin), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE paniers (id INT AUTO_INCREMENT NOT NULL, cin_acheteur VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_ajout DATETIME DEFAULT NULL, statut VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_validation DATETIME DEFAULT NULL, date_annulation DATETIME DEFAULT NULL, INDEX fk_paniers_utilisateur (cin_acheteur), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE reservation_transport (id INT AUTO_INCREMENT NOT NULL, adresseDepart VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, adresseDestination VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, tempsArrivage VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, status VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'en_attente\' COLLATE `utf8mb4_general_ci`, cinEtudiant VARCHAR(8) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, cinTransporteur VARCHAR(8) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX fk_reservation_transport_cinTransporteur (cinTransporteur), INDEX fk_reservation_transport_cinEtudiant (cinEtudiant), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE utilisateurs (cin VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, nom VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, prenom VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, email VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, mdp VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, numTel VARCHAR(15) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, role VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, UNIQUE INDEX email (email), PRIMARY KEY(cin)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE lignes_panier ADD CONSTRAINT lignes_panier_ibfk_1 FOREIGN KEY (id_panier) REFERENCES paniers (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE lignes_panier ADD CONSTRAINT lignes_panier_ibfk_2 FOREIGN KEY (id_meuble) REFERENCES meubles (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT fk_reclamation_logement FOREIGN KEY (idLogement) REFERENCES logement (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT reclamation_ibfk_1 FOREIGN KEY (cin) REFERENCES utilisateur (cin) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation_logement ADD CONSTRAINT fk_reservation_logement_cinEtudiant FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE reservation_logement ADD CONSTRAINT fk_reservation_logement_cinProprietaire FOREIGN KEY (cinProprietaire) REFERENCES utilisateur (cin) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE meubles ADD CONSTRAINT fk_meubles_utilisateur FOREIGN KEY (cin_vendeur) REFERENCES utilisateur (cin) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT message_ibfk_1 FOREIGN KEY (senderCin) REFERENCES utilisateur (cin) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT message_ibfk_2 FOREIGN KEY (receiverCin) REFERENCES utilisateur (cin) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paniers ADD CONSTRAINT fk_paniers_utilisateur FOREIGN KEY (cin_acheteur) REFERENCES utilisateur (cin) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE reservation_transport ADD CONSTRAINT fk_reservation_transport_cinEtudiant FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE reservation_transport ADD CONSTRAINT fk_reservation_transport_cinTransporteur FOREIGN KEY (cinTransporteur) REFERENCES utilisateur (cin) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE image_logement RENAME INDEX idx_7f0dcaff58abf955 TO logement_id');
        $this->addSql('ALTER TABLE options CHANGE id_option id_option INT NOT NULL');
        $this->addSql('ALTER TABLE logement DROP FOREIGN KEY FK_F0FD445726A98CA9');
        $this->addSql('ALTER TABLE logement DROP nbrChambre, CHANGE utilisateur_cin utilisateur_cin VARCHAR(8) NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE type type VARCHAR(100) DEFAULT NULL, CHANGE localisation localisation POINT DEFAULT NULL COMMENT \'(DC2Type:point)\', CHANGE adresse adresse VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(255) DEFAULT \'DISPONIBLE\' NOT NULL');
        $this->addSql('ALTER TABLE logement RENAME INDEX idx_f0fd445726a98ca9 TO fk_utilisateur');
        $this->addSql('ALTER TABLE logement_options DROP FOREIGN KEY FK_AAE2EAD5A026A8C2');
        $this->addSql('ALTER TABLE logement_options DROP FOREIGN KEY FK_AAE2EAD57CB1B55D');
        $this->addSql('ALTER TABLE logement_options ADD CONSTRAINT logement_options_ibfk_1 FOREIGN KEY (id_logement) REFERENCES logement (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE logement_options ADD CONSTRAINT logement_options_ibfk_2 FOREIGN KEY (id_option) REFERENCES options (id_option) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE logement_options RENAME INDEX idx_aae2ead57cb1b55d TO logement_options_ibfk_2');
        $this->addSql('ALTER TABLE utilisateur DROP blocked, DROP created_at, CHANGE role role VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX email ON utilisateur (email)');
    }
}
