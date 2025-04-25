<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250413160922 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY fk_reservation_id');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('DROP TABLE reservation_transport');
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY fk_cin');
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY fk_id_voiture');
        $this->addSql('DROP INDEX fk_reservation_id ON transport');
        $this->addSql('DROP INDEX fk_cin ON transport');
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY fk_id_voiture');
        $this->addSql('ALTER TABLE transport DROP cin, DROP reservation_id, CHANGE status status VARCHAR(20) NOT NULL COMMENT \'(DC2Type:transport_status)\', CHANGE timestamp timestamp DATETIME NOT NULL');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT FK_66AB212E377F287F FOREIGN KEY (id_voiture) REFERENCES voiture (id_voiture)');
        $this->addSql('DROP INDEX fk_id_voiture ON transport');
        $this->addSql('CREATE INDEX IDX_66AB212E377F287F ON transport (id_voiture)');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT fk_id_voiture FOREIGN KEY (id_voiture) REFERENCES voiture (id_voiture) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('DROP INDEX email ON utilisateur');
        $this->addSql('ALTER TABLE utilisateur ADD image VARCHAR(255) NOT NULL, ADD created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE role role VARCHAR(20) NOT NULL, CHANGE blocked blocked TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY fk_cin_v');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY fk_cin_v');
        $this->addSql('ALTER TABLE voiture CHANGE photo photo VARCHAR(200) DEFAULT NULL, CHANGE disponibilite disponibilite VARCHAR(20) NOT NULL COMMENT \'(DC2Type:voiture_disponibilite)\', CHANGE timestamp timestamp DATETIME NOT NULL');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810FABE530DA FOREIGN KEY (cin) REFERENCES utilisateur (cin)');
        $this->addSql('DROP INDEX fk_cin_v ON voiture');
        $this->addSql('CREATE INDEX IDX_E9E2810FABE530DA ON voiture (cin)');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT fk_cin_v FOREIGN KEY (cin) REFERENCES utilisateur (cin) ON UPDATE CASCADE ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reservation_transport (id INT AUTO_INCREMENT NOT NULL, adresseDepart VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, adresseDestination VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, tempsArrivage VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, status VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'en_attente\' COLLATE `utf8mb4_general_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY FK_66AB212E377F287F');
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY FK_66AB212E377F287F');
        $this->addSql('ALTER TABLE transport ADD cin VARCHAR(8) NOT NULL, ADD reservation_id INT NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE timestamp timestamp DATETIME DEFAULT \'current_timestamp(6)\' NOT NULL');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT fk_reservation_id FOREIGN KEY (reservation_id) REFERENCES reservation_transport (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT fk_cin FOREIGN KEY (cin) REFERENCES utilisateur (cin) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT fk_id_voiture FOREIGN KEY (id_voiture) REFERENCES voiture (id_voiture) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE INDEX fk_reservation_id ON transport (reservation_id)');
        $this->addSql('CREATE INDEX fk_cin ON transport (cin)');
        $this->addSql('DROP INDEX idx_66ab212e377f287f ON transport');
        $this->addSql('CREATE INDEX fk_id_voiture ON transport (id_voiture)');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT FK_66AB212E377F287F FOREIGN KEY (id_voiture) REFERENCES voiture (id_voiture)');
        $this->addSql('ALTER TABLE utilisateur DROP image, DROP created_at, CHANGE role role VARCHAR(255) NOT NULL, CHANGE blocked blocked TINYINT(1) DEFAULT 0');
        $this->addSql('CREATE UNIQUE INDEX email ON utilisateur (email)');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810FABE530DA');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810FABE530DA');
        $this->addSql('ALTER TABLE voiture CHANGE photo photo VARCHAR(200) NOT NULL, CHANGE disponibilite disponibilite VARCHAR(255) NOT NULL, CHANGE timestamp timestamp DATETIME DEFAULT \'current_timestamp(6)\' NOT NULL');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT fk_cin_v FOREIGN KEY (cin) REFERENCES utilisateur (cin) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_e9e2810fabe530da ON voiture');
        $this->addSql('CREATE INDEX fk_cin_v ON voiture (cin)');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810FABE530DA FOREIGN KEY (cin) REFERENCES utilisateur (cin)');
    }
}
