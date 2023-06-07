<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230606125916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE html_signature (id INT AUTO_INCREMENT NOT NULL, user_id_id INT NOT NULL, name VARCHAR(255) NOT NULL, job_title VARCHAR(255) NOT NULL, organization VARCHAR(255) NOT NULL, adress VARCHAR(255) NOT NULL, postal_code VARCHAR(255) NOT NULL, city VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(255) NOT NULL, social_link JSON DEFAULT NULL, html_code LONGTEXT NOT NULL, baniere LONGTEXT DEFAULT NULL, create_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', update_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F8DF5FF99D86650F (user_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE html_signature_logo (html_signature_id INT NOT NULL, logo_id INT NOT NULL, INDEX IDX_3551DE68F71B0349 (html_signature_id), INDEX IDX_3551DE68F98F144A (logo_id), PRIMARY KEY(html_signature_id, logo_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE html_signature ADD CONSTRAINT FK_F8DF5FF99D86650F FOREIGN KEY (user_id_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE html_signature_logo ADD CONSTRAINT FK_3551DE68F71B0349 FOREIGN KEY (html_signature_id) REFERENCES html_signature (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE html_signature_logo ADD CONSTRAINT FK_3551DE68F98F144A FOREIGN KEY (logo_id) REFERENCES logo (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE html_signature DROP FOREIGN KEY FK_F8DF5FF99D86650F');
        $this->addSql('ALTER TABLE html_signature_logo DROP FOREIGN KEY FK_3551DE68F71B0349');
        $this->addSql('ALTER TABLE html_signature_logo DROP FOREIGN KEY FK_3551DE68F98F144A');
        $this->addSql('DROP TABLE html_signature');
        $this->addSql('DROP TABLE html_signature_logo');
    }
}
