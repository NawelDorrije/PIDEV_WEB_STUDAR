<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250403154639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_logement DROP FOREIGN KEY fk_reservation_logement_cinEtudiant');
        $this->addSql('ALTER TABLE reservation_logement DROP FOREIGN KEY fk_reservation_logement_cinProprietaire');
        $this->addSql('ALTER TABLE reservation_transport DROP FOREIGN KEY fk_reservation_transport_cinTransporteur');
        $this->addSql('ALTER TABLE reservation_transport DROP FOREIGN KEY fk_reservation_transport_cinEtudiant');
        $this->addSql('CREATE TABLE rendezvous (id INT AUTO_INCREMENT NOT NULL, date DATE DEFAULT NULL, heure VARCHAR(50) DEFAULT NULL, cin_proprietaire VARCHAR(8) NOT NULL, cin_etudiant VARCHAR(8) NOT NULL, id_logement VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE commandes DROP FOREIGN KEY commandes_ibfk_1');
        $this->addSql('ALTER TABLE commandes DROP FOREIGN KEY commandes_ibfk_2');
        $this->addSql('ALTER TABLE image_logement DROP FOREIGN KEY image_logement_ibfk_1');
        $this->addSql('ALTER TABLE lignes_panier DROP FOREIGN KEY lignes_panier_ibfk_1');
        $this->addSql('ALTER TABLE lignes_panier DROP FOREIGN KEY lignes_panier_ibfk_2');
        $this->addSql('ALTER TABLE logement_options DROP FOREIGN KEY logement_options_ibfk_1');
        $this->addSql('ALTER TABLE logement_options DROP FOREIGN KEY logement_options_ibfk_2');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY message_ibfk_2');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY message_ibfk_1');
        $this->addSql('ALTER TABLE meubles DROP FOREIGN KEY fk_meubles_utilisateur');
        $this->addSql('ALTER TABLE paniers DROP FOREIGN KEY fk_paniers_utilisateur');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY fk_reclamation_logement');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY reclamation_ibfk_1');
        $this->addSql('DROP TABLE commandes');
        $this->addSql('DROP TABLE image_logement');
        $this->addSql('DROP TABLE lignes_panier');
        $this->addSql('DROP TABLE logement');
        $this->addSql('DROP TABLE logement_options');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE meubles');
        $this->addSql('DROP TABLE options');
        $this->addSql('DROP TABLE paniers');
        $this->addSql('DROP TABLE reclamation');
        $this->addSql('DROP TABLE rendez_vous');
        $this->addSql('DROP TABLE transport');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE utilisateurs');
        $this->addSql('DROP TABLE voiture');
        $this->addSql('DROP INDEX fk_logement_id ON reservation_logement');
        $this->addSql('DROP INDEX fk_reservation_logement_cinEtudiant ON reservation_logement');
        $this->addSql('DROP INDEX fk_reservation_logement_cinProprietaire ON reservation_logement');
        $this->addSql('ALTER TABLE reservation_logement ADD date_debut DATE DEFAULT NULL, ADD date_fin DATE DEFAULT NULL, DROP dateDebut, DROP dateFin, CHANGE status status LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\', CHANGE cinEtudiant cin_etudiant VARCHAR(20) DEFAULT NULL, CHANGE cinProprietaire cin_proprietaire VARCHAR(8) DEFAULT NULL, CHANGE idLogement id_logement VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX fk_reservation_transport_cinEtudiant ON reservation_transport');
        $this->addSql('DROP INDEX fk_reservation_transport_cinTransporteur ON reservation_transport');
        $this->addSql('ALTER TABLE reservation_transport ADD adresse_depart VARCHAR(255) DEFAULT NULL, ADD adresse_destination VARCHAR(255) DEFAULT NULL, ADD cin_etudiant VARCHAR(8) DEFAULT NULL, ADD cin_transporteur VARCHAR(8) DEFAULT NULL, DROP adresseDepart, DROP adresseDestination, DROP cinEtudiant, DROP cinTransporteur, CHANGE status status LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\', CHANGE tempsArrivage temps_arrivage VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commandes (id INT AUTO_INCREMENT NOT NULL, id_panier INT NOT NULL, cin_acheteur VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_commande DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, statut VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'EN_ATTENTE\' NOT NULL COLLATE `utf8mb4_general_ci`, methode_paiement VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, montant_total NUMERIC(10, 2) NOT NULL, adresse_livraison VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, session_stripe VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_annulation DATETIME DEFAULT NULL, raison_annulation VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX commandes_ibfk_2 (cin_acheteur), INDEX commandes_ibfk_1 (id_panier), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE image_logement (id_image INT AUTO_INCREMENT NOT NULL, logement_id INT NOT NULL, url VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, INDEX image_logement_ibfk_1 (logement_id), PRIMARY KEY(id_image)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE lignes_panier (id INT AUTO_INCREMENT NOT NULL, id_panier INT NOT NULL, id_meuble INT NOT NULL, INDEX lignes_panier_ibfk_2 (id_meuble), INDEX lignes_panier_ibfk_1 (id_panier), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE logement (id INT AUTO_INCREMENT NOT NULL, nbrChambre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, prix DOUBLE PRECISION NOT NULL, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, type VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, statut VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, utilisateur_cin VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, localisation POINT DEFAULT NULL, adresse VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX fk_utilisateur (utilisateur_cin), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE logement_options (id_logement INT NOT NULL, id_option INT NOT NULL, valeur TINYINT(1) DEFAULT NULL, INDEX logement_options_ibfk_2 (id_option), INDEX IDX_AAE2EAD5A026A8C2 (id_logement), PRIMARY KEY(id_logement, id_option)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, senderCin VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, receiverCin VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, content TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX receiverCin (receiverCin), INDEX senderCin (senderCin), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE meubles (id INT AUTO_INCREMENT NOT NULL, cin_vendeur VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, prix NUMERIC(10, 2) NOT NULL, statut VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, categorie VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_enregistrement DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, image VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX idx_cin_vendeur (cin_vendeur), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE options (id_option INT NOT NULL, nom_option VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY(id_option)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE paniers (id INT AUTO_INCREMENT NOT NULL, cin_acheteur VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_ajout DATETIME DEFAULT NULL, statut VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_validation DATETIME DEFAULT NULL, date_annulation DATETIME DEFAULT NULL, INDEX fk_paniers_utilisateur (cin_acheteur), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE reclamation (cin VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, idReclamation INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, idLogement INT DEFAULT NULL, INDEX cin (cin), INDEX fk_reclamation_logement (idLogement), PRIMARY KEY(idReclamation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE rendez_vous (id INT AUTO_INCREMENT NOT NULL, date DATE DEFAULT NULL, heure VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, status VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'en_attente\' COLLATE `utf8mb4_general_ci`, cinProprietaire VARCHAR(8) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, cinEtudiant VARCHAR(8) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, idLogement VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX fk_cinEtudiant (cinEtudiant), INDEX fk_cinProprietaire (cinProprietaire), INDEX fk_logement_id (idLogement), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE transport (id INT AUTO_INCREMENT NOT NULL, id_voiture INT NOT NULL, cin VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, reservation_id INT NOT NULL, trajet_en_km DOUBLE PRECISION NOT NULL, tarif DOUBLE PRECISION NOT NULL, status VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, timestamp DATETIME DEFAULT \'current_timestamp(6)\' NOT NULL, INDEX fk_id_voiture (id_voiture), INDEX fk_reservation_id (reservation_id), INDEX fk_cin (cin), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE utilisateur (cin VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, nom VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, prenom VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, email VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, mdp VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, numTel VARCHAR(15) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, role VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, reset_code VARCHAR(10) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, blocked TINYINT(1) DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, UNIQUE INDEX email (email), PRIMARY KEY(cin)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE utilisateurs (cin VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, nom VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, prenom VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, email VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, mdp VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, numTel VARCHAR(15) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, role VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, UNIQUE INDEX email (email), PRIMARY KEY(cin)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE voiture (id_voiture INT AUTO_INCREMENT NOT NULL, cin VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, model VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, num_serie VARCHAR(12) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, photo VARCHAR(200) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, disponibilite VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, timestamp DATETIME DEFAULT \'current_timestamp(6)\' NOT NULL, INDEX fk_cin_v (cin), PRIMARY KEY(id_voiture)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE commandes ADD CONSTRAINT commandes_ibfk_1 FOREIGN KEY (id_panier) REFERENCES paniers (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commandes ADD CONSTRAINT commandes_ibfk_2 FOREIGN KEY (cin_acheteur) REFERENCES utilisateur (cin) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE image_logement ADD CONSTRAINT image_logement_ibfk_1 FOREIGN KEY (logement_id) REFERENCES logement (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lignes_panier ADD CONSTRAINT lignes_panier_ibfk_1 FOREIGN KEY (id_panier) REFERENCES paniers (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lignes_panier ADD CONSTRAINT lignes_panier_ibfk_2 FOREIGN KEY (id_meuble) REFERENCES meubles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE logement_options ADD CONSTRAINT logement_options_ibfk_1 FOREIGN KEY (id_logement) REFERENCES logement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE logement_options ADD CONSTRAINT logement_options_ibfk_2 FOREIGN KEY (id_option) REFERENCES options (id_option) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT message_ibfk_2 FOREIGN KEY (receiverCin) REFERENCES utilisateur (cin) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT message_ibfk_1 FOREIGN KEY (senderCin) REFERENCES utilisateur (cin) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE meubles ADD CONSTRAINT fk_meubles_utilisateur FOREIGN KEY (cin_vendeur) REFERENCES utilisateur (cin)');
        $this->addSql('ALTER TABLE paniers ADD CONSTRAINT fk_paniers_utilisateur FOREIGN KEY (cin_acheteur) REFERENCES utilisateur (cin) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT fk_reclamation_logement FOREIGN KEY (idLogement) REFERENCES logement (id)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT reclamation_ibfk_1 FOREIGN KEY (cin) REFERENCES utilisateur (cin) ON DELETE CASCADE');
        $this->addSql('DROP TABLE rendezvous');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE reservation_logement ADD dateDebut DATE DEFAULT NULL, ADD dateFin DATE DEFAULT NULL, DROP date_debut, DROP date_fin, CHANGE status status VARCHAR(255) DEFAULT \'en_attente\', CHANGE cin_etudiant cinEtudiant VARCHAR(20) DEFAULT NULL, CHANGE cin_proprietaire cinProprietaire VARCHAR(8) DEFAULT NULL, CHANGE id_logement idLogement VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_logement ADD CONSTRAINT fk_reservation_logement_cinEtudiant FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin)');
        $this->addSql('ALTER TABLE reservation_logement ADD CONSTRAINT fk_reservation_logement_cinProprietaire FOREIGN KEY (cinProprietaire) REFERENCES utilisateur (cin)');
        $this->addSql('CREATE INDEX fk_logement_id ON reservation_logement (idLogement)');
        $this->addSql('CREATE INDEX fk_reservation_logement_cinEtudiant ON reservation_logement (cinEtudiant)');
        $this->addSql('CREATE INDEX fk_reservation_logement_cinProprietaire ON reservation_logement (cinProprietaire)');
        $this->addSql('ALTER TABLE reservation_transport ADD adresseDepart VARCHAR(255) DEFAULT NULL, ADD adresseDestination VARCHAR(255) DEFAULT NULL, ADD cinEtudiant VARCHAR(8) DEFAULT NULL, ADD cinTransporteur VARCHAR(8) DEFAULT NULL, DROP adresse_depart, DROP adresse_destination, DROP cin_etudiant, DROP cin_transporteur, CHANGE status status VARCHAR(255) DEFAULT \'en_attente\', CHANGE temps_arrivage tempsArrivage VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_transport ADD CONSTRAINT fk_reservation_transport_cinTransporteur FOREIGN KEY (cinTransporteur) REFERENCES utilisateur (cin)');
        $this->addSql('ALTER TABLE reservation_transport ADD CONSTRAINT fk_reservation_transport_cinEtudiant FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin)');
        $this->addSql('CREATE INDEX fk_reservation_transport_cinEtudiant ON reservation_transport (cinEtudiant)');
        $this->addSql('CREATE INDEX fk_reservation_transport_cinTransporteur ON reservation_transport (cinTransporteur)');
    }
}
