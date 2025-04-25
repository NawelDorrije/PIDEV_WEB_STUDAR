<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250412172212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY fk_reservation_id');
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY fk_cin');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY fk_cin_v');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('DROP TABLE reservation_transport');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY fk_id_voiture');
        $this->addSql('DROP INDEX fk_cin ON transport');
        $this->addSql('DROP INDEX fk_id_voiture ON transport');
        $this->addSql('DROP INDEX fk_reservation_id ON transport');
        $this->addSql('ALTER TABLE transport DROP id_voiture, DROP cin, DROP reservation_id, CHANGE timestamp timestamp DATETIME NOT NULL');
        $this->addSql('DROP INDEX fk_cin_v ON voiture');
        $this->addSql('ALTER TABLE voiture DROP cin, CHANGE photo photo VARCHAR(200) DEFAULT NULL, CHANGE disponibilite disponibilite VARCHAR(20) NOT NULL COMMENT \'(DC2Type:voiture_disponibilite)\', CHANGE timestamp timestamp DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reservation_transport (id INT AUTO_INCREMENT NOT NULL, adresseDepart VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, adresseDestination VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, tempsArrivage VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, status VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'en_attente\' COLLATE `utf8mb4_general_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE utilisateur (cin VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, nom VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, prenom VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, email VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, mdp VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, numTel VARCHAR(15) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, role VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, reset_code VARCHAR(10) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, blocked TINYINT(1) DEFAULT 0, UNIQUE INDEX email (email), PRIMARY KEY(cin)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE transport ADD id_voiture INT NOT NULL, ADD cin VARCHAR(8) NOT NULL, ADD reservation_id INT NOT NULL, CHANGE timestamp timestamp DATETIME DEFAULT \'current_timestamp(6)\' NOT NULL');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT fk_cin FOREIGN KEY (cin) REFERENCES utilisateur (cin) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT fk_id_voiture FOREIGN KEY (id_voiture) REFERENCES voiture (id_voiture) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT fk_reservation_id FOREIGN KEY (reservation_id) REFERENCES reservation_transport (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX fk_cin ON transport (cin)');
        $this->addSql('CREATE INDEX fk_id_voiture ON transport (id_voiture)');
        $this->addSql('CREATE INDEX fk_reservation_id ON transport (reservation_id)');
        $this->addSql('ALTER TABLE voiture ADD cin VARCHAR(8) NOT NULL, CHANGE photo photo VARCHAR(200) NOT NULL, CHANGE disponibilite disponibilite VARCHAR(255) NOT NULL, CHANGE timestamp timestamp DATETIME DEFAULT \'current_timestamp(6)\' NOT NULL');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT fk_cin_v FOREIGN KEY (cin) REFERENCES utilisateur (cin) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE INDEX fk_cin_v ON voiture (cin)');
    }
}
