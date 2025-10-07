<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250919100554 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE scan_result ADD uploaded_file_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE scan_result ADD CONSTRAINT FK_CFDBE4ED276973A0 FOREIGN KEY (uploaded_file_id) REFERENCES uploaded_dependency_file (id)');
        $this->addSql('CREATE INDEX IDX_CFDBE4ED276973A0 ON scan_result (uploaded_file_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE scan_result DROP FOREIGN KEY FK_CFDBE4ED276973A0');
        $this->addSql('DROP INDEX IDX_CFDBE4ED276973A0 ON scan_result');
        $this->addSql('ALTER TABLE scan_result DROP uploaded_file_id');
    }
}
