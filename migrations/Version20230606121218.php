<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230606121218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE logo (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        // $this->addSql('ALTER TABLE email_signature CHANGE logo logo VARCHAR(255) NOT NULL, CHANGE social_link social_link VARCHAR(255) DEFAULT NULL');
        // $this->addSql('ALTER TABLE email_signature RENAME INDEX fk_user_id TO IDX_A1839E6AA76ED395');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE logo');
        // $this->addSql('ALTER TABLE email_signature CHANGE logo logo JSON DEFAULT NULL, CHANGE social_link social_link JSON DEFAULT NULL');
        // $this->addSql('ALTER TABLE email_signature RENAME INDEX idx_a1839e6aa76ed395 TO FK_USER_ID');
    }
}
