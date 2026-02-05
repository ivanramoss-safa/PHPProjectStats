<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20260116164048 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {

        $this->addSql('ALTER TABLE review ADD external_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {

        $this->addSql('ALTER TABLE review DROP external_id');
    }
}
