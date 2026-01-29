<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129153855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, type VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category_item (id INT AUTO_INCREMENT NOT NULL, external_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_94805F5912469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ranking (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_80B839D0A76ED395 (user_id), INDEX IDX_80B839D012469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ranking_item (id INT AUTO_INCREMENT NOT NULL, external_id INT NOT NULL, position INT NOT NULL, ranking_id INT NOT NULL, INDEX IDX_B626AA9420F64684 (ranking_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE category_item ADD CONSTRAINT FK_94805F5912469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE ranking ADD CONSTRAINT FK_80B839D0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE ranking ADD CONSTRAINT FK_80B839D012469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE ranking_item ADD CONSTRAINT FK_B626AA9420F64684 FOREIGN KEY (ranking_id) REFERENCES ranking (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category_item DROP FOREIGN KEY FK_94805F5912469DE2');
        $this->addSql('ALTER TABLE ranking DROP FOREIGN KEY FK_80B839D0A76ED395');
        $this->addSql('ALTER TABLE ranking DROP FOREIGN KEY FK_80B839D012469DE2');
        $this->addSql('ALTER TABLE ranking_item DROP FOREIGN KEY FK_B626AA9420F64684');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE category_item');
        $this->addSql('DROP TABLE ranking');
        $this->addSql('DROP TABLE ranking_item');
    }
}
