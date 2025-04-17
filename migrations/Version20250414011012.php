<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250414011012 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reservation_transport (id INT AUTO_INCREMENT NOT NULL, adresseDepart VARCHAR(255) DEFAULT NULL, adresseDestination VARCHAR(255) DEFAULT NULL, tempsArrivage VARCHAR(50) DEFAULT NULL, status ENUM(\'confirmée\', \'en_attente\', \'refusée\') DEFAULT \'en_attente\', departure_lat DOUBLE PRECISION DEFAULT NULL, departure_lng DOUBLE PRECISION DEFAULT NULL, destination_lat DOUBLE PRECISION DEFAULT NULL, destination_lng DOUBLE PRECISION DEFAULT NULL, cinEtudiant VARCHAR(8) DEFAULT NULL, cinTransporteur VARCHAR(8) DEFAULT NULL, INDEX IDX_7CEC40B1F6C8AB85 (cinEtudiant), INDEX IDX_7CEC40B193BCDE18 (cinTransporteur), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE transport (id INT AUTO_INCREMENT NOT NULL, id_voiture INT NOT NULL, reservation_id INT NOT NULL, trajet_en_km DOUBLE PRECISION NOT NULL, tarif DOUBLE PRECISION NOT NULL, status VARCHAR(20) NOT NULL COMMENT \'(DC2Type:transport_status)\', timestamp DATETIME NOT NULL, INDEX IDX_66AB212E377F287F (id_voiture), UNIQUE INDEX UNIQ_66AB212EB83297E7 (reservation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE utilisateur (cin VARCHAR(8) NOT NULL, nom VARCHAR(50) NOT NULL, image VARCHAR(255) NOT NULL, prenom VARCHAR(50) NOT NULL, email VARCHAR(100) NOT NULL, mdp VARCHAR(255) NOT NULL, numTel VARCHAR(15) DEFAULT NULL, role VARCHAR(20) NOT NULL, reset_code VARCHAR(10) DEFAULT NULL, blocked TINYINT(1) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(cin)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE voiture (id_voiture INT AUTO_INCREMENT NOT NULL, cin VARCHAR(8) NOT NULL, model VARCHAR(20) NOT NULL, num_serie VARCHAR(12) NOT NULL, image VARCHAR(200) DEFAULT NULL, disponibilite VARCHAR(20) NOT NULL COMMENT \'(DC2Type:voiture_disponibilite)\', timestamp DATETIME NOT NULL, INDEX IDX_E9E2810FABE530DA (cin), PRIMARY KEY(id_voiture)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reservation_transport ADD CONSTRAINT FK_7CEC40B1F6C8AB85 FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin)');
        $this->addSql('ALTER TABLE reservation_transport ADD CONSTRAINT FK_7CEC40B193BCDE18 FOREIGN KEY (cinTransporteur) REFERENCES utilisateur (cin)');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT FK_66AB212E377F287F FOREIGN KEY (id_voiture) REFERENCES voiture (id_voiture)');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT FK_66AB212EB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation_transport (id)');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810FABE530DA FOREIGN KEY (cin) REFERENCES utilisateur (cin)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_transport DROP FOREIGN KEY FK_7CEC40B1F6C8AB85');
        $this->addSql('ALTER TABLE reservation_transport DROP FOREIGN KEY FK_7CEC40B193BCDE18');
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY FK_66AB212E377F287F');
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY FK_66AB212EB83297E7');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810FABE530DA');
        $this->addSql('DROP TABLE reservation_transport');
        $this->addSql('DROP TABLE transport');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE voiture');
    }
}
